<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth_check.php';
require_role(['member', 'admin', 'super_admin', 'school_officer']);
require_once __DIR__ . '/../../../includes/csrf.php';

$pageTitle = 'Events';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - IECEP-LSC</title>
    <?= csrf_meta() ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/portal.css">
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-calendar"></i> <?= $pageTitle ?></h1>
            <a href="my-events.php" class="btn btn-secondary">
                <i class="fas fa-ticket-alt"></i> My Registrations
            </a>
        </div>

        <div class="filters">
            <select id="typeFilter" onchange="loadEvents()">
                <option value="">All Types</option>
                <option value="seminar">Seminar</option>
                <option value="workshop">Workshop</option>
                <option value="community">Community</option>
                <option value="chapter_meeting">Chapter Meeting</option>
            </select>
        </div>

        <div id="eventsContainer" class="events-grid"></div>
    </div>

    <script>
        const API_BASE = '/IECEP-LSC-MEMSYS/public/api';
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        async function loadEvents() {
            const type = document.getElementById('typeFilter').value;
            
            let url = `${API_BASE}/events.php?action=list&status=published`;
            if (type) url += `&event_type=${type}`;

            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayEvents(data.events);
                }
            } catch (error) {
                console.error('Error loading events:', error);
            }
        }

        function displayEvents(events) {
            const container = document.getElementById('eventsContainer');
            
            if (!events || events.length === 0) {
                container.innerHTML = '<p class="no-data">No upcoming events</p>';
                return;
            }

            container.innerHTML = events.map(event => `
                <div class="event-card">
                    <div class="event-header">
                        <h3>${event.title}</h3>
                        <span class="badge badge-${event.event_type}">${event.event_type}</span>
                    </div>
                    <p class="event-description">${event.description || ''}</p>
                    <p class="event-date"><i class="fas fa-calendar"></i> ${new Date(event.start_datetime).toLocaleString()}</p>
                    <p class="event-location"><i class="fas fa-map-marker-alt"></i> ${event.location || 'TBA'}</p>
                    ${event.fee > 0 ? `<p class="event-fee"><i class="fas fa-money-bill"></i> ₱${parseFloat(event.fee).toFixed(2)}</p>` : ''}
                    ${event.max_capacity ? `<p class="event-capacity"><i class="fas fa-users"></i> Max: ${event.max_capacity}</p>` : ''}
                    <div class="event-actions">
                        <button class="btn btn-primary" onclick="registerForEvent('${event.id}')">
                            <i class="fas fa-check"></i> Register
                        </button>
                    </div>
                </div>
            `).join('');
        }

        async function registerForEvent(eventId) {
            if (!confirm('Register for this event?')) return;

            const formData = new FormData();
            formData.append('action', 'register');
            formData.append('event_id', eventId);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch(`${API_BASE}/event-registration.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert(data.status === 'waitlisted' ? 
                        'You have been added to the waitlist!' : 
                        'Registration successful! Check My Registrations for your QR code.');
                    loadEvents();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error registering for event');
                console.error(error);
            }
        }

        loadEvents();
    </script>
</body>
</html>
