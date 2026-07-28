<?php
$root = realpath(__DIR__ . '/..');
$dir = new RecursiveDirectoryIterator($root . '/public/portal');
$it = new RecursiveIteratorIterator($dir);
foreach ($it as $f) {
    if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
        $content = file_get_contents($f->getPathname());
        if (strpos($content, '$current_page') === false) {
            echo str_replace('\\', '/', $f->getPathname()) . PHP_EOL;
        }
    }
}
