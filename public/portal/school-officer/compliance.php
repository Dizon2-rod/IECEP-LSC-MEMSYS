<?php
require_once __DIR__ . '/../auth_check.php';

require_once __DIR__ . '/../bootstrap.php';
$current_page = 'compliance';

require_once __DIR__ . '/../../../includes/config.php';
require_role(['school_officer']);

header('Location: ' . PORTAL_URL . '/school-officer/compliance/status.php');
exit;
