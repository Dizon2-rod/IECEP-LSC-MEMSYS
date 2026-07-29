<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once dirname(__DIR__, 2) . '/auth_check.php';
require_role(['admin', 'super_admin', 'committee_registration']);

require_once __DIR__ . '/../../../../includes/db.php';

$db = Database::getInstance();
$memberId = $_GET['id'] ?? '';
$mode = $_GET['mode'] ?? 'view';

if (empty($memberId)) {
    header('Location: member-directory.php');
    exit;
}

// Get member details
$member = $db->fetchOne("SELECT m.*, i.name as institution_name, i.acronym as institution_acronym, up.role 
    FROM members m 
    LEFT JOIN institutions i ON m.institution_id = i.id
    LEFT JOIN user_profiles up ON m.user_id = up.user_id
    WHERE m.id = ?", [$memberId]);

if (!$member) {
    header('Location: member-directory.php');
    exit;
}

// Get payment history
$payments = $db->fetchAll("SELECT * FROM transactions WHERE member_id = ? ORDER BY transaction_date DESC LIMIT 10", [$memberId]);

// Get event attendance
$events = $db->fetchAll("SELECT e.title, e.start_date, ea.status, ea.check_in_time 
    FROM event_attendees ea
    JOIN events e ON ea.event_id = e.id
    WHERE ea.member_id = ?
    ORDER BY e.start_date DESC LIMIT 10", [$memberId]);

// Get blockchain records
$blockchain = $db->fetchAll("SELECT * FROM blockchain_records WHERE entity_id = ? ORDER BY created_at DESC", [$memberId]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'edit') {
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $yearLevel = $_POST['year_level'] ?? '';
    $membershipStatus = $_POST['membership_status'] ?? '';
    $paymentStatus = isset($_POST['payment_status']) ? 1 : 0;
    
    $db->update('members', [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'year_level' => $yearLevel,
        'membership_status' => $membershipStatus,
        'payment_status' => $paymentStatus
    ], 'id = ?', [$memberId]);
    
    // Log to audit trail
    $db->insert('audit_logs', [
        'id' => generateUUID(),
        'user_id' => $_SESSION['user']['id'],
        'action' => 'UPDATE_MEMBER',
        'details' => json_encode(['member_id' => $memberId, 'changes' => $_POST]),
        'affected_entity_type' => 'member',
        'affected_entity_id' => $memberId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    header("Location: member-profile.php?id=$memberId&mode=view");
    exit;
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Profile - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .profile-header {
            background: var(--primary-navy);
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }
        .profile-header h1 { margin-bottom: 0.5rem; }
        .profile-header .meta { opacity: 0.8; }
        
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--gray-200);
        }
        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            font-weight: var(--font-weight-medium);
            color: var(--gray-600);
        }
        .tab.active {
            border-bottom-color: var(--accent-gold);
            color: var(--primary-navy);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .info-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        .info-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-weight: var(--font-weight-semibold);
            color: var(--gray-900);
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .history-table th {
            background: var(--gray-100);
            padding: 1rem;
            text-align: left;
            font-weight: var(--font-weight-semibold);
        }
        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .action-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-weight: var(--font-weight-medium);
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }
        .form-group input[type="checkbox"] {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="profile-header">
                <h1><?php echo htmlspecialchars($member['full_name']); ?></h1>
                <div class="meta">
                    <?php echo htmlspecialchars($member['membership_id'] ?: 'No ID assigned'); ?> | 
                    <?php echo htmlspecialchars($member['institution_acronym'] ?: $member['institution_name']); ?> |
                    <?php echo htmlspecialchars($member['role']); ?>
                </div>
            </div>

            <?php if ($mode === 'view'): ?>
            <div class="action-bar">
                <button onclick="setMode('edit')" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Profile
                </button>
                <button onclick="generateDigitalID()" class="btn btn-secondary">
                    <i class="fas fa-id-card"></i> Generate Digital ID
                </button>
                <button onclick="sendCredentials()" class="btn btn-secondary">
                    <i class="fas fa-envelope"></i> Send Credentials
                </button>
                <button onclick="window.location.href='member-directory.php'" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Directory
                </button>
            </div>
            <?php endif; ?>

            <?php if ($mode === 'edit'): ?>
            <form method="POST" class="info-card" style="margin-bottom: 2rem;">
                <h2>Edit Member Information</h2>
                <div class="info-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($member['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($member['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level">
                            <option value="">Select Year Level</option>
                            <option value="1st Year" <?php echo $member['year_level'] === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2nd Year" <?php echo $member['year_level'] === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3rd Year" <?php echo $member['year_level'] === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4th Year" <?php echo $member['year_level'] === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                            <option value="5th Year" <?php echo $member['year_level'] === '5th Year' ? 'selected' : ''; ?>>5th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Membership Status</label>
                        <select name="membership_status">
                            <option value="active" <?php echo $member['membership_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $member['membership_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending" <?php echo $member['membership_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="suspended" <?php echo $member['membership_status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="payment_status" <?php echo $member['payment_status'] ? 'checked' : ''; ?>>
                            Payment Status (Paid)
                        </label>
                    </div>
                </div>
                <div class="action-bar">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" onclick="setMode('view')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($mode === 'view'): ?>
            <div class="tabs">
                <div class="tab active" onclick="switchTab('personal')">Personal Info</div>
                <div class="tab" onclick="switchTab('membership')">Membership</div>
                <div class="tab" onclick="switchTab('payments')">Payment History</div>
                <div class="tab" onclick="switchTab('events')">Event Attendance</div>
                <div class="tab" onclick="switchTab('blockchain')">Blockchain Records</div>
            </div>

            <div id="personal" class="tab-content active">
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['full_name']); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['email']); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Birthday</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['birthday'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['address'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Alumni Status</div>
                        <div class="info-value"><?php echo $member['alumni_status'] ? 'Yes' : 'No'; ?></div>
                    </div>
                </div>
            </div>

            <div id="membership" class="tab-content">
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Membership ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['membership_id'] ?: 'Not assigned'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Membership Type</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['member_type'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Membership Status</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['membership_status']); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Payment Status</div>
                        <div class="info-value"><?php echo $member['payment_status'] ? 'Paid' : 'Unpaid'; ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Membership Expiry</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['membership_expiry'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Last Renewal</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['last_renewal_date'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <div id="payments" class="tab-content">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="5" style="text-align: center;">No payment history</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['transaction_date']); ?></td>
                                <td><?php echo htmlspecialchars($payment['type']); ?></td>
                                <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['status']); ?></td>
                                <td><?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="events" class="tab-content">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Check-in Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr><td colspan="4" style="text-align: center;">No event attendance records</td></tr>
                        <?php else: ?>
                            <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo htmlspecialchars($event['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($event['status']); ?></td>
                                <td><?php echo htmlspecialchars($event['check_in_time'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="blockchain" class="tab-content">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Entity Type</th>
                            <th>Transaction Hash</th>
                            <th>Block Number</th>
                            <th>Confirmed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blockchain)): ?>
                            <tr><td colspan="5" style="text-align: center;">No blockchain records</td></tr>
                        <?php else: ?>
                            <?php foreach ($blockchain as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($record['entity_type']); ?></td>
                                <td><?php echo htmlspecialchars(substr($record['transaction_hash'], 0, 20)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars($record['block_number'] ?? 'N/A'); ?></td>
                                <td><?php echo $record['confirmed'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-clock text-warning"></i>'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        function setMode(mode) {
            window.location.href = `member-profile.php?id=<?php echo $memberId; ?>&mode=${mode}`;
        }

        function generateDigitalID() {
            if (confirm('Generate digital ID for this member?')) {
                window.location.href = `../member/digital-id.php?member_id=<?php echo $memberId; ?>`;
            }
        }

        function sendCredentials() {
            if (confirm('Send login credentials to this member?')) {
                fetch('<?php echo BASE_URL; ?>/api/send-credentials.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ member_id: '<?php echo $memberId; ?>' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Credentials sent successfully!');
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        }
    </script>
</body>
</html>
