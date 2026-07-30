<?php
require_once __DIR__ . '/../../auth_check.php';

require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_page = 'transactions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - IECEP-LSC MEMSYS</title>
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
        
        .status-paid { color: #10b981; font-weight: 600; }
        .status-pending { color: #f59e0b; font-weight: 600; }
        .status-failed { color: #ef4444; font-weight: 600; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-12">
                    <h2 class="mb-3">Transactions</h2>
                    <div class="d-flex gap-2 mb-3">
                        <select class="form-select w-auto" id="filter-status" onchange="loadTransactions()">
                            <option value="">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                        <select class="form-select w-auto" id="filter-type" onchange="loadTransactions()">
                            <option value="">All Types</option>
                            <option value="membership_fee">Membership Fee</option>
                            <option value="event_fee">Event Fee</option>
                            <option value="donation">Donation</option>
                            <option value="penalty">Penalty</option>
                        </select>
                        <select class="form-select w-auto" id="filter-institution" onchange="loadTransactions()">
                            <option value="">All Institutions</option>
                        </select>
                        <input type="date" class="form-control w-auto" id="filter-date-from" onchange="loadTransactions()">
                        <input type="date" class="form-control w-auto" id="filter-date-to" onchange="loadTransactions()">
                        <button class="btn btn-primary" onclick="loadTransactions()">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                        <button class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times me-2"></i>Clear
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Transactions Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="metric-card">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Institution</th>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="transactions-table">
                                    <!-- Data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        <nav id="pagination">
                            <!-- Pagination will be loaded here -->
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transaction Detail Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="transaction-detail">
                    <!-- Details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="generateReceipt()">
                        <i class="fas fa-file-pdf me-2"></i>Generate Receipt
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="verifyBlockchain()">
                        <i class="fas fa-link me-2"></i>Verify on Blockchain
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        let currentTransactionId = null;
        let currentPage = 1;
        
        async function loadTransactions(page = 1) {
            currentPage = page;
            
            const status = document.getElementById('filter-status').value;
            const type = document.getElementById('filter-type').value;
            const institution = document.getElementById('filter-institution').value;
            const dateFrom = document.getElementById('filter-date-from').value;
            const dateTo = document.getElementById('filter-date-to').value;
            
            let url = `/api/transactions.php?action=list&page=${page}`;
            if (status) url += `&status=${status}`;
            if (type) url += `&type=${type}`;
            if (institution) url += `&institution_id=${institution}`;
            if (dateFrom) url += `&date_from=${dateFrom}`;
            if (dateTo) url += `&date_to=${dateTo}`;
            
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayTransactions(data.transactions);
                    displayPagination(data.pagination);
                    loadInstitutions();
                }
            } catch (error) {
                console.error('Error loading transactions:', error);
            }
        }
        
        function displayTransactions(transactions) {
            const tbody = document.getElementById('transactions-table');
            tbody.innerHTML = '';
            
            if (transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No transactions found</td></tr>';
                return;
            }
            
            transactions.forEach(tx => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><code>${tx.id.substring(0, 8)}...</code></td>
                    <td>${formatDate(tx.transaction_date)}</td>
                    <td>${formatType(tx.type)}</td>
                    <td>${tx.institution_name || 'N/A'}</td>
                    <td>${tx.member_name || 'N/A'}</td>
                    <td>₱${parseFloat(tx.amount).toLocaleString()}</td>
                    <td><span class="status-${tx.status}">${tx.status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewTransaction('${tx.id}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        function displayPagination(pagination) {
            const nav = document.getElementById('pagination');
            nav.innerHTML = '';
            
            if (!pagination || pagination.total_pages <= 1) return;
            
            let html = '<ul class="pagination justify-content-center">';
            
            if (pagination.current_page > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadTransactions(${pagination.current_page - 1})">Previous</a></li>`;
            }
            
            for (let i = 1; i <= pagination.total_pages; i++) {
                html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadTransactions(${i})">${i}</a>
                </li>`;
            }
            
            if (pagination.current_page < pagination.total_pages) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadTransactions(${pagination.current_page + 1})">Next</a></li>`;
            }
            
            html += '</ul>';
            nav.innerHTML = html;
        }
        
        async function loadInstitutions() {
            try {
                const response = await fetch('/api/institutions.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    const select = document.getElementById('filter-institution');
                    select.innerHTML = '<option value="">All Institutions</option>';
                    
                    data.institutions.forEach(inst => {
                        const option = document.createElement('option');
                        option.value = inst.id;
                        option.textContent = inst.name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading institutions:', error);
            }
        }
        
        async function viewTransaction(txId) {
            currentTransactionId = txId;
            
            try {
                const response = await fetch(`/api/transactions.php?action=detail&id=${txId}`);
                const data = await response.json();
                
                if (data.success) {
                    displayTransactionDetail(data.transaction);
                    bootstrap.Modal.getInstance(document.getElementById('transactionModal')).show();
                }
            } catch (error) {
                console.error('Error loading transaction detail:', error);
            }
        }
        
        function displayTransactionDetail(tx) {
            const container = document.getElementById('transaction-detail');
            container.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Transaction ID:</strong><br>
                        <code>${tx.id}</code>
                    </div>
                    <div class="col-md-6">
                        <strong>Date:</strong><br>
                        ${formatDate(tx.transaction_date)}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Type:</strong><br>
                        ${formatType(tx.type)}
                    </div>
                    <div class="col-md-6">
                        <strong>Amount:</strong><br>
                        ₱${parseFloat(tx.amount).toLocaleString()}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        <span class="status-${tx.status}">${tx.status}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Institution:</strong><br>
                        ${tx.institution_name || 'N/A'}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Member:</strong><br>
                        ${tx.member_name || 'N/A'}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Description:</strong><br>
                        ${tx.description || 'N/A'}
                    </div>
                </div>
            `;
        }
        
        function generateReceipt() {
            if (!currentTransactionId) return;
            window.open(`/api/transactions.php?action=receipt&id=${currentTransactionId}`, '_blank');
        }
        
        function verifyBlockchain() {
            if (!currentTransactionId) return;
            window.open(`/blockchain-explorer.php?transaction_id=${currentTransactionId}`, '_blank');
        }
        
        function clearFilters() {
            document.getElementById('filter-status').value = '';
            document.getElementById('filter-type').value = '';
            document.getElementById('filter-institution').value = '';
            document.getElementById('filter-date-from').value = '';
            document.getElementById('filter-date-to').value = '';
            loadTransactions();
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
            loadTransactions();
        });
    </script>
</body>
</html>

