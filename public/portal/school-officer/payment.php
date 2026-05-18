<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/helpers/cbl_fee_calculator.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'school_officer') {
    header('Location: /login.php');
    exit;
}

$schoolId = $_SESSION['school_id'] ?? null;
$memberCount = 0;

if ($schoolId) {
    $query = "SELECT total_members FROM school_profiles WHERE id = $1";
    $result = pg_query_params($conn, $query, [$schoolId]);
    if ($result && $row = pg_fetch_assoc($result)) {
        $memberCount = intval($row['total_members']);
    }
}

$feeBracket = getFeeBracket($memberCount);
$totalFee = calculateTotalFee($memberCount);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Portal - IECEP LSC</title>
    <link rel="stylesheet" href="/public/css/portal.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>Payment Portal</h1>
            </div>

            <div class="payment-calculator">
                <h2>Fee Calculator</h2>
                <div class="fee-breakdown">
                    <p><strong>Member Count:</strong> <?php echo $memberCount; ?></p>
                    <p><strong>Bracket:</strong> <?php echo $feeBracket['bracket']; ?></p>
                    <p><strong>Affiliation Fee:</strong> ₱<?php echo number_format($feeBracket['affiliation'], 2); ?></p>
                    <p><strong>Operational Fee:</strong> ₱800.00</p>
                    <hr>
                    <p><strong>Total Fee:</strong> ₱<?php echo number_format($totalFee, 2); ?></p>
                </div>
            </div>

            <div class="payment-upload">
                <h2>Upload Payment Proof</h2>
                <form id="paymentForm" enctype="multipart/form-data">
                    <input type="hidden" name="school_id" value="<?php echo htmlspecialchars($schoolId); ?>">
                    <input type="hidden" name="amount" value="<?php echo $totalFee; ?>">
                    
                    <div class="form-group">
                        <label>Payment Type</label>
                        <select name="payment_type" required>
                            <option value="Affiliation">Affiliation Fee</option>
                            <option value="Operational">Operational Fee</option>
                            <option value="Individual_Dues">Individual Dues</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Proof of Payment (Screenshot)</label>
                        <input type="file" name="proof_of_payment" accept="image/*,.pdf" required>
                    </div>
                    
                    <button type="submit" class="btn-primary">Submit Payment</button>
                </form>
            </div>

            <div class="ledger-section">
                <h2>My Ledger</h2>
                <table id="ledgerTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Official Receipt</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('paymentForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            const response = await fetch('/public/api/school-officer/financial.php?action=upload_payment', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                alert('Payment submitted successfully!');
                loadLedger();
                e.target.reset();
            } else {
                alert('Error: ' + result.error);
            }
        });

        async function loadLedger() {
            const schoolId = '<?php echo $schoolId; ?>';
            const response = await fetch(`/public/api/school-officer/financial.php?action=get_ledger&school_id=${schoolId}`);
            const data = await response.json();
            
            const tbody = document.querySelector('#ledgerTable tbody');
            tbody.innerHTML = '';
            
            data.records.forEach(record => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td>${new Date(record.created_at).toLocaleDateString()}</td>
                    <td>₱${parseFloat(record.amount).toFixed(2)}</td>
                    <td>${record.payment_type}</td>
                    <td><span class="badge badge-${record.payment_status.toLowerCase()}">${record.payment_status}</span></td>
                    <td>${record.official_receipt_url ? `<a href="${record.official_receipt_url}" target="_blank">Download</a>` : 'Pending'}</td>
                `;
            });
        }

        loadLedger();
    </script>
</body>
</html>
