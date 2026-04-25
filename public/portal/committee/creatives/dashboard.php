<?php
// dashboard.php - Creatives Committee Dashboard
require_once __DIR__ . '/../../auth_check.php';
require_role(['eb_pro_1', 'committee_creatives']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creatives Committee Dashboard - IECEP-LSC MEMSYS</title>
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
                <h3>Creatives Committee</h3>
                <p><?php echo htmlspecialchars($user['name']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#overview" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Overview</a></li>
                    <li><a href="#designs" class="nav-link"><i class="fas fa-palette"></i> Designs</a></li>
                    <li><a href="#materials" class="nav-link"><i class="fas fa-images"></i> Materials</a></li>
                    <li><a href="#events" class="nav-link"><i class="fas fa-calendar"></i> Events</a></li>
                    <li><a href="#tasks" class="nav-link"><i class="fas fa-tasks"></i> Tasks</a></li>
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
                    <p>Creatives Committee Dashboard - Design & Content Management</p>
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
                            <i class="fas fa-palette"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalDesigns">0</div>
                            <div class="metric-label">Total Designs</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-images"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalMaterials">0</div>
                            <div class="metric-label">Materials Created</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="eventsCovered">0</div>
                            <div class="metric-label">Events Covered</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="pendingTasks">0</div>
                            <div class="metric-label">Pending Tasks</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h3>Recent Activity</h3>
                    <div class="activity-list" id="recentActivity">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-palette"></i>
                            </div>
                            <div class="activity-content">
                                <p>New design created</p>
                                <span class="activity-time">2 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-images"></i>
                            </div>
                            <div class="activity-content">
                                <p>Materials uploaded for event</p>
                                <span class="activity-time">5 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="activity-content">
                                <p>Event coverage completed</p>
                                <span class="activity-time">1 day ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Designs Section -->
            <section id="designs" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Design Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddDesignModal()">
                            <i class="fas fa-plus"></i> Add Design
                        </button>
                    </div>
                </div>
                
                <div class="design-grid" id="designsGrid">
                    <div class="design-card">
                        <div class="design-preview">
                            <i class="fas fa-image"></i>
                        </div>
                        <div class="design-info">
                            <h4>Sample Design</h4>
                            <p>Event poster design</p>
                            <span class="design-status status-active">Active</span>
                        </div>
                        <div class="design-actions">
                            <button class="btn btn-sm" onclick="viewDesign('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editDesign('1')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Materials Section -->
            <section id="materials" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Materials Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openUploadMaterialModal()">
                            <i class="fas fa-upload"></i> Upload Material
                        </button>
                    </div>
                </div>
                
                <div class="materials-grid" id="materialsGrid">
                    <div class="material-card">
                        <div class="material-preview">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="material-info">
                            <h4>Event Guidelines</h4>
                            <p>PDF document • 2.5 MB</p>
                            <span class="material-date">Uploaded 2 days ago</span>
                        </div>
                        <div class="material-actions">
                            <button class="btn btn-sm" onclick="downloadMaterial('1')">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-sm" onclick="deleteMaterial('1')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        class CreativesDashboard {
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
                // Add any specific event listeners for creatives committee
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
                    case 'designs':
                        this.loadDesigns();
                        break;
                    case 'materials':
                        this.loadMaterials();
                        break;
                }
            }

            async loadOverviewData() {
                try {
                    const response = await this.apiCall('/committee/overview', 'POST', {
                        committee_type: 'creatives'
                    });
                    
                    if (response.success) {
                        this.updateOverviewMetrics(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load overview data:', error);
                }
            }

            updateOverviewMetrics(data) {
                document.getElementById('totalDesigns').textContent = data.total_designs || 0;
                document.getElementById('totalMaterials').textContent = data.total_materials || 0;
                document.getElementById('eventsCovered').textContent = data.events_covered || 0;
                document.getElementById('pendingTasks').textContent = data.pending_tasks || 0;
            }

            async loadDesigns() {
                try {
                    const response = await this.apiCall('/committee/designs', 'POST', {
                        committee_type: 'creatives'
                    });
                    
                    if (response.success) {
                        this.displayDesigns(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load designs:', error);
                }
            }

            displayDesigns(designs) {
                const grid = document.getElementById('designsGrid');
                grid.innerHTML = '';
                
                if (designs.length === 0) {
                    grid.innerHTML = '<p class="no-data">No designs found</p>';
                    return;
                }
                
                designs.forEach(design => {
                    const card = document.createElement('div');
                    card.className = 'design-card';
                    card.innerHTML = `
                        <div class="design-preview">
                            ${design.preview_url ? `<img src="${design.preview_url}" alt="${design.title}">` : '<i class="fas fa-image"></i>'}
                        </div>
                        <div class="design-info">
                            <h4>${design.title}</h4>
                            <p>${design.description}</p>
                            <span class="design-status status-${design.status}">${design.status}</span>
                        </div>
                        <div class="design-actions">
                            <button class="btn btn-sm" onclick="viewDesign('${design.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editDesign('${design.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            }

            async loadMaterials() {
                try {
                    const response = await this.apiCall('/committee/materials', 'POST', {
                        committee_type: 'creatives'
                    });
                    
                    if (response.success) {
                        this.displayMaterials(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load materials:', error);
                }
            }

            displayMaterials(materials) {
                const grid = document.getElementById('materialsGrid');
                grid.innerHTML = '';
                
                if (materials.length === 0) {
                    grid.innerHTML = '<p class="no-data">No materials found</p>';
                    return;
                }
                
                materials.forEach(material => {
                    const card = document.createElement('div');
                    card.className = 'material-card';
                    card.innerHTML = `
                        <div class="material-preview">
                            <i class="fas fa-${this.getFileIcon(material.file_type)}"></i>
                        </div>
                        <div class="material-info">
                            <h4>${material.title}</h4>
                            <p>${material.file_type} • ${this.formatFileSize(material.file_size)}</p>
                            <span class="material-date">${new Date(material.created_at).toLocaleDateString()}</span>
                        </div>
                        <div class="material-actions">
                            <button class="btn btn-sm" onclick="downloadMaterial('${material.id}')">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-sm" onclick="deleteMaterial('${material.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            }

            getFileIcon(fileType) {
                const icons = {
                    'pdf': 'file-pdf',
                    'doc': 'file-word',
                    'docx': 'file-word',
                    'jpg': 'file-image',
                    'png': 'file-image',
                    'zip': 'file-archive'
                };
                return icons[fileType.toLowerCase()] || 'file';
            }

            formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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

        function openAddDesignModal() {
            console.log('Opening add design modal');
        }

        function openUploadMaterialModal() {
            console.log('Opening upload material modal');
        }

        function viewDesign(id) {
            console.log('Viewing design:', id);
        }

        function editDesign(id) {
            console.log('Editing design:', id);
        }

        function downloadMaterial(id) {
            console.log('Downloading material:', id);
        }

        function deleteMaterial(id) {
            if (confirm('Are you sure you want to delete this material?')) {
                console.log('Deleting material:', id);
            }
        }

        // Initialize dashboard
        let dashboard;
        document.addEventListener('DOMContentLoaded', () => {
            dashboard = new CreativesDashboard();
        });
    </script>
</body>
</html>
