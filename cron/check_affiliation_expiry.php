<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Affiliation Validity Checker
 * Article IV, Section 4: Affiliation valid for one (1) academic year
 * 
 * Run daily via cron: 0 0 * * * php /path/to/check_affiliation_expiry.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// Find all schools with expired affiliation
$query = "
    SELECT id, school_name, validity_expiry, institution_id
    FROM school_profiles
    WHERE affiliation_status = 'Active'
    AND validity_expiry < CURRENT_DATE
";

$result = pg_query($conn, $query);

if (!$result) {
    error_log('Affiliation expiry check failed: ' . pg_last_error($conn));
    exit(1);
}

$expiredCount = 0;

while ($row = pg_fetch_assoc($result)) {
    $schoolId = $row['id'];
    $schoolName = $row['school_name'];
    $institutionId = $row['institution_id'];
    
    // Update status to Pending_Renewal
    $updateQuery = "
        UPDATE school_profiles 
        SET affiliation_status = 'Pending_Renewal'
        WHERE id = $1
    ";
    pg_query_params($conn, $updateQuery, [$schoolId]);
    
    // Get school officer user_id
    $officerQuery = "
        SELECT user_id 
        FROM user_profiles 
        WHERE institution_id = $1 
        AND role = 'school_officer'
        LIMIT 1
    ";
    $officerResult = pg_query_params($conn, $officerQuery, [$institutionId]);
    
    if ($officerResult && $officerRow = pg_fetch_assoc($officerResult)) {
        $userId = $officerRow['user_id'];
        
        // Send notification
        $notificationQuery = "
            INSERT INTO notifications (user_id, title, message, type, priority, action_url)
            VALUES ($1, $2, $3, 'affiliation', 'high', '/portal/school-officer/renewal.php')
        ";
        
        $title = 'Affiliation Renewal Required';
        $message = "Your school affiliation for {$schoolName} has expired. Please re-upload documents and pay renewal fees to maintain Active status.";
        
        pg_query_params($conn, $notificationQuery, [$userId, $title, $message]);
    }
    
    $expiredCount++;
    error_log("Affiliation expired for: {$schoolName} (ID: {$schoolId})");
}

echo "Processed {$expiredCount} expired affiliations\n";

// Also check for upcoming expirations (30 days warning)
$warningQuery = "
    SELECT id, school_name, validity_expiry, institution_id
    FROM school_profiles
    WHERE affiliation_status = 'Active'
    AND validity_expiry BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days'
";

$warningResult = pg_query($conn, $warningQuery);
$warningCount = 0;

while ($row = pg_fetch_assoc($warningResult)) {
    $schoolId = $row['id'];
    $schoolName = $row['school_name'];
    $institutionId = $row['institution_id'];
    $expiryDate = $row['validity_expiry'];
    
    $officerQuery = "
        SELECT user_id 
        FROM user_profiles 
        WHERE institution_id = $1 
        AND role = 'school_officer'
        LIMIT 1
    ";
    $officerResult = pg_query_params($conn, $officerQuery, [$institutionId]);
    
    if ($officerResult && $officerRow = pg_fetch_assoc($officerResult)) {
        $userId = $officerRow['user_id'];
        
        $notificationQuery = "
            INSERT INTO notifications (user_id, title, message, type, priority, action_url)
            VALUES ($1, $2, $3, 'reminder', 'normal', '/portal/school-officer/renewal.php')
        ";
        
        $title = 'Affiliation Expiring Soon';
        $message = "Your school affiliation for {$schoolName} will expire on {$expiryDate}. Please prepare renewal documents and payment.";
        
        pg_query_params($conn, $notificationQuery, [$userId, $title, $message]);
    }
    
    $warningCount++;
}

echo "Sent {$warningCount} expiry warnings\n";
exit(0);
