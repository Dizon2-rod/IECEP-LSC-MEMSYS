<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

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
    $currentYear = date('Y');
    $years = [$currentYear - 2, $currentYear - 1, $currentYear];
    
    $trendData = [];
    
    foreach ($years as $year) {
        // Get institutions
        $institutions = $sb->from('institutions')
            ->select('id, name')
            ->eq('status', 'active')
            ->get(true);
        
        if ($institutions['error']) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch institutions']);
            exit;
        }
        
        foreach ($institutions['data'] as $institution) {
            // Calculate participation rate for this institution and year
            $totalEvents = $sb->from('events')
                ->select('id')
                ->gte('start_date', $year . '-01-01')
                ->lte('start_date', $year . '-12-31')
                ->get(true);
            
            $attendedEvents = $sb->from('event_registrations')
                ->select('event_id')
                ->innerJoin('members', 'event_registrations.user_id=members.user_id')
                ->eq('members.institution_id', $institution['id'])
                ->eq('status', 'attended')
                ->get(true);
            
            $totalEventCount = count($totalEvents['data'] ?? []);
            $attendedEventCount = count($attendedEvents['data'] ?? []);
            
            $participationRate = $totalEventCount > 0 
                ? round(($attendedEventCount / $totalEventCount) * 100, 2) 
                : 0;
            
            $trendData[] = [
                'institution_name' => $institution['name'],
                'institution_id' => $institution['id'],
                'year' => $year,
                'participation_rate' => $participationRate,
                'total_events' => $totalEventCount,
                'attended_events' => $attendedEventCount
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $trendData,
        'years' => $years
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
