<?php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Links - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="css/dashboard.css">
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
