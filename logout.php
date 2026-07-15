<?php
require_once __DIR__ . '/bootstrap.php';
session_start();
require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/config.php';

// Destroy the session completely
$_SESSION = [];
session_unset();
session_destroy();
session_write_close();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear any other cookies
setcookie('PHPSESSID', '', time() - 42000, '/');

// Redirect to the landing page
header('Location: ' . BASE_URL . '/index.php');
exit;