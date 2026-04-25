<?php
// dashboard.php - Marketing Committee Dashboard
require_once __DIR__ . '/../../auth_check.php';
require_role(['eb_pro_2', 'committee_marketing']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing Committee Dashboard - IECEP-LSC MEMSYS</title>
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
                <h3>Marketing Committee</h3>
                <p><?php echo htmlspecialchars($user['name']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#overview" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Overview</a></li>
                    <li><a href="#campaigns" class="nav-link"><i class="fas fa-bullhorn"></i> Campaigns</a></li>
                    <li><a href="#social" class="nav-link"><i class="fas fa-share-alt"></i> Social Media</a></li>
                    <li><a href="#outreach" class="nav-link"><i class="fas fa-users"></i> Outreach</a></li>
                    <li><a href="#analytics" class="nav-link"><i class="fas fa-chart-line"></i> Analytics</a></li>
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
                    <p>Marketing Committee Dashboard - Campaign & Outreach Management</p>
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
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="activeCampaigns">0</div>
                            <div class="metric-label">Active Campaigns</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-share-alt"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="socialPosts">0</div>
                            <div class="metric-label">Social Posts</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="outreachEvents">0</div>
                            <div class="metric-label">Outreach Events</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalReach">0</div>
                            <div class="metric-label">Total Reach</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h3>Recent Activity</h3>
                    <div class="activity-list" id="recentActivity">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="activity-content">
                                <p>New campaign launched</p>
                                <span class="activity-time">1 hour ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-share-alt"></i>
                            </div>
                            <div class="activity-content">
                                <p>Social media post published</p>
                                <span class="activity-time">3 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="activity-content">
                                <p>Outreach event completed</p>
                                <span class="activity-time">1 day ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Campaigns Section -->
            <section id="campaigns" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Campaign Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddCampaignModal()">
                            <i class="fas fa-plus"></i> Add Campaign
                        </button>
                    </div>
                </div>
                
                <div class="campaign-grid" id="campaignsGrid">
                    <div class="campaign-card">
                        <div class="campaign-header">
                            <h4>Membership Drive 2026</h4>
                            <span class="campaign-status status-active">Active</span>
                        </div>
                        <div class="campaign-content">
                            <p>Annual membership recruitment campaign targeting engineering students across Laguna.</p>
                            <div class="campaign-stats">
                                <div class="stat">
                                    <span class="stat-label">Reach:</span>
                                    <span class="stat-value">5,234</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Engagement:</span>
                                    <span class="stat-value">12.5%</span>
                                </div>
                            </div>
                        </div>
                        <div class="campaign-actions">
                            <button class="btn btn-sm" onclick="viewCampaign('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editCampaign('1')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Social Media Section -->
            <section id="social" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Social Media Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openCreatePostModal()">
                            <i class="fas fa-plus"></i> Create Post
                        </button>
                    </div>
                </div>
                
                <div class="social-platforms">
                    <div class="platform-card">
                        <div class="platform-header">
                            <i class="fab fa-facebook"></i>
                            <h4>Facebook</h4>
                        </div>
                        <div class="platform-stats">
                            <div class="stat">
                                <span class="stat-label">Followers:</span>
                                <span class="stat-value">2,456</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label">Posts:</span>
                                <span class="stat-value">156</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="platform-card">
                        <div class="platform-header">
                            <i class="fab fa-twitter"></i>
                            <h4>Twitter</h4>
                        </div>
                        <div class="platform-stats">
                            <div class="stat">
                                <span class="stat-label">Followers:</span>
                                <span class="stat-value">1,892</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label">Tweets:</span>
                                <span class="stat-value">342</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="platform-card">
                        <div class="platform-header">
                            <i class="fab fa-instagram"></i>
                            <h4>Instagram</h4>
                        </div>
                        <div class="platform-stats">
                            <div class="stat">
                                <span class="stat-label">Followers:</span>
                                <span class="stat-value">3,127</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label">Posts:</span>
                                <span class="stat-value">89</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        class MarketingDashboard {
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
                // Add marketing-specific event listeners
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
                    case 'campaigns':
                        this.loadCampaigns();
                        break;
                    case 'social':
                        this.loadSocialMedia();
                        break;
                }
            }

            async loadOverviewData() {
                try {
                    const response = await this.apiCall('/committee/overview', 'POST', {
                        committee_type: 'marketing'
                    });
                    
                    if (response.success) {
                        this.updateOverviewMetrics(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load overview data:', error);
                }
            }

            updateOverviewMetrics(data) {
                document.getElementById('activeCampaigns').textContent = data.active_campaigns || 0;
                document.getElementById('socialPosts').textContent = data.social_posts || 0;
                document.getElementById('outreachEvents').textContent = data.outreach_events || 0;
                document.getElementById('totalReach').textContent = (data.total_reach || 0).toLocaleString();
            }

            async loadCampaigns() {
                try {
                    const response = await this.apiCall('/committee/campaigns', 'POST', {
                        committee_type: 'marketing'
                    });
                    
                    if (response.success) {
                        this.displayCampaigns(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load campaigns:', error);
                }
            }

            displayCampaigns(campaigns) {
                const grid = document.getElementById('campaignsGrid');
                grid.innerHTML = '';
                
                if (campaigns.length === 0) {
                    grid.innerHTML = '<p class="no-data">No campaigns found</p>';
                    return;
                }
                
                campaigns.forEach(campaign => {
                    const card = document.createElement('div');
                    card.className = 'campaign-card';
                    card.innerHTML = `
                        <div class="campaign-header">
                            <h4>${campaign.title}</h4>
                            <span class="campaign-status status-${campaign.status}">${campaign.status}</span>
                        </div>
                        <div class="campaign-content">
                            <p>${campaign.description}</p>
                            <div class="campaign-stats">
                                <div class="stat">
                                    <span class="stat-label">Reach:</span>
                                    <span class="stat-value">${(campaign.reach || 0).toLocaleString()}</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Engagement:</span>
                                    <span class="stat-value">${campaign.engagement || 0}%</span>
                                </div>
                            </div>
                        </div>
                        <div class="campaign-actions">
                            <button class="btn btn-sm" onclick="viewCampaign('${campaign.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="editCampaign('${campaign.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            }

            async loadSocialMedia() {
                try {
                    const response = await this.apiCall('/committee/social', 'POST', {
                        committee_type: 'marketing'
                    });
                    
                    if (response.success) {
                        this.displaySocialPlatforms(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load social media data:', error);
                }
            }

            displaySocialPlatforms(platforms) {
                // This would populate the social media platform cards
                console.log('Social platforms:', platforms);
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

        function openAddCampaignModal() {
            console.log('Opening add campaign modal');
        }

        function openCreatePostModal() {
            console.log('Opening create post modal');
        }

        function viewCampaign(id) {
            console.log('Viewing campaign:', id);
        }

        function editCampaign(id) {
            console.log('Editing campaign:', id);
        }

        // Initialize dashboard
        let dashboard;
        document.addEventListener('DOMContentLoaded', () => {
            dashboard = new MarketingDashboard();
        });
    </script>
</body>
</html>
