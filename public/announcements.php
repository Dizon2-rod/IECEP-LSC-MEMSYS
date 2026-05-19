<?php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - IECEP-LSC MEMSYS</title>
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
            <h1>Announcements</h1>

            <div class="announcements-header">
                <div class="filter-buttons">
                    <button class="btn btn-primary active">All</button>
                    <button class="btn btn-outline">Events</button>
                    <button class="btn btn-outline">Important</button>
                    <button class="btn btn-outline">Updates</button>
                </div>
                <button class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Announcement
                </button>
            </div>

            <div class="announcements-list">
                <div class="announcement-card important">
                    <div class="announcement-header">
                        <span class="badge important">Important</span>
                        <span class="date">December 15, 2024</span>
                    </div>
                    <h3>IECEP National Conference 2025 Registration Open</h3>
                    <p>We are pleased to announce that registration for the 2025 IECEP National Conference is now open. This year's conference will focus on "Digital Transformation in Electronics Engineering" and will feature keynote speakers from industry
                        leaders.
                    </p>
                    <div class="announcement-footer">
                        <span class="author">Posted by: Chapter President</span>
                        <button class="btn btn-outline btn-sm">Read More</button>
                    </div>
                </div>

                <div class="announcement-card event">
                    <div class="announcement-header">
                        <span class="badge event">Event</span>
                        <span class="date">December 10, 2024</span>
                    </div>
                    <h3>Technical Workshop: IoT Fundamentals</h3>
                    <p>Join us for an intensive workshop on Internet of Things fundamentals. This hands-on session will cover basic concepts, programming, and practical applications. Limited slots available.</p>
                    <div class="announcement-footer">
                        <span class="author">Posted by: Technical Committee</span>
                        <button class="btn btn-outline btn-sm">Register Now</button>
                    </div>
                </div>

                <div class="announcement-card update">
                    <div class="announcement-header">
                        <span class="badge update">Update</span>
                        <span class="date">December 5, 2024</span>
                    </div>
                    <h3>Chapter Affiliation Guidelines Updated</h3>
                    <p>The Board of Directors has approved updated guidelines for school chapter affiliation. New requirements include updated documentation templates and streamlined approval process.</p>
                    <div class="announcement-footer">
                        <span class="author">Posted by: Secretariat</span>
                        <button class="btn btn-outline btn-sm">View Guidelines</button>
                    </div>
                </div>

                <div class="announcement-card">
                    <div class="announcement-header">
                        <span class="badge">General</span>
                        <span class="date">November 28, 2024</span>
                    </div>
                    <h3>December Monthly Meeting Schedule</h3>
                    <p>Regular monthly meeting will be held on December 20, 2024 at 6:00 PM. Agenda includes year-end reports and 2025 planning. All members are encouraged to attend.</p>
                    <div class="announcement-footer">
                        <span class="author">Posted by: Secretary</span>
                        <button class="btn btn-outline btn-sm">Add to Calendar</button>
                    </div>
                </div>

                <div class="announcement-card event">
                    <div class="announcement-header">
                        <span class="badge event">Event</span>
                        <span class="date">November 20, 2024</span>
                    </div>
                    <h3>Community Outreach Program</h3>
                    <p>IECEP-LSC will be conducting a community outreach program at local high schools to promote electronics engineering. Volunteers needed for December 15-17, 2024.</p>
                    <div class="announcement-footer">
                        <span class="author">Posted by: Outreach Committee</span>
                        <button class="btn btn-outline btn-sm">Volunteer</button>
                    </div>
                </div>
            </div>

            <div class="pagination">
                <button class="btn btn-outline" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span class="page-info">Page 1 of 3</span>
                <button class="btn btn-outline">
                    Next <i class="fas fa-chevron-right"></i>
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
