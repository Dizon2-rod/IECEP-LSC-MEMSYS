<?php
/**
 * Fix script to update all PHP files to use bootstrap.php
 * This script updates the require statements in all root-level PHP files
 */

$projectRoot = dirname(__FILE__);
$files = [
    'index.php',
    'iecep-officers.php',
    'former-presidents.php',
    'mission-vision.php',
    'objective.php',
    'calendar-activity.php',
    'board-of-trustees.php',
    'awards-distinctions.php',
    'affiliated-schools.php',
    'iecep-hymn.php',
    'contact.php',
    'login.php',
    'logout.php',
    'change-password.php',
];

foreach ($files as $file) {
    $path = $projectRoot . '/' . $file;
    if (!file_exists($path)) {
        echo "Skipping $file (not found)\n";
        continue;
    }

    $content = file_get_contents($path);
    
    // Replace the require statements
    $oldPattern = "<?php\nsession_start();\nrequire_once __DIR__ . '/autoload.php';\nrequire_once __DIR__ . '/includes/supabase.php';\nrequire_once __DIR__ . '/includes/paths.php';";
    $newPattern = "<?php\nrequire_once __DIR__ . '/bootstrap.php';";
    
    if (strpos($content, $oldPattern) !== false) {
        $content = str_replace($oldPattern, $newPattern, $content);
        file_put_contents($path, $content);
        echo "✓ Fixed $file\n";
    } else {
        echo "- $file doesn't match pattern (may already be fixed)\n";
    }
}

echo "\nDone!\n";
?>
