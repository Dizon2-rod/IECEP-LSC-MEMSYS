<?php
require_once __DIR__ . '/../bootstrap.php';
// Prevent multiple inclusions
if (defined('FOOTER_INCLUDED')) return;
define('FOOTER_INCLUDED', true);
?>
<!-- Minimal Theme-Aligned Footer -->
<footer class="footer" style="background: #07122E; color: #94A3B8; padding: 3.5rem 0 1.75rem; border-top: 1px solid rgba(255,255,255,0.08); font-size: 0.875rem;">
    <div class="footer-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2.5rem; max-width: 1240px; margin: 0 auto; padding: 0 1.5rem;">
        
        <!-- Brand & Vision -->
        <div class="footer-col" style="grid-column: span 1;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo" style="height: 38px; width: auto;" loading="lazy">
                <span style="font-size: 1.05rem; font-weight: 700; color: #FFFFFF; letter-spacing: -0.01em;">IECEP-LSC</span>
            </div>
            <p style="color: #94A3B8; font-size: 0.85rem; line-height: 1.6; margin: 0 0 1.25rem 0;">
                <strong style="color: #E2E8F0;">One IECEP, One Vision.</strong> Connecting, empowering, and advancing Electronics Engineering student chapters across Laguna.
            </p>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="https://www.facebook.com/IECEPLSC" target="_blank" rel="noopener noreferrer" aria-label="Facebook" style="width: 34px; height: 34px; border-radius: 8px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #94A3B8; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;">
                    <i class="fa-brands fa-facebook-f" style="font-size: 0.85rem;"></i>
                </a>
                <a href="https://www.tiktok.com/@iecep.lagunasc?_r=1&_t=ZS-95en4lyrRas" target="_blank" rel="noopener noreferrer" aria-label="TikTok" style="width: 34px; height: 34px; border-radius: 8px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #94A3B8; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;">
                    <i class="fa-brands fa-tiktok" style="font-size: 0.85rem;"></i>
                </a>
                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=ieceplsc24@gmail.com" target="_blank" rel="noopener noreferrer" aria-label="Email" style="width: 34px; height: 34px; border-radius: 8px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #94A3B8; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;">
                    <i class="fa-solid fa-envelope" style="font-size: 0.85rem;"></i>
                </a>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="footer-col">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; color: #D4AF37; margin-bottom: 1rem;">Quick Links</div>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.6rem;">
                <li><a href="<?php echo BASE_URL; ?>/index.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>/#features" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Features</a></li>
                <li><a href="<?php echo BASE_URL; ?>/#how-to-affiliate" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">How to Affiliate</a></li>
                <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Calendar Activity</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/merchandise.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Merchandise</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/blockchain-explorer.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Blockchain Explorer</a></li>
            </ul>
        </div>

        <!-- Resources -->
        <div class="footer-col">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; color: #D4AF37; margin-bottom: 1rem;">Resources</div>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.6rem;">
                <li><a href="<?php echo BASE_URL; ?>/iecep-officers.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">IECEP Officers</a></li>
                <li><a href="<?php echo BASE_URL; ?>/former-presidents.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Former Presidents</a></li>
                <li><a href="<?php echo BASE_URL; ?>/iecep-hymn.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">IECEP Hymn</a></li>
                <li><a href="<?php echo BASE_URL; ?>/awards-distinctions.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Awards &amp; Distinctions</a></li>
                <li><a href="<?php echo BASE_URL; ?>/affiliated-schools.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Affiliated Schools</a></li>
            </ul>
        </div>

        <!-- About & Legal -->
        <div class="footer-col">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; color: #D4AF37; margin-bottom: 1rem;">About IECEP-LSC</div>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.6rem;">
                <li><a href="<?php echo BASE_URL; ?>/mission-vision.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Mission &amp; Vision</a></li>
                <li><a href="<?php echo BASE_URL; ?>/objective.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Institutional Objectives</a></li>
                <li><a href="<?php echo BASE_URL; ?>/contact.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Contact Secretariat</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/privacy-policy.php" style="color: #94A3B8; text-decoration: none; transition: color 0.2s ease;">Privacy Policy</a></li>
                <li><a href="<?php echo BASE_URL; ?>/login.php" style="color: #D4AF37; font-weight: 600; text-decoration: none; transition: color 0.2s ease;">Portal Login &rarr;</a></li>
            </ul>
        </div>

    </div>

    <!-- Bottom Bar -->
    <div style="border-top: 1px solid rgba(255,255,255,0.06); margin-top: 2.5rem; padding-top: 1.5rem;">
        <div style="max-width: 1240px; margin: 0 auto; padding: 0 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; font-size: 0.8rem; color: #64748B;">
            <div>
                &copy; <?php echo date('Y'); ?> <span style="color: #94A3B8; font-weight: 600;">IECEP-LSC MEMSYS</span>. All rights reserved.
            </div>
            <div style="display: flex; gap: 1.5rem;">
                <a href="<?php echo BASE_URL; ?>/mission-vision.php" style="color: #64748B; text-decoration: none; transition: color 0.2s ease;">About</a>
                <a href="<?php echo BASE_URL; ?>/public/privacy-policy.php" style="color: #64748B; text-decoration: none; transition: color 0.2s ease;">Privacy</a>
                <a href="<?php echo BASE_URL; ?>/contact.php" style="color: #64748B; text-decoration: none; transition: color 0.2s ease;">Contact</a>
            </div>
        </div>
    </div>
</footer>
