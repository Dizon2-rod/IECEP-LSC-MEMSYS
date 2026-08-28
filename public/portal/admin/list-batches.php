<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'registration', 'committee_registration']);
header('Location: /IECEP-LSC-MEMSYS/public/portal/admin/members/batch-process.php');
exit;