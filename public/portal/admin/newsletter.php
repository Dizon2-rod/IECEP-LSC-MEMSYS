<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary']);
header('Location: /IECEP-LSC-MEMSYS/public/portal/admin/communication/newsletter.php');
exit;
