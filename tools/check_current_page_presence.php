<?php
$root = __DIR__ . '/../public/portal';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$missing = [];
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    if (strtolower($f->getExtension()) !== 'php') continue;
    $path = $f->getPathname();
    if (strpos($path, '.bak.') !== false) continue;
    $c = file_get_contents($path);
    if ($c === false) continue;
    // only consider files that output HTML
    if (stripos($c, '<!DOCTYPE') === false && stripos($c, '<html') === false) continue;
    if (strpos($c, '$current_page') === false) $missing[] = $path;
}
if (empty($missing)) echo "All HTML portal pages define \$current_page\n";
else {
    echo "Pages missing \$current_page:\n";
    foreach ($missing as $m) echo $m . "\n";
}
