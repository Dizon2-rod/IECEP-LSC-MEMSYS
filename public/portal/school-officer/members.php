<?php
require_once __DIR__ . '/../auth_check.php';

require_once __DIR__ . '/../bootstrap.php';
$current_page = 'members';

require_once __DIR__ . '/../../../includes/config.php';
require_role(['school_officer']);

header('Location: ' . PORTAL_URL . '/school-officer/members/list.php');
exit;
