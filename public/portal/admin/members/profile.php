<?php
/**
 * Member Profile Dossier (Deprecated in favor of unified Member Directory Profile Modal)
 * Redirecting seamlessly to list.php
 */
require_once dirname(__DIR__, 2) . '/auth_check.php';
require_role(['admin', 'super_admin', 'committee_registration']);

header('Location: /IECEP-LSC-MEMSYS/public/portal/admin/members/list.php');
exit;
