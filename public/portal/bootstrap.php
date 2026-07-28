<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Portal Bootstrap - Requires root bootstrap
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
