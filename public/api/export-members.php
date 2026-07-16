<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="member_directory_' . date('Y-m-d') . '.csv"');

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

use App\Lib\Supabase;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();

// Admin only access
$user = $auth->requireRole(['admin']);

try {
    // Fetch all members with institution details
    $members = $sb->from('members')
        ->select('*, institutions(name, acronym, city, province)')
        ->order('full_name', 'asc')
        ->get(true);
    
    if ($members['error']) {
        throw new Exception('Failed to fetch members: ' . $members['message']);
    }
    
    $memberData = $members['data'] ?? [];
    
    // Create CSV headers
    $headers = [
        'Membership ID',
        'Full Name',
        'Email',
        'Institution',
        'Institution Acronym',
        'City',
        'Province',
        'Member Type',
        'Payment Status',
        'Year Level',
        'Phone',
        'Membership Status',
        'Created Date'
    ];
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write member data
    foreach ($memberData as $member) {
        $institution = $member['institutions'] ?? [];
        $row = [
            $member['membership_id'] ?? 'N/A',
            $member['full_name'] ?? 'N/A',
            $member['email'] ?? 'N/A',
            $institution['name'] ?? 'N/A',
            $institution['acronym'] ?? 'N/A',
            $institution['city'] ?? 'N/A',
            $institution['province'] ?? 'N/A',
            $member['member_type'] ?? 'N/A',
            $member['payment_status'] ? 'Paid' : 'Pending',
            $member['year_level'] ?? 'N/A',
            $member['phone'] ?? 'N/A',
            $member['is_new'] ? 'New' : 'Returning',
            $member['created_at'] ? date('Y-m-d', strtotime($member['created_at'])) : 'N/A'
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    // Output error as CSV
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Error', $e->getMessage()]);
    fclose($output);
}
