<?php
require_once __DIR__ . '/../bootstrap.php';
// Prevent multiple inclusions
if (defined('FOOTER_INCLUDED')) return;
define('FOOTER_INCLUDED', true);
?>
<style>
/* ═══════════════════════════════════════════════════════════
   Universal SaaS Responsive Minimal Footer
   ═══════════════════════════════════════════════════════════ */
.footer-site {
    background: #07122E;
    color: #94A3B8;
    padding: 3rem 0 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.08);
    font-size: 0.8rem;
    font-family: 'DM Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.footer-site-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr 1.2fr;
    gap: 2rem;
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 1.25rem;
    box-sizing: border-box;
}

.footer-brand-col {
    grid-column: span 1;
}

.footer-col-title {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    color: #D4AF37;
    margin-bottom: 0.85rem;
}

.footer-links-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.footer-links-list a {
    color: #94A3B8;
    text-decoration: none;
    font-size: 0.78rem;
    transition: all 0.2s ease;
    display: inline-block;
}

.footer-links-list a:hover {
    color: #D4AF37;
    transform: translateX(2px);
}

.footer-social-btn {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    color: #94A3B8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.8rem;
}

.footer-social-btn:hover {
    background: rgba(212,175,55,0.15);
    border-color: rgba(212,175,55,0.4);
    color: #D4AF37;
    transform: translateY(-2px);
}

.footer-bottom-bar {
    border-top: 1px solid rgba(255,255,255,0.06);
    margin-top: 2rem;
    padding-top: 1.25rem;
}

.footer-bottom-inner {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.65rem;
    font-size: 0.75rem;
    color: #64748B;
}

/* Tablet & Mobile Multi-Column Responsive Layout (Parallel Columns like Desktop) */
@media (max-width: 991.98px) and (min-width: 641px) {
    .footer-site-grid {
        grid-template-columns: 1fr 1fr;
        gap: 1.75rem 1.25rem;
    }
}

@media (max-width: 640px) {
    .footer-site {
        padding: 1.75rem 0 1rem !important;
        font-size: 0.72rem !important;
    }
    
    .footer-site-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 1rem 0.35rem !important;
        padding: 0 0.65rem !important;
    }
    
    .footer-brand-col {
        grid-column: 1 / -1 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding-bottom: 0.75rem;
        margin-bottom: 0.2rem;
        text-align: left;
    }

    .footer-brand-col img {
        height: 28px !important;
    }

    .footer-brand-col span {
        font-size: 0.88rem !important;
    }

    .footer-brand-col p {
        font-size: 0.68rem !important;
        line-height: 1.4 !important;
        margin-bottom: 0.65rem !important;
    }
    
    .footer-col-title {
        font-size: 0.58rem !important;
        margin-bottom: 0.35rem !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .footer-links-list {
        gap: 0.25rem !important;
    }
    
    .footer-links-list a {
        font-size: 0.6rem !important;
        line-height: 1.25 !important;
        word-break: break-word;
    }

    .footer-social-btn {
        width: 24px !important;
        height: 24px !important;
        font-size: 0.65rem !important;
        border-radius: 5px !important;
    }
    
    .footer-bottom-bar {
        margin-top: 1.25rem !important;
        padding-top: 0.75rem !important;
    }
    
    .footer-bottom-inner {
        flex-direction: column !important;
        text-align: center !important;
        gap: 0.35rem !important;
        padding: 0 0.5rem !important;
        font-size: 0.62rem !important;
    }

    .footer-bottom-inner a {
        font-size: 0.62rem !important;
    }
}
</style>

