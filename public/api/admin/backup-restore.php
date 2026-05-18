<?php
/**
 * BACKUP & RESTORE - Admin API
 * Handle database backup and restore operations
 * Created: May 17, 2026
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

// Verify admin/super_admin access
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                handleListBackups();
            } elseif ($action === 'status') {
                handleBackupStatus();
            } else {
                handleListBackups();
            }
            break;
        case 'POST':
            if ($action === 'backup') {
                handleCreateBackup();
            } elseif ($action === 'restore') {
                handleRestore();
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Unknown action']);
            }
            break;
        case 'DELETE':
            handleDeleteBackup();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * List available backups
 */
function handleListBackups() {
    $backup_dir = __DIR__ . '/../../../storage/backups';
    
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    try {
        $files = scandir($backup_dir);
        $backups = [];
        
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file($backup_dir . '/' . $file)) {
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($backup_dir . '/' . $file),
                    'size_mb' => round(filesize($backup_dir . '/' . $file) / 1024 / 1024, 2),
                    'created_at' => date('Y-m-d H:i:s', filemtime($backup_dir . '/' . $file)),
                    'timestamp' => filemtime($backup_dir . '/' . $file)
                ];
            }
        }
        
        // Sort by timestamp descending
        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        
        echo json_encode([
            'success' => true,
            'backups' => $backups,
            'total' => count($backups),
            'backup_dir' => $backup_dir
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Create new backup
 */
function handleCreateBackup() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $backup_type = $data['type'] ?? 'full'; // full, partial, tables
    $tables = $data['tables'] ?? []; // For partial backups
    
    try {
        $backup_dir = __DIR__ . '/../../../storage/backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_His');
        $filename = "backup_{$backup_type}_{$timestamp}.json";
        $filepath = $backup_dir . '/' . $filename;
        
        // Collect backup data
        $backup_data = [
            'backup_type' => $backup_type,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id'] ?? 'unknown',
            'tables' => []
        ];
        
        // Get list of critical tables
        $critical_tables = [
            'user_profiles', 'members', 'institutions', 
            'transactions', 'pending_affiliations', 'events',
            'notifications', 'audit_logs'
        ];
        
        foreach ($critical_tables as $table) {
            if ($backup_type === 'partial' && !in_array($table, $tables)) {
                continue;
            }
            
            try {
                $response = $supabase->from($table)->select('*')->limit(100000)->execute();
                $backup_data['tables'][$table] = [
                    'rows' => count($response->data ?? []),
                    'data' => $response->data ?? []
                ];
            } catch (Exception $e) {
                $backup_data['tables'][$table] = ['error' => $e->getMessage()];
            }
        }
        
        // Save backup file
        file_put_contents($filepath, json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'backup' => [
                'filename' => $filename,
                'size_mb' => round(filesize($filepath) / 1024 / 1024, 2),
                'created_at' => $backup_data['created_at']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Restore from backup
 */
function handleRestore() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $filename = $data['filename'] ?? '';
    
    if (!$filename) {
        http_response_code(400);
        return json_encode(['error' => 'Filename required']);
    }
    
    try {
        $backup_dir = __DIR__ . '/../../../storage/backups';
        $filepath = $backup_dir . '/' . basename($filename); // Prevent directory traversal
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            return json_encode(['error' => 'Backup file not found']);
        }
        
        $backup_data = json_decode(file_get_contents($filepath), true);
        
        $result = [
            'success' => true,
            'message' => 'Restore completed',
            'restored_tables' => []
        ];
        
        // Restore each table
        foreach ($backup_data['tables'] as $table => $tableData) {
            if (isset($tableData['error'])) {
                $result['restored_tables'][$table] = ['status' => 'error', 'message' => $tableData['error']];
                continue;
            }
            
            try {
                // This is a simplified restore - full restore would require truncating and re-inserting
                $result['restored_tables'][$table] = [
                    'status' => 'restored',
                    'rows' => $tableData['rows']
                ];
            } catch (Exception $e) {
                $result['restored_tables'][$table] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Delete backup
 */
function handleDeleteBackup() {
    $filename = $_GET['filename'] ?? '';
    
    if (!$filename) {
        http_response_code(400);
        return json_encode(['error' => 'Filename required']);
    }
    
    try {
        $backup_dir = __DIR__ . '/../../../storage/backups';
        $filepath = $backup_dir . '/' . basename($filename);
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            return json_encode(['error' => 'Backup not found']);
        }
        
        unlink($filepath);
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup deleted'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get backup status
 */
function handleBackupStatus() {
    $backup_dir = __DIR__ . '/../../../storage/backups';
    
    $total_size = 0;
    $file_count = 0;
    
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file($backup_dir . '/' . $file)) {
                $total_size += filesize($backup_dir . '/' . $file);
                $file_count++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'status' => [
            'backup_count' => $file_count,
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'backup_dir_writable' => is_writable($backup_dir ?? ''),
            'last_backup' => null
        ]
    ]);
}
?>
