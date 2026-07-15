<?php
/**
 * Admin Analytics Dashboard API
 * Provides comprehensive analytics data with charts
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
            if ($action === 'dashboard') {
                // Get comprehensive dashboard analytics
                $year = (int)($_GET['year'] ?? date('Y'));
                
                // Membership growth data
                $membershipGrowth = [];
                for ($month = 1; $month <= 12; $month++) {
                    $monthStart = sprintf('%04d-%02d-01', $year, $month);
                    $monthEnd = date('Y-m-t', strtotime($monthStart));
                    
                    $members = $db->select('members', [
                        'created_at' => "gte.{$monthStart}",
                        'created_at' => "lte.{$monthEnd}"
                    ]);
                    
                    $membershipGrowth[] = [
                        'month' => $month,
                        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                        'new_members' => count($members)
                    ];
                }
                
                // Revenue trends
                $revenueData = [];
                for ($month = 1; $month <= 12; $month++) {
                    $monthStart = sprintf('%04d-%02d-01', $year, $month);
                    $monthEnd = date('Y-m-t', strtotime($monthStart));
                    
                    $transactions = $db->select('transactions', [
                        'transaction_date' => "gte.{$monthStart}",
                        'transaction_date' => "lte.{$monthEnd}",
                        'status' => 'eq.paid'
                    ]);
                    
                    $monthlyRevenue = array_sum(array_map(function($tx) {
                        return (float)($tx['amount'] ?? 0);
                    }, $transactions));
                    
                    $revenueData[] = [
                        'month' => $month,
                        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                        'revenue' => $monthlyRevenue
                    ];
                }
                
                // Event participation rates
                $events = $db->select('events', [
                    'status' => 'eq.completed',
                    'start_date' => "gte.{$year}-01-01",
                    'start_date' => "lte.{$year}-12-31"
                ]);
                
                $eventParticipation = [];
                foreach ($events as $event) {
                    $registrations = $db->select('event_registrations', [
                        'event_id' => 'eq.' . $event['id'],
                        'status' => 'eq.attended'
                    ]);
                    
                    $eventParticipation[] = [
                        'event_name' => $event['title'],
                        'attendees' => count($registrations),
                        'date' => $event['start_date']
                    ];
                }
                
                // Institution compliance overview
                $institutions = $db->select('institutions', [
                    'status' => 'eq.active'
                ]);
                
                $complianceOverview = [];
                foreach ($institutions as $inst) {
                    $complianceScores = $db->select('compliance_scores', [
                        'institution_id' => 'eq.' . $inst['id'],
                        'year' => 'eq.' . $year
                    ]);
                    
                    $complianceOverview[] = [
                        'institution_name' => $inst['name'],
                        'compliance_status' => $complianceScores[0]['compliance_status'] ?? 'not_evaluated',
                        'score' => $complianceScores[0]['overall_score'] ?? 0,
                        'participation_rate' => $complianceScores[0]['participation_rate'] ?? 0
                    ];
                }
                
                // Key metrics
                $totalMembers = count($db->select('members', [
                    'membership_status' => 'eq.active'
                ]));
                
                $totalInstitutions = count($db->select('institutions', [
                    'status' => 'eq.active'
                ]));
                
                $totalEvents = count($db->select('events', [
                    'start_date' => "gte.{$year}-01-01",
                    'start_date' => "lte.{$year}-12-31"
                ]));
                
                $totalRevenue = array_sum(array_column($revenueData, 'revenue'));
                
                $compliantInstitutions = count(array_filter($complianceOverview, function($c) {
                    return $c['compliance_status'] === 'compliant';
                }));
                
                echo json_encode([
                    'success' => true,
                    'year' => $year,
                    'key_metrics' => [
                        'total_members' => $totalMembers,
                        'total_institutions' => $totalInstitutions,
                        'total_events' => $totalEvents,
                        'total_revenue' => $totalRevenue,
                        'compliant_institutions' => $compliantInstitutions,
                        'compliance_rate' => $totalInstitutions > 0 ? round(($compliantInstitutions / $totalInstitutions) * 100, 2) : 0
                    ],
                    'membership_growth' => $membershipGrowth,
                    'revenue_trends' => $revenueData,
                    'event_participation' => $eventParticipation,
                    'compliance_overview' => $complianceOverview
                ]);
                
            } elseif ($action === 'decision-support') {
                // Get decision support highlights
                $year = (int)($_GET['year'] ?? date('Y'));
                
                // Institutions at risk
                $complianceScores = $db->select('compliance_scores', [
                    'year' => 'eq.' . $year
                ]);
                
                $atRiskInstitutions = array_filter($complianceScores, function($score) {
                    return $score['compliance_status'] === 'at_risk' || $score['compliance_status'] === 'non_compliant';
                });
                
                // Upcoming membership expirations
                $thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
                $expiringMembers = $db->select('members', [
                    'membership_expiry' => "lte.{$thirtyDaysFromNow}",
                    'membership_expiry' => "gte." . date('Y-m-d'),
                    'membership_status' => 'eq.active'
                ]);
                
                // Pending applications
                $pendingAffiliations = count($db->select('pending_affiliations', [
                    'status' => 'eq.pending'
                ]));
                
                $pendingPayments = count($db->select('transactions', [
                    'status' => 'eq.pending'
                ]));
                
                // Recommended actions
                $recommendations = [];
                
                if (count($atRiskInstitutions) > 0) {
                    $recommendations[] = [
                        'priority' => 'high',
                        'action' => 'Send compliance reminders',
                        'description' => count($atRiskInstitutions) . ' institutions are at risk of non-compliance',
                        'target' => 'institutions'
                    ];
                }
                
                if (count($expiringMembers) > 0) {
                    $recommendations[] = [
                        'priority' => 'high',
                        'action' => 'Send renewal reminders',
                        'description' => count($expiringMembers) . ' members have expiring memberships',
                        'target' => 'members'
                    ];
                }
                
                if ($pendingAffiliations > 0) {
                    $recommendations[] = [
                        'priority' => 'medium',
                        'action' => 'Review pending affiliations',
                        'description' => $pendingAffiliations . ' affiliation applications awaiting review',
                        'target' => 'affiliations'
                    ];
                }
                
                if ($pendingPayments > 0) {
                    $recommendations[] = [
                        'priority' => 'medium',
                        'action' => 'Follow up on pending payments',
                        'description' => $pendingPayments . ' payments awaiting confirmation',
                        'target' => 'payments'
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'decision_support' => [
                        'at_risk_institutions' => count($atRiskInstitutions),
                        'expiring_members' => count($expiringMembers),
                        'pending_affiliations' => $pendingAffiliations,
                        'pending_payments' => $pendingPayments,
                        'recommendations' => $recommendations
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
