<?php
require_once __DIR__ . '/../auth_check.php';

require_once __DIR__ . '/../bootstrap.php';
$current_page = 'events';

require_once __DIR__ . '/../../../includes/config.php';
require_role(['admin', 'super_admin', 'committee_registration']);

header('Location: ' . PORTAL_URL . '/admin/events/list.php');
exit;
