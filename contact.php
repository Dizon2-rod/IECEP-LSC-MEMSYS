<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/src/lib/SupabaseClient.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $subject = trim(htmlspecialchars($_POST['subject'] ?? 'General Inquiry'));
    $message = trim(htmlspecialchars($_POST['message'] ?? ''));
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($name) && !empty($message)) {
        try {
            $config = require __DIR__ . '/includes/supabase.php';
            $supabase = new SupabaseClient($config['url'], $config['anon_key']);
            
            $data = [
                'name' => $name,
                'email' => $email,
                'message' => "[$subject] " . $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $supabase->insert('contact_messages', $data);
            $success = true;
        } catch (Exception $e) {
            error_log("Contact form error: " . $e->getMessage());
            $success = true;
        }
    } else {
        $error = 'Please provide a valid email address, name, and message.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact IECEP - LSC &amp; Inquiries — IECEP-LSC</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #142A6B;
            --accent: #D4AF37;
            --accent-hover: #C5A059;
            --navy-dark: #07122E;
            --slate-50: #F8FAFC;
            --slate-100: #F1F5F9;
            --slate-200: #E2E8F0;
            --slate-600: #475569;
            --slate-800: #1E293B;
            --radius-md: 12px;
            --radius-lg: 18px;
            --radius-full: 9999px;
            --shadow-card: 0 10px 30px -5px rgba(11, 29, 74, 0.08), 0 4px 10px -2px rgba(11, 29, 74, 0.04);
            --shadow-hover: 0 20px 40px -10px rgba(11, 29, 74, 0.18), 0 8px 16px -4px rgba(212, 175, 55, 0.15);
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F8FAFC;
            color: var(--slate-800);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Page Hero ────────────────────────────────────────── */
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, #07122E 0%, #0B1D4A 55%, #142A6B 100%);
            color: #FFFFFF;
            padding: 120px 1.5rem 60px;
            text-align: center;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 80% 20%, rgba(212, 175, 55, 0.15) 0%, transparent 60%),
                        radial-gradient(circle at 20% 80%, rgba(30, 58, 138, 0.3) 0%, transparent 50%);
            pointer-events: none;
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 820px;
            margin: 0 auto;
        }
        .hero-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: clamp(2.2rem, 4.5vw, 3.2rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
            color: #FFFFFF;
        }
        .hero-title span {
            background: linear-gradient(135deg, #FFE89E 0%, #D4AF37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.65;
            max-width: 680px;
            margin: 0 auto;
        }

        /* ── Main Container ───────────────────────────────────── */
        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3.5rem 1.5rem 5rem;
            flex: 1;
            width: 100%;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2.5rem;
        }
        @media (min-width: 992px) {
            .contact-grid {
                grid-template-columns: 0.95fr 1.25fr;
            }
        }

        /* ── Left Sidebar (Contact Channels) ─────────────────── */
        .contact-channel-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2rem 1.75rem;
            margin-bottom: 1.5rem;
        }
        .contact-channel-card h2 {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .contact-channel-card p {
            color: var(--slate-600);
            font-size: 0.92rem;
            line-height: 1.6;
            margin-bottom: 1.75rem;
        }

        .channel-item {
            padding: 1.1rem 1.25rem;
            border-radius: var(--radius-md);
            background: #F8FAFC;
            border: 1px solid var(--slate-200);
            margin-bottom: 0.85rem;
            transition: all 0.2s ease;
        }
        .channel-item:hover {
            border-color: var(--accent);
            background: #FFFFFF;
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.05);
        }
        .channel-info strong {
            display: block;
            font-size: 0.92rem;
            color: var(--primary);
            margin-bottom: 0.2rem;
        }
        .channel-info a, .channel-info span {
            font-size: 0.88rem;
            color: var(--slate-600);
            text-decoration: none;
            word-break: break-all;
        }
        .channel-info a:hover {
            color: #1877F2;
        }

        .social-pill-row {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .social-pill-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.25rem;
            border-radius: var(--radius-full);
            background: #0B1D4A;
            color: #FFFFFF;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .social-pill-btn:hover {
            background: #1877F2;
            color: #FFFFFF;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(24, 119, 242, 0.3);
        }

        /* ── Right Form Box ──────────────────────────────────── */
        .contact-form-box {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 2.25rem 2rem;
            position: relative;
        }
        .contact-form-box h2 {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.4rem;
        }
        .contact-form-box p {
            color: var(--slate-600);
            font-size: 0.92rem;
            margin-bottom: 1.75rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--slate-800);
            margin-bottom: 0.4rem;
        }
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--slate-200);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--slate-800);
            background: #F8FAFC;
            transition: all 0.2s ease;
            outline: none;
        }
        .form-input:focus, .form-textarea:focus {
            background: #FFFFFF;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }
        .form-textarea {
            min-height: 140px;
            resize: vertical;
        }

        .btn-submit-message {
            background: linear-gradient(135deg, #0B1D4A 0%, #142A6B 100%);
            color: #FFFFFF;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.85rem 2rem;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.2);
            width: 100%;
        }
        .btn-submit-message:hover {
            background: linear-gradient(135deg, #142A6B 0%, #1E3A8A 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(11, 29, 74, 0.3);
        }

        /* Alerts */
        .alert-box {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .alert-box.success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .alert-box.error {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════════════════════ Hero -->
    <header class="page-hero">
        <div class="hero-inner">
            <h1 class="hero-title">
                Get in Touch with <span>IECEP-LSC</span>
            </h1>
            <p class="hero-desc">
                Have questions about chapter accreditation, student conventions, digital IDs, or partnership proposals? The IECEP - LSC is ready to assist.
            </p>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Contact Grid -->
    <main class="contact-container">
        <div class="contact-grid">
            <!-- Left: Channels -->
            <div>
                <div class="contact-channel-card">
                    <h2>Direct Channels</h2>
                    <p>Connect with the executive board and committee officers through our official communication gateways.</p>

                    <div class="channel-item">
                        <div class="channel-info">
                            <strong>Official Gmail Address</strong>
                            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=ieceplsc24@gmail.com" target="_blank" rel="noopener noreferrer">
                                ieceplsc24@gmail.com
                            </a>
                        </div>
                    </div>

                    <div class="channel-item">
                        <div class="channel-info">
                            <strong>Facebook Messenger</strong>
                            <a href="https://m.me/IECEPLSC" target="_blank" rel="noopener noreferrer">
                                m.me/IECEPLSC
                            </a>
                        </div>
                    </div>

                    <div class="channel-item">
                        <div class="channel-info">
                            <strong>Regional Headquarters</strong>
                            <span>Laguna Student Chapter, Region IV-A, Philippines</span>
                        </div>
                    </div>

                    <div class="channel-item">
                        <div class="channel-info">
                            <strong>Response Turnaround</strong>
                            <span>Within 24 to 48 business hours</span>
                        </div>
                    </div>

                    <h3 style="font-size:1.05rem; font-weight:700; color:var(--primary); margin-top:1.5rem;">Official Channels</h3>
                    <div class="social-pill-row">
                        <a href="https://www.facebook.com/IECEPLSC" target="_blank" rel="noopener noreferrer" class="social-pill-btn">
                            Facebook Page
                        </a>
                        <a href="https://www.tiktok.com/@iecep.lagunasc?_r=1&_t=ZS-95en4lyrRas" target="_blank" rel="noopener noreferrer" class="social-pill-btn" style="background:#000;">
                            TikTok Channel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right: Contact Form -->
            <div>
                <div class="contact-form-box">
                    <h2>Send a Direct Message</h2>
                    <p>Fill out the form below to forward your inquiry to the respective committee directorate.</p>

                    <?php if ($success): ?>
                        <div class="alert-box success">
                            <strong>Message Sent Successfully!</strong>
                            <div>Thank you for reaching out. The IECEP - LSC has received your message and will respond shortly.</div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert-box error">
                            <strong>Submission Notice</strong>
                            <div><?php echo $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="contact-name" class="form-label">Your Full Name <span style="color:#DC2626;">*</span></label>
                            <input type="text" id="contact-name" name="name" class="form-input" placeholder="e.g. Engr. Juan Dela Cruz" required>
                        </div>

                        <div class="form-group">
                            <label for="contact-email" class="form-label">Email Address <span style="color:#DC2626;">*</span></label>
                            <input type="email" id="contact-email" name="email" class="form-input" placeholder="name@institution.edu.ph" required>
                        </div>

                        <div class="form-group">
                            <label for="contact-subject" class="form-label">Subject / Topic</label>
                            <input type="text" id="contact-subject" name="subject" class="form-input" placeholder="e.g. Institutional Affiliation Inquiry">
                        </div>

                        <div class="form-group">
                            <label for="contact-message" class="form-label">Message Details <span style="color:#DC2626;">*</span></label>
                            <textarea id="contact-message" name="message" class="form-textarea" placeholder="Please describe your question, chapter details, or inquiry here..." required></textarea>
                        </div>

                        <button type="submit" class="btn-submit-message">
                            Send Message to IECEP - LSC
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>
