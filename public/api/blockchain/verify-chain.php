<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../src/lib/BlockchainService.php';

$allowedRoles = ['eb_president', 'eb_vp_internal', 'eb_auditor', 'super_admin'];
$userRole = $_SESSION['user']['role'] ?? null;

if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$recordType = trim((string) ($_GET['type'] ?? ''));
if ($recordType === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required type parameter']);
    exit;
}

try {
    if (!isset($GLOBALS['blockchain']) || !$GLOBALS['blockchain'] instanceof \App\Lib\BlockchainService) {
        throw new \RuntimeException('Blockchain service unavailable');
    }

    $result = $GLOBALS['blockchain']->verifyChain($recordType);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage(),
    ]);
}
