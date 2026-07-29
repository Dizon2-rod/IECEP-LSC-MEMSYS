<?php
require_once __DIR__ . '/../auth_check.php';

require_once __DIR__ . '/../bootstrap.php';
$current_page = 'affiliations';

require_once __DIR__ . '/../../../includes/config.php';
require_role(['admin', 'super_admin', 'registration', 'committee_registration']);

header('Location: ' . PORTAL_URL . '/admin/institutions/list.php');
exit;
