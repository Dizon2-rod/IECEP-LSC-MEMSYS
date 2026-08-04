<?php
require_once __DIR__ . '/../../auth_check.php';

require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_page = 'transactions';
$pageTitle = 'Transactions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - IECEP-LSC</title>
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-exchange-alt"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <div class="d-flex gap-2">
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
                    <i class="fas fa-search"></i> Filter
                </button>
                <button class="btn btn-secondary" onclick="clearFilters()">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>

        <div class="content-card">
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
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="empty-state">
                                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Loading transactions...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav id="pagination"></nav>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="generateReceipt()">
                        <i class="fas fa-file-pdf"></i> Generate Receipt
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="verifyBlockchain()">
                        <i class="fas fa-link"></i> Verify on Blockchain
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTransactionId = null;
        let currentPage = 1;
        let transactionModal;

        document.addEventListener('DOMContentLoaded', function() {
            transactionModal = new bootstrap.Modal(document.getElementById('transactionModal'));
            loadTransactions();
            loadInstitutions();
        });

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
                }
            } catch (error) {
                console.error('Error loading transactions:', error);
            }
        }

        function displayTransactions(transactions) {
            const tbody = document.getElementById('transactions-table');
            tbody.innerHTML = '';

            if (!transactions || transactions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="empty-state">
                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No transactions found</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            transactions.forEach(tx => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><code>${tx.id ? tx.id.substring(0, 8) + '...' : 'N/A'}</code></td>
                    <td>${formatDate(tx.transaction_date || tx.created_at)}</td>
                    <td>${formatType(tx.type)}</td>
                    <td>${escapeHtml(tx.institution_name || 'N/A')}</td>
                    <td>${escapeHtml(tx.member_name || 'N/A')}</td>
                    <td>₱${(parseFloat(tx.amount) || 0).toLocaleString()}</td>
                    <td><span class="status-${tx.status || 'unknown'}">${tx.status || 'unknown'}</span></td>
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
                    transactionModal.show();
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
                        <code>${tx.id || 'N/A'}</code>
                    </div>
                    <div class="col-md-6">
                        <strong>Date:</strong><br>
                        ${formatDate(tx.transaction_date || tx.created_at)}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Type:</strong><br>
                        ${formatType(tx.type)}
                    </div>
                    <div class="col-md-6">
                        <strong>Amount:</strong><br>
                        ₱${(parseFloat(tx.amount) || 0).toLocaleString()}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        <span class="status-${tx.status || 'unknown'}">${tx.status || 'unknown'}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Institution:</strong><br>
                        ${escapeHtml(tx.institution_name || 'N/A')}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Member:</strong><br>
                        ${escapeHtml(tx.member_name || 'N/A')}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Description:</strong><br>
                        ${escapeHtml(tx.description || 'N/A')}
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
            window.open(`/portal/admin/blockchain/explorer.php?transaction_id=${currentTransactionId}`, '_blank');
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
            if (!dateStr) return 'N/A';
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
            return types[type] || type || 'N/A';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>

    
</body>
</html>
