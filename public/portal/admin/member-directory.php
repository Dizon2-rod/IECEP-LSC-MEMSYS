<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/auth_check.php';
require_role(['admin', 'super_admin', 'committee_registration']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Directory - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
        }
        .filter-group label {
            display: block;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-family: var(--font-family);
            font-size: var(--font-size-base);
        }
        .table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: var(--primary-navy);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-sm);
        }
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .data-table tr:hover {
            background: var(--gray-50);
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }
        .status-active { background: var(--success-light); color: var(--success-dark); }
        .status-inactive { background: var(--gray-200); color: var(--gray-700); }
        .status-pending { background: var(--warning-light); color: var(--warning-dark); }
        .status-suspended { background: var(--error-light); color: var(--error-dark); }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: var(--font-size-sm);
        }
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
        }
        .pagination-info {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }
        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Member Directory</h1>
                    <p class="text-gray">View and manage all members</p>
                </div>
                <div class="action-buttons">
                    <button onclick="exportCSV()" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>

            <div class="filters">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="searchInput" placeholder="Name, email, or membership ID">
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Institution</label>
                    <input type="text" id="institutionFilter" placeholder="Institution name">
                </div>
                <div class="filter-group">
                    <label>Year Level</label>
                    <select id="yearLevelFilter">
                        <option value="">All Year Levels</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                        <option value="5th Year">5th Year</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button onclick="applyFilters()" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Membership ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Institution</th>
                            <th>Year Level</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="membersTable">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem;">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="pagination">
                    <div class="pagination-info" id="paginationInfo">
                        Showing 0 of 0 members
                    </div>
                    <div class="pagination-controls">
                        <button onclick="changePage(-1)" class="btn btn-secondary btn-sm" id="prevBtn">Previous</button>
                        <span id="pageInfo">Page 1</span>
                        <button onclick="changePage(1)" class="btn btn-secondary btn-sm" id="nextBtn">Next</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete this member? This action cannot be undone.</p>
            <div class="modal-actions">
                <button onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmDelete()" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let totalPages = 1;
        let memberToDelete = null;

        function loadMembers() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            const institution = document.getElementById('institutionFilter').value;
            const yearLevel = document.getElementById('yearLevelFilter').value;

            const url = `<?php echo BASE_URL; ?>/api/member-directory.php?page=${currentPage}&limit=20&search=${encodeURIComponent(search)}&status=${status}&institution=${encodeURIComponent(institution)}&year_level=${yearLevel}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderMembers(data.data);
                        updatePagination(data.pagination);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function renderMembers(members) {
            const tbody = document.getElementById('membersTable');
            
            if (members.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem;">No members found</td></tr>';
                return;
            }

            tbody.innerHTML = members.map(member => `
                <tr>
                    <td><strong>${member.membership_id || 'N/A'}</strong></td>
                    <td>${member.full_name}</td>
                    <td>${member.email}</td>
                    <td>${member.institution_acronym || member.institution_name || 'N/A'}</td>
                    <td>${member.year_level || 'N/A'}</td>
                    <td><span class="status-badge status-${member.membership_status}">${member.membership_status}</span></td>
                    <td>${member.payment_status ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td>
                    <td>
                        <div class="action-buttons">
                            <button onclick="viewMember('${member.id}')" class="btn btn-sm btn-primary" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="editMember('${member.id}')" class="btn btn-sm btn-secondary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteMember('${member.id}')" class="btn btn-sm btn-danger" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function updatePagination(pagination) {
            currentPage = pagination.page;
            totalPages = pagination.total_pages;
            
            document.getElementById('paginationInfo').textContent = 
                `Showing ${(currentPage - 1) * pagination.limit + 1}-${Math.min(currentPage * pagination.limit, pagination.total)} of ${pagination.total} members`;
            
            document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = currentPage === totalPages;
        }

        function changePage(delta) {
            currentPage += delta;
            loadMembers();
        }

        function applyFilters() {
            currentPage = 1;
            loadMembers();
        }

        function viewMember(id) {
            window.location.href = `member-profile.php?id=${id}`;
        }

        function editMember(id) {
            window.location.href = `member-profile.php?id=${id}&mode=edit`;
        }

        function deleteMember(id) {
            memberToDelete = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
            memberToDelete = null;
        }

        function confirmDelete() {
            if (memberToDelete) {
                fetch(`<?php echo BASE_URL; ?>/api/delete-member.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: memberToDelete })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal();
                        loadMembers();
                    } else {
                        alert('Error deleting member: ' + data.error);
                    }
                });
            }
        }

        function exportCSV() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            const institution = document.getElementById('institutionFilter').value;
            const yearLevel = document.getElementById('yearLevelFilter').value;
            
            window.location.href = `<?php echo BASE_URL; ?>/api/export-members.php?search=${encodeURIComponent(search)}&status=${status}&institution=${encodeURIComponent(institution)}&year_level=${yearLevel}`;
        }

        // Load members on page load
        document.addEventListener('DOMContentLoaded', loadMembers);
        
        // Add enter key listener for search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });
    </script>

    <!-- Supabase CDN + realtime engine -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script>
        window.IECEP_CONFIG = window.IECEP_CONFIG || {
            SUPABASE_URL: <?php echo json_encode(SUPABASE_URL); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(SUPABASE_ANON_KEY); ?>
        };
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/assets/js/realtime.js" defer></script>
    <script src="/IECEP-LSC-MEMSYS/public/js/realtime.js" defer></script>

    <!-- Realtime script for member directory -->
    <script>
    /**
     * Member Directory — Realtime block
     * Subscribes to: members (INSERT/UPDATE/DELETE)
     * On change: reload member data from API to keep table in sync.
     */
    (function () {
        'use strict';

        function boot() {
            const RT = window.IECEP_REALTIME;
            if (!RT) return;

            // — members: INSERT/UPDATE/DELETE — reload table —————————
            RT.subscribe('members', ['INSERT', 'UPDATE', 'DELETE'], (payload) => {
                if (!RT.validatePayload(payload, ['id'])) return;
                RT.showToast('Member data updated — refreshing directory', 'info');
                // Reload members to reflect changes
                loadMembers();
            });
        }

        if (window.IECEP_REALTIME) {
            boot();
        } else {
            window.addEventListener('iecep:realtime:ready', boot, { once: true });
        }
    })();
    </script>
</body>
</html>
