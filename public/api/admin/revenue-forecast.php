<?php
/**
 * Revenue Forecasting API
 * Projects income based on current member count and fee brackets
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    $db = $GLOBALS['supabaseClient'] ?? null;
    if (!$db) {
        throw new Exception('Database connection not available');
    }
    
    switch ($method) {
        case 'GET':
            if ($action === 'forecast') {
                // Generate revenue forecast
                $months = (int)($_GET['months'] ?? 12);
                
                // Get current active members
                $activeMembers = count($db->select('members', [
                    'membership_status' => 'eq.active'
                ]));
                
                // Get institutions with member counts
                $institutions = $db->select('institutions', [
                    'status' => 'eq.active'
                ]);
                
                // Get fee brackets
                $feeBrackets = $db->select('fee_brackets', [
                    'is_active' => 'eq.true'
                ]);
                
                // Calculate projected monthly revenue
                $forecast = [];
                $cumulativeRevenue = 0;
                
                for ($i = 1; $i <= $months; $i++) {
                    $forecastDate = date('Y-m-d', strtotime("+$i months"));
                    $monthName = date('F Y', strtotime("+$i months"));
                    
                    // Base revenue from membership fees
                    $monthlyRevenue = 0;
                    
                    foreach ($institutions as $inst) {
                        $memberCount = $inst['membership_count'] ?? 0;
                        
                        // Find applicable fee bracket
                        $applicableBracket = null;
                        foreach ($feeBrackets as $bracket) {
                            if ($memberCount >= $bracket['min_members'] && 
                                ($memberCount <= $bracket['max_members'] || $bracket['max_members'] === null)) {
                                $applicableBracket = $bracket;
                                break;
                            }
                        }
                        
                        if ($applicableBracket) {
                            $monthlyRevenue += (float)($applicableBracket['annual_fee'] ?? 0) / 12;
                        }
                    }
                    
                    // Add event revenue projection (average of past 3 months)
                    $threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));
                    $recentEvents = $db->select('events', [
                        'start_date' => "gte.{$threeMonthsAgo}",
                        'status' => 'eq.completed'
                    ]);
                    
                    $avgEventRevenue = 0;
                    if (count($recentEvents) > 0) {
                        $totalEventRevenue = 0;
                        foreach ($recentEvents as $event) {
                            $registrations = $db->select('event_registrations', [
                                'event_id' => 'eq.' . $event['id'],
                                'payment_status' => 'eq.paid'
                            ]);
                            $totalEventRevenue += count($registrations) * ($event['registration_fee'] ?? 0);
                        }
                        $avgEventRevenue = $totalEventRevenue / 3; // Average per month
                    }
                    
                    $monthlyRevenue += $avgEventRevenue;
                    
                    // Add growth factor (5% annual growth assumption)
                    $growthFactor = 1 + (0.05 / 12) * $i;
                    $monthlyRevenue *= $growthFactor;
                    
                    $cumulativeRevenue += $monthlyRevenue;
                    
                    $forecast[] = [
                        'month' => $i,
                        'month_name' => $monthName,
                        'projected_revenue' => round($monthlyRevenue, 2),
                        'cumulative_revenue' => round($cumulativeRevenue, 2),
                        'active_members' => $activeMembers,
                        'growth_factor' => round($growthFactor, 4)
                    ];
                }
                
                // Calculate summary statistics
                $totalProjectedRevenue = $cumulativeRevenue;
                $averageMonthlyRevenue = $totalProjectedRevenue / $months;
                $currentMonthlyRevenue = $forecast[0]['projected_revenue'];
                $growthRate = (($forecast[$months - 1]['projected_revenue'] - $currentMonthlyRevenue) / $currentMonthlyRevenue) * 100;
                
                echo json_encode([
                    'success' => true,
                    'forecast_period' => [
                        'months' => $months,
                        'start_date' => date('Y-m-d'),
                        'end_date' => date('Y-m-d', strtotime("+$months months"))
                    ],
                    'summary' => [
                        'total_projected_revenue' => round($totalProjectedRevenue, 2),
                        'average_monthly_revenue' => round($averageMonthlyRevenue, 2),
                        'current_monthly_revenue' => round($currentMonthlyRevenue, 2),
                        'growth_rate' => round($growthRate, 2),
                        'active_members' => $activeMembers,
                        'active_institutions' => count($institutions)
                    ],
                    'monthly_forecast' => $forecast
                ]);
                
            } elseif ($action === 'breakdown') {
                // Get revenue breakdown by source
                $year = (int)($_GET['year'] ?? date('Y'));
                
                // Membership fees
                $membershipTransactions = $db->select('transactions', [
                    'type' => 'eq.membership_fee',
                    'status' => 'eq.paid',
                    'transaction_date' => "gte.{$year}-01-01",
                    'transaction_date' => "lte.{$year}-12-31"
                ]);
                
                $membershipRevenue = array_sum(array_map(function($tx) {
                    return (float)($tx['amount'] ?? 0);
                }, $membershipTransactions));
                
                // Event fees
                $eventTransactions = $db->select('transactions', [
                    'type' => 'eq.event_fee',
                    'status' => 'eq.paid',
                    'transaction_date' => "gte.{$year}-01-01",
                    'transaction_date' => "lte.{$year}-12-31"
                ]);
                
                $eventRevenue = array_sum(array_map(function($tx) {
                    return (float)($tx['amount'] ?? 0);
                }, $eventTransactions));
                
                // Donations
                $donationTransactions = $db->select('transactions', [
                    'type' => 'eq.donation',
                    'status' => 'eq.paid',
                    'transaction_date' => "gte.{$year}-01-01",
                    'transaction_date' => "lte.{$year}-12-31"
                ]);
                
                $donationRevenue = array_sum(array_map(function($tx) {
                    return (float)($tx['amount'] ?? 0);
                }, $donationTransactions));
                
                // Penalties
                $penaltyTransactions = $db->select('transactions', [
                    'type' => 'eq.penalty',
                    'status' => 'eq.paid',
                    'transaction_date' => "gte.{$year}-01-01",
                    'transaction_date' => "lte.{$year}-12-31"
                ]);
                
                $penaltyRevenue = array_sum(array_map(function($tx) {
                    return (float)($tx['amount'] ?? 0);
                }, $penaltyTransactions));
                
                $totalRevenue = $membershipRevenue + $eventRevenue + $donationRevenue + $penaltyRevenue;
                
                echo json_encode([
                    'success' => true,
                    'year' => $year,
                    'breakdown' => [
                        'membership_fees' => round($membershipRevenue, 2),
                        'event_fees' => round($eventRevenue, 2),
                        'donations' => round($donationRevenue, 2),
                        'penalties' => round($penaltyRevenue, 2),
                        'total' => round($totalRevenue, 2)
                    ],
                    'percentages' => [
                        'membership_fees' => $totalRevenue > 0 ? round(($membershipRevenue / $totalRevenue) * 100, 2) : 0,
                        'event_fees' => $totalRevenue > 0 ? round(($eventRevenue / $totalRevenue) * 100, 2) : 0,
                        'donations' => $totalRevenue > 0 ? round(($donationRevenue / $totalRevenue) * 100, 2) : 0,
                        'penalties' => $totalRevenue > 0 ? round(($penaltyRevenue / $totalRevenue) * 100, 2) : 0
                    ]
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
