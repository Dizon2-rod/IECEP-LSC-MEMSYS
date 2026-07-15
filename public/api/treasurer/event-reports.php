<?php
/**
 * Event Financial Reports API
 * Provides per-event financial reports
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
            if ($action === 'event-summary') {
                // Get financial summary for a specific event
                $eventId = $_GET['event_id'] ?? '';
                
                if (empty($eventId)) {
                    throw new Exception('event_id parameter is required');
                }
                
                // Get event details
                $event = $db->select('events', [
                    'id' => 'eq.' . $eventId
                ]);
                
                if (empty($event)) {
                    throw new Exception('Event not found');
                }
                
                $eventData = $event[0];
                
                // Get event registrations with payments
                $registrations = $db->select('event_registrations', [
                    'event_id' => 'eq.' . $eventId,
                    'payment_status' => 'eq.paid'
                ]);
                
                // Calculate total income
                $totalIncome = 0;
                $attendeeCount = count($registrations);
                
                foreach ($registrations as $reg) {
                    // Get transaction for this registration
                    $transactions = $db->select('transactions', [
                        'type' => 'eq.event_fee',
                        'status' => 'eq.paid'
                    ]);
                    
                    foreach ($transactions as $tx) {
                        $totalIncome += (float)($tx['amount'] ?? 0);
                    }
                }
                
                // If no transactions found, use event fee * attendee count
                if ($totalIncome === 0 && $eventData['registration_fee']) {
                    $totalIncome = (float)$eventData['registration_fee'] * $attendeeCount;
                }
                
                // Get payment status breakdown
                $allRegistrations = $db->select('event_registrations', [
                    'event_id' => 'eq.' . $eventId
                ]);
                
                $paymentStatus = [
                    'paid' => 0,
                    'pending' => 0,
                    'unpaid' => 0,
                    'waived' => 0
                ];
                
                foreach ($allRegistrations as $reg) {
                    $status = $reg['payment_status'] ?? 'pending';
                    if (isset($paymentStatus[$status])) {
                        $paymentStatus[$status]++;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'event' => [
                        'id' => $eventData['id'],
                        'title' => $eventData['title'],
                        'start_date' => $eventData['start_date'],
                        'end_date' => $eventData['end_date'],
                        'registration_fee' => $eventData['registration_fee'],
                        'venue' => $eventData['venue']
                    ],
                    'financial_summary' => [
                        'total_income' => $totalIncome,
                        'attendee_count' => $attendeeCount,
                        'payment_status' => $paymentStatus,
                        'average_revenue_per_attendee' => $attendeeCount > 0 ? round($totalIncome / $attendeeCount, 2) : 0
                    ]
                ]);
                
            } elseif ($action === 'all-events') {
                // Get financial summary for all events
                $year = (int)($_GET['year'] ?? date('Y'));
                $startDate = sprintf('%04d-01-01', $year);
                $endDate = sprintf('%04d-12-31', $year);
                
                // Get all events for the year
                $events = $db->select('events', [
                    'start_date' => "gte.{$startDate}",
                    'start_date' => "lte.{$endDate}",
                    'status' => 'eq.completed',
                    'order' => 'start_date.asc'
                ]);
                
                $eventReports = [];
                foreach ($events as $event) {
                    // Get registrations for this event
                    $registrations = $db->select('event_registrations', [
                        'event_id' => 'eq.' . $event['id'],
                        'payment_status' => 'eq.paid'
                    ]);
                    
                    $attendeeCount = count($registrations);
                    $totalIncome = 0;
                    
                    if ($event['registration_fee']) {
                        $totalIncome = (float)$event['registration_fee'] * $attendeeCount;
                    }
                    
                    $eventReports[] = [
                        'event_id' => $event['id'],
                        'title' => $event['title'],
                        'start_date' => $event['start_date'],
                        'venue' => $event['venue'],
                        'attendee_count' => $attendeeCount,
                        'registration_fee' => $event['registration_fee'],
                        'total_income' => $totalIncome
                    ];
                }
                
                // Calculate totals
                $totalEvents = count($eventReports);
                $totalAttendees = array_sum(array_column($eventReports, 'attendee_count'));
                $totalIncome = array_sum(array_column($eventReports, 'total_income'));
                
                echo json_encode([
                    'success' => true,
                    'year' => $year,
                    'summary' => [
                        'total_events' => $totalEvents,
                        'total_attendees' => $totalAttendees,
                        'total_income' => $totalIncome,
                        'average_income_per_event' => $totalEvents > 0 ? round($totalIncome / $totalEvents, 2) : 0
                    ],
                    'events' => $eventReports
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
