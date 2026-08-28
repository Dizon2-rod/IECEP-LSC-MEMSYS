<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_treasurer', 'eb_auditor']);
header('Location: /IECEP-LSC-MEMSYS/public/portal/admin/financial/transparency.php');
exit;
