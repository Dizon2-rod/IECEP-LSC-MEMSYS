<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../autoload.php';
require_once __DIR__ . '/../../../includes/supabase.php';
require_once __DIR__ . '/../../../includes/paths.php';

// Check if user is logged in and has creatives role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'creatives') {
    header('Location: ' . PORTAL_URL . '/dashboard.php');
    exit;
}

$supabase = new \App\Lib\Supabase();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $data = [
            'title' => $_POST['title'] ?? '',
            'event_date' => $_POST['event_date'] ?? '',
            'time' => $_POST['time'] ?? '',
            'venue' => $_POST['venue'] ?? '',
            'description' => $_POST['description'] ?? '',
            'organizer' => $_POST['organizer'] ?? 'IECEP-LSC',
            'created_by' => $_SESSION['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $result = $supabase->from('calendar_activities')->insert($data)->get(true);
            if ($result) {
                $message = 'Event added successfully!';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error adding event: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'delete' && !empty($_POST['event_id'])) {
        try {
            $supabase->from('calendar_activities')->delete()->eq('id', $_POST['event_id'])->get(true);
            $message = 'Event deleted successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error deleting event: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'edit' && !empty($_POST['event_id'])) {
        $data = [
            'title' => $_POST['title'] ?? '',
            'event_date' => $_POST['event_date'] ?? '',
            'time' => $_POST['time'] ?? '',
            'venue' => $_POST['venue'] ?? '',
            'description' => $_POST['description'] ?? '',
            'organizer' => $_POST['organizer'] ?? 'IECEP-LSC'
        ];
        
        try {
            $supabase->from('calendar_activities')->update($data)->eq('id', $_POST['event_id'])->get(true);
            $message = 'Event updated successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error updating event: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch all events
$events = [];
try {
    $result = $supabase->from('calendar_activities')
        ->select('*')
        ->order('event_date', true)
        ->get(true);
    $events = $result['data'] ?? [];
} catch (Exception $e) {
    $events = [];
}

// Get upcoming events count (next 7 days)
$upcomingCount = 0;
foreach ($events as $event) {
    $eventDate = strtotime($event['event_date']);
    $daysUntil = ceil(($eventDate - time()) / 86400);
    if ($daysUntil >= 0 && $daysUntil <= 7) {
        $upcomingCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Calendar - IECEP-LSC Creatives</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #1E3A6E;
            --accent: #D4AF37;
            --accent-hover: #C4A030;
            --white: #FFFFFF;
            --neutral-100: #F5F5F5;
            --neutral-200: #E5E5E5;
            --neutral-500: #6B7280;
            --neutral-700: #374151;
            --neutral-900: #111827;
            --success: #10B981;
            --error: #EF4444;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-6: 24px;
            --space-8: 32px;
            --radius-md: 8px;
            --radius-lg: 12px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--neutral-100); color: var(--neutral-900); }
        
        .container { max-width: 1200px; margin: 0 auto; padding: var(--space-6); }
        
        .page-header { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            padding: var(--space-8) var(--space-6);
            margin-bottom: var(--space-6);
        }
        .page-header h1 { font-size: 2rem; margin-bottom: var(--space-2); }
        .page-header a { color: var(--accent); text-decoration: none; }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }
        .stat-card { 
            background: var(--white); 
            padding: var(--space-6);
            border-radius: var(--radius-lg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 { font-size: 0.9rem; color: var(--neutral-500); margin-bottom: var(--space-2); }
        .stat-card .number { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .stat-card.upcoming .number { color: var(--accent); }
        
        .card { 
            background: var(--white); 
            border-radius: var(--radius-lg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: var(--space-6);
            overflow: hidden;
        }
        .card-header { 
            padding: var(--space-4) var(--space-6);
            border-bottom: 1px solid var(--neutral-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h2 { font-size: 1.25rem; color: var(--primary); }
        .card-body { padding: var(--space-6); }
        
        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-4);
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        .form-group { margin-bottom: var(--space-4); }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-label { display: block; font-weight: 600; margin-bottom: var(--space-2); color: var(--neutral-700); }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: var(--space-3);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 1rem;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        .form-textarea { min-height: 100px; resize: vertical; }
        
        .btn {
            padding: var(--space-3) var(--space-6);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            transition: all 0.3s;
        }
        .btn-primary { background: var(--primary); color: var(--white); }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-accent { background: var(--accent); color: var(--primary); }
        .btn-accent:hover { background: var(--accent-hover); }
        .btn-danger { background: var(--error); color: var(--white); }
        .btn-danger:hover { background: #DC2626; }
        .btn-outline { background: transparent; border: 2px solid var(--neutral-200); color: var(--neutral-700); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        
        .alert { 
            padding: var(--space-4);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-4);
        }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        
        .events-table { width: 100%; border-collapse: collapse; }
        .events-table th, .events-table td { 
            padding: var(--space-3);
            text-align: left;
            border-bottom: 1px solid var(--neutral-200);
        }
        .events-table th { 
            background: var(--neutral-100);
            font-weight: 600;
            color: var(--neutral-700);
        }
        .events-table tr:hover { background: var(--neutral-100); }
        
        .event-date-cell { 
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        .date-box {
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: var(--white);
            border-radius: var(--radius-md);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .date-box .day { font-size: 1.25rem; line-height: 1; }
        .date-box .month { font-size: 0.7rem; text-transform: uppercase; }
        .date-box.upcoming { background: var(--accent); color: var(--primary); }
        .date-box.today { background: #10B981; }
        
        .notification-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-soon { background: #FEE2E2; color: #DC2626; }
        .badge-upcoming { background: #DBEAFE; color: #1E40AF; }
        
        .action-btns { display: flex; gap: var(--space-2); }
        
        .empty-state { text-align: center; padding: var(--space-8); color: var(--neutral-500); }
        .empty-state i { font-size: 3rem; margin-bottom: var(--space-4); }
        
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: var(--space-4) var(--space-6);
            border-bottom: 1px solid var(--neutral-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 1.25rem; color: var(--primary); }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--neutral-500);
            cursor: pointer;
        }
        .modal-body { padding: var(--space-6); }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-calendar-alt"></i> Manage Calendar</h1>
            <p><a href="<?php echo PORTAL_URL; ?>/dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a> | 
               <a href="<?php echo BASE_URL; ?>/calendar-activity.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Public Calendar</a></p>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>TOTAL EVENTS</h3>
                <div class="number"><?php echo count($events); ?></div>
            </div>
            <div class="stat-card upcoming">
                <h3>UPCOMING (7 DAYS)</h3>
                <div class="number"><?php echo $upcomingCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>THIS MONTH</h3>
                <div class="number"><?php echo count(array_filter($events, fn($e) => date('m', strtotime($e['event_date'])) == date('m'))); ?></div>
            </div>
        </div>

        <!-- Add Event Form -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Event</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Event Title *</label>
                            <input type="text" name="title" class="form-input" required placeholder="e.g., General Assembly">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date *</label>
                            <input type="date" name="event_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time</label>
                            <input type="text" name="time" class="form-input" placeholder="e.g., 9:00 AM - 12:00 PM">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Venue</label>
                            <input type="text" name="venue" class="form-input" placeholder="e.g., LSPU - Santa Cruz">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Organizer</label>
                            <input type="text" name="organizer" class="form-input" value="IECEP-LSC">
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-textarea" placeholder="Event details..."></textarea>
                        </div>
                    </div>
                    <div style="margin-top: var(--space-4);">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Event
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Events List -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> All Events</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($events)): ?>
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Event Details</th>
                                <th>Venue/Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): 
                                $eventDate = strtotime($event['event_date']);
                                $daysUntil = ceil(($eventDate - time()) / 86400);
                                $isUpcoming = $daysUntil >= 0 && $daysUntil <= 7;
                                $isToday = $daysUntil == 0;
                            ?>
                                <tr>
                                    <td>
                                        <div class="event-date-cell">
                                            <div class="date-box <?php echo $isUpcoming ? 'upcoming' : ''; ?> <?php echo $isToday ? 'today' : ''; ?>">
                                                <span class="day"><?php echo date('d', $eventDate); ?></span>
                                                <span class="month"><?php echo date('M', $eventDate); ?></span>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600;"><?php echo date('l', $eventDate); ?></div>
                                                <div style="font-size: 0.85rem; color: var(--neutral-500);">
                                                    <?php 
                                                    if ($isToday) echo '<span style="color: #10B981; font-weight: 600;">Today</span>';
                                                    elseif ($daysUntil == 1) echo '<span style="color: var(--accent); font-weight: 600;">Tomorrow</span>';
                                                    elseif ($isUpcoming) echo $daysUntil . ' days';
                                                    else echo date('Y', $eventDate);
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--neutral-500);">
                                            <?php echo htmlspecialchars($event['organizer']); ?>
                                        </div>
                                        <?php if ($daysUntil >= 0 && $daysUntil <= 3): ?>
                                            <span class="notification-badge badge-soon">
                                                <i class="fas fa-bell"></i> Soon
                                            </span>
                                        <?php elseif ($daysUntil > 3 && $daysUntil <= 7): ?>
                                            <span class="notification-badge badge-upcoming">
                                                <i class="fas fa-calendar"></i> This Week
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><i class="fas fa-map-marker-alt" style="color: var(--accent);"></i> <?php echo htmlspecialchars($event['venue'] ?? 'TBA'); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--neutral-500);">
                                            <i class="far fa-clock"></i> <?php echo htmlspecialchars($event['time'] ?? 'TBA'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-outline" onclick="editEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this event?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-calendar-alt"></i>
                        <h3>No Events Yet</h3>
                        <p>Add your first event using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Event</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="event_id" id="editEventId">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Event Title *</label>
                            <input type="text" name="title" id="editTitle" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date *</label>
                            <input type="date" name="event_date" id="editDate" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time</label>
                            <input type="text" name="time" id="editTime" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Venue</label>
                            <input type="text" name="venue" id="editVenue" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Organizer</label>
                            <input type="text" name="organizer" id="editOrganizer" class="form-input">
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="editDescription" class="form-textarea"></textarea>
                        </div>
                    </div>
                    <div style="margin-top: var(--space-4);">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal()" style="margin-left: var(--space-3);">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editEvent(event) {
            document.getElementById('editEventId').value = event.id;
            document.getElementById('editTitle').value = event.title;
            document.getElementById('editDate').value = event.event_date;
            document.getElementById('editTime').value = event.time || '';
            document.getElementById('editVenue').value = event.venue || '';
            document.getElementById('editOrganizer').value = event.organizer || 'IECEP-LSC';
            document.getElementById('editDescription').value = event.description || '';
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // Close modal on outside click
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>

