<nav class="navbar">
    <div class="nav-container">
        <div class="logo">
            <img src="/public/assets/icons/iecep-logo.png" alt="IECEP-LSC Logo" class="logo-img">
            <span>IECEP-LSC MEMSYS</span>
        </div>
        <div class="nav-links">
            <div class="dropdown">
                <a href="public/dashboard.php" class="dropdown-toggle">Home <i class="fas fa-chevron-down"></i></a>
                <div class="dropdown-menu">
                    <a href="index.php">Dashboard</a>
                    <a href="public/announcements.php">Announcements</a>
                    <a href="public/quick-links.php">Quick Links</a>
                    <a href="public/recent-activities.php">Recent Activities</a>
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
                    <a href="#">Documentation</a>
                    <a href="#">Event Calendar</a>
                    <a href="#">Downloads</a>
                </div>
            </div>
            <a href="/login.php" id="loginBtn" class="btn-outline btn" style="padding: 8px 20px;">Login</a>
        </div>
    </div>
</nav>

<style>
/* Dropdown Styles */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    min-width: 200px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 8px 0;
    z-index: 9999;
    margin-top: 0;
}

.dropdown:hover .dropdown-menu {
    display: block;
}

.dropdown-menu a {
    display: block;
    padding: 10px 20px;
    color: #333;
    text-decoration: none;
    transition: background 0.2s;
}

.dropdown-menu a:hover {
    background: #f5f5f5;
}

.dropdown-toggle {
    cursor: pointer;
}

.dropdown-toggle i {
    margin-left: 5px;
    font-size: 0.7em;
}
</style>
