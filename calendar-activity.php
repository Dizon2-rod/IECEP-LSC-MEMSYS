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
    <title>Calendar of Activities &amp; Events — IECEP-LSC</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #142A6B;
            --accent: #D4AF37;
            --accent-hover: #C5A059;
            --navy-dark: #07122E;
            --slate-50: #F8FAFC;
            --slate-100: #F1F5F9;
            --slate-200: #E2E8F0;
            --slate-600: #475569;
            --slate-800: #1E293B;
            --radius-md: 12px;
            --radius-lg: 18px;
            --radius-full: 9999px;
            --shadow-card: 0 10px 30px -5px rgba(11, 29, 74, 0.08), 0 4px 10px -2px rgba(11, 29, 74, 0.04);
            --shadow-hover: 0 20px 40px -10px rgba(11, 29, 74, 0.18), 0 8px 16px -4px rgba(212, 175, 55, 0.15);
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F8FAFC;
            color: var(--slate-800);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Page Hero ────────────────────────────────────────── */
        .page-hero {
            position: relative;
            background: linear-gradient(135deg, #07122E 0%, #0B1D4A 55%, #142A6B 100%);
            color: #FFFFFF;
            padding: 120px 1.5rem 60px;
            text-align: center;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 80% 20%, rgba(212, 175, 55, 0.15) 0%, transparent 60%),
                        radial-gradient(circle at 20% 80%, rgba(30, 58, 138, 0.3) 0%, transparent 50%);
            pointer-events: none;
        }
        .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 820px;
            margin: 0 auto;
        }
        .hero-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: clamp(2.2rem, 4.5vw, 3.2rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
            color: #FFFFFF;
        }
        .hero-title span {
            background: linear-gradient(135deg, #FFE89E 0%, #D4AF37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.65;
            max-width: 680px;
            margin: 0 auto;
        }

        /* ── Main Container ───────────────────────────────────── */
        .calendar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3.5rem 1.5rem 5rem;
            flex: 1;
            width: 100%;
        }

        /* ── Grid Layout ──────────────────────────────────────── */
        .calendar-layout-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2.5rem;
        }
        @media (min-width: 992px) {
            .calendar-layout-grid {
                grid-template-columns: 1.35fr 0.9fr;
            }
        }

        /* ── Month & Event Cards ──────────────────────────────── */
        .section-box-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.25rem;
        }

        .month-header-tag {
            display: inline-block;
            color: #D4AF37;
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin: 1.5rem 0 0.85rem;
        }
        .month-header-tag:first-of-type {
            margin-top: 0;
        }

        .event-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 1.4rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1.25rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        .event-card:hover {
            transform: translateY(-4px);
            border-color: rgba(212, 175, 55, 0.4);
            box-shadow: var(--shadow-hover);
        }

        /* Date Block */
        .event-date-block {
            width: 72px;
            height: 76px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #0B1D4A 0%, #142A6B 100%);
            color: #FFFFFF;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.15);
            border: 1px solid rgba(212, 175, 55, 0.3);
        }
        .event-date-day {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
            color: #F8E7A2;
        }
        .event-date-month {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 0.15rem;
        }

        /* Event Info */
        .event-info {
            flex: 1;
        }
        .event-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.4rem;
            line-height: 1.35;
        }
        .event-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 0.65rem;
            font-size: 0.85rem;
            color: var(--slate-600);
        }
        .event-desc {
            color: var(--slate-600);
            font-size: 0.88rem;
            line-height: 1.55;
            margin: 0;
        }

        /* ── Sidebar Cards ────────────────────────────────────── */
        .sidebar-card {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            border: 1px solid var(--slate-200);
            box-shadow: var(--shadow-card);
            padding: 1.75rem;
            margin-bottom: 2rem;
        }
        .calendar-frame-wrap {
            width: 100%;
            height: 480px;
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--slate-200);
            margin-top: 1rem;
        }
        .calendar-frame-wrap iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .sync-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0B1D4A 0%, #142A6B 100%);
            color: #FFFFFF;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
        }
        .sync-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(11, 29, 74, 0.25);
            background: linear-gradient(135deg, #142A6B 0%, #1E3A8A 100%);
        }

        /* Responsive */
        @media (max-width: 640px) {
            .event-card {
                flex-direction: column;
            }
            .event-date-block {
                width: 100%;
                height: 48px;
                flex-direction: row;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════════════════════ Hero -->
    <header class="page-hero">
        <div class="hero-inner">
            <h1 class="hero-title">
                Calendar of <span>Activities &amp; Events</span>
            </h1>
            <p class="hero-desc">
                Stay updated with regional conventions, technical innovation summits, leadership seminars, and chapter accreditation deadlines.
            </p>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════ Main Calendar -->
    <main class="calendar-container">
        <div class="calendar-layout-grid">
            <!-- Left Column: Events Timeline -->
            <div>
                <h2 class="section-box-title">Upcoming Schedule</h2>

                <?php if (!empty($groupedEvents)): ?>
                    <?php foreach ($groupedEvents as $month => $monthEvents): ?>
                        <div class="month-header-tag">
                            <?php echo htmlspecialchars($month); ?>
                        </div>

                        <?php foreach ($monthEvents as $event): 
                            $dateObj = strtotime($event['event_date']);
                            $day = date('d', $dateObj);
                            $monthShort = date('M', $dateObj);
                            $venue = !empty($event['venue']) ? htmlspecialchars($event['venue']) : 'Laguna / Virtual';
                            $time = !empty($event['event_time']) ? htmlspecialchars($event['event_time']) : '';
                        ?>
                            <article class="event-card">
                                <div class="event-date-block">
                                    <span class="event-date-day"><?php echo $day; ?></span>
                                    <span class="event-date-month"><?php echo $monthShort; ?></span>
                                </div>
                                <div class="event-info">
                                    <h3 class="event-title"><?php echo htmlspecialchars($event['title'] ?? 'Chapter Event'); ?></h3>
                                    <div class="event-meta-row">
                                        <span><?php echo $venue; ?></span>
                                        <?php if ($time): ?>
                                            <span>• <?php echo $time; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($event['description'])): ?>
                                        <p class="event-desc"><?php echo htmlspecialchars($event['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Default Upcoming Events if DB empty -->
                    <div class="month-header-tag">
                        Academic Year 2026–2027
                    </div>

                    <article class="event-card">
                        <div class="event-date-block">
                            <span class="event-date-day">15</span>
                            <span class="event-date-month">Sep</span>
                        </div>
                        <div class="event-info">
                            <h3 class="event-title">Annual Institutional Affiliation Renewal Deadline</h3>
                            <div class="event-meta-row">
                                <span>IECEP-LSC Portal</span>
                                <span>• 11:59 PM PST</span>
                            </div>
                            <p class="event-desc">Accreditation period closing for all Higher Education Institutions in Laguna offering ECE and ECT degree curricula.</p>
                        </div>
                    </article>

                    <article class="event-card">
                        <div class="event-date-block">
                            <span class="event-date-day">24</span>
                            <span class="event-date-month">Oct</span>
                        </div>
                        <div class="event-info">
                            <h3 class="event-title">IECEP-LSC Regional Student Convention 2026</h3>
                            <div class="event-meta-row">
                                <span>Laguna Provincial Capitol Cultural Center</span>
                                <span>• 8:00 AM – 5:00 PM</span>
                            </div>
                            <p class="event-desc">The flagship gathering of engineering students, research symposiums, technical quiz bowl, and robotics innovation challenges.</p>
                        </div>
                    </article>

                    <article class="event-card">
                        <div class="event-date-block">
                            <span class="event-date-day">12</span>
                            <span class="event-date-month">Nov</span>
                        </div>
                        <div class="event-info">
                            <h3 class="event-title">TechX &amp; IoT Embedded Systems Masterclass</h3>
                            <div class="event-meta-row">
                                <span>Virtual (Zoom / Live Stream)</span>
                                <span>• 1:00 PM – 4:30 PM</span>
                            </div>
                            <p class="event-desc">Hands-on microcontrollers, RF protocols, firmware debugging, and smart sensing workshop led by certified industry engineers.</p>
                        </div>
                    </article>
                <?php endif; ?>
            </div>

            <!-- Right Column: Sync & Google Calendar -->
            <div>
                <div class="sidebar-card">
                    <h3 class="section-box-title" style="font-size:1.25rem;">Sync to Calendar</h3>
                    <p style="font-size:0.88rem; color:var(--slate-600); margin-bottom:1.25rem; line-height:1.5;">
                        Export the official IECEP-LSC calendar events directly into Google Calendar, Apple Calendar, or Microsoft Outlook via iCal (.ics).
                    </p>
                    <button type="button" class="sync-action-btn" onclick="syncToGoogleCalendar()">
                        Download iCal File (.ics)
                    </button>
                </div>

                <div class="sidebar-card">
                    <h3 class="section-box-title" style="font-size:1.25rem;">Live Schedule Feed</h3>
                    <p style="font-size:0.85rem; color:var(--slate-600); margin-bottom:0.75rem;">
                        Interactive Google Calendar schedule feed:
                    </p>
                    <div class="calendar-frame-wrap">
                        <iframe 
                            src="https://calendar.google.com/calendar/embed?src=primary&ctz=Asia%2FManila&showPrint=0&showTabs=0&showCalendars=0&showTz=0&mode=MONTH"
                            loading="lazy">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const eventsData = <?php echo json_encode($events); ?>;
        function syncToGoogleCalendar() {
            let icsContent = 'BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//IECEP-LSC//Calendar//EN\n';
            if (eventsData && eventsData.length > 0) {
                eventsData.forEach(event => {
                    const date = (event.event_date || '').replace(/-/g, '');
                    icsContent += `BEGIN:VEVENT\nDTSTART;VALUE=DATE:${date}\nDTEND;VALUE=DATE:${date}\nSUMMARY:${event.title}\nDESCRIPTION:${event.description || ''}\nLOCATION:${event.venue || 'TBA'}\nEND:VEVENT\n`;
                });
            } else {
                icsContent += 'BEGIN:VEVENT\nDTSTART;VALUE=DATE:20261024\nDTEND;VALUE=DATE:20261025\nSUMMARY:IECEP-LSC Regional Student Convention 2026\nDESCRIPTION:Flagship student convention\nLOCATION:Laguna\nEND:VEVENT\n';
            }
            icsContent += 'END:VCALENDAR';
            const blob = new Blob([icsContent], { type: 'text/calendar' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'iecep-lsc-calendar.ics';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
        }
    </script>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>
