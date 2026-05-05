<?php
session_start();
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/paths.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $subject = htmlspecialchars($_POST['subject'] ?? '');
    $message = htmlspecialchars($_POST['message'] ?? '');
    
    if ($name && $email && $subject && $message) {
        // Here you would typically send the email or save to database
        $success = true;
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Contact Us - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--primary) 100%);
            color: var(--white);
            padding: 140px 1rem var(--space-8);
            text-align: center;
            overflow: hidden;
        }
        .page-hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: var(--space-4);
        }
        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-8) var(--space-4);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-8);
        }
        @media (max-width: 768px) {
            .contact-container {
                grid-template-columns: 1fr;
            }
        }
        .contact-info {
            background: var(--white);
            padding: var(--space-6);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
        }
        .contact-info h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: var(--space-4);
        }
        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
            margin-bottom: var(--space-4);
        }
        .contact-item i {
            color: var(--accent);
            font-size: 1.2rem;
            margin-top: 4px;
        }
        .contact-form {
            background: var(--white);
            padding: var(--space-6);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
        }
        .contact-form h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: var(--space-4);
        }
        .form-group {
            margin-bottom: var(--space-4);
        }
        .form-group label {
            display: block;
            margin-bottom: var(--space-2);
            color: var(--neutral-700);
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--neutral-200);
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .submit-btn {
            background: var(--primary);
            color: var(--white);
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .submit-btn:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }
        .alert {
            padding: var(--space-3);
            border-radius: 8px;
            margin-bottom: var(--space-4);
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .social-links {
            display: flex;
            gap: var(--space-3);
            margin-top: var(--space-4);
        }
        .social-links a {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .social-links a:hover {
            background: var(--accent);
            color: var(--primary);
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="page-hero">
        <div class="page-hero-content">
            <h1>Contact Us</h1>
            <p>We'd love to hear from you. Reach out to IECEP-LSC.</p>
        </div>
    </section>

    <main class="contact-container">
        <div class="contact-info">
            <h2>Get in Touch</h2>
            <p style="margin-bottom: var(--space-6); color: var(--neutral-600);">
                Have questions, suggestions, or want to collaborate? 
                Contact us through any of the channels below.
            </p>
            
            <div class="contact-item">
                <i class="fas fa-envelope"></i>
                <div>
                    <strong>Email</strong><br>
                    <a href="mailto:ieceplsc24@gmail.com" style="color: var(--neutral-700);">ieceplsc24@gmail.com</a>
                </div>
            </div>
            
            <div class="contact-item">
                <i class="fas fa-map-marker-alt"></i>
                <div>
                    <strong>Location</strong><br>
                    <span style="color: var(--neutral-700);">Laguna, Philippines</span>
                </div>
            </div>
            
            <div class="contact-item">
                <i class="fas fa-clock"></i>
                <div>
                    <strong>Office Hours</strong><br>
                    <span style="color: var(--neutral-700);">Monday - Friday: 9:00 AM - 5:00 PM</span>
                </div>
            </div>
            
            <h3 style="margin-top: var(--space-6); margin-bottom: var(--space-3);">Follow Us</h3>
            <div class="social-links">
                <a href="https://www.facebook.com/IECEPLSC" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://www.tiktok.com/@iecep.lagunasc" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-tiktok"></i>
                </a>
            </div>
        </div>

        <div class="contact-form">
            <h2>Send us a Message</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Thank you! Your message has been sent successfully.
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Your Name</label>
                    <input type="text" id="name" name="name" required placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required placeholder="What is this about?">
                </div>
                
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required placeholder="Type your message here..."></textarea>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>
