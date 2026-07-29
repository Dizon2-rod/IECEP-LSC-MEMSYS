<?php
/**
 * Automated Fee Recalculation Component
 * Include this in the financial dashboard to display the automated fee recalculation section
 */
?>

<!-- Automated Fee Recalculation Section -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="metric-card" style="background: linear-gradient(135deg, #0B1D4A 0%, #1E3A6E 100%); color: white;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-2"><i class="fas fa-calculator me-2"></i>Automated Fee Recalculation</h5>
                    <p class="mb-0 opacity-75">Automatically calculates and adjusts fees based on member count brackets and compliance status</p>
                </div>
                <div class="text-end">
                    <div class="mb-2">
                        <span class="badge bg-success" id="fee-recalc-status">
                            <i class="fas fa-check-circle me-1"></i>System Active
                        </span>
                    </div>
                    <div class="small opacity-75">
                        Last run: <span id="fee-recalc-last-run">Today, 10:30 AM</span>
                    </div>
                    <button class="btn btn-light btn-sm mt-2" onclick="triggerFeeRecalculation()">
                        <i class="fas fa-sync-alt me-1"></i>Run Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function triggerFeeRecalculation() {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Running...';
    
    try {
        const response = await fetch('/api/cron/auto-adjust-fees.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('fee-recalc-last-run').textContent = 'Just now';
            alert('Fee recalculation completed successfully!\n\n' + 
                  'Institutions processed: ' + (result.institutions_processed || 0) + '\n' +
                  'Fees adjusted: ' + (result.fees_adjusted || 0));
        } else {
            alert('Fee recalculation failed: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Fee recalculation error:', error);
        alert('Error running fee recalculation: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Run Now';
    }
}

// Update last run time from server
async function updateFeeRecalcStatus() {
    try {
        const response = await fetch('/api/cron/auto-adjust-fees.php?action=status');
        const result = await response.json();
        
        if (result.success && result.last_run) {
            const lastRun = new Date(result.last_run);
            document.getElementById('fee-recalc-last-run').textContent = lastRun.toLocaleString();
        }
    } catch (error) {
        console.error('Error updating fee recalc status:', error);
    }
}

// Initialize status on page load
document.addEventListener('DOMContentLoaded', updateFeeRecalcStatus);
</script>
