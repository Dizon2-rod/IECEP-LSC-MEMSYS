<?php
require_once __DIR__ . '/../../auth_check.php';

require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$transactionId = $_GET['id'] ?? '';

if (empty($transactionId)) {
    die('Invalid transaction ID');
}

$current_page = 'receipt';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/design-tokens.css">
    <style>
        :root {
            --primary-color: #0B1D4A;
            --secondary-color: #D4AF37;
        }
        body {
            background-color: #f8fafc;
            font-family: 'Inter', sans-serif;
        }
        .receipt-container {
            max-width: 800px;
            margin: 2rem auto;
            background: #fff;
            padding: 3rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        .receipt-header h1 {
            color: var(--primary-color);
            font-weight: 700;
        }
        .receipt-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .receipt-meta div {
            flex: 1;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        .receipt-table th,
        .receipt-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .receipt-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: var(--primary-color);
        }
        .receipt-total {
            text-align: right;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 1rem;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
        }
        .btn-print {
            margin-top: 2rem;
        }
        @media print {
            .btn-print, .sidebar, .main-content > *:not(.receipt-container) {
                display: none !important;
            }
            body {
                background: #fff;
            }
            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="receipt-container">
            <div class="receipt-header">
                <h1><i class="fas fa-file-invoice"></i> OFFICIAL RECEIPT</h1>
                <p class="text-muted">IECEP-LSC MEMSYS</p>
            </div>

            <div class="receipt-meta">
                <div>
                    <strong>Receipt No:</strong><br>
                    <span id="receipt-id"><?php echo htmlspecialchars($transactionId); ?></span>
                </div>
                <div style="text-align: right;">
                    <strong>Date Issued:</strong><br>
                    <span id="receipt-date"><?php echo date('F j, Y'); ?></span>
                </div>
            </div>

            <div id="receipt-loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Loading receipt details...</p>
            </div>

            <div id="receipt-content" style="display: none;">
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Member</th>
                            <th>Date</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="receipt-items">
                    </tbody>
                </table>

                <div class="receipt-total" id="receipt-total">
                    Total: PHP 0.00
                </div>

                <div class="receipt-footer">
                    <p><strong>IECEP-LSC MEMSYS</strong></p>
                    <p>This is an official receipt generated by the system.</p>
                    <p class="small text-muted">Transaction ID: <?php echo htmlspecialchars($transactionId); ?></p>
                </div>

                <div class="text-center btn-print">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                    <a href="<?php echo PORTAL_URL; ?>/admin/financial/transactions.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Transactions
                    </a>
                </div>
            </div>

            <div id="receipt-error" style="display: none;" class="alert alert-danger">
                <h4>Receipt Not Found</h4>
                <p>The requested transaction could not be found or you do not have permission to view it.</p>
                <a href="<?php echo PORTAL_URL; ?>/admin/financial/transactions.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Transactions
                </a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const transactionId = '<?php echo htmlspecialchars($transactionId); ?>';
        
        fetch('/IECEP-LSC-MEMSYS/public/api/transactions.php?id=' + encodeURIComponent(transactionId))
            .then(response => response.json())
            .then(data => {
                document.getElementById('receipt-loading').style.display = 'none';
                
                if (data.error || !data.data) {
                    document.getElementById('receipt-error').style.display = 'block';
                    return;
                }
                
                const tx = data.data;
                const tbody = document.getElementById('receipt-items');
                tbody.innerHTML = '';
                
                const items = [
                    { description: tx.description || 'Payment', member: tx.member_name || tx.user_email || 'N/A', date: tx.created_at ? new Date(tx.created_at).toLocaleDateString() : 'N/A', amount: parseFloat(tx.amount || 0) }
                ];
                
                items.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.description}</td>
                        <td>${item.member}</td>
                        <td>${item.date}</td>
                        <td style="text-align: right;">PHP ${item.amount.toFixed(2)}</td>
                    `;
                    tbody.appendChild(row);
                });
                
                const total = items.reduce((sum, item) => sum + item.amount, 0);
                document.getElementById('receipt-total').textContent = 'Total: PHP ' + total.toFixed(2);
                document.getElementById('receipt-content').style.display = 'block';
            })
            .catch(error => {
                document.getElementById('receipt-loading').style.display = 'none';
                document.getElementById('receipt-error').style.display = 'block';
                console.error('Error loading receipt:', error);
            });
    });
    </script>
</body>
</html>
