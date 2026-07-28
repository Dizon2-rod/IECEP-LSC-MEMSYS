<?php
/**
 * Standardize portal PHP files:
 * - Insert $current_page fallback if missing
 * - Ensure require_once to public/portal/auth_check.php exists (if missing)
 * - Ensure include of includes/sidebar.php exists (if missing)
 * Backups are created as .bak.TIMESTAMP
 */

$root = realpath(__DIR__ . '/../public/portal');
if (!$root) {
    echo "public/portal not found\n";
    exit(1);
}

$skipNames = ['auth_check.php', 'bootstrap.php', 'realtime.php'];

function iterPhp($dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') yield $f->getPathname();
    }
}

function findParentWith($startDir, $relativeTarget) {
    $p = $startDir;
    while (true) {
        if (file_exists($p . '/' . $relativeTarget)) return $p . '/' . $relativeTarget;
        $up = dirname($p);
        if ($up === $p) break;
        $p = $up;
    }
    return false;
}

$patched = 0; $skipped = 0; $notes = [];

foreach (iterPhp($root) as $path) {
    $name = basename($path);
    if (in_array($name, $skipNames)) {
        echo "Skipping special file: $path\n";
        $skipped++; continue;
    }

    $src = file_get_contents($path);
    if ($src === false) { echo "Failed to read $path\n"; continue; }

    $orig = $src;

    // Ensure $current_page
    if (strpos($src, '$current_page') === false) {
        $pos = strpos($src, '<?php');
        if ($pos !== false) {
            $insert = "\nif (!isset(\$current_page)) { \$current_page = basename(__FILE__, '.php'); }\n";
            $src = substr($src, 0, $pos + 5) . $insert . substr($src, $pos + 5);
        }
    }

    // Ensure auth_check requires public/portal/auth_check.php
    if (strpos($src, 'auth_check.php') === false) {
        // find public/portal/auth_check.php from this file's dir
        $fileDir = dirname($path);
        $authFull = findParentWith($fileDir, 'public/portal/auth_check.php');
        if ($authFull) {
            // compute number of dirname(__DIR__, N) needed from this file dir to auth's parent
            $authParent = dirname($authFull);
            $p = $fileDir; $depth = 0;
            while (realpath($p) !== realpath($authParent) && $p !== dirname($p)) { $p = dirname($p); $depth++; }
            if (realpath($p) === realpath($authParent)) {
                $requireLine = "require_once dirname(__DIR__, $depth) . '/public/portal/auth_check.php';\n";
                // insert after first <?php if not already
                $pos = strpos($src, '<?php');
                if ($pos !== false) {
                    $insertAt = $pos + 5;
                    $src = substr($src, 0, $insertAt) . $requireLine . substr($src, $insertAt);
                }
            }
        } else {
            // couldn't find auth_check; add note
            $notes[] = "auth_check not found for $path";
        }
    }

    // Ensure includes/sidebar.php is required
    if (strpos($src, "sidebar.php") === false) {
        $fileDir = dirname($path);
        $sideFull = findParentWith($fileDir, 'includes/sidebar.php');
        if ($sideFull) {
            $sideParent = dirname($sideFull);
            $p = $fileDir; $depth = 0;
            while (realpath($p) !== realpath($sideParent) && $p !== dirname($p)) { $p = dirname($p); $depth++; }
            if (realpath($p) === realpath($sideParent)) {
                $incLine = "require_once dirname(__DIR__, $depth) . '/includes/sidebar.php';\n";
                // append before opening HTML (look for <!DOCTYPE or <html)
                $htmlPos = strpos($src, '<!DOCTYPE');
                if ($htmlPos === false) $htmlPos = strpos($src, '<html');
                if ($htmlPos !== false) {
                    // insert just before HTML
                    $src = substr($src, 0, $htmlPos) . "<?php\n" . $incLine . "?>\n" . substr($src, $htmlPos);
                } else {
                    // fallback: insert after opening php block
                    $pos = strpos($src, '<?php');
                    if ($pos !== false) {
                        $insertAt = $pos + 5;
                        $src = substr($src, 0, $insertAt) . $incLine . substr($src, $insertAt);
                    }
                }
            }
        } else {
            $notes[] = "sidebar not found for $path";
        }
    }

    if ($src !== $orig) {
        $bak = $path . '.bak.' . date('YmdHis');
        if (!copy($path, $bak)) { echo "Failed to backup $path\n"; continue; }
        if (file_put_contents($path, $src) !== false) {
            echo "Standardized: $path\n";
            $patched++;
        } else {
            echo "Failed to write: $path\n";
        }
    } else {
        $skipped++;
    }
}

echo "\nDone. Patched: $patched, Skipped: $skipped\n";
if (!empty($notes)) {
    echo "Notes:\n" . implode("\n", array_unique($notes)) . "\n";
}
