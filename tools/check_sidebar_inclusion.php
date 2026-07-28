<?php
$root = __DIR__ . '/../public/portal';
if (!is_dir($root)) { echo "portal folder not found\n"; exit(1); }

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$missing = [];
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    if (strtolower($f->getExtension()) !== 'php') continue;
    $path = $f->getPathname();
    // skip backups
    if (strpos($path, '.bak.') !== false) continue;
    $contents = file_get_contents($path);
    if ($contents === false) continue;
    if (strpos($contents, 'sidebar.php') === false) {
        $missing[] = $path;
    }
}

if (empty($missing)) {
    echo "All portal files include sidebar.php\n";
} else {
    echo "Files missing sidebar include:\n";
    foreach ($missing as $m) echo $m . "\n";
}
