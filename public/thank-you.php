<?php
require_once __DIR__ . '/bootstrap.php';
// Thank You Modal
session_start();
$successMessage = $_GET['success'] ?? 'Your affiliation application has been successfully submitted.';
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #0A2F6C;
        --success: #10b981;
    }

    .thank-you-modal {
        max-width: 500px;
        margin: 0 auto;
        text-align: center;
        padding: 32px;
        font-family: 'Inter', sans-serif;
    }

    .success-icon {
        width: 80px;
        height: 80px;
        background: var(--success);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
        font-size: 40px;
        color: white;
        box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        animation: scaleIn 0.5s ease-out;
    }

    @keyframes scaleIn {
        from { transform: scale(0); }
        to { transform: scale(1); }
    }

    h1 {
        font-size: 1.8rem;
        color: var(--primary);
        margin-bottom: 12px;
        font-weight: 700;
    }

    p {
        color: #6c757d;
        margin-bottom: 24px;
        font-size: 1rem;
    }

    .card {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        text-align: left;
        border: 1px solid #e2e8f0;
        margin: 24px 0;
    }

    h3 {
        color: var(--primary);
        margin-bottom: 12px;
        font-size: 1rem;
        font-weight: 600;
    }

    ol {
        line-height: 1.6;
        color: #495057;
        padding-left: 20px;
        margin: 0;
        font-size: 0.9rem;
    }

    ol li {
        margin-bottom: 8px;
    }

    .btn-close {
        display: inline-block;
        padding: 10px 24px;
        background: var(--primary);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .btn-close:hover {
        background: #072255;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(10, 47, 108, 0.3);
    }
</style>

<div class="thank-you-modal">
    <div class="success-icon">✓</div>
    <h1>Thank You!</h1>
    <p><?php echo htmlspecialchars($successMessage); ?></p>

    <div class="card">
        <h3>What's Next?</h3>
        <ol>
            <li>The Registration Committee will review your application within 3-5 business days.</li>
            <li>We will contact you via email at the address you provided.</li>
            <li>Once approved, you will receive login credentials for your institution.</li>
            <li>You can then create accounts for your members to access the system.</li>
        </ol>
    </div>

    <button class="btn-close" onclick="window.parent.postMessage({action: 'closeModal'}, '*')">Close</button>
</div>

<script>
    // Send message to parent to close modal
    window.addEventListener('load', function() {
        window.parent.postMessage({action: 'modalLoaded'}, '*');
    });
</script>
