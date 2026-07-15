<?php
require_once __DIR__ . '/bootstrap.php';

$supabase = getSupabaseClient();
$events = [];

if ($supabase) {
    try {
        $result = $supabase->select('calendar_activities');
        $events = is_array($result) ? $result : [];
    } catch (Exception $e) {
        error_log("Calendar Load Error: " . $e->getMessage());
        $events = [];
    }
}

// Ensure all events are arrays
$events = array_filter($events, function($event) {
    return is_array($event) && isset($event['event_date']);
});

// Sort events by date
usort($events, function($a, $b) {
    return strtotime($a['event_date']) - strtotime($b['event_date']);
});

// Group events by month
$groupedEvents = [];
foreach ($events as $event) {
    if (is_array($event) && isset($event['event_date'])) {
        $monthYear = date('F Y', strtotime($event['event_date']));
        if (!isset($groupedEvents[$monthYear])) {
            $groupedEvents[$monthYear] = [];
        }
        $groupedEvents[$monthYear][] = $event;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Activity - IECEP-LSC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-5: 20px;
            --space-6: 24px;
            --space-8: 32px;
            --space-12: 48px;
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-full: 9999px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --transition-base: 0.3s ease;
            --transition-fast: 0.2s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; color: var(--neutral-900); background: var(--neutral-100); line-height: 1.6; padding-top: 64px; }
        
        .header { background: var(--white); border-bottom: 1px solid var(--neutral-200); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }
        .header-container { max-width: 1200px; margin: 0 auto; padding: 0 var(--space-4); display: flex; align-items: center; justify-content: space-between; height: 64px; width: 100%; }
        .logo { display: flex; align-items: center; gap: var(--space-2); text-decoration: none; color: var(--primary); font-weight: 700; flex-shrink: 0; }
        .logo img { width: 40px; height: 40px; }
        .nav { flex: 1; display: flex; align-items: center; justify-content: center; }
        .nav-links { display: flex; list-style: none; gap: var(--space-4); align-items: center; }
        .nav-link { color: var(--neutral-700); text-decoration: none; font-weight: 500; padding: var(--space-2) var(--space-3); border-radius: var(--radius-md); transition: all var(--transition-base); display: flex; align-items: center; gap: var(--space-2); white-space: nowrap; }
        .nav-link:hover { color: var(--primary); background: var(--neutral-100); }
        .btn-login { padding: var(--space-2) var(--space-4); background: transparent; border: 2px solid var(--primary); color: var(--primary); text-decoration: none; border-radius: var(--radius-full); font-weight: 600; transition: all var(--transition-base); flex-shrink: 0; }
        .btn-login:hover { background: var(--primary); color: var(--white); }
        
        .nav-item { position: relative; list-style: none; }
        .dropdown-menu { 
            position: absolute; 
            top: calc(100% + 8px); 
            left: 0; 
            background: var(--white); 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.15); 
            min-width: 240px; 
            opacity: 0; 
            visibility: hidden; 
            transform: translateY(-8px); 
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
            z-index: 1001; 
            padding: 8px; 
            list-style: none;
        }
        .dropdown-menu::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 24px;
            width: 12px;
            height: 12px;
            background: var(--white);
            transform: rotate(45deg);
            border-radius: 2px;
        }
        .nav-item:hover > .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-menu li { list-style: none; }
        .dropdown-item { 
            display: block;
            padding: 12px 16px; 
            color: var(--neutral-700); 
            text-decoration: none; 
            border-radius: 8px; 
            transition: all 0.2s ease; 
            white-space: nowrap; 
            font-size: 0.9rem;
            font-weight: 500;
        }
        .dropdown-item:hover { 
            background: linear-gradient(135deg, var(--neutral-100) 0%, #F8FAFC 100%); 
            color: var(--primary); 
        }
        
        .page-header { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: var(--white); padding: var(--space-12) 0; text-align: center; }
        .page-header h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: var(--space-2); }
        .page-header p { font-size: 1.1rem; opacity: 0.9; }
        
        .calendar-container { max-width: 1000px; margin: 0 auto; padding: var(--space-8) var(--space-4); }
        .month-section { margin-bottom: var(--space-8); }
        .month-title { font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-bottom: var(--space-4); padding-bottom: var(--space-2); border-bottom: 2px solid var(--accent); display: inline-block; }
        
        .event-list { display: flex; flex-direction: column; gap: var(--space-4); }
        .event-item { display: flex; gap: var(--space-4); background: var(--white); border-radius: var(--radius-lg); padding: var(--space-5); box-shadow: var(--shadow-sm); border: 1px solid var(--neutral-200); transition: all var(--transition-base); }
        .event-item:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); border-color: var(--accent); }
        
        .event-date-box { display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 80px; height: 80px; background: var(--primary); color: var(--white); border-radius: var(--radius-md); text-align: center; flex-shrink: 0; }
        .event-day { font-size: 2rem; font-weight: 800; line-height: 1; }
        .event-month { font-size: 0.875rem; font-weight: 600; text-transform: uppercase; margin-top: var(--space-1); }
        
        .event-content { flex: 1; }
        .event-title { font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: var(--space-2); }
        .event-meta { display: flex; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-3); font-size: 0.9rem; color: var(--neutral-500); }
        .event-meta span { display: flex; align-items: center; gap: var(--space-2); }
        .event-meta i { color: var(--accent); }
        
        .upcoming-section { background: var(--white); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-8); box-shadow: var(--shadow-sm); }
        .upcoming-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-3); }
        .upcoming-header h3 { font-size: 1.25rem; color: var(--primary); display: flex; align-items: center; gap: var(--space-2); }
        .notification-badge { display: inline-flex; align-items: center; gap: var(--space-1); padding: var(--space-1) var(--space-3); background: #FEE2E2; color: #DC2626; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; }
        .notification-badge.upcoming { background: #DBEAFE; color: #1E40AF; }
        
        .google-calendar-section { background: var(--white); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-8); box-shadow: var(--shadow-sm); }
        .google-calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-3); }
        .google-calendar-header h3 { font-size: 1.25rem; color: var(--primary); display: flex; align-items: center; gap: var(--space-2); }
        .sync-btn { padding: var(--space-2) var(--space-4); background: #4285F4; color: var(--white); border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: var(--space-2); transition: background 0.3s; }
        .sync-btn:hover { background: #3367D6; }
        .add-event-btn { padding: var(--space-2) var(--space-4); background: var(--accent); color: var(--primary); border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: var(--space-2); transition: all 0.3s; }
        .add-event-btn:hover { background: var(--accent-hover); }
        .google-calendar-frame { width: 100%; height: 600px; border: none; border-radius: var(--radius-md); }
        
        .footer { background: var(--neutral-900); color: var(--white); padding: var(--space-8) 0 var(--space-4); margin-top: var(--space-12); }
        .footer-grid { display: grid; grid-template-columns: 1fr; gap: var(--space-6); max-width: 1200px; margin: 0 auto; padding: 0 var(--space-4); }
        @media (min-width: 640px) { .footer-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 992px) { .footer-grid { grid-template-columns: repeat(4, 1fr); } }
        .footer-col h4 { font-size: 1.125rem; font-weight: 700; margin-bottom: var(--space-4); color: var(--accent); }
        .footer-col p { color: var(--neutral-500); font-size: 0.9rem; margin-bottom: var(--space-3); }
        .footer-brand { display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-4); }
        .footer-brand img { width: 48px; height: 48px; object-fit: contain; }
        .footer-brand h4 { color: var(--accent); font-size: 1rem; margin: 0; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: var(--space-2); }
        .footer-links a { color: var(--neutral-500); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
        .footer-links a:hover { color: var(--accent); }
        .footer-social { display: flex; flex-direction: column; gap: var(--space-2); }
        .footer-social a { color: var(--neutral-500); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; display: flex; align-items: center; gap: var(--space-2); }
        .footer-social a:hover { color: var(--accent); }
        .footer-bottom { text-align: center; border-top: 1px solid var(--neutral-700); padding-top: var(--space-4); margin-top: var(--space-6); color: var(--neutral-500); font-size: 0.9rem; }
        
        @media (max-width: 768px) {
            .page-header h1 { font-size: 2rem; }
            .event-item { flex-direction: column; }
            .event-date-box { width: 100%; flex-direction: row; gap: var(--space-3); height: auto; padding: var(--space-3); }
        }
    </style>
</head>
<body>
    <!-- Header (Nasa loob ng navbar.php ang mobile menu overlay na dapat mong burahin) -->
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="page-header">
        <h1>Calendar Activity</h1>
        <p>Stay updated with our upcoming events, seminars, and activities</p>
    </section>

    <div class="calendar-container">
        <div class="upcoming-section">
            <div class="upcoming-header">
                <h3><i class="fas fa-bell"></i> Upcoming Events</h3>
                <?php 
                $upcomingCount = 0;
                $notifiedEvents = [];
                foreach ($events as $event) {
                    if (!is_array($event) || !isset($event['event_date'])) continue;
                    $eventDate = strtotime($event['event_date']);
                    $daysUntil = ceil(($eventDate - time()) / 86400);
                    if ($daysUntil >= 0 && $daysUntil <= 7) {
                        $upcomingCount++;
                        $notifiedEvents[] = ['title' => $event['title'] ?? '', 'days' => $daysUntil, 'date' => $event['event_date']];
                    }
                }
                if ($upcomingCount > 0): 
                ?>
                <span class="notification-badge <?php echo $upcomingCount > 3 ? '' : 'upcoming'; ?>">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $upcomingCount; ?> event<?php echo $upcomingCount > 1 ? 's' : ''; ?> this week
                </span>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($notifiedEvents)): ?>
            <div class="event-list" style="margin-bottom: var(--space-6);">
                <?php foreach (array_slice($notifiedEvents, 0, 5) as $evt): 
                    if (!is_array($evt)) continue;
                    $daysText = $evt['days'] == 0 ? 'Today' : ($evt['days'] == 1 ? 'Tomorrow' : $evt['days'] . ' days');
                ?>
                <div class="event-item" style="border-left: 4px solid <?php echo $evt['days'] <= 2 ? '#DC2626' : ($evt['days'] <= 5 ? '#F59E0B' : '#3B82F6'); ?>;">
                    <div class="event-date-box" style="background: <?php echo $evt['days'] <= 2 ? '#DC2626' : ($evt['days'] <= 5 ? '#F59E0B' : '#3B82F6'); ?>;">
                        <span class="event-day"><?php echo date('d', strtotime($evt['date'])); ?></span>
                        <span class="event-month"><?php echo date('M', strtotime($evt['date'])); ?></span>
                    </div>
                    <div class="event-content">
                        <h4 class="event-title"><?php echo htmlspecialchars($evt['title']); ?></h4>
                        <p style="color: var(--neutral-500); font-size: 0.9rem;">
                            <i class="fas fa-clock" style="color: var(--accent);"></i> <?php echo $daysText; ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color: var(--neutral-500); text-align: center; padding: var(--space-4);">No upcoming events in the next 7 days.</p>
            <?php endif; ?>
        </div>

        <div class="google-calendar-section">
            <div class="google-calendar-header">
                <h3><i class="fab fa-google"></i> Google Calendar</h3>
                <div style="display: flex; gap: var(--space-3);">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'creatives'): ?>
                    <button class="add-event-btn" onclick="window.location.href='<?php echo PORTAL_URL; ?>/creatives/manage-calendar.php'">
                        <i class="fas fa-plus"></i> Add Event
                    </button>
                    <?php endif; ?>
                    <button class="sync-btn" onclick="syncToGoogleCalendar()">
                        <i class="fas fa-sync-alt"></i> Sync
                    </button>
                </div>
            </div>
            <p style="color: var(--neutral-500); margin-bottom: var(--space-4);">
                All events are synced with Google Calendar.
            </p>
            <iframe 
                class="google-calendar-frame"
                src="https://calendar.google.com/calendar/embed?src=primary&ctz=Asia%2FManila&showPrint=0&showTabs=0&showCalendars=0&showTz=0&mode=MONTH"
                frameborder="0"
                scrolling="no">
            </iframe>
        </div>
    </div>

    <script>
        const eventsData = <?php echo json_encode($events); ?>;
        function syncToGoogleCalendar() {
            let icsContent = 'BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//IECEP-LSC//Calendar//EN\n';
            eventsData.forEach(event => {
                const date = event.event_date.replace(/-/g, '');
                icsContent += `BEGIN:VEVENT\nDTSTART;VALUE=DATE:${date}\nDTEND;VALUE=DATE:${date}\nSUMMARY:${event.title}\nDESCRIPTION:${event.description || ''}\nLOCATION:${event.venue || 'TBA'}\nEND:VEVENT\n`;
            });
            icsContent += 'END:VCALENDAR';
            const blob = new Blob([icsContent], { type: 'text/calendar' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'iecep-lsc-events.ics';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            alert('Calendar file downloaded!');
        }
    </script>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>
