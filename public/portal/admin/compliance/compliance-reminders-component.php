<?php
/**
 * Automated Compliance Reminders Component
 * Include this in the compliance dashboard to display the automated compliance reminders section
 */
?>

<!-- Automated Compliance Reminders Section -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="summary-card" style="background: linear-gradient(135deg, #D4AF37 0%, #F5A623 100%); color: white;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-2"><i class="fas fa-bell me-2"></i>Automated Compliance Reminders</h5>
                    <p class="mb-0 opacity-75">Automatically sends compliance reminders to institutions based on participation rates and deadlines</p>
                </div>
                <div class="text-end">
                    <div class="mb-2">
                        <span class="badge bg-light text-dark" id="compliance-reminder-status">
                            <i class="fas fa-check-circle me-1"></i>System Active
                        </span>
                    </div>
                    <div class="small opacity-75">
                        Next scheduled run: <span id="compliance-reminder-next-run">Tomorrow, 9:00 AM</span>
                    </div>
                    <button class="btn btn-light btn-sm mt-2" onclick="triggerComplianceReminders()">
                        <i class="fas fa-paper-plane me-1"></i>Send Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compliance Reminder Settings -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="table-container">
            <div style="padding: 1.5rem;">
                <h6 class="mb-3"><i class="fas fa-cog me-2"></i>Reminder Configuration</h6>
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Participation Rate Threshold (%)</label>
                            <input type="number" class="form-control" id="participation-threshold" value="75" min="0" max="100">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Reminder Frequency</label>
                            <select class="form-select" id="reminder-frequency">
                                <option value="weekly">Weekly</option>
                                <option value="biweekly">Bi-weekly</option>
                                <option value="monthly" selected>Monthly</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Reminder Schedule</label>
                            <input type="time" class="form-control" id="reminder-schedule" value="09:00">
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm" onclick="saveReminderSettings()">
                    <i class="fas fa-save me-1"></i>Save Settings
                </button>
            </div>
        </div>
    </div>
</div>

<script>
async function triggerComplianceReminders() {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
    
    try {
        const response = await fetch('/api/cron/compliance-deadline-reminders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('compliance-reminder-next-run').textContent = 'Just sent';
            alert('Compliance reminders sent successfully!\n\n' + 
                  'Institutions notified: ' + (result.institutions_notified || 0) + '\n' +
                  'Emails sent: ' + (result.emails_sent || 0));
        } else {
            alert('Failed to send compliance reminders: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Compliance reminders error:', error);
        alert('Error sending compliance reminders: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send Now';
    }
}

async function saveReminderSettings() {
    const settings = {
        participation_threshold: document.getElementById('participation-threshold').value,
        reminder_frequency: document.getElementById('reminder-frequency').value,
        reminder_schedule: document.getElementById('reminder-schedule').value
    };
    
    try {
        const response = await fetch('/api/compliance-reminder-settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify(settings)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Reminder settings saved successfully!');
        } else {
            alert('Failed to save settings: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Save settings error:', error);
        alert('Error saving settings: ' + error.message);
    }
}

// Update next run time from server
async function updateComplianceReminderStatus() {
    try {
        const response = await fetch('/api/cron/compliance-deadline-reminders.php?action=status');
        const result = await response.json();
        
        if (result.success && result.next_run) {
            const nextRun = new Date(result.next_run);
            document.getElementById('compliance-reminder-next-run').textContent = nextRun.toLocaleString();
        }
    } catch (error) {
        console.error('Error updating compliance reminder status:', error);
    }
}

// Initialize status on page load
document.addEventListener('DOMContentLoaded', updateComplianceReminderStatus);
</script>
