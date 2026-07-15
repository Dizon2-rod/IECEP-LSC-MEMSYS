<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Activities - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/styles.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/professional.css">
    <style>
        .activity-filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #D4AF37;
            border: 3px solid #fff;
            box-shadow: 0 0 0 3px #D4AF37;
        }
        
        .timeline-marker.event { background: #28a745; box-shadow: 0 0 0 3px #28a745; }
        .timeline-marker.meeting { background: #17a2b8; box-shadow: 0 0 0 3px #17a2b8; }
        .timeline-marker.community { background: #ffc107; box-shadow: 0 0 0 3px #ffc107; }
        .timeline-marker.workshop { background: #6f42c1; box-shadow: 0 0 0 3px #6f42c1; }
        
        .timeline-content {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 4px solid #D4AF37;
        }
        
        .timeline-content.event { border-left-color: #28a745; }
        .timeline-content.meeting { border-left-color: #17a2b8; }
        .timeline-content.community { border-left-color: #ffc107; }
        .timeline-content.workshop { border-left-color: #6f42c1; }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .activity-header h3 {
            color: #0B1D4A;
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .activity-date {
            color: #888;
            font-size: 0.85rem;
            background: #f8fafc;
            padding: 4px 12px;
            border-radius: 50px;
        }
        
        .timeline-content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .activity-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .activity-stats span {
            color: #666;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .activity-stats i {
            color: #0B1D4A;
        }
        
        .activity-images {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .activity-images img {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .activity-images img:hover {
            transform: scale(1.05);
        }
        
        .activity-documents {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .document-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f8fafc;
            border-radius: 8px;
            text-decoration: none;
            color: #0B1D4A;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .document-link:hover {
            background: #0B1D4A;
            color: #fff;
        }
        
        .load-more {
            text-align: center;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .timeline {
                padding-left: 1.5rem;
            }
            
            .timeline::before {
                left: -0.5rem;
            }
            
            .timeline-marker {
                left: -1.5rem;
            }
            
            .activity-stats {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <img src="assets/icons/logo.png" alt="IECEP-LSC Logo" class="logo-img">
                <span>IECEP-LSC MEMSYS</span>
            </div>
            <div class="nav-links">
                <div class="dropdown">
                    <a href="dashboard.php" class="dropdown-toggle">Home <i class="fas fa-chevron-down"></i></a>
                    <div class="dropdown-menu">
                        <a href="../index.php">Dashboard</a>
                        <a href="announcements.php">Announcements</a>
                        <a href="quick-links.php">Quick Links</a>
                        <a href="recent-activities.php">Recent Activities</a>
                    </div>
                </div>
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle">About <i class="fas fa-chevron-down"></i></a>
                    <div class="dropdown-menu">
                        <a href="#">Our Mission</a>
                        <a href="#">Leadership Team</a>
                        <a href="#">Chapter History</a>
                        <a href="#">Contact Us</a>
                    </div>
                </div>
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle">Resources <i class="fas fa-chevron-down"></i></a>
                    <div class="dropdown-menu">
                        <a href="#">Member Portal</a>
                        <a href="#">Documentation</a>
                        <a href="#">Event Calendar</a>
                        <a href="#">Downloads</a>
                    </div>
                </div>
                <a href="#" class="btn-outline btn">Login</a>
            </div>
        </div>
    </nav>

    <main class="dashboard-main">
        <div class="container">
            <h1>Recent Activities</h1>

            <div class="activity-filters">
                <button class="btn btn-primary active">All Activities</button>
                <button class="btn btn-outline">Events</button>
                <button class="btn btn-outline">Meetings</button>
                <button class="btn btn-outline">Workshops</button>
                <button class="btn btn-outline">Community</button>
            </div>

            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-marker event"></div>
                    <div class="timeline-content">
                        <div class="activity-header">
                            <h3>Technical Workshop: Arduino Basics</h3>
                            <span class="activity-date">December 14, 2024</span>
                        </div>
                        <p>Successfully conducted a hands-on Arduino workshop for 45 students from 5 different schools. Covered basic electronics, programming concepts, and project building.</p>
                        <div class="activity-stats">
                            <span><i class="fas fa-users"></i> 45 Participants</span>
                            <span><i class="fas fa-school"></i> 5 Schools</span>
                            <span><i class="fas fa-clock"></i> 4 Hours</span>
                        </div>
                        <div class="activity-images">
                            <img src="https://via.placeholder.com/100x100/0A2F6C/white?text=Workshop+1" alt="Workshop">
                            <img src="https://via.placeholder.com/100x100/0A2F6C/white?text=Workshop+2" alt="Workshop">
                            <img src="https://via.placeholder.com/100x100/0A2F6C/white?text=Workshop+3" alt="Workshop">
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-marker meeting"></div>
                    <div class="timeline-content">
                        <div class="activity-header">
                            <h3>Monthly Chapter Meeting</h3>
                            <span class="activity-date">December 10, 2024</span>
                        </div>
                        <p>Regular monthly meeting held to discuss upcoming events, budget allocation, and chapter development plans. Key decisions made for Q1 2025 activities.</p>
                        <div class="activity-stats">
                            <span><i class="fas fa-users"></i> 28 Attendees</span>
                            <span><i class="fas fa-list-check"></i> 8 Agenda Items</span>
                            <span><i class="fas fa-clock"></i> 2 Hours</span>
                        </div>
                        <div class="activity-documents">
                            <a href="#" class="document-link">
                                <i class="fas fa-file-pdf"></i> Meeting Minutes
                            </a>
                            <a href="#" class="document-link">
                                <i class="fas fa-file-powerpoint"></i> Presentation
                            </a>
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-marker community"></div>
                    <div class="timeline-content">
                        <div class="activity-header">
                            <h3>Community Outreach: High School Visit</h3>
                            <span class="activity-date">December 5, 2024</span>
                        </div>
                        <p>Visited Laguna National High School to promote electronics engineering. Conducted career talks and demonstrations for 120 senior high school students.</p>
                        <div class="activity-stats">
                            <span><i class="fas fa-users"></i> 120 Students</span>
                            <span><i class="fas fa-school"></i> 1 School</span>
                            <span><i class="fas fa-clock"></i> 3 Hours</span>
                        </div>
                        <div class="activity-images">
                            <img src="https://via.placeholder.com/100x100/0A2F6C/white?text=Outreach+1" alt="Outreach">
                            <img src="https://via.placeholder.com/100x100/0A2F6C/white?text=Outreach+2" alt="Outreach">
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-marker event"></div>
                    <div class="timeline-content">
                        <div class="activity-header">
                            <h3>Chapter Anniversary Celebration</h3>
                            <span class="activity-date">November 28, 2024</span>
                        </div>
                        <p>Celebrated 5 years of IECEP-LSC with a formal dinner and awarding ceremony. Recognized outstanding members and partner schools.</p>
                        <div class="activity-stats">
                            <span><i class="fas fa-users"></i> 85 Attendees</span>
                            <span><i class="fas fa-trophy"></i> 12 Awards</span>
                            <span><i class="fas fa-clock"></i> 4 Hours</span>
                        </div>
                        <div class="activity-images">
                            <img src="https://via.placeholder.com/100x100/0A2F6C/white?text=Anniversary+1" alt="Anniversary">
                            <img src="https://via.placeholder.com/100x100/0A2F6C/white?text=Anniversary+2" alt="Anniversary">
                            <img src="https://via.placeholder.com/100x100/0A2F6C/white?text=Anniversary+3" alt="Anniversary">
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-marker workshop"></div>
                    <div class="timeline-content">
                        <div class="activity-header">
                            <h3>PCB Design Workshop</h3>
                            <span class="activity-date">November 20, 2024</span>
                        </div>
                        <p>Advanced workshop on printed circuit board design using industry-standard software. Participants created their own PCB layouts and learned manufacturing processes.</p>
                        <div class="activity-stats">
                            <span><i class="fas fa-users"></i> 32 Participants</span>
                            <span><i class="fas fa-laptop"></i> 16 Workstations</span>
                            <span><i class="fas fa-clock"></i> 6 Hours</span>
                        </div>
                        <div class="activity-documents">
                            <a href="#" class="document-link">
                                <i class="fas fa-file-pdf"></i> Workshop Materials
                            </a>
                            <a href="#" class="document-link">
                                <i class="fas fa-download"></i> Design Files
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="load-more">
                <button class="btn btn-primary">
                    <i class="fas fa-plus"></i> Load More Activities
                </button>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 IECEP-LSC MEMSYS. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Dropdown functionality
        const dropdowns = document.querySelectorAll('.dropdown');

        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const links = dropdown.querySelectorAll('.dropdown-menu a');

            // Prevent navigation on toggle click
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Close all other dropdowns
                dropdowns.forEach(otherDropdown => {
                    if (otherDropdown !== dropdown) {
                        otherDropdown.classList.remove('active');
                    }
                });

                // Toggle current dropdown
                dropdown.classList.toggle('active');
            });

            // Allow navigation only when option is clicked
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    // Close dropdown after clicking an option
                    dropdown.classList.remove('active');
                });
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                dropdowns.forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        });

        // Close dropdowns when pressing Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                dropdowns.forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        });
    </script>
</body>

</html>
