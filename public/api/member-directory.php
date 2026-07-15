<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../portal/auth_check.php';

header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

// Get query parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$institution = isset($_GET['institution']) ? trim($_GET['institution']) : '';
$yearLevel = isset($_GET['year_level']) ? trim($_GET['year_level']) : '';

// Build query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "(m.full_name LIKE ? OR m.email LIKE ? OR m.membership_id LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if (!empty($status)) {
    $where[] = "m.membership_status = ?";
    $params[] = $status;
}

if (!empty($institution)) {
    $where[] = "i.name LIKE ?";
    $params[] = "%$institution%";
}

if (!empty($yearLevel)) {
    $where[] = "m.year_level = ?";
    $params[] = $yearLevel;
}

$whereClause = implode(' AND ', $where);

// Get total count
$countSql = "SELECT COUNT(*) as total FROM members m LEFT JOIN institutions i ON m.institution_id = i.id WHERE $whereClause";
$totalResult = $db->fetchOne($countSql, $params);
$total = $totalResult['total'] ?? 0;

// Get members with pagination
$sql = "SELECT 
    m.id,
    m.full_name,
    m.email,
    m.membership_id,
    m.membership_status,
    m.year_level,
    m.payment_status,
    m.created_at,
    i.name as institution_name,
    i.acronym as institution_acronym,
    up.role
FROM members m 
LEFT JOIN institutions i ON m.institution_id = i.id
LEFT JOIN user_profiles up ON m.user_id = up.user_id
WHERE $whereClause
ORDER BY m.created_at DESC
LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$members = $db->fetchAll($sql, $params);

echo json_encode([
    'success' => true,
    'data' => $members,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => ceil($total / $limit)
    ]
]);
