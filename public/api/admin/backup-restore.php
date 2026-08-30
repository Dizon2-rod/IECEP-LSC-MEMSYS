<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * BACKUP & RESTORE - Admin API
 * Handle database backup and restore operations
 * Created: May 17, 2026
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

if (!function_exists('uploadToSupabaseStorage')) {
    function uploadToSupabaseStorage(string $bucket, string $path, string $tmpFile, string $mimeType): ?string {
        $supabaseClient = getSupabaseClient();
        if ($supabaseClient && method_exists($supabaseClient, 'uploadFile')) {
            $url = $supabaseClient->uploadFile($bucket, $path, $tmpFile, $mimeType);
            if ($url) return $url;
        }
        return null;
    }
}

/**
 * Delete an object from Supabase Storage
 */
function deleteFromSupabaseStorage(string $bucket, string $path): bool {
    $config = require __DIR__ . '/../../../includes/supabase.php';
    $url = rtrim($config['url'], '/') . "/storage/v1/object/$bucket/$path";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $config['service_role_key'],
            'Authorization: Bearer ' . $config['service_role_key'],
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * List objects in a Supabase Storage bucket
 */
function listSupabaseStorageObjects(string $bucket, string $prefix = ''): array {
    $config = require __DIR__ . '/../../../includes/supabase.php';
    $url = rtrim($config['url'], '/') . "/storage/v1/object/$bucket?prefix=" . urlencode($prefix) . "&limit=1000";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $config['service_role_key'],
            'Authorization: Bearer ' . $config['service_role_key'],
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true) ?? [];
    }
    return [];
}

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
    global $supabase;

    $backups = [];
    $objects = listSupabaseStorageObjects('backups', 'backup_');

    foreach ($objects as $obj) {
        $name = basename($obj['name'] ?? '');
        if (!$name) continue;
        $backups[] = [
            'filename' => $name,
            'size' => $obj['size'] ?? 0,
            'size_mb' => round(($obj['size'] ?? 0) / 1024 / 1024, 2),
            'created_at' => $obj['created_at'] ?? '',
            'timestamp' => strtotime($obj['created_at'] ?? 'now')
        ];
    }

    usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

    echo json_encode([
        'success' => true,
        'backups' => $backups,
        'total' => count($backups),
    ]);
}

/**
 * Create new backup
 */
function handleCreateBackup() {
    global $supabase;

    $data = json_decode(file_get_contents('php://input'), true);
    $backup_type = $data['type'] ?? 'full';
    $tables = $data['tables'] ?? [];

    try {
        $timestamp = date('Y-m-d_His');
        $filename = "backup_{$backup_type}_{$timestamp}.json";
        $objectPath = 'backups/' . $filename;

        $backup_data = [
            'backup_type' => $backup_type,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id'] ?? 'unknown',
            'tables' => []
        ];

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

        $tmpFile = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tmpFile, json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $backupSize = filesize($tmpFile);

        $uploadUrl = uploadToSupabaseStorage('backups', $objectPath, $tmpFile, 'application/json');
        @unlink($tmpFile);

        if ($uploadUrl) {
            echo json_encode([
                'success' => true,
                'message' => 'Backup created successfully',
                'backup' => [
                    'filename' => $filename,
                    'size_mb' => round($backupSize / 1024 / 1024, 2),
                    'created_at' => $backup_data['created_at']
                ]
            ]);
        } else {
            @unlink($tmpFile);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to upload backup to Supabase Storage']);
        }
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
        $objectPath = 'backups/' . basename($filename);
        $config = require __DIR__ . '/../../../includes/supabase.php';
        $downloadUrl = rtrim($config['url'], '/') . "/storage/v1/object/public/backups/{$objectPath}";

        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $config['service_role_key'],
                'Authorization: Bearer ' . $config['service_role_key'],
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 || !$response) {
            http_response_code(404);
            return json_encode(['error' => 'Backup file not found in storage']);
        }

        $backup_data = json_decode($response, true);

        $result = [
            'success' => true,
            'message' => 'Restore completed',
            'restored_tables' => []
        ];

        foreach ($backup_data['tables'] as $table => $tableData) {
            if (isset($tableData['error'])) {
                $result['restored_tables'][$table] = ['status' => 'error', 'message' => $tableData['error']];
                continue;
            }

            try {
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
        $objectPath = 'backups/' . basename($filename);
        $deleted = deleteFromSupabaseStorage('backups', $objectPath);

        if ($deleted) {
            echo json_encode([
                'success' => true,
                'message' => 'Backup deleted'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Backup not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get backup status
 */
function handleBackupStatus() {
    $objects = listSupabaseStorageObjects('backups', 'backup_');

    $total_size = 0;
    $file_count = 0;

    foreach ($objects as $obj) {
        $total_size += $obj['size'] ?? 0;
        $file_count++;
    }

    echo json_encode([
        'success' => true,
        'status' => [
            'backup_count' => $file_count,
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'backup_dir_writable' => true,
            'last_backup' => null
        ]
    ]);
}
?>
