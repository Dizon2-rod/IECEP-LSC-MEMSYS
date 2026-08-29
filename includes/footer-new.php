<?php
require_once __DIR__ . '/../bootstrap.php';
// Prevent multiple inclusions
if (defined('FOOTER_INCLUDED')) return;
define('FOOTER_INCLUDED', true);
?>
<!-- Footer -->
<footer class="footer">
    <div class="footer-grid">
        <!-- Brand Column -->
        <div class="footer-col footer-col-brand">
            <div class="footer-brand">
                <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo">
                <h4 style="margin-bottom:0; color:#FFFFFF;">IECEP-LSC MEMSYS</h4>
            </div>
            <p><strong>One IECEP, One Vision</strong><br>
            Institute of Electronics Engineers of the Philippines – Laguna Student Chapter brings together 8 affiliated higher education institutions, united through connection, collaboration, and shared purpose.</p>
            <div style="margin-top: 1rem;">
                <a href="<?php echo BASE_URL; ?>/login.php" class="footer-cta-btn" style="display:inline-flex; align-items:center; gap:0.5rem; background:linear-gradient(135deg,#D4AF37 0%,#C5A059 100%); color:#0B1D4A; font-weight:700; font-size:0.85rem; padding:0.5rem 1.1rem; border-radius:50px; text-decoration:none; transition:all 0.2s ease;">
                    <i class="fa-solid fa-right-to-bracket"></i> Portal Login
                </a>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="footer-col">
            <h4>Quick Links</h4>
            <ul class="footer-links">
                <li><a href="<?php echo BASE_URL; ?>/index.php"><i class="fa-solid fa-house" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>/#features"><i class="fa-solid fa-star" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Features</a></li>
                <li><a href="<?php echo BASE_URL; ?>/#how-to-affiliate"><i class="fa-solid fa-circle-question" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> How to Affiliate</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/merchandise.php"><i class="fa-solid fa-shirt" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Merchandise Store</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/blockchain-explorer.php"><i class="fa-solid fa-link" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Blockchain Explorer</a></li>
                <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php"><i class="fa-solid fa-calendar-days" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Calendar Activity</a></li>
            </ul>
        </div>

        <!-- Resources -->
        <div class="footer-col">
            <h4>Resources</h4>
            <ul class="footer-links">
                <li><a href="<?php echo BASE_URL; ?>/iecep-officers.php"><i class="fa-solid fa-users-gear" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> IECEP Officers</a></li>
                <li><a href="<?php echo BASE_URL; ?>/former-presidents.php"><i class="fa-solid fa-crown" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Former Presidents</a></li>
                <li><a href="<?php echo BASE_URL; ?>/iecep-hymn.php"><i class="fa-solid fa-music" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> IECEP Hymn</a></li>
                <li><a href="<?php echo BASE_URL; ?>/awards-distinctions.php"><i class="fa-solid fa-trophy" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Awards &amp; Distinctions</a></li>
                <li><a href="<?php echo BASE_URL; ?>/affiliated-schools.php"><i class="fa-solid fa-building-columns" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Affiliated Schools</a></li>
            </ul>
        </div>

        <!-- About IECEP-LSC & Connect -->
        <div class="footer-col">
            <h4>About IECEP-LSC</h4>
            <ul class="footer-links">
                <li><a href="<?php echo BASE_URL; ?>/mission-vision.php"><i class="fa-solid fa-compass" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Mission &amp; Vision</a></li>
                <li><a href="<?php echo BASE_URL; ?>/objective.php"><i class="fa-solid fa-bullseye" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Institutional Objectives</a></li>
                <li><a href="<?php echo BASE_URL; ?>/contact.php"><i class="fa-solid fa-envelope-open-text" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Contact Secretariat</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/privacy-policy.php"><i class="fa-solid fa-shield-halved" style="font-size:0.75rem; width:16px; color:#D4AF37;"></i> Privacy Policy</a></li>
            </ul>
            
            <h4 style="margin-top: 1.25rem; margin-bottom: 0.6rem;">Connect With Us</h4>
            <div class="footer-social">
                <a href="https://www.facebook.com/IECEPLSC" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-facebook" style="color:#1877F2;"></i> Facebook
                </a>
                <a href="https://www.tiktok.com/@iecep.lagunasc?_r=1&_t=ZS-95en4lyrRas" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-tiktok" style="color:#EE1D52;"></i> TikTok
                </a>
            </div>
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=ieceplsc24@gmail.com" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--neutral-400); text-decoration: none; font-size: 0.85rem; margin-top: 0.25rem; transition: color 0.2s ease;">
                <i class="fa-solid fa-envelope" style="color:#D4AF37;"></i> ieceplsc24@gmail.com
            </a>
        </div>
    </div>

    <div class="footer-bottom">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; max-width:1320px; margin:0 auto; padding:0 2rem;">
            <div>
                &copy; <?php echo date('Y'); ?> <strong>IECEP-LSC MEMSYS</strong>. All rights reserved.
            </div>
            <div style="display:flex; gap:1.25rem; font-size:0.82rem;">
                <a href="<?php echo BASE_URL; ?>/mission-vision.php" style="color:var(--neutral-400); text-decoration:none;">About</a>
                <a href="<?php echo BASE_URL; ?>/public/privacy-policy.php" style="color:var(--neutral-400); text-decoration:none;">Privacy Policy</a>
                <a href="<?php echo BASE_URL; ?>/contact.php" style="color:var(--neutral-400); text-decoration:none;">Contact</a>
            </div>
        </div>
    </div>
</footer>
