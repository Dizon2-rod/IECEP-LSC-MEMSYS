<?php
require_once __DIR__ . '/bootstrap.php';
// Offline Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #0A2F6C; color: #fff; text-align: center; padding: 24px; }
        h1 { font-size: 48px; margin-bottom: 8px; }
        p { opacity: 0.8; font-size: 16px; margin-bottom: 24px; }
        a { color: #F5A623; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div>
        <h1>📡</h1>
        <h2>You're Offline</h2>
        <p>IECEP-LSC MEMSYS requires an internet connection for most features. Please check your connection and try again.</p>
        <a href="/">Try Again</a>
    </div>
</body>
</html>
