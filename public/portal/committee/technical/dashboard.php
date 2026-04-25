<?php
// dashboard.php - Technical Committee Dashboard
require_once __DIR__ . '/../../auth_check.php';
require_role(['committee_technical']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technical Committee Dashboard - IECEP-LSC MEMSYS</title>
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
                <h3>Technical Committee</h3>
                <p><?php echo htmlspecialchars($user['name']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#overview" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Overview</a></li>
                    <li><a href="#systems" class="nav-link"><i class="fas fa-server"></i> Systems</a></li>
                    <li><a href="#maintenance" class="nav-link"><i class="fas fa-tools"></i> Maintenance</a></li>
                    <li><a href="#support" class="nav-link"><i class="fas fa-headset"></i> Support</a></li>
                    <li><a href="#documentation" class="nav-link"><i class="fas fa-book"></i> Documentation</a></li>
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
                    <p>Technical Committee Dashboard - System & Support Management</p>
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
                            <i class="fas fa-server"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="activeSystems">0</div>
                            <div class="metric-label">Active Systems</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="maintenanceTasks">0</div>
                            <div class="metric-label">Maintenance Tasks</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="supportTickets">0</div>
                            <div class="metric-label">Support Tickets</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="documentationPages">0</div>
                            <div class="metric-label">Documentation Pages</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h3>Recent Activity</h3>
                    <div class="activity-list" id="recentActivity">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-server"></i>
                            </div>
                            <div class="activity-content">
                                <p>System maintenance completed</p>
                                <span class="activity-time">2 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <div class="activity-content">
                                <p>Support ticket resolved</p>
                                <span class="activity-time">4 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="activity-content">
                                <p>Documentation updated</p>
                                <span class="activity-time">1 day ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Systems Section -->
            <section id="systems" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>System Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddSystemModal()">
                            <i class="fas fa-plus"></i> Add System
                        </button>
                    </div>
                </div>
                
                <div class="systems-grid" id="systemsGrid">
                    <div class="system-card">
                        <div class="system-header">
                            <h4>Web Server</h4>
                            <span class="system-status status-online">Online</span>
                        </div>
                        <div class="system-content">
                            <p>Main web server hosting the MEMSYS application</p>
                            <div class="system-stats">
                                <div class="stat">
                                    <span class="stat-label">Uptime:</span>
                                    <span class="stat-value">99.9%</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">CPU:</span>
                                    <span class="stat-value">45%</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Memory:</span>
                                    <span class="stat-value">62%</span>
                                </div>
                            </div>
                        </div>
                        <div class="system-actions">
                            <button class="btn btn-sm" onclick="viewSystem('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="restartSystem('1')">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Maintenance Section -->
            <section id="maintenance" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Maintenance Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddMaintenanceModal()">
                            <i class="fas fa-plus"></i> Schedule Maintenance
                        </button>
                    </div>
                </div>
                
                <div class="maintenance-list" id="maintenanceList">
                    <div class="maintenance-card">
                        <div class="maintenance-header">
                            <h4>Database Backup</h4>
                            <span class="maintenance-status status-scheduled">Scheduled</span>
                        </div>
                        <div class="maintenance-content">
                            <p>Weekly database backup and optimization</p>
                            <div class="maintenance-details">
                                <div class="detail">
                                    <span class="detail-label">Date:</span>
                                    <span class="detail-value">2026-01-20 02:00 AM</span>
                                </div>
                                <div class="detail">
                                    <span class="detail-label">Duration:</span>
                                    <span class="detail-value">2 hours</span>
                                </div>
                                <div class="detail">
                                    <span class="detail-label">Impact:</span>
                                    <span class="detail-value">Low</span>
                                </div>
                            </div>
                        </div>
                        <div class="maintenance-actions">
                            <button class="btn btn-sm" onclick="editMaintenance('1')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm" onclick="cancelMaintenance('1')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Support Section -->
            <section id="support" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Support Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddTicketModal()">
                            <i class="fas fa-plus"></i> Create Ticket
                        </button>
                    </div>
                </div>
                
                <div class="support-filters">
                    <div class="filter-dropdown">
                        <select id="ticketStatus">
                            <option value="all">All Tickets</option>
                            <option value="open">Open</option>
                            <option value="in-progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="filter-dropdown">
                        <select id="ticketPriority">
                            <option value="all">All Priorities</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="tickets-list" id="ticketsList">
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <h4>#T001 - Login Issues</h4>
                            <span class="ticket-priority priority-high">High</span>
                        </div>
                        <div class="ticket-content">
                            <p>Users unable to login to the system</p>
                            <div class="ticket-details">
                                <div class="detail">
                                    <span class="detail-label">Reporter:</span>
                                    <span class="detail-value">John Doe</span>
                                </div>
                                <div class="detail">
                                    <span class="detail-label">Created:</span>
                                    <span class="detail-value">2 hours ago</span>
                                </div>
                                <div class="detail">
                                    <span class="detail-label">Assigned:</span>
                                    <span class="detail-value">Tech Team</span>
                                </div>
                            </div>
                        </div>
                        <div class="ticket-status">
                            <span class="status-badge status-in-progress">In Progress</span>
                        </div>
                        <div class="ticket-actions">
                            <button class="btn btn-sm" onclick="viewTicket('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="updateTicket('1')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        class TechnicalDashboard {
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
                // Add technical-specific event listeners
                document.getElementById('ticketStatus').addEventListener('change', (e) => {
                    this.filterTickets(e.target.value, 'status');
                });

                document.getElementById('ticketPriority').addEventListener('change', (e) => {
                    this.filterTickets(e.target.value, 'priority');
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
                    case 'systems':
                        this.loadSystems();
                        break;
                    case 'maintenance':
                        this.loadMaintenance();
                        break;
                    case 'support':
                        this.loadSupport();
                        break;
                }
            }

            async loadOverviewData() {
                try {
                    const response = await this.apiCall('/committee/overview', 'POST', {
                        committee_type: 'technical'
                    });
                    
                    if (response.success) {
                        this.updateOverviewMetrics(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load overview data:', error);
                }
            }

            updateOverviewMetrics(data) {
                document.getElementById('activeSystems').textContent = data.active_systems || 0;
                document.getElementById('maintenanceTasks').textContent = data.maintenance_tasks || 0;
                document.getElementById('supportTickets').textContent = data.support_tickets || 0;
                document.getElementById('documentationPages').textContent = data.documentation_pages || 0;
            }

            async loadSystems() {
                try {
                    const response = await this.apiCall('/committee/systems', 'POST', {
                        committee_type: 'technical'
                    });
                    
                    if (response.success) {
                        this.displaySystems(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load systems:', error);
                }
            }

            displaySystems(systems) {
                const grid = document.getElementById('systemsGrid');
                grid.innerHTML = '';
                
                if (systems.length === 0) {
                    grid.innerHTML = '<p class="no-data">No systems found</p>';
                    return;
                }
                
                systems.forEach(system => {
                    const card = document.createElement('div');
                    card.className = 'system-card';
                    card.innerHTML = `
                        <div class="system-header">
                            <h4>${system.name}</h4>
                            <span class="system-status status-${system.status}">${system.status}</span>
                        </div>
                        <div class="system-content">
                            <p>${system.description}</p>
                            <div class="system-stats">
                                <div class="stat">
                                    <span class="stat-label">Uptime:</span>
                                    <span class="stat-value">${system.uptime || 'N/A'}%</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">CPU:</span>
                                    <span class="stat-value">${system.cpu_usage || 'N/A'}%</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Memory:</span>
                                    <span class="stat-value">${system.memory_usage || 'N/A'}%</span>
                                </div>
                            </div>
                        </div>
                        <div class="system-actions">
                            <button class="btn btn-sm" onclick="viewSystem('${system.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="restartSystem('${system.id}')">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            }

            async loadMaintenance() {
                try {
                    const response = await this.apiCall('/committee/maintenance', 'POST', {
                        committee_type: 'technical'
                    });
                    
                    if (response.success) {
                        this.displayMaintenance(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load maintenance:', error);
                }
            }

            displayMaintenance(tasks) {
                const list = document.getElementById('maintenanceList');
                list.innerHTML = '';
                
                if (tasks.length === 0) {
                    list.innerHTML = '<p class="no-data">No maintenance tasks found</p>';
                    return;
                }
                
                tasks.forEach(task => {
                    const card = document.createElement('div');
                    card.className = 'maintenance-card';
                    card.innerHTML = `
                        <div class="maintenance-header">
                            <h4>${task.title}</h4>
                            <span class="maintenance-status status-${task.status}">${task.status}</span>
                        </div>
                        <div class="maintenance-content">
                            <p>${task.description}</p>
                            <div class="maintenance-details">
                                <div class="detail">
                                    <span class="detail-label">Date:</span>
                                    <span class="detail-value">${new Date(task.scheduled_date).toLocaleString()}</span>
                                </div>
                                <div class="detail">
                                    <span class="detail-label">Duration:</span>
                                    <span class="detail-value">${task.duration || 'N/A'}</span>
                                </div>
                                <div class="detail">
                                    <span class="detail-label">Impact:</span>
                                    <span class="detail-value">${task.impact || 'N/A'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="maintenance-actions">
                            <button class="btn btn-sm" onclick="editMaintenance('${task.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm" onclick="cancelMaintenance('${task.id}')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    list.appendChild(card);
                });
            }

            async loadSupport() {
                try {
                    const response = await this.apiCall('/committee/support', 'POST', {
                        committee_type: 'technical'
                    });
                    
                    if (response.success) {
                        this.displayTickets(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load support tickets:', error);
                }
            }

            displayTickets(tickets) {
                const list = document.getElementById('ticketsList');
                list.innerHTML = '';
                
                if (tickets.length === 0) {
                    list.innerHTML = '<p class="no-data">No tickets found</p>';
                    return;
                }
                
                tickets.forEach(ticket => {
                    const card = document.createElement('div');
                    card.className = 'ticket-card';
                    card.innerHTML = `
                        <div class="ticket-header">
                            <h4>#${ticket.ticket_number} - ${ticket.title}</h4>
                            <span class="ticket-priority priority-${ticket.priority}">${ticket.priority}</span>
                        </div>
                        <div class="ticket-content">
                            <p>${ticket.description}</p>
                            <div class="ticket-details">
                                <div class="detail">
                                    <span class="detail-label">Reporter:</span>
                                    <span class="detail-value">${ticket.reporter_name}</span>
                                </div>
                                <div class="detail">
                                    <span class="detail-label">Created:</span>
                                    <span class="detail-value">${this.getTimeAgo(ticket.created_at)}</span>
                                </div>
                                <div class="detail">
                                    <span class="detail-label">Assigned:</span>
                                    <span class="detail-value">${ticket.assigned_to || 'Unassigned'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="ticket-status">
                            <span class="status-badge status-${ticket.status}">${ticket.status}</span>
                        </div>
                        <div class="ticket-actions">
                            <button class="btn btn-sm" onclick="viewTicket('${ticket.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="updateTicket('${ticket.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    `;
                    list.appendChild(card);
                });
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

            filterTickets(value, type) {
                // Implement ticket filtering logic
                console.log(`Filtering tickets by ${type}:`, value);
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

        function openAddSystemModal() {
            console.log('Opening add system modal');
        }

        function openAddMaintenanceModal() {
            console.log('Opening add maintenance modal');
        }

        function openAddTicketModal() {
            console.log('Opening add ticket modal');
        }

        function viewSystem(id) {
            console.log('Viewing system:', id);
        }

        function restartSystem(id) {
            if (confirm('Are you sure you want to restart this system?')) {
                console.log('Restarting system:', id);
            }
        }

        function editMaintenance(id) {
            console.log('Editing maintenance:', id);
        }

        function cancelMaintenance(id) {
            if (confirm('Are you sure you want to cancel this maintenance?')) {
                console.log('Canceling maintenance:', id);
            }
        }

        function viewTicket(id) {
            console.log('Viewing ticket:', id);
        }

        function updateTicket(id) {
            console.log('Updating ticket:', id);
        }

        // Initialize dashboard
        let dashboard;
        document.addEventListener('DOMContentLoaded', () => {
            dashboard = new TechnicalDashboard();
        });
    </script>
</body>
</html>
