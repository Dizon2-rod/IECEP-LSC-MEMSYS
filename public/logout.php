<?php
require_once __DIR__ . '/bootstrap.php';
// Start session to access it
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_unset();
session_destroy();

// Close session write
session_write_close();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Clear any other cookies
setcookie('PHPSESSID', '', time() - 42000, '/');

// Redirect to login page
header('Location: /IECEP-LSC-MEMSYS/login.php');
exit;
