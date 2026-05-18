<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/helpers/participation_calculator.php';

header('Content-Type: application/json');

session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;

if (!$userId || !in_array($userRole, ['eb_president', 'eb_vp_internal', 'eb_vp_external'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$query = "
    SELECT 
        sp.id,
        sp.school_name,
        sp.total_members,
        sp.affiliation_status,
        COUNT(DISTINCT cd.id) as total_docs,
        COUNT(DISTINCT CASE WHEN cd.is_verified = true THEN cd.id END) as verified_docs,
        COUNT(DISTINCT al.user_id) as attending_members,
        (COUNT(DISTINCT al.user_id) * 100.0 / NULLIF(sp.total_members, 0)) as participation_rate
    FROM school_profiles sp
    LEFT JOIN compliance_docs cd ON cd.school_id = sp.id
    LEFT JOIN user_profiles up ON up.institution_id = sp.institution_id
    LEFT JOIN attendance_logs al ON al.user_id = up.user_id
    GROUP BY sp.id, sp.school_name, sp.total_members, sp.affiliation_status
    ORDER BY sp.school_name
";

$result = pg_query($conn, $query);

$schools = [];
while ($row = pg_fetch_assoc($result)) {
    $participationRate = floatval($row['participation_rate']);
    $verifiedDocs = intval($row['verified_docs']);
    
    $status = 'red';
    
    if ($participationRate >= 40 && $verifiedDocs >= 6) {
        $status = 'green';
    } elseif ($participationRate < 40 && $participationRate >= 20) {
        $status = 'yellow';
    }
    
    $schools[] = [
        'id' => $row['id'],
        'school_name' => $row['school_name'],
        'total_members' => intval($row['total_members']),
        'affiliation_status' => $row['affiliation_status'],
        'total_docs' => intval($row['total_docs']),
        'verified_docs' => $verifiedDocs,
        'attending_members' => intval($row['attending_members']),
        'participation_rate' => round($participationRate, 2),
        'compliance_status' => $status
    ];
}

echo json_encode(['schools' => $schools]);
