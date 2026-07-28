<?php
require_once __DIR__ . '/../includes/role-config.php';
foreach (getAllRoles() as $role) {
    $cfg = getRoleConfig($role);
    foreach ($cfg['nav_items'] as $item) {
        $url = $item['url'];
        $path = preg_replace('#^/+#', '', $url);
        $repoRoot = realpath(__DIR__ . '/..');
        $fs1 = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $fs2 = $repoRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (file_exists($fs2)) {
            $fs = $fs2; $exists = 'exists';
        } elseif (file_exists($fs1)) {
            $fs = $fs1; $exists = 'exists';
        } else {
            $fs = $fs2; $exists = 'missing';
        }

        echo implode('|', [$role, $item['label'], $url, str_replace('\\', '/', $fs), $exists]) . PHP_EOL;
    }
}
