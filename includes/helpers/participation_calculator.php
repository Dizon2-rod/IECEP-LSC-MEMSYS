<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../database.php';

/**
 * Calculate participation rate for a school
 * Implements the 40% Participation Rule from CBL
 */
function calculateParticipationRate($schoolId, $conn) {
    $query = "
        SELECT 
            sp.total_members,
            COUNT(DISTINCT al.user_id) as attending_members,
            (COUNT(DISTINCT al.user_id) * 100.0 / NULLIF(sp.total_members, 0)) as participation_rate
        FROM school_profiles sp
        LEFT JOIN user_profiles up ON up.institution_id = sp.institution_id
        LEFT JOIN attendance_logs al ON al.user_id = up.user_id
        WHERE sp.id = $1
        GROUP BY sp.id, sp.total_members
    ";
    
    $result = pg_query_params($conn, $query, [$schoolId]);
    
    if ($result && pg_num_rows($result) > 0) {
        return pg_fetch_assoc($result);
    }
    
    return [
        'total_members' => 0,
        'attending_members' => 0,
        'participation_rate' => 0
    ];
}

/**
 * Get compliance status based on participation rate
 */
function getComplianceStatus($participationRate) {
    if ($participationRate >= 40) return 'Compliant';
    if ($participationRate >= 20) return 'At Risk';
    return 'Non-Compliant';
}

/**
 * Update school compliance status for monitoring purposes
 * Note: Per project guidelines, compliance is for monitoring and advisory purposes only.
 * It does NOT revoke or suspend institutional affiliation status.
 */
function updateSchoolStatus($schoolId, $conn) {
    $stats = calculateParticipationRate($schoolId, $conn);
    $status = getComplianceStatus($stats['participation_rate']);
    
    // In monitoring-only model, affiliation status remains unaffected.
    // We update the compliance_status column to track chapter engagement standing.
    $query = "UPDATE school_profiles SET compliance_status = $1 WHERE id = $2";
    @pg_query_params($conn, $query, [$status, $schoolId]);
    
    return [
        'compliance_status' => $status,
        'participation_rate' => $stats['participation_rate']
    ];
}
