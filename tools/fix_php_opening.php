<?php
// Fix files that have '<?php' immediately followed by non-whitespace (e.g., '<?phprequire')
$root = __DIR__ . '/../public/portal';
if (!is_dir($root)) { echo "portal not found\n"; exit(1); }
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$fixed = 0; $skipped = 0;
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    if (strtolower($f->getExtension()) !== 'php') continue;
    $path = $f->getPathname();
    if (strpos($path, '.bak.') !== false) continue;
    $c = file_get_contents($path);
    if ($c === false) continue;
    // Replace '<?php' followed by non-whitespace (not newline or space) with '<?php\n'
    $new = preg_replace('/<\?php(?!\s)/', "<?php\n", $c, -1, $count);
    if ($new !== null && $count > 0) {
        $bak = $path . '.bak.' . date('YmdHis');
        copy($path, $bak);
        file_put_contents($path, $new);
        echo "Fixed opening tag: $path\n";
        $fixed++;
    } else {
        $skipped++;
    }
}
echo "Done. Fixed: $fixed, Skipped: $skipped\n";
