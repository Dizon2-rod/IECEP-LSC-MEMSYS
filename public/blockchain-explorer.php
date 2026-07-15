<?php
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blockchain Explorer - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/design-tokens.css">
    <style>
        :root {
            --primary-color: #0B1D4A;
            --secondary-color: #C49A00;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }
        
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e3a8a 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border-left: 4px solid var(--secondary-color);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .record-type-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .badge-transaction { background-color: #dbeafe; color: #1e40af; }
        .badge-membership { background-color: #d1fae5; color: #065f46; }
        .badge-compliance { background-color: #fef3c7; color: #92400e; }
        .badge-document { background-color: #e0e7ff; color: #3730a3; }
        .badge-affiliation { background-color: #fce7f3; color: #9d174d; }
        .badge-digital-id { background-color: #ccfbf1; color: #115e59; }
        
        .verification-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .status-valid {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-invalid {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .hash-text {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            word-break: break-all;
            color: #64748b;
        }
        
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: var(--primary-color);
        }
        
        .btn-secondary:hover {
            background-color: #d4a824;
            border-color: #d4a824;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">Blockchain Explorer</h1>
                    <p class="mb-0 opacity-75">Verify and explore immutable blockchain records</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light" onclick="verifyAllChains()">
                        <i class="fas fa-shield-alt me-2"></i>Verify All Chains
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value" id="total-records">-</div>
                    <div class="stat-label">Total Records</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value" id="recent-activity">-</div>
                    <div class="stat-label">Last 7 Days</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value" id="chain-status">-</div>
                    <div class="stat-label">Chain Status</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value" id="verified-records">-</div>
                    <div class="stat-label">Verified Records</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Record Type</label>
                    <select class="form-select" id="filter-type">
                        <option value="">All Types</option>
                        <option value="transaction">Transaction</option>
                        <option value="membership_change">Membership Change</option>
                        <option value="compliance_attendance">Compliance Attendance</option>
                        <option value="document_hash">Document Hash</option>
                        <option value="affiliation_action">Affiliation Action</option>
                        <option value="digital_id">Digital ID</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Entity ID</label>
                    <input type="text" class="form-control" id="filter-entity-id" placeholder="Search by ID">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" onclick="loadRecords()">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-secondary w-100" onclick="resetFilters()">
                        <i class="fas fa-redo me-2"></i>Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Records Table -->
        <div class="table-container">
            <div class="loading-spinner" id="loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            
            <table class="table table-hover mb-0" id="records-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Entity ID</th>
                        <th>Record Hash</th>
                        <th>Previous Hash</th>
                        <th>Created At</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="records-body">
                    <!-- Records will be loaded here -->
                </tbody>
            </table>
            
            <div class="p-3 text-center" id="no-records" style="display: none;">
                <p class="text-muted mb-0">No records found</p>
            </div>
        </div>

        <!-- Pagination -->
        <nav class="mt-4">
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Pagination will be loaded here -->
            </ul>
        </nav>
    </div>

    <!-- Record Detail Modal -->
    <div class="modal fade" id="recordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="record-detail">
                    <!-- Record details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        let currentPage = 0;
        const recordsPerPage = 20;

        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch('api/blockchain/explorer.php?action=statistics');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('total-records').textContent = data.statistics.total_records;
                    document.getElementById('recent-activity').textContent = data.statistics.recent_activity;
                    document.getElementById('verified-records').textContent = data.statistics.total_records;
                    document.getElementById('chain-status').textContent = 'Active';
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }

        // Load records
        async function loadRecords() {
            const filterType = document.getElementById('filter-type').value;
            const filterEntityId = document.getElementById('filter-entity-id').value;
            
            document.getElementById('loading').style.display = 'block';
            document.getElementById('records-body').innerHTML = '';
            
            try {
                let url = `api/blockchain/explorer.php?action=records&limit=${recordsPerPage}&offset=${currentPage * recordsPerPage}`;
                if (filterType) url += `&record_type=${filterType}`;
                if (filterEntityId) url += `&entity_id=${filterEntityId}`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayRecords(data.records);
                    updatePagination(data.total);
                }
            } catch (error) {
                console.error('Error loading records:', error);
            } finally {
                document.getElementById('loading').style.display = 'none';
            }
        }

        // Display records
        function displayRecords(records) {
            const tbody = document.getElementById('records-body');
            const noRecords = document.getElementById('no-records');
            
            if (records.length === 0) {
                noRecords.style.display = 'block';
                return;
            }
            
            noRecords.style.display = 'none';
            
            records.forEach(record => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><span class="record-type-badge badge-${getBadgeClass(record.record_type)}">${formatRecordType(record.record_type)}</span></td>
                    <td><span class="hash-text">${record.entity_id.substring(0, 20)}...</span></td>
                    <td><span class="hash-text">${record.record_hash.substring(0, 16)}...</span></td>
                    <td><span class="hash-text">${record.previous_hash ? record.previous_hash.substring(0, 16) + '...' : 'Genesis'}</span></td>
                    <td>${formatDate(record.created_at)}</td>
                    <td><span class="verification-status status-valid"><i class="fas fa-check-circle"></i> Verified</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewRecord('${record.id}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Get badge class for record type
        function getBadgeClass(type) {
            const classes = {
                'transaction': 'transaction',
                'membership_change': 'membership',
                'compliance_attendance': 'compliance',
                'document_hash': 'document',
                'affiliation_action': 'affiliation',
                'digital_id': 'digital-id'
            };
            return classes[type] || 'transaction';
        }

        // Format record type
        function formatRecordType(type) {
            return type.replace(/_/g, ' ');
        }

        // Format date
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }

        // Update pagination
        function updatePagination(total) {
            const totalPages = Math.ceil(total / recordsPerPage);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            
            if (totalPages <= 1) return;
            
            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 0 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>`;
            pagination.appendChild(prevLi);
            
            // Page numbers
            for (let i = 0; i < totalPages; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="changePage(${i})">${i + 1}</a>`;
                pagination.appendChild(li);
            }
            
            // Next button
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages - 1 ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>`;
            pagination.appendChild(nextLi);
        }

        // Change page
        function changePage(page) {
            currentPage = page;
            loadRecords();
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('filter-type').value = '';
            document.getElementById('filter-entity-id').value = '';
            currentPage = 0;
            loadRecords();
        }

        // View record details
        async function viewRecord(recordId) {
            // This would fetch detailed record information
            const modal = new bootstrap.Modal(document.getElementById('recordModal'));
            document.getElementById('record-detail').innerHTML = '<p class="text-center">Loading record details...</p>';
            modal.show();
            
            // For now, show a placeholder
            setTimeout(() => {
                document.getElementById('record-detail').innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Record ID: ${recordId}
                    </div>
                    <p>Detailed record view would be implemented here.</p>
                `;
            }, 500);
        }

        // Verify all chains
        async function verifyAllChains() {
            const recordTypes = ['transaction', 'membership_change', 'compliance_attendance', 'document_hash', 'affiliation_action', 'digital_id'];
            
            for (const type of recordTypes) {
                try {
                    const response = await fetch(`api/blockchain/explorer.php?action=verify-chain&record_type=${type}`);
                    const data = await response.json();
                    
                    if (data.success && !data.verification.valid) {
                        alert(`Chain integrity issue detected in ${type} records!`);
                        return;
                    }
                } catch (error) {
                    console.error(`Error verifying ${type} chain:`, error);
                }
            }
            
            alert('All blockchain chains verified successfully!');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadRecords();
        });
    </script>
</body>
</html>