<!-- Minimal Theme-Aligned Responsive Footer -->
<footer class="footer-site">
    <div class="footer-site-grid">
        
        <!-- Brand & Vision -->
        <div class="footer-col footer-brand-col">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.85rem;">
                <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo" style="height: 36px; width: auto;" loading="lazy">
                <span style="font-size: 1.05rem; font-weight: 700; color: #FFFFFF; letter-spacing: -0.01em;">IECEP-LSC</span>
            </div>
            <p style="color: #94A3B8; font-size: 0.82rem; line-height: 1.55; margin: 0 0 1rem 0;">
                <strong style="color: #E2E8F0;">One IECEP, One Vision.</strong> Connecting, empowering, and advancing Electronics Engineering student chapters across Laguna.
            </p>
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <a href="https://www.facebook.com/IECEPLSC" target="_blank" rel="noopener noreferrer" aria-label="Facebook Page" class="footer-social-btn" title="Facebook Page">
                    <i class="fa-brands fa-facebook-f" style="font-size: 0.85rem;"></i>
                </a>
                <a href="https://www.tiktok.com/@iecep.lagunasc?_r=1&_t=ZS-95en4lyrRas" target="_blank" rel="noopener noreferrer" aria-label="TikTok" class="footer-social-btn" title="TikTok">
                    <i class="fa-brands fa-tiktok" style="font-size: 0.85rem;"></i>
                </a>
                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=ieceplsc24@gmail.com" target="_blank" rel="noopener noreferrer" aria-label="Email" class="footer-social-btn" title="Email via Gmail">
                    <i class="fa-solid fa-envelope" style="font-size: 0.85rem;"></i>
                </a>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="footer-col">
            <div class="footer-col-title">Quick Links</div>
            <ul class="footer-links-list">
                <li><a href="<?php echo BASE_URL; ?>/index.php">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>/#features">Features</a></li>
                <li><a href="<?php echo BASE_URL; ?>/#how-to-affiliate">How to Affiliate</a></li>
                <li><a href="<?php echo BASE_URL; ?>/calendar-activity.php">Calendar</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/merchandise.php">Merchandise</a></li>
            </ul>
        </div>

        <!-- Resources -->
        <div class="footer-col">
            <div class="footer-col-title">Resources</div>
            <ul class="footer-links-list">
                <li><a href="<?php echo BASE_URL; ?>/iecep-officers.php">IECEP Officers</a></li>
                <li><a href="<?php echo BASE_URL; ?>/former-presidents.php">Presidents</a></li>
                <li><a href="<?php echo BASE_URL; ?>/iecep-hymn.php">IECEP Hymn</a></li>
                <li><a href="<?php echo BASE_URL; ?>/awards-distinctions.php">Awards</a></li>
                <li><a href="<?php echo BASE_URL; ?>/affiliated-schools.php">Schools</a></li>
            </ul>
        </div>

        <!-- About & Legal -->
        <div class="footer-col">
            <div class="footer-col-title">About IECEP</div>
            <ul class="footer-links-list">
                <li><a href="<?php echo BASE_URL; ?>/mission-vision.php">Mission/Vision</a></li>
                <li><a href="<?php echo BASE_URL; ?>/objective.php">Objectives</a></li>
                <li><a href="<?php echo BASE_URL; ?>/contact.php">Contact</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/privacy-policy.php">Privacy Policy</a></li>
                <li><a href="<?php echo BASE_URL; ?>/login.php" style="color: #D4AF37; font-weight: 700;">Portal Login &rarr;</a></li>
            </ul>
        </div>

    </div>

    <!-- Bottom Bar -->
    <div class="footer-bottom-bar">
        <div class="footer-bottom-inner">
            <div>
                &copy; <?php echo date('Y'); ?> <span style="color: #94A3B8; font-weight: 600;">IECEP-LSC MEMSYS</span>. All rights reserved.
            </div>
            <div style="display: flex; gap: 1.25rem;">
                <a href="<?php echo BASE_URL; ?>/mission-vision.php" style="color: #64748B; text-decoration: none;">About</a>
                <a href="<?php echo BASE_URL; ?>/public/privacy-policy.php" style="color: #64748B; text-decoration: none;">Privacy</a>
                <a href="<?php echo BASE_URL; ?>/contact.php" style="color: #64748B; text-decoration: none;">Contact</a>
            </div>
        </div>
    </div>
</footer>
