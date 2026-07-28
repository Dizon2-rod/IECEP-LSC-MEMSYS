<?php
/**
 * Ensure every portal PHP file defines a $current_page fallback.
 * Creates a .bak before modifying.
 */
$root = __DIR__ . '/../public/portal';

function iterFiles($dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            yield $file->getPathname();
        }
    }
}

$patched = 0;
$skipped = 0;

foreach (iterFiles($root) as $path) {
    $contents = file_get_contents($path);
    if ($contents === false) continue;

    // Skip files that already reference $current_page
    if (strpos($contents, '$current_page') !== false) {
        $skipped++;
        echo "Skipped: $path\n";
        continue;
    }

    // Find opening <?php tag
    $pos = strpos($contents, '<?php');
    if ($pos === false) {
        // no php opening tag—skip
        $skipped++;
        echo "No PHP tag, skipped: $path\n";
        continue;
    }

    // Prepare insertion after <?php
    $insert = "\nif (!isset(\$current_page)) { \$current_page = basename(__FILE__, '.php'); }\n";

    $new = substr($contents, 0, $pos + 5) . $insert . substr($contents, $pos + 5);

    // backup
    $bak = $path . '.bak.' . date('YmdHis');
    if (!copy($path, $bak)) {
        echo "Failed to backup $path\n";
        continue;
    }

    if (file_put_contents($path, $new) !== false) {
        $patched++;
        echo "Patched: $path\n";
    } else {
        echo "Failed to write: $path\n";
    }
}

echo "\nDone. Patched: $patched, Skipped: $skipped\n";
