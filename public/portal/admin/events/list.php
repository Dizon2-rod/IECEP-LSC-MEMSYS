<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'committee_registration']);
require_once __DIR__ . '/../../../../includes/csrf.php';

$pageTitle = 'Event Management';
$current_page = 'list';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - IECEP-LSC</title>
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <?= csrf_meta() ?>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-calendar"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <button class="btn btn-primary" onclick="showCreateModal()">
                <i class="fas fa-plus"></i> Create Event
            </button>
        </div>

        <div class="content-card">
            <div class="d-flex gap-2 mb-4">
                <select id="statusFilter" class="form-select w-auto" onchange="loadEvents()">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select id="typeFilter" class="form-select w-auto" onchange="loadEvents()">
                    <option value="">All Types</option>
                    <option value="seminar">Seminar</option>
                    <option value="workshop">Workshop</option>
                    <option value="community">Community</option>
                    <option value="chapter_meeting">Chapter Meeting</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTable">
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="empty-state">
                                    <i class="fas fa-calendar-alt"></i>
                                    <p>Loading events...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create/Edit Event Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Create Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="eventForm" onsubmit="saveEvent(event)">
                    <?= csrf_field() ?>
                    <input type="hidden" id="eventId" name="event_id">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control" id="eventTitle" name="title" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="eventDescription" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Event Type *</label>
                                <select class="form-select" id="eventType" name="event_type" required>
                                    <option value="seminar">Seminar</option>
                                    <option value="workshop">Workshop</option>
                                    <option value="community">Community</option>
                                    <option value="chapter_meeting">Chapter Meeting</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="eventStatus" name="status">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Start Date & Time *</label>
                                <input type="datetime-local" class="form-control" id="eventStart" name="start_datetime" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date & Time *</label>
                                <input type="datetime-local" class="form-control" id="eventEnd" name="end_datetime" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" id="eventLocation" name="location">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Online Link</label>
                                <input type="url" class="form-control" id="eventOnlineLink" name="online_link">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Max Capacity</label>
                                <input type="number" class="form-control" id="eventCapacity" name="max_capacity" min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Registration Deadline</label>
                                <input type="datetime-local" class="form-control" id="eventDeadline" name="registration_deadline">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fee (₱)</label>
                                <input type="number" class="form-control" id="eventFee" name="fee" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <input type="checkbox" id="eventRequiresPayment" name="requires_payment" value="1"> Requires Payment
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Event Detail Modal -->
    <div class="modal fade" id="eventDetailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Event Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="eventDetailContent">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let eventModal, detailModal;

        document.addEventListener('DOMContentLoaded', function() {
            eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
            detailModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
            loadEvents();
        });

        async function loadEvents() {
            const status = document.getElementById('statusFilter').value;
            const type = document.getElementById('typeFilter').value;

            let url = `/api/events.php?action=list`;
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
            const tbody = document.getElementById('eventsTable');

            if (!events || events.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No events found</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = events.map(event => `
                <tr>
                    <td><strong>${escapeHtml(event.title)}</strong></td>
                    <td>${escapeHtml(event.event_type)}</td>
                    <td>${new Date(event.start_datetime).toLocaleString()}</td>
                    <td>${escapeHtml(event.location || 'TBA')}</td>
                    <td><span class="badge ${getStatusBadgeClass(event.status)}">${escapeHtml(event.status)}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewEvent('${event.id}')" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editEvent('${event.id}')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteEvent('${event.id}', '${escapeHtml(event.title)}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function getStatusBadgeClass(status) {
            const classes = {
                'draft': 'badge-warning',
                'published': 'badge-info',
                'completed': 'badge-success',
                'cancelled': 'badge-danger'
            };
            return classes[status] || 'badge-warning';
        }

        function showCreateModal() {
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';
            document.getElementById('eventModalTitle').textContent = 'Create Event';
            eventModal.show();
        }

        async function editEvent(eventId) {
            try {
                const response = await fetch(`/api/events.php?action=get&event_id=${eventId}`);
                const data = await response.json();

                if (data.success && data.event) {
                    const event = data.event;
                    document.getElementById('eventId').value = event.id;
                    document.getElementById('eventTitle').value = event.title || '';
                    document.getElementById('eventDescription').value = event.description || '';
                    document.getElementById('eventType').value = event.event_type || 'seminar';
                    document.getElementById('eventStatus').value = event.status || 'draft';
                    document.getElementById('eventStart').value = event.start_datetime ? event.start_datetime.replace(' ', 'T').substring(0, 16) : '';
                    document.getElementById('eventEnd').value = event.end_datetime ? event.end_datetime.replace(' ', 'T').substring(0, 16) : '';
                    document.getElementById('eventLocation').value = event.location || '';
                    document.getElementById('eventOnlineLink').value = event.online_link || '';
                    document.getElementById('eventCapacity').value = event.max_capacity || '';
                    document.getElementById('eventDeadline').value = event.registration_deadline ? event.registration_deadline.replace(' ', 'T').substring(0, 16) : '';
                    document.getElementById('eventFee').value = event.fee || 0;
                    document.getElementById('eventRequiresPayment').checked = event.requires_payment ? true : false;
                    document.getElementById('eventModalTitle').textContent = 'Edit Event';
                    eventModal.show();
                }
            } catch (error) {
                console.error('Error loading event:', error);
                alert('Error loading event data');
            }
        }

        async function saveEvent(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const eventId = document.getElementById('eventId').value;
            const action = eventId ? 'update' : 'create';

            if (eventId) {
                formData.append('event_id', eventId);
            }
            formData.append('action', action);

            try {
                const response = await fetch(`/api/events.php?action=${action}`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    eventModal.hide();
                    loadEvents();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                alert('Error saving event');
                console.error(error);
            }
        }

        async function deleteEvent(eventId, title) {
            if (!confirm(`Are you sure you want to delete "${title}"?`)) return;

            const formData = new FormData();
            formData.append('event_id', eventId);
            formData.append('action', 'delete');
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const response = await fetch(`/api/events.php?action=delete`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    loadEvents();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                alert('Error deleting event');
                console.error(error);
            }
        }

        async function viewEvent(eventId) {
            try {
                const response = await fetch(`/api/events.php?action=get&event_id=${eventId}`);
                const data = await response.json();

                if (data.success && data.event) {
                    const event = data.event;
                    const content = document.getElementById('eventDetailContent');
                    content.innerHTML = `
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <h4>${escapeHtml(event.title)}</h4>
                                <span class="badge ${getStatusBadgeClass(event.status)}">${escapeHtml(event.status)}</span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Type:</strong><br>
                                ${escapeHtml(event.event_type)}
                            </div>
                            <div class="col-md-6">
                                <strong>Location:</strong><br>
                                ${escapeHtml(event.location || 'TBA')}
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Start:</strong><br>
                                ${new Date(event.start_datetime).toLocaleString()}
                            </div>
                            <div class="col-md-6">
                                <strong>End:</strong><br>
                                ${new Date(event.end_datetime).toLocaleString()}
                            </div>
                        </div>
                        ${event.online_link ? `
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Online Link:</strong><br>
                                <a href="${escapeHtml(event.online_link)}" target="_blank">${escapeHtml(event.online_link)}</a>
                            </div>
                        </div>` : ''}
                        ${event.description ? `
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Description:</strong><br>
                                ${escapeHtml(event.description)}
                            </div>
                        </div>` : ''}
                    `;
                    detailModal.show();
                }
            } catch (error) {
                console.error('Error loading event detail:', error);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
