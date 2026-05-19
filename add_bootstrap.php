<?php
/**
 * Script to add require_once __DIR__ . '/bootstrap.php'; to all PHP files
 */

$rootDir = __DIR__;
$bootstrapLine = "require_once __DIR__ . '/bootstrap.php';";
$filesModified = 0;
$filesSkipped = 0;

function processDirectory($dir, $bootstrapLine, &$filesModified, &$filesSkipped) {
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($filePath)) {
            // Skip vendor and node_modules
            if (basename($filePath) === 'vendor' || basename($filePath) === 'node_modules') {
                continue;
            }
            processDirectory($filePath, $bootstrapLine, $filesModified, $filesSkipped);
        } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
            $content = file_get_contents($filePath);
            
            // Skip if already has bootstrap
            if (strpos($content, '/bootstrap.php') !== false) {
                echo "SKIP: $filePath (already has bootstrap)\n";
                $filesSkipped++;
                continue;
            }
            
            // Skip if file is bootstrap.php itself
            if (basename($filePath) === 'bootstrap.php') {
                echo "SKIP: $filePath (is bootstrap.php)\n";
                $filesSkipped++;
                continue;
            }
            
            // Find the opening PHP tag
            if (preg_match('/<\?php/', $content)) {
                // Insert after opening PHP tag and any namespace/use statements
                $lines = explode("\n", $content);
                $insertIndex = 0;
                
                // Find where to insert (after <?php and any namespace/use declarations)
                for ($i = 0; $i < count($lines); $i++) {
                    $trimmed = trim($lines[$i]);
                    if (preg_match('/^<\?php/', $trimmed) || 
                        preg_match('/^namespace\s+/', $trimmed) || 
                        preg_match('/^use\s+/', $trimmed) ||
                        $trimmed === '') {
                        $insertIndex = $i + 1;
                    } else {
                        break;
                    }
                }
                
                // Insert the bootstrap require
                array_splice($lines, $insertIndex, 0, $bootstrapLine);
                $newContent = implode("\n", $lines);
                
                file_put_contents($filePath, $newContent);
                echo "MODIFIED: $filePath\n";
                $filesModified++;
            } else {
                echo "SKIP: $filePath (no PHP opening tag)\n";
                $filesSkipped++;
            }
        }
    }
}

processDirectory($rootDir, $bootstrapLine, $filesModified, $filesSkipped);

echo "\n=== SUMMARY ===\n";
echo "Files Modified: $filesModified\n";
echo "Files Skipped: $filesSkipped\n";
?>
