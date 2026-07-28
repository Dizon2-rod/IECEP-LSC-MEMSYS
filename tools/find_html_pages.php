<?php
$root = __DIR__ . '/../public/portal';
if (!is_dir($root)) { echo "portal folder not found\n"; exit(1); }
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$pages = [];
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    if (strtolower($f->getExtension()) !== 'php') continue;
    $path = $f->getPathname();
    if (strpos($path, '.bak.') !== false) continue;
    $c = file_get_contents($path);
    if ($c === false) continue;
    if (stripos($c, '<!DOCTYPE') !== false || stripos($c, '<html') !== false) {
        $pages[] = $path;
    }
}

if (empty($pages)) { echo "No HTML pages found under public/portal\n"; }
else {
    echo "HTML pages:\n";
    foreach ($pages as $p) echo $p . "\n";
}
