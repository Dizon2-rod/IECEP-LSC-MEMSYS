<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Links - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/styles.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/professional.css">
    <style>
        .quick-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .quick-link-card {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            border-top: 4px solid #D4AF37;
        }
        
        .quick-link-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .quick-link-card .card-icon {
            width: 60px;
            height: 60px;
            background: #f0f9ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: #0B1D4A;
            font-size: 1.5rem;
        }
        
        .quick-link-card h3 {
            color: #0B1D4A;
            margin-bottom: 0.75rem;
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .quick-link-card p {
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .external-links {
            margin-top: 3rem;
        }
        
        .external-links h2 {
            color: #0B1D4A;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        
        .external-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .external-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: #fff;
            border-radius: 8px;
            text-decoration: none;
            color: #0B1D4A;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        
        .external-link:hover {
            background: #0B1D4A;
            color: #D4AF37;
            transform: translateY(-2px);
        }
        
        .external-link i {
            font-size: 1.25rem;
        }
        
        @media (max-width: 768px) {
            .quick-links-grid {
                grid-template-columns: 1fr;
            }
            
            .external-links-grid {
                grid-template-columns: 1fr;
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
            <h1>Quick Links</h1>

            <div class="quick-links-grid">
                <div class="quick-link-card">
                    <div class="card-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3>Member Portal</h3>
                    <p>Access your personal dashboard, update profile, and manage membership details.</p>
                    <a href="#" class="btn btn-primary">Access Portal</a>
                </div>

                <div class="quick-link-card">
                    <div class="card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Event Calendar</h3>
                    <p>View upcoming events, workshops, and chapter activities.</p>
                    <a href="#" class="btn btn-primary">View Calendar</a>
                </div>

                <div class="quick-link-card">
                    <div class="card-icon">
                        <i class="fas fa-file-download"></i>
                    </div>
                    <h3>Downloads</h3>
                    <p>Access forms, templates, and important chapter documents.</p>
                    <a href="#" class="btn btn-primary">Browse Files</a>
                </div>

                <div class="quick-link-card">
                    <div class="card-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Documentation</h3>
                    <p>Chapter bylaws, policies, and procedural manuals.</p>
                    <a href="#" class="btn btn-primary">Read Docs</a>
                </div>

                <div class="quick-link-card">
                    <div class="card-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3>Affiliation</h3>
                    <p>Apply for school chapter affiliation and track application status.</p>
                    <a href="#" class="btn btn-primary">Apply Now</a>
                </div>

                <div class="quick-link-card">
                    <div class="card-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3>Certification</h3>
                    <p>Professional development programs and certification opportunities.</p>
                    <a href="#" class="btn btn-primary">Learn More</a>
                </div>

                <div class="quick-link-card">
                    <div class="card-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Forum</h3>
                    <p>Connect with fellow members and discuss technical topics.</p>
                    <a href="#" class="btn btn-primary">Join Forum</a>
                </div>

                <div class="quick-link-card">
                    <div class="card-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3>Contact Support</h3>
                    <p>Get help with technical issues and chapter matters.</p>
                    <a href="#" class="btn btn-primary">Contact Us</a>
                </div>
            </div>

            <div class="external-links">
                <h2>External Resources</h2>
                <div class="external-links-grid">
                    <a href="#" class="external-link">
                        <i class="fas fa-globe"></i>
                        <span>IECEP National Website</span>
                    </a>
                    <a href="#" class="external-link">
                        <i class="fas fa-graduation-cap"></i>
                        <span>PRC Website</span>
                    </a>
                    <a href="#" class="external-link">
                        <i class="fas fa-university"></i>
                        <span>CHED Portal</span>
                    </a>
                    <a href="#" class="external-link">
                        <i class="fas fa-briefcase"></i>
                        <span>Job Board</span>
                    </a>
                </div>
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
