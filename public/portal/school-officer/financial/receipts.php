<?php
require_once dirname(__DIR__, 2) . '/public/portal/auth_check.php';

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/middleware/auth.php';

// Check if user is school_officer
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'school_officer') {
    header('Location: /login.php');
    exit;
}

$current_page = 'receipts';

// Get user's institution
$userId = $_SESSION['user']['id'];
$db = $GLOBALS['supabaseClient'] ?? null;

$institutionId = null;
$institutionName = 'Unknown Institution';

if ($db) {
    try {
        $members = $db->select('members', ['user_id' => 'eq.' . $userId]);
        if (!empty($members)) {
            $institutionId = $members[0]['institution_id'];
            $institutions = $db->select('institutions', ['id' => 'eq.' . $institutionId]);
            if (!empty($institutions)) {
                $institutionName = $institutions[0]['name'];
            }
        }
    } catch (Exception $e) {
        // Use defaults if query fails
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipts - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/design-tokens.css">
    <style>
        :root {
            --primary-color: #0B1D4A;
            --secondary-color: #D4AF37;
        }
        
        body {
            background-color: #f8fafc;
        }
        
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .receipt-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }
        
        .receipt-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .receipt-number {
            font-family: 'Courier New', monospace;
            color: #64748b;
            font-size: 0.85rem;
        }
        
        .receipt-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/header.php'; ?>
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-12">
                    <h2 class="mb-3">Receipts</h2>
                    <p class="text-muted mb-3">Institution: <?php echo htmlspecialchars($institutionName); ?></p>
                    <div class="d-flex gap-2 mb-3">
                        <select class="form-select w-auto" id="filter-year" onchange="loadReceipts()">
                            <option value="2024">2024</option>
                            <option value="2025">2025</option>
                            <option value="2026" selected>2026</option>
                        </select>
                        <select class="form-select w-auto" id="filter-type" onchange="loadReceipts()">
                            <option value="">All Types</option>
                            <option value="membership_fee">Membership Fee</option>
                            <option value="event_fee">Event Fee</option>
                            <option value="donation">Donation</option>
                        </select>
                        <button class="btn btn-primary" onclick="loadReceipts()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                        <button class="btn btn-outline-primary" onclick="downloadAll()">
                            <i class="fas fa-download me-2"></i>Download All
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <div class="h4 mb-0" id="total-receipts">0</div>
                        <small class="text-muted">Total Receipts</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <div class="h4 mb-0" id="total-amount">₱0</div>
                        <small class="text-muted">Total Amount</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <div class="h4 mb-0" id="membership-amount">₱0</div>
                        <small class="text-muted">Membership Fees</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <div class="h4 mb-0" id="event-amount">₱0</div>
                        <small class="text-muted">Event Fees</small>
                    </div>
                </div>
            </div>
            
            <!-- Receipts List -->
            <div class="row">
                <div class="col-md-12">
                    <div class="metric-card">
                        <h5 class="mb-3">Receipt History</h5>
                        <div id="receipts-list">
                            <!-- Receipts will be loaded here -->
                        </div>
                        <nav id="pagination">
                            <!-- Pagination will be loaded here -->
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        const institutionId = <?php echo json_encode($institutionId); ?>;
        let currentPage = 1;
        
        async function loadReceipts(page = 1) {
            currentPage = page;
            
            const year = document.getElementById('filter-year').value;
            const type = document.getElementById('filter-type').value;
            
            let url = `/api/transactions.php?action=list&institution_id=${institutionId}&page=${page}`;
            if (type) url += `&type=${type}`;
            if (year) url += `&year=${year}`;
            
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayReceipts(data.transactions);
                    displayPagination(data.pagination);
                    updateSummaryCards(data.transactions);
                }
            } catch (error) {
                console.error('Error loading receipts:', error);
            }
        }
        
        function displayReceipts(transactions) {
            const container = document.getElementById('receipts-list');
            container.innerHTML = '';
            
            if (transactions.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-4">No receipts found</div>';
                return;
            }
            
            transactions.forEach(tx => {
                const card = document.createElement('div');
                card.className = 'receipt-card';
                card.innerHTML = `
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <div class="receipt-number">RCPT-${tx.id.substring(0, 8).toUpperCase()}</div>
                            <small class="text-muted">${formatDate(tx.transaction_date)}</small>
                        </div>
                        <div class="col-md-3">
                            <div class="fw-bold">${formatType(tx.type)}</div>
                            <small class="text-muted">${tx.member_name || 'N/A'}</small>
                        </div>
                        <div class="col-md-2">
                            <div class="receipt-amount">₱${parseFloat(tx.amount).toLocaleString()}</div>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-${tx.status === 'paid' ? 'success' : 'warning'}">${tx.status}</span>
                        </div>
                        <div class="col-md-3 text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadReceipt('${tx.id}')">
                                <i class="fas fa-download me-1"></i>Download
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="viewReceipt('${tx.id}')">
                                <i class="fas fa-eye me-1"></i>View
                            </button>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        }
        
        function displayPagination(pagination) {
            const nav = document.getElementById('pagination');
            nav.innerHTML = '';
            
            if (!pagination || pagination.total_pages <= 1) return;
            
            let html = '<ul class="pagination justify-content-center">';
            
            if (pagination.current_page > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadReceipts(${pagination.current_page - 1})">Previous</a></li>`;
            }
            
            for (let i = 1; i <= pagination.total_pages; i++) {
                html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadReceipts(${i})">${i}</a>
                </li>`;
            }
            
            if (pagination.current_page < pagination.total_pages) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadReceipts(${pagination.current_page + 1})">Next</a></li>`;
            }
            
            html += '</ul>';
            nav.innerHTML = html;
        }
        
        function updateSummaryCards(transactions) {
            document.getElementById('total-receipts').textContent = transactions.length;
            
            const totalAmount = transactions.reduce((sum, tx) => sum + parseFloat(tx.amount || 0), 0);
            document.getElementById('total-amount').textContent = '₱' + totalAmount.toLocaleString();
            
            const membershipAmount = transactions
                .filter(tx => tx.type === 'membership_fee')
                .reduce((sum, tx) => sum + parseFloat(tx.amount || 0), 0);
            document.getElementById('membership-amount').textContent = '₱' + membershipAmount.toLocaleString();
            
            const eventAmount = transactions
                .filter(tx => tx.type === 'event_fee')
                .reduce((sum, tx) => sum + parseFloat(tx.amount || 0), 0);
            document.getElementById('event-amount').textContent = '₱' + eventAmount.toLocaleString();
        }
        
        function downloadReceipt(txId) {
            window.open(`/api/transactions.php?action=receipt&id=${txId}`, '_blank');
        }
        
        function viewReceipt(txId) {
            window.open(`/api/transactions.php?action=receipt&id=${txId}`, '_blank');
        }
        
        function downloadAll() {
            alert('Download all receipts as ZIP would be implemented here');
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        function formatType(type) {
            const types = {
                'membership_fee': 'Membership Fee',
                'event_fee': 'Event Fee',
                'donation': 'Donation',
                'penalty': 'Penalty'
            };
            return types[type] || type;
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            if (institutionId) {
                loadReceipts();
            } else {
                document.getElementById('receipts-list').innerHTML = '<div class="text-center text-muted py-4">No institution associated with your account</div>';
            }
        });
    </script>
</body>
</html>
