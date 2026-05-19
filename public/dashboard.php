<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            <h1>Dashboard</h1>
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Total Members</h3>
                    <p class="card-number">250+</p>
                    <span class="card-label">Active Members</span>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <h3>Upcoming Events</h3>
                    <p class="card-number">5</p>
                    <span class="card-label">This Month</span>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-school"></i>
                    </div>
                    <h3>Affiliated Schools</h3>
                    <p class="card-number">8</p>
                    <span class="card-label">Active Chapters</span>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3>Compliance Rate</h3>
                    <p class="card-number">92%</p>
                    <span class="card-label">Overall</span>
                </div>
            </div>

            <div class="dashboard-sections">
                <div class="section">
                    <h2>Recent Announcements</h2>
                    <div class="announcement-list">
                        <div class="announcement-item">
                            <h4>IECEP National Conference 2025</h4>
                            <p>Registration now open for the annual conference. Early bird discounts available.</p>
                            <span class="date">2 days ago</span>
                        </div>
                        <div class="announcement-item">
                            <h4>New Chapter Affiliation Guidelines</h4>
                            <p>Updated requirements for school chapter affiliation have been released.</p>
                            <span class="date">1 week ago</span>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2>Quick Actions</h2>
                    <div class="action-buttons">
                        <button class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add Member
                        </button>
                        <button class="btn btn-outline">
                            <i class="fas fa-calendar-plus"></i> Create Event
                        </button>
                        <button class="btn btn-outline">
                            <i class="fas fa-file-alt"></i> Generate Report
                        </button>
                    </div>
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
