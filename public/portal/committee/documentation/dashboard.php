<?php
// dashboard.php - Documentation Committee Dashboard
require_once __DIR__ . '/../../../auth_check.php';
require_role(['committee_documentation']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation Committee Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../../../assets/icons/iecep-logo.png" alt="IECEP-LSC Logo" class="sidebar-logo">
                <h3>Documentation Committee</h3>
                <p><?php echo htmlspecialchars($user['name']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#overview" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Overview</a></li>
                    <li><a href="#documents" class="nav-link"><i class="fas fa-file-alt"></i> Documents</a></li>
                    <li><a href="#templates" class="nav-link"><i class="fas fa-copy"></i> Templates</a></li>
                    <li><a href="#guidelines" class="nav-link"><i class="fas fa-book"></i> Guidelines</a></li>
                    <li><a href="#archives" class="nav-link"><i class="fas fa-archive"></i> Archives</a></li>
                    <li><a href="../../../login.php?logout=1" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <h1>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                    <p>Documentation Committee Dashboard - Document & Archive Management</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <div class="user-menu">
                        <img src="../../../assets/icons/default-avatar.png" alt="User" class="user-avatar">
                        <span><?php echo htmlspecialchars($role_display); ?></span>
                    </div>
                </div>
            </header>

            <!-- Overview Section -->
            <section id="overview" class="dashboard-section">
                <h2>Overview</h2>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalDocuments">0</div>
                            <div class="metric-label">Total Documents</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-copy"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalTemplates">0</div>
                            <div class="metric-label">Templates Created</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="guidelinesPublished">0</div>
                            <div class="metric-label">Guidelines Published</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-archive"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="archivedItems">0</div>
                            <div class="metric-label">Archived Items</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h3>Recent Activity</h3>
                    <div class="activity-list" id="recentActivity">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="activity-content">
                                <p>New document uploaded</p>
                                <span class="activity-time">1 hour ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-copy"></i>
                            </div>
                            <div class="activity-content">
                                <p>Template created</p>
                                <span class="activity-time">3 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="activity-content">
                                <p>Guidelines updated</p>
                                <span class="activity-time">1 day ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Documents Section -->
            <section id="documents" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Document Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddDocumentModal()">
                            <i class="fas fa-plus"></i> Add Document
                        </button>
                    </div>
                </div>
                
                <div class="document-filters">
                    <div class="search-box">
                        <input type="text" placeholder="Search documents..." id="documentSearch">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-dropdown">
                        <select id="documentType">
                            <option value="all">All Types</option>
                            <option value="policy">Policy</option>
                            <option value="procedure">Procedure</option>
                            <option value="manual">Manual</option>
                            <option value="form">Form</option>
                            <option value="report">Report</option>
                        </select>
                    </div>
                    <div class="filter-dropdown">
                        <select id="documentStatus">
                            <option value="all">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="review">Under Review</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>

                <div class="documents-grid" id="documentsGrid">
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="document-info">
                            <h4>Chapter Bylaws 2026</h4>
                            <p>Updated chapter bylaws and constitution</p>
                            <div class="document-meta">
                                <span class="document-type">Policy</span>
                                <span class="document-date">Updated 2 days ago</span>
                            </div>
                        </div>
                        <div class="document-status">
                            <span class="status-badge status-published">Published</span>
                        </div>
                        <div class="document-actions">
                            <button class="btn btn-sm" onclick="viewDocument('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editDocument('1')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm" onclick="downloadDocument('1')">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Templates Section -->
            <section id="templates" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Template Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddTemplateModal()">
                            <i class="fas fa-plus"></i> Add Template
                        </button>
                    </div>
                </div>
                
                <div class="templates-grid" id="templatesGrid">
                    <div class="template-card">
                        <div class="template-preview">
                            <i class="fas fa-file-word"></i>
                        </div>
                        <div class="template-info">
                            <h4>Event Report Template</h4>
                            <p>Standard template for event reporting</p>
                            <div class="template-meta">
                                <span class="template-type">Report</span>
                                <span class="template-usage">Used 15 times</span>
                            </div>
                        </div>
                        <div class="template-status">
                            <span class="status-badge status-active">Active</span>
                        </div>
                        <div class="template-actions">
                            <button class="btn btn-sm" onclick="viewTemplate('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editTemplate('1')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm" onclick="useTemplate('1')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Guidelines Section -->
            <section id="guidelines" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Guidelines Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddGuidelineModal()">
                            <i class="fas fa-plus"></i> Add Guideline
                        </button>
                    </div>
                </div>
                
                <div class="guidelines-list" id="guidelinesList">
                    <div class="guideline-item">
                        <div class="guideline-header">
                            <h4>Member Conduct Guidelines</h4>
                            <span class="guideline-status status-published">Published</span>
                        </div>
                        <div class="guideline-content">
                            <p>Comprehensive guidelines for member conduct and professional behavior</p>
                            <div class="guideline-meta">
                                <span class="guideline-version">Version 2.1</span>
                                <span class="guideline-date">Last updated: 1 week ago</span>
                            </div>
                        </div>
                        <div class="guideline-actions">
                            <button class="btn btn-sm" onclick="viewGuideline('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editGuideline('1')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm" onclick="publishGuideline('1')">
                                <i class="fas fa-share"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        class DocumentationDashboard {
            constructor() {
                this.apiBase = '/IECEP-LSC-MEMSYS/src/api';
                this.currentSection = 'overview';
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.loadOverviewData();
                this.setupNavigation();
            }

            setupEventListeners() {
                // Add documentation-specific event listeners
                document.getElementById('documentSearch').addEventListener('input', (e) => {
                    this.searchDocuments(e.target.value);
                });

                document.getElementById('documentType').addEventListener('change', (e) => {
                    this.filterDocuments(e.target.value, 'type');
                });

                document.getElementById('documentStatus').addEventListener('change', (e) => {
                    this.filterDocuments(e.target.value, 'status');
                });
            }

            setupNavigation() {
                const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        const section = link.getAttribute('href').substring(1);
                        this.showSection(section);
                        
                        navLinks.forEach(l => l.classList.remove('active'));
                        link.classList.add('active');
                    });
                });
            }

            showSection(section) {
                document.querySelectorAll('.dashboard-section').forEach(s => {
                    s.style.display = 'none';
                });
                
                const targetSection = document.getElementById(section);
                if (targetSection) {
                    targetSection.style.display = 'block';
                    this.currentSection = section;
                    
                    this.loadSectionData(section);
                }
            }

            loadSectionData(section) {
                switch(section) {
                    case 'overview':
                        this.loadOverviewData();
                        break;
                    case 'documents':
                        this.loadDocuments();
                        break;
                    case 'templates':
                        this.loadTemplates();
                        break;
                    case 'guidelines':
                        this.loadGuidelines();
                        break;
                }
            }

            async loadOverviewData() {
                try {
                    const response = await this.apiCall('/committee/overview', 'POST', {
                        committee_type: 'documentation'
                    });
                    
                    if (response.success) {
                        this.updateOverviewMetrics(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load overview data:', error);
                }
            }

            updateOverviewMetrics(data) {
                document.getElementById('totalDocuments').textContent = data.total_documents || 0;
                document.getElementById('totalTemplates').textContent = data.total_templates || 0;
                document.getElementById('guidelinesPublished').textContent = data.guidelines_published || 0;
                document.getElementById('archivedItems').textContent = data.archived_items || 0;
            }

            async loadDocuments() {
                try {
                    const response = await this.apiCall('/committee/documents', 'POST', {
                        committee_type: 'documentation'
                    });
                    
                    if (response.success) {
                        this.displayDocuments(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load documents:', error);
                }
            }

            displayDocuments(documents) {
                const grid = document.getElementById('documentsGrid');
                grid.innerHTML = '';
                
                if (documents.length === 0) {
                    grid.innerHTML = '<p class="no-data">No documents found</p>';
                    return;
                }
                
                documents.forEach(document => {
                    const card = document.createElement('div');
                    card.className = 'document-card';
                    card.innerHTML = `
                        <div class="document-icon">
                            <i class="fas fa-${this.getDocumentIcon(document.type)}"></i>
                        </div>
                        <div class="document-info">
                            <h4>${document.title}</h4>
                            <p>${document.description}</p>
                            <div class="document-meta">
                                <span class="document-type">${document.type}</span>
                                <span class="document-date">${this.getTimeAgo(document.updated_at)}</span>
                            </div>
                        </div>
                        <div class="document-status">
                            <span class="status-badge status-${document.status}">${document.status}</span>
                        </div>
                        <div class="document-actions">
                            <button class="btn btn-sm" onclick="viewDocument('${document.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editDocument('${document.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm" onclick="downloadDocument('${document.id}')">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            }

            async loadTemplates() {
                try {
                    const response = await this.apiCall('/committee/templates', 'POST', {
                        committee_type: 'documentation'
                    });
                    
                    if (response.success) {
                        this.displayTemplates(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load templates:', error);
                }
            }

            displayTemplates(templates) {
                const grid = document.getElementById('templatesGrid');
                grid.innerHTML = '';
                
                if (templates.length === 0) {
                    grid.innerHTML = '<p class="no-data">No templates found</p>';
                    return;
                }
                
                templates.forEach(template => {
                    const card = document.createElement('div');
                    card.className = 'template-card';
                    card.innerHTML = `
                        <div class="template-preview">
                            <i class="fas fa-${this.getTemplateIcon(template.type)}"></i>
                        </div>
                        <div class="template-info">
                            <h4>${template.title}</h4>
                            <p>${template.description}</p>
                            <div class="template-meta">
                                <span class="template-type">${template.type}</span>
                                <span class="template-usage">Used ${template.usage_count || 0} times</span>
                            </div>
                        </div>
                        <div class="template-status">
                            <span class="status-badge status-${template.status}">${template.status}</span>
                        </div>
                        <div class="template-actions">
                            <button class="btn btn-sm" onclick="viewTemplate('${template.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editTemplate('${template.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm" onclick="useTemplate('${template.id}')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            }

            async loadGuidelines() {
                try {
                    const response = await this.apiCall('/committee/guidelines', 'POST', {
                        committee_type: 'documentation'
                    });
                    
                    if (response.success) {
                        this.displayGuidelines(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load guidelines:', error);
                }
            }

            displayGuidelines(guidelines) {
                const list = document.getElementById('guidelinesList');
                list.innerHTML = '';
                
                if (guidelines.length === 0) {
                    list.innerHTML = '<p class="no-data">No guidelines found</p>';
                    return;
                }
                
                guidelines.forEach(guideline => {
                    const item = document.createElement('div');
                    item.className = 'guideline-item';
                    item.innerHTML = `
                        <div class="guideline-header">
                            <h4>${guideline.title}</h4>
                            <span class="guideline-status status-${guideline.status}">${guideline.status}</span>
                        </div>
                        <div class="guideline-content">
                            <p>${guideline.description}</p>
                            <div class="guideline-meta">
                                <span class="guideline-version">Version ${guideline.version || '1.0'}</span>
                                <span class="guideline-date">Last updated: ${this.getTimeAgo(guideline.updated_at)}</span>
                            </div>
                        </div>
                        <div class="guideline-actions">
                            <button class="btn btn-sm" onclick="viewGuideline('${guideline.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editGuideline('${guideline.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm" onclick="publishGuideline('${guideline.id}')">
                                <i class="fas fa-share"></i>
                            </button>
                        </div>
                    `;
                    list.appendChild(item);
                });
            }

            getDocumentIcon(type) {
                const icons = {
                    'policy': 'file-contract',
                    'procedure': 'file-alt',
                    'manual': 'file-code',
                    'form': 'file-invoice',
                    'report': 'file-chart-line'
                };
                return icons[type.toLowerCase()] || 'file';
            }

            getTemplateIcon(type) {
                const icons = {
                    'report': 'file-chart-line',
                    'form': 'file-invoice',
                    'letter': 'file-alt',
                    'certificate': 'file-certificate'
                };
                return icons[type.toLowerCase()] || 'file';
            }

            getTimeAgo(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diff = now - date;
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const days = Math.floor(hours / 24);
                
                if (days > 0) {
                    return `${days} day${days > 1 ? 's' : ''} ago`;
                } else if (hours > 0) {
                    return `${hours} hour${hours > 1 ? 's' : ''} ago`;
                } else {
                    return 'Just now';
                }
            }

            searchDocuments(query) {
                console.log('Searching documents:', query);
            }

            filterDocuments(value, type) {
                console.log(`Filtering documents by ${type}:`, value);
            }

            async apiCall(endpoint, method = 'GET', data = null) {
                const options = {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                    }
                };

                if (data) {
                    options.body = JSON.stringify(data);
                }

                const response = await fetch(this.apiBase + endpoint, options);
                return await response.json();
            }

            refreshData() {
                this.loadSectionData(this.currentSection);
            }
        }

        // Global functions
        function refreshData() {
            dashboard.refreshData();
        }

        function openAddDocumentModal() {
            console.log('Opening add document modal');
        }

        function openAddTemplateModal() {
            console.log('Opening add template modal');
        }

        function openAddGuidelineModal() {
            console.log('Opening add guideline modal');
        }

        function viewDocument(id) {
            console.log('Viewing document:', id);
        }

        function editDocument(id) {
            console.log('Editing document:', id);
        }

        function downloadDocument(id) {
            console.log('Downloading document:', id);
        }

        function viewTemplate(id) {
            console.log('Viewing template:', id);
        }

        function editTemplate(id) {
            console.log('Editing template:', id);
        }

        function useTemplate(id) {
            console.log('Using template:', id);
        }

        function viewGuideline(id) {
            console.log('Viewing guideline:', id);
        }

        function editGuideline(id) {
            console.log('Editing guideline:', id);
        }

        function publishGuideline(id) {
            console.log('Publishing guideline:', id);
        }

        // Initialize dashboard
        let dashboard;
        document.addEventListener('DOMContentLoaded', () => {
            dashboard = new DocumentationDashboard();
        });
    </script>
</body>
</html>
