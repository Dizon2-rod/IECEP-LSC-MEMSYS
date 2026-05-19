<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'committee_registration']);
require_once __DIR__ . '/../../../includes/csrf.php';

$pageTitle = 'Event Management';
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
            <button class="btn btn-primary" onclick="showCreateModal()">
                <i class="fas fa-plus"></i> Create Event
            </button>
        </div>

        <div class="filters">
            <select id="statusFilter" onchange="loadEvents()">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select id="typeFilter" onchange="loadEvents()">
                <option value="">All Types</option>
                <option value="seminar">Seminar</option>
                <option value="workshop">Workshop</option>
                <option value="community">Community</option>
                <option value="chapter_meeting">Chapter Meeting</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div id="eventsContainer" class="events-grid"></div>
    </div>

    <!-- Create Event Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Create Event</h2>
            <form id="createEventForm" onsubmit="createEvent(event)">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Event Type *</label>
                        <select name="event_type" required>
                            <option value="seminar">Seminar</option>
                            <option value="workshop">Workshop</option>
                            <option value="community">Community</option>
                            <option value="chapter_meeting">Chapter Meeting</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date & Time *</label>
                        <input type="datetime-local" name="start_datetime" required>
                    </div>
                    <div class="form-group">
                        <label>End Date & Time *</label>
                        <input type="datetime-local" name="end_datetime" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_online" value="1"> Online Event
                    </label>
                </div>
                <div class="form-group">
                    <label>Online Link</label>
                    <input type="url" name="online_link">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Max Capacity</label>
                        <input type="number" name="max_capacity" min="1">
                    </div>
                    <div class="form-group">
                        <label>Registration Deadline</label>
                        <input type="datetime-local" name="registration_deadline">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fee (₱)</label>
                        <input type="number" name="fee" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="requires_payment" value="1"> Requires Payment
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Event</button>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = '/IECEP-LSC-MEMSYS/public/api';
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        async function loadEvents() {
            const status = document.getElementById('statusFilter').value;
            const type = document.getElementById('typeFilter').value;
            
            let url = `${API_BASE}/events.php?action=list`;
            if (status) url += `&status=${status}`;
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
                container.innerHTML = '<p class="no-data">No events found</p>';
                return;
            }

            container.innerHTML = events.map(event => `
                <div class="event-card">
                    <div class="event-header">
                        <h3>${event.title}</h3>
                        <span class="badge badge-${event.status}">${event.status}</span>
                    </div>
                    <p class="event-type"><i class="fas fa-tag"></i> ${event.event_type}</p>
                    <p class="event-date"><i class="fas fa-calendar"></i> ${new Date(event.start_datetime).toLocaleString()}</p>
                    <p class="event-location"><i class="fas fa-map-marker-alt"></i> ${event.location || 'TBA'}</p>
                    <div class="event-actions">
                        <a href="event-detail.php?id=${event.id}" class="btn btn-sm">View Details</a>
                    </div>
                </div>
            `).join('');
        }

        function showCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('createModal').style.display = 'none';
        }

        async function createEvent(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'create');

            try {
                const response = await fetch(`${API_BASE}/events.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Event created successfully!');
                    closeModal();
                    loadEvents();
                    e.target.reset();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error creating event');
                console.error(error);
            }
        }

        // Load events on page load
        loadEvents();
    </script>
</body>
</html>
