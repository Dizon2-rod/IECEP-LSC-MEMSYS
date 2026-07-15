<?php
/**
 * Financial Reports API
 * Provides monthly financial reports with Chart.js data
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';
require_once __DIR__ . '/../../includes/auth_check.php';

header('Content-Type: application/json');

// Admin only access
require_role(['admin']);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    $db = $GLOBALS['supabaseClient'] ?? null;
    if (!$db) {
        throw new Exception('Database connection not available');
    }
    
    switch ($method) {
        case 'GET':
            if ($action === 'monthly') {
                // Get monthly financial data for dashboard
                $year = (int)($_GET['year'] ?? date('Y'));
                
                $startDate = sprintf('%04d-01-01', $year);
                $endDate = sprintf('%04d-12-31', $year);
                
                // Get transactions for the year
                $transactions = $db->select('transactions', [
                    'created_at' => "gte.{$startDate}",
                    'created_at' => "lte.{$endDate}",
                    'status' => 'eq.paid',
                    'order' => 'created_at.asc'
                ]);
                
                // Calculate monthly totals
                $monthlyData = [];
                for ($month = 1; $month <= 12; $month++) {
                    $monthStart = sprintf('%04d-%02d-01', $year, $month);
                    $monthEnd = date('Y-m-t', strtotime($monthStart));
                    
                    $monthTransactions = array_filter($transactions, function($tx) use ($monthStart, $monthEnd) {
                        $txDate = substr($tx['created_at'] ?? '', 0, 10);
                        return $txDate >= $monthStart && $txDate <= $monthEnd;
                    });
                    
                    $monthlyTotal = array_sum(array_map(function($tx) {
                        return (float)($tx['amount'] ?? 0);
                    }, $monthTransactions));
                    
                    $monthlyData[] = [
                        'month' => date('F', mktime(0, 0, 0, $month, 1)),
                        'total_income' => $monthlyTotal,
                        'total_transactions' => count($monthTransactions)
                    ];
                }
                
                // Calculate totals by type
                $incomeByType = [
                    'membership_fee' => 0,
                    'event_fee' => 0,
                    'donation' => 0,
                    'other' => 0
                ];
                
                foreach ($transactions as $tx) {
                    $type = $tx['type'] ?? 'other';
                    if (isset($incomeByType[$type])) {
                        $incomeByType[$type] += (float)($tx['amount'] ?? 0);
                    } else {
                        $incomeByType['other'] += (float)($tx['amount'] ?? 0);
                    }
                }
                
                // Calculate summary metrics
                $totalIncome = array_sum(array_map(function($tx) {
                    return (float)($tx['amount'] ?? 0);
                }, $transactions));
                
                $pendingTransactions = $db->select('transactions', [
                    'created_at' => "gte.{$startDate}",
                    'created_at' => "lte.{$endDate}",
                    'status' => 'eq.pending'
                ]);
                $pendingPayments = array_sum(array_map(function($tx) {
                    return (float)($tx['amount'] ?? 0);
                }, $pendingTransactions));
                
                $totalMembers = count($db->select('members'));
                $totalInstitutions = count($db->select('institutions'));
                
                echo json_encode([
                    'success' => true,
                    'total_income' => $totalIncome,
                    'pending_payments' => $pendingPayments,
                    'total_members' => $totalMembers,
                    'total_institutions' => $totalInstitutions,
                    'monthly_data' => $monthlyData,
                    'income_by_type' => $incomeByType
                ]);
                
            } elseif ($action === 'institutions') {
                // Get per-institution financial data
                $year = (int)($_GET['year'] ?? date('Y'));
                
                $startDate = sprintf('%04d-01-01', $year);
                $endDate = sprintf('%04d-12-31', $year);
                
                // Get all institutions
                $institutions = $db->select('institutions');
                
                $institutionData = [];
                foreach ($institutions as $inst) {
                    $instId = $inst['id'];
                    
                    // Get member count
                    $members = $db->select('members', [
                        'institution_id' => 'eq.' . $instId
                    ]);
                    $memberCount = count($members);
                    
                    // Get paid transactions
                    $paidTransactions = $db->select('transactions', [
                        'institution_id' => 'eq.' . $instId,
                        'created_at' => "gte.{$startDate}",
                        'created_at' => "lte.{$endDate}",
                        'status' => 'eq.paid'
                    ]);
                    
                    $totalPaid = array_sum(array_map(function($tx) {
                        return (float)($tx['amount'] ?? 0);
                    }, $paidTransactions));
                    
                    // Get pending transactions
                    $pendingTransactions = $db->select('transactions', [
                        'institution_id' => 'eq.' . $instId,
                        'created_at' => "gte.{$startDate}",
                        'created_at' => "lte.{$endDate}",
                        'status' => 'eq.pending'
                    ]);
                    
                    $pending = array_sum(array_map(function($tx) {
                        return (float)($tx['amount'] ?? 0);
                    }, $pendingTransactions));
                    
                    // Get last payment date
                    $lastPayment = null;
                    if (!empty($paidTransactions)) {
                        usort($paidTransactions, function($a, $b) {
                            return strtotime($b['created_at']) - strtotime($a['created_at']);
                        });
                        $lastPayment = $paidTransactions[0]['created_at'] ?? null;
                    }
                    
                    // Check blockchain verification
                    $blockchainVerified = false;
                    if (!empty($paidTransactions)) {
                        $blockchainRecords = $db->select('blockchain_records', [
                            'record_type' => 'eq.payment',
                            'reference_id' => 'in.' . $instId,
                            'limit' => 1
                        ]);
                        $blockchainVerified = !empty($blockchainRecords);
                    }
                    
                    $institutionData[] = [
                        'id' => $instId,
                        'name' => $inst['name'] ?? 'Unknown',
                        'member_count' => $memberCount,
                        'total_paid' => $totalPaid,
                        'pending' => $pending,
                        'last_payment' => $lastPayment,
                        'blockchain_verified' => $blockchainVerified
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'institutions' => $institutionData
                ]);
                
            } elseif ($action === 'events') {
                // Get per-event financial data
                $year = (int)($_GET['year'] ?? date('Y'));
                
                $startDate = sprintf('%04d-01-01', $year);
                $endDate = sprintf('%04d-12-31', $year);
                
                // Get events for the year
                $events = $db->select('events', [
                    'event_date' => "gte.{$startDate}",
                    'event_date' => "lte.{$endDate}",
                    'order' => 'event_date.asc'
                ]);
                
                $eventData = [];
                foreach ($events as $event) {
                    $eventId = $event['id'];
                    
                    // Get transactions for this event
                    $eventTransactions = $db->select('transactions', [
                        'event_id' => 'eq.' . $eventId,
                        'status' => 'eq.paid'
                    ]);
                    
                    $totalIncome = array_sum(array_map(function($tx) {
                        return (float)($tx['amount'] ?? 0);
                    }, $eventTransactions));
                    
                    $participantCount = count($eventTransactions);
                    
                    // Check blockchain verification
                    $blockchainRecords = $db->select('blockchain_records', [
                        'record_type' => 'eq.payment',
                        'reference_id' => 'ev.' . $eventId,
                        'limit' => 1
                    ]);
                    $blockchainVerified = !empty($blockchainRecords);
                    
                    $eventData[] = [
                        'id' => $eventId,
                        'name' => $event['name'] ?? 'Unknown Event',
                        'date' => $event['event_date'] ?? null,
                        'total_income' => $totalIncome,
                        'participant_count' => $participantCount,
                        'blockchain_verified' => $blockchainVerified
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'events' => $eventData
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
