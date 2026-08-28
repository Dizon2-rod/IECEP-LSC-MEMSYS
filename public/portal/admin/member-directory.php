<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'committee_registration']);
header('Location: /IECEP-LSC-MEMSYS/public/portal/admin/members/list.php');
exit;
