<?php
// Thank You Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand"><span>IECEP</span>-LSC</a>
    </nav>

    <div style="max-width:800px;margin:80px auto;padding:0 24px;text-align:center">
        <div style="width:120px;height:120px;background:var(--success);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 32px;font-size:60px;color:white">
            ✓
        </div>
        
        <h1 style="font-size:2.5rem;color:var(--primary);margin-bottom:16px;font-weight:700">Thank You!</h1>
        <p style="font-size:1.2rem;color:#6c757d;margin-bottom:32px">Your application has been successfully submitted.</p>
        
        <div class="card" style="padding:32px;text-align:left;background:#f8f9fa">
            <h3 style="color:var(--primary);margin-bottom:16px">What's Next?</h3>
            <ol style="line-height:1.8;color:#495057">
                <li>Our admin team will review your application within 3-5 business days.</li>
                <li>We will contact you via email at the address you provided.</li>
                <li>Once approved, you can create user accounts for your members.</li>
                <li>Members can then register and start using the system.</li>
            </ol>
        </div>

        <a href="/" class="btn btn-primary btn-lg" style="margin-top:32px">Return to Home</a>
    </div>

    <script src="/js/app.js"></script>
</body>
</html>
