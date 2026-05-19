<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth_check.php';
require_role(['member', 'admin', 'super_admin', 'school_officer']);
require_once __DIR__ . '/../../../includes/csrf.php';

$pageTitle = 'My Event Registrations';
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
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-ticket-alt"></i> <?= $pageTitle ?></h1>
            <a href="events.php" class="btn btn-secondary">
                <i class="fas fa-calendar"></i> Browse Events
            </a>
        </div>

        <div id="registrationsContainer"></div>
    </div>

    <script>
        const API_BASE = '/IECEP-LSC-MEMSYS/public/api';
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        async function loadRegistrations() {
            try {
                const response = await fetch(`${API_BASE}/event-registration.php?action=my_registrations`);
                const data = await response.json();
                
                if (data.success) {
                    displayRegistrations(data.registrations);
                }
            } catch (error) {
                console.error('Error loading registrations:', error);
            }
        }

        async function displayRegistrations(registrations) {
            const container = document.getElementById('registrationsContainer');
            
            if (!registrations || registrations.length === 0) {
                container.innerHTML = '<p class="no-data">No event registrations found</p>';
                return;
            }

            // Get event details for each registration
            const registrationsWithEvents = await Promise.all(
                registrations.map(async (reg) => {
                    try {
                        const response = await fetch(`${API_BASE}/events.php?action=get&event_id=${reg.event_id}`);
                        const data = await response.json();
                        return { ...reg, event: data.event };
                    } catch {
                        return { ...reg, event: null };
                    }
                })
            );

            container.innerHTML = registrationsWithEvents.map((reg, index) => {
                const event = reg.event;
                if (!event) return '';

                const qrId = `qr-${index}`;
                
                return `
                    <div class="registration-card">
                        <div class="registration-header">
                            <h3>${event.title}</h3>
                            <span class="badge badge-${reg.status}">${reg.status}</span>
                        </div>
                        <div class="registration-details">
                            <p><i class="fas fa-calendar"></i> ${new Date(event.start_datetime).toLocaleString()}</p>
                            <p><i class="fas fa-map-marker-alt"></i> ${event.location || 'TBA'}</p>
                            ${reg.payment_status !== 'waived' ? `<p><i class="fas fa-money-bill"></i> Payment: ${reg.payment_status}</p>` : ''}
                            ${reg.checked_in_at ? `<p class="checked-in"><i class="fas fa-check-circle"></i> Checked in: ${new Date(reg.checked_in_at).toLocaleString()}</p>` : ''}
                        </div>
                        ${reg.status === 'registered' && !reg.checked_in_at ? `
                            <div class="qr-section">
                                <p><strong>Show this QR code at check-in:</strong></p>
                                <div id="${qrId}" class="qr-code"></div>
                                <p class="qr-token">${reg.qr_token}</p>
                            </div>
                        ` : ''}
                        ${reg.status === 'registered' && !reg.checked_in_at ? `
                            <button class="btn btn-danger btn-sm" onclick="cancelRegistration('${reg.event_id}')">
                                Cancel Registration
                            </button>
                        ` : ''}
                    </div>
                `;
            }).join('');

            // Generate QR codes
            registrationsWithEvents.forEach((reg, index) => {
                if (reg.status === 'registered' && !reg.checked_in_at && reg.qr_token) {
                    const qrId = `qr-${index}`;
                    const qrData = JSON.stringify({
                        event_id: reg.event_id,
                        qr_token: reg.qr_token
                    });
                    
                    new QRCode(document.getElementById(qrId), {
                        text: qrData,
                        width: 200,
                        height: 200
                    });
                }
            });
        }

        async function cancelRegistration(eventId) {
            if (!confirm('Cancel this registration?')) return;

            const formData = new FormData();
            formData.append('action', 'cancel');
            formData.append('event_id', eventId);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch(`${API_BASE}/event-registration.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert('Registration cancelled');
                    loadRegistrations();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error cancelling registration');
                console.error(error);
            }
        }

        loadRegistrations();
    </script>

    <style>
        .registration-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .registration-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .qr-section {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .qr-code {
            display: inline-block;
            margin: 10px 0;
        }
        .qr-token {
            font-family: monospace;
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
        }
        .checked-in {
            color: #10b981;
            font-weight: 600;
        }
    </style>
</body>
</html>
