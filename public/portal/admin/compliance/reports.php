<?php
require_once dirname(__DIR__, 2) . '/public/portal/auth_check.php';

if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

// Check if user is admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin', 'super_admin', 'auditor'])) {
    header('Location: /login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance Reports - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/design-tokens.css">
    <style>
        :root {
            --primary-color: #0B1D4A;
            --secondary-color: #C49A00;
        }
        
        body {
            background-color: #f8fafc;
        }
        
        .report-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-compliant { background-color: #d1fae5; color: #065f46; }
        .status-at_risk { background-color: #fef3c7; color: #92400e; }
        .status-non_compliant { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2 class="mb-3">Compliance Reports</h2>
                <div class="d-flex gap-2 mb-3">
                    <select class="form-select w-auto" id="report-year" onchange="loadReports()">
                        <option value="2024">2024</option>
                        <option value="2025">2025</option>
                        <option value="2026" selected>2026</option>
                    </select>
                    <button class="btn btn-primary" onclick="loadReports()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
        
        <div id="reports-list">
            <!-- Reports will be loaded here -->
        </div>
    </div>
    
    <!-- Report Detail Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Compliance Report Detail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="report-detail">
                    <!-- Report details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="generatePDF()">
                        <i class="fas fa-file-pdf me-2"></i>Generate PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        let currentInstitutionId = null;
        
        async function loadReports() {
            const year = document.getElementById('report-year').value;
            
            try {
                const response = await fetch(`/api/compliance/reports.php?action=all-institutions&year=${year}`);
                const data = await response.json();
                
                if (data.success) {
                    displayReports(data.reports);
                }
            } catch (error) {
                console.error('Error loading reports:', error);
            }
        }
        
        function displayReports(reports) {
            const container = document.getElementById('reports-list');
            container.innerHTML = '';
            
            if (reports.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No compliance reports found.</div>';
                return;
            }
            
            reports.forEach(report => {
                const compliance = report.compliance;
                const status = compliance ? compliance.compliance_status : 'not_evaluated';
                const score = compliance ? compliance.overall_score : 0;
                const participation = compliance ? compliance.participation_rate : 0;
                const hostedEvents = compliance ? compliance.hosted_event_count : 0;
                
                const card = document.createElement('div');
                card.className = 'report-card';
                card.innerHTML = `
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-1">${report.institution_name}</h5>
                            <small class="text-muted">Compliance Score: ${score}%</small>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="status-badge status-${status}">${status.replace('_', ' ')}</span>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="h5 mb-0">${participation}%</div>
                            <small class="text-muted">Participation</small>
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewReport('${report.institution_id}')">
                                <i class="fas fa-eye me-1"></i>View
                            </button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <small class="text-muted">
                                <i class="fas fa-calendar-check me-1"></i>Events Hosted: ${hostedEvents}
                            </small>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        }
        
        async function viewReport(institutionId) {
            currentInstitutionId = institutionId;
            const year = document.getElementById('report-year').value;
            
            try {
                const response = await fetch(`/api/compliance/reports.php?action=institution-report&institution_id=${institutionId}&year=${year}`);
                const data = await response.json();
                
                if (data.success) {
                    displayReportDetail(data.report);
                    bootstrap.Modal.getInstance(document.getElementById('reportModal')).show();
                }
            } catch (error) {
                console.error('Error loading report detail:', error);
            }
        }
        
        function displayReportDetail(report) {
            const container = document.getElementById('report-detail');
            const stats = report.statistics;
            const compliance = report.compliance;
            
            container.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>${report.institution.name}</h6>
                        <p class="text-muted mb-0">${report.institution.address || 'N/A'}</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="status-badge status-${compliance ? compliance.compliance_status : 'not_evaluated'}">
                            ${compliance ? ucfirst(compliance.compliance_status) : 'Not Evaluated'}
                        </span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="h4 mb-0">${stats.participation_rate}%</div>
                            <small class="text-muted">Participation</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="h4 mb-0">${stats.events_hosted}</div>
                            <small class="text-muted">Events Hosted</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="h4 mb-0">${stats.events_attended}</div>
                            <small class="text-muted">Events Attended</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <div class="h4 mb-0">${stats.total_members}</div>
                            <small class="text-muted">Total Members</small>
                        </div>
                    </div>
                </div>
                
                <h6 class="mb-2">Recommendations</h6>
                <ul class="mb-3">
                    ${report.recommendations.map(r => `<li>${r}</li>`).join('')}
                </ul>
                
                <h6 class="mb-2">Hosted Events</h6>
                ${report.hosted_events.length > 0 ? `
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Venue</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${report.hosted_events.map(e => `
                                <tr>
                                    <td>${e.title}</td>
                                    <td>${formatDate(e.start_date)}</td>
                                    <td>${e.venue || 'N/A'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                ` : '<p class="text-muted">No events hosted this year</p>'}
            `;
        }
        
        async function generatePDF() {
            if (!currentInstitutionId) return;
            
            const year = document.getElementById('report-year').value;
            
            try {
                const response = await fetch(`/api/compliance/reports.php?action=generate-pdf&institution_id=${currentInstitutionId}&year=${year}`);
                const data = await response.json();
                
                if (data.success) {
                    window.open(data.pdf_path, '_blank');
                } else {
                    alert('Error generating PDF: ' + data.error);
                }
            } catch (error) {
                console.error('Error generating PDF:', error);
                alert('Error generating PDF');
            }
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadReports();
        });
    </script>
</body>
</html>
