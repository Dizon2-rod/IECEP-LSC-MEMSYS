<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin']);
header('Location: /IECEP-LSC-MEMSYS/public/portal/admin/system/users.php');
exit;
