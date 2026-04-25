<?php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Activities - IECEP-LSC MEMSYS</title>
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
