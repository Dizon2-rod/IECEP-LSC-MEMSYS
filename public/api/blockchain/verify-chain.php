<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/supabase.php';
require_once __DIR__ . '/../../../src/lib/BlockchainService.php';
require_once __DIR__ . '/../../../includes/middleware/auth.php';

use App\Lib\Supabase;
use App\Lib\BlockchainService;
use App\Middleware\AuthMiddleware;

$allowedRoles = ['admin', 'super_admin'];
$auth = new AuthMiddleware();
$auth->requireRole($allowedRoles);

$sb = new Supabase();
$blockchain = new BlockchainService($sb->getClient());

$input = json_decode(file_get_contents('php://input'), true);
$entityType = trim((string) ($input['entity_type'] ?? ''));

if ($entityType === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required entity_type parameter']);
    exit;
}

try {
    $result = $blockchain->verifyChain($entityType);
    echo json_encode($result);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'error' => 'Server error',
        'message' => $e->getMessage(),
    ]);
}
