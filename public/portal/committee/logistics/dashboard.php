<?php
// dashboard.php - Logistics Committee Dashboard
require_once __DIR__ . '/../../auth_check.php';
require_role(['eb_pro_2', 'committee_logistics']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Committee Dashboard - IECEP-LSC MEMSYS</title>
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
                <h3>Logistics Committee</h3>
                <p><?php echo htmlspecialchars($user['name']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#overview" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Overview</a></li>
                    <li><a href="#venues" class="nav-link"><i class="fas fa-map-marker-alt"></i> Venues</a></li>
                    <li><a href="#equipment" class="nav-link"><i class="fas fa-tools"></i> Equipment</a></li>
                    <li><a href="#transportation" class="nav-link"><i class="fas fa-truck"></i> Transportation</a></li>
                    <li><a href="#catering" class="nav-link"><i class="fas fa-utensils"></i> Catering</a></li>
                    <li><a href="#inventory" class="nav-link"><i class="fas fa-boxes"></i> Inventory</a></li>
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
                    <p>Logistics Committee Dashboard - Event & Resource Management</p>
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
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="totalVenues">0</div>
                            <div class="metric-label">Total Venues</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="equipmentItems">0</div>
                            <div class="metric-label">Equipment Items</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="vehiclesManaged">0</div>
                            <div class="metric-label">Vehicles Managed</div>
                        </div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value" id="eventsSupported">0</div>
                            <div class="metric-label">Events Supported</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h3>Recent Activity</h3>
                    <div class="activity-list" id="recentActivity">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="activity-content">
                                <p>New venue booked</p>
                                <span class="activity-time">2 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="activity-content">
                                <p>Equipment maintenance completed</p>
                                <span class="activity-time">5 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="activity-content">
                                <p>Transportation arranged for event</p>
                                <span class="activity-time">1 day ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Venues Section -->
            <section id="venues" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Venue Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddVenueModal()">
                            <i class="fas fa-plus"></i> Add Venue
                        </button>
                    </div>
                </div>
                
                <div class="venues-grid" id="venuesGrid">
                    <div class="venue-card">
                        <div class="venue-image">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="venue-info">
                            <h4>Main Auditorium</h4>
                            <p>Capacity: 500 people</p>
                            <div class="venue-features">
                                <span class="feature">Projector</span>
                                <span class="feature">Sound System</span>
                                <span class="feature">Air Conditioning</span>
                            </div>
                        </div>
                        <div class="venue-status">
                            <span class="status-badge status-available">Available</span>
                        </div>
                        <div class="venue-actions">
                            <button class="btn btn-sm" onclick="viewVenue('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="bookVenue('1')">
                                <i class="fas fa-calendar-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Equipment Section -->
            <section id="equipment" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Equipment Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddEquipmentModal()">
                            <i class="fas fa-plus"></i> Add Equipment
                        </button>
                    </div>
                </div>
                
                <div class="equipment-grid" id="equipmentGrid">
                    <div class="equipment-card">
                        <div class="equipment-icon">
                            <i class="fas fa-laptop"></i>
                        </div>
                        <div class="equipment-info">
                            <h4>Projector</h4>
                            <p>High-resolution projector for presentations</p>
                            <div class="equipment-details">
                                <span class="detail">Quantity: 5</span>
                                <span class="detail">Available: 3</span>
                            </div>
                        </div>
                        <div class="equipment-status">
                            <span class="status-badge status-available">Available</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn btn-sm" onclick="viewEquipment('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="reserveEquipment('1')">
                                <i class="fas fa-hand-holding"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Transportation Section -->
            <section id="transportation" class="dashboard-section" style="display: none;">
                <div class="section-header">
                    <h2>Transportation Management</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="openAddVehicleModal()">
                            <i class="fas fa-plus"></i> Add Vehicle
                        </button>
                    </div>
                </div>
                
                <div class="transportation-grid" id="transportationGrid">
                    <div class="vehicle-card">
                        <div class="vehicle-icon">
                            <i class="fas fa-bus"></i>
                        </div>
                        <div class="vehicle-info">
                            <h4>Bus #1</h4>
                            <p>50-seater bus for group transportation</p>
                            <div class="vehicle-details">
                                <span class="detail">Capacity: 50</span>
                                <span class="detail">Driver: Available</span>
                            </div>
                        </div>
                        <div class="vehicle-status">
                            <span class="status-badge status-available">Available</span>
                        </div>
                        <div class="vehicle-actions">
                            <button class="btn btn-sm" onclick="viewVehicle('1')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="bookVehicle('1')">
                                <i class="fas fa-calendar-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        class LogisticsDashboard {
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
                // Add logistics-specific event listeners
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
                    case 'venues':
                        this.loadVenues();
                        break;
                    case 'equipment':
                        this.loadEquipment();
                        break;
                    case 'transportation':
                        this.loadTransportation();
                        break;
                }
            }

            async loadOverviewData() {
                try {
                    const response = await this.apiCall('/committee/overview', 'POST', {
                        committee_type: 'logistics'
                    });
                    
                    if (response.success) {
                        this.updateOverviewMetrics(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load overview data:', error);
                }
            }

            updateOverviewMetrics(data) {
                document.getElementById('totalVenues').textContent = data.total_venues || 0;
                document.getElementById('equipmentItems').textContent = data.equipment_items || 0;
                document.getElementById('vehiclesManaged').textContent = data.vehicles_managed || 0;
                document.getElementById('eventsSupported').textContent = data.events_supported || 0;
            }

            async loadVenues() {
                try {
                    const response = await this.apiCall('/committee/venues', 'POST', {
                        committee_type: 'logistics'
                    });
                    
                    if (response.success) {
                        this.displayVenues(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load venues:', error);
                }
            }

            displayVenues(venues) {
                const grid = document.getElementById('venuesGrid');
                grid.innerHTML = '';
                
                if (venues.length === 0) {
                    grid.innerHTML = '<p class="no-data">No venues found</p>';
                    return;
                }
                
                venues.forEach(venue => {
                    const card = document.createElement('div');
                    card.className = 'venue-card';
                    card.innerHTML = `
                        <div class="venue-image">
                            ${venue.image_url ? `<img src="${venue.image_url}" alt="${venue.name}">` : '<i class="fas fa-building"></i>'}
                        </div>
                        <div class="venue-info">
                            <h4>${venue.name}</h4>
                            <p>Capacity: ${venue.capacity} people</p>
                            <div class="venue-features">
                                ${venue.features.map(feature => `<span class="feature">${feature}</span>`).join('')}
                            </div>
                        </div>
                        <div class="venue-status">
                            <span class="status-badge status-${venue.status}">${venue.status}</span>
                        </div>
                        <div class="venue-actions">
                            <button class="btn btn-sm" onclick="viewVenue('${venue.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="bookVenue('${venue.id}')">
                                <i class="fas fa-calendar-plus"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            }

            async loadEquipment() {
                try {
                    const response = await this.apiCall('/committee/equipment', 'POST', {
                        committee_type: 'logistics'
                    });
                    
                    if (response.success) {
                        this.displayEquipment(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load equipment:', error);
                }
            }

            displayEquipment(equipment) {
                const grid = document.getElementById('equipmentGrid');
                grid.innerHTML = '';
                
                if (equipment.length === 0) {
                    grid.innerHTML = '<p class="no-data">No equipment found</p>';
                    return;
                }
                
                equipment.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'equipment-card';
                    card.innerHTML = `
                        <div class="equipment-icon">
                            <i class="fas fa-${this.getEquipmentIcon(item.type)}"></i>
                        </div>
                        <div class="equipment-info">
                            <h4>${item.name}</h4>
                            <p>${item.description}</p>
                            <div class="equipment-details">
                                <span class="detail">Quantity: ${item.total_quantity}</span>
                                <span class="detail">Available: ${item.available_quantity}</span>
                            </div>
                        </div>
                        <div class="equipment-status">
                            <span class="status-badge status-${item.status}">${item.status}</span>
                        </div>
                        <div class="equipment-actions">
                            <button class="btn btn-sm" onclick="viewEquipment('${item.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="reserveEquipment('${item.id}')">
                                <i class="fas fa-hand-holding"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            }

            async loadTransportation() {
                try {
                    const response = await this.apiCall('/committee/transportation', 'POST', {
                        committee_type: 'logistics'
                    });
                    
                    if (response.success) {
                        this.displayTransportation(response.data);
                    }
                } catch (error) {
                    console.error('Failed to load transportation:', error);
                }
            }

            displayTransportation(vehicles) {
                const grid = document.getElementById('transportationGrid');
                grid.innerHTML = '';
                
                if (vehicles.length === 0) {
                    grid.innerHTML = '<p class="no-data">No vehicles found</p>';
                    return;
                }
                
                vehicles.forEach(vehicle => {
                    const card = document.createElement('div');
                    card.className = 'vehicle-card';
                    card.innerHTML = `
                        <div class="vehicle-icon">
                            <i class="fas fa-${this.getVehicleIcon(vehicle.type)}"></i>
                        </div>
                        <div class="vehicle-info">
                            <h4>${vehicle.name}</h4>
                            <p>${vehicle.description}</p>
                            <div class="vehicle-details">
                                <span class="detail">Capacity: ${vehicle.capacity}</span>
                                <span class="detail">Driver: ${vehicle.driver_available ? 'Available' : 'Not Available'}</span>
                            </div>
                        </div>
                        <div class="vehicle-status">
                            <span class="status-badge status-${vehicle.status}">${vehicle.status}</span>
                        </div>
                        <div class="vehicle-actions">
                            <button class="btn btn-sm" onclick="viewVehicle('${vehicle.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm" onclick="bookVehicle('${vehicle.id}')">
                                <i class="fas fa-calendar-plus"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            }

            getEquipmentIcon(type) {
                const icons = {
                    'projector': 'laptop',
                    'sound': 'volume-up',
                    'microphone': 'microphone',
                    'camera': 'camera',
                    'lighting': 'lightbulb',
                    'table': 'table',
                    'chair': 'chair'
                };
                return icons[type.toLowerCase()] || 'tools';
            }

            getVehicleIcon(type) {
                const icons = {
                    'bus': 'bus',
                    'van': 'van-shuttle',
                    'car': 'car',
                    'truck': 'truck'
                };
                return icons[type.toLowerCase()] || 'truck';
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

        function openAddVenueModal() {
            console.log('Opening add venue modal');
        }

        function openAddEquipmentModal() {
            console.log('Opening add equipment modal');
        }

        function openAddVehicleModal() {
            console.log('Opening add vehicle modal');
        }

        function viewVenue(id) {
            console.log('Viewing venue:', id);
        }

        function bookVenue(id) {
            console.log('Booking venue:', id);
        }

        function viewEquipment(id) {
            console.log('Viewing equipment:', id);
        }

        function reserveEquipment(id) {
            console.log('Reserving equipment:', id);
        }

        function viewVehicle(id) {
            console.log('Viewing vehicle:', id);
        }

        function bookVehicle(id) {
            console.log('Booking vehicle:', id);
        }

        // Initialize dashboard
        let dashboard;
        document.addEventListener('DOMContentLoaded', () => {
            dashboard = new LogisticsDashboard();
        });
    </script>
</body>
</html>
