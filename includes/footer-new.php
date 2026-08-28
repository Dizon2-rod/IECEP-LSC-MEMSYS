<?php
require_once __DIR__ . '/../bootstrap.php';
// Prevent multiple inclusions
if (defined('FOOTER_INCLUDED')) return;
define('FOOTER_INCLUDED', true);
?>
<!-- Footer -->
<footer class="footer">
    <div class="footer-grid">
        <div class="footer-col">
            <div class="footer-brand">
                <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo">
                <h4 style="margin-bottom:0;">IECEP-LSC MEMSYS</h4>
            </div>
            <p><strong>One IECEP, One Vision</strong><br>
            The IECEP-LSC brings together 8 affiliated higher education institutions, united through connection, collaboration, and shared purpose.</p>
        </div>
        <div class="footer-col">
            <h4>Quick Links</h4>
            <ul class="footer-links">
                <li><a href="<?php echo BASE_URL; ?>/">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>/#features">Features</a></li>
                <li><a href="<?php echo BASE_URL; ?>/#how-to-affiliate">How it works</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>About IECEP-LSC</h4>
            <ul class="footer-links">
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Connect</h4>
            <div class="footer-social">
                <a href="https://www.facebook.com/IECEPLSC" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-facebook"></i> Facebook
                </a>
                <a href="https://www.tiktok.com/@iecep.lagunasc?_r=1&_t=ZS-95en4lyrRas" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-tiktok"></i> TikTok
                </a>
            </div>
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=ieceplsc24@gmail.com" target="_blank" rel="noopener noreferrer" style="display: flex; align-items: center; gap: 0.5rem; color: var(--neutral-400); text-decoration: none; font-size: 0.85rem; margin-top: 0.4rem;">
                <i class="fa-solid fa-envelope"></i> ieceplsc24@gmail.com
            </a>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?php echo date('Y'); ?> IECEP-LSC MEMSYS – All rights reserved.
    </div>
</footer>
