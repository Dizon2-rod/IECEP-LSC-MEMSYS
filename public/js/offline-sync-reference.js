/**
 * Offline Sync Priority - Quick Reference
 * 
 * This file documents how to use the priority-based offline sync system
 * for financial transactions and other operations.
 */

// ============================================================================
// USAGE IN HTML FORMS
// ============================================================================

// For financial transaction forms (HIGH PRIORITY - syncs first):
<form action="/api/submit-payment.php" 
      method="POST" 
      data-offline-sync 
      data-table-name="transactions">
    <!-- form fields -->
</form>

// For other forms (NORMAL PRIORITY):
<form action="/api/register-event.php" 
      method="POST" 
      data-offline-sync>
    <!-- form fields -->
</form>

// ============================================================================
// USAGE IN JAVASCRIPT
// ============================================================================

// Queue a high-priority transaction
await offlineSyncManager.queueRequest(
    '/api/submit-payment.php',
    'POST',
    new Headers({'Content-Type': 'application/json'}),
    JSON.stringify(paymentData),
    'transactions' // This makes it high priority
);

// Queue a normal-priority operation
await offlineSyncManager.queueRequest(
    '/api/register-event.php',
    'POST',
    new Headers({'Content-Type': 'application/json'}),
    JSON.stringify(registrationData)
    // No table name = normal priority
);

// ============================================================================
// PRIORITY LEVELS
// ============================================================================

// Priority 1 (HIGH) - Syncs FIRST:
// - Financial transactions
// - Payment submissions
// - Receipt generation
// - Any operation with data-table-name="transactions"

// Priority 10 (NORMAL) - Syncs AFTER high priority:
// - Event registrations
// - Profile updates
// - Announcements
// - All other operations

// ============================================================================
// TESTING OFFLINE SYNC
// ============================================================================

// 1. Open Chrome DevTools
// 2. Go to Application tab > Service Workers
// 3. Check "Offline" checkbox
// 4. Submit a financial transaction form
// 5. Submit an event registration form
// 6. Go to Application tab > IndexedDB > IECEP_MEMSYS_Offline > pendingRequests
// 7. Verify transaction has priority: 1, registration has priority: 10
// 8. Uncheck "Offline" checkbox
// 9. Go to Network tab
// 10. Verify transaction request appears BEFORE registration request

// ============================================================================
// INDEXEDDB SCHEMA
// ============================================================================

// Each queued request has these fields:
{
    id: 1,                          // Auto-increment
    url: '/api/submit-payment.php', // Request URL
    method: 'POST',                 // HTTP method
    headers: {...},                 // Request headers
    body: FormData,                 // Request body
    timestamp: 1234567890,          // Queue time
    retries: 0,                     // Retry count
    priority: 1,                    // 1 = high, 10 = normal
    table_name: 'transactions'      // Source table (optional)
}

// ============================================================================
// SYNC BEHAVIOR
// ============================================================================

// When online connection is restored:
// 1. offlineSyncManager.syncAll() is called automatically
// 2. Pending requests are sorted by priority (ascending)
// 3. Priority 1 requests sync first
// 4. Priority 10 requests sync after
// 5. Successfully synced requests are removed from queue
// 6. Failed requests remain in queue for next sync attempt

// ============================================================================
// EVENTS
// ============================================================================

// Listen for sync completion
window.addEventListener('online', async () => {
    console.log('Connection restored, syncing...');
    const results = await offlineSyncManager.syncAll();
    
    // Check results
    results.forEach(result => {
        if (result.success) {
            console.log(`Synced request ${result.id} (priority: ${result.priority})`);
        } else {
            console.error(`Failed to sync request ${result.id}: ${result.error}`);
        }
    });
});

// ============================================================================
// BEST PRACTICES
// ============================================================================

// 1. Always add data-offline-sync to forms that should queue when offline
// 2. Add data-table-name="transactions" to financial forms for high priority
// 3. Test offline functionality regularly
// 4. Monitor IndexedDB size (clear old failed requests periodically)
// 5. Provide user feedback when operations are queued
// 6. Show sync status indicator in UI

// ============================================================================
// TROUBLESHOOTING
// ============================================================================

// Queue not working:
// - Verify data-offline-sync attribute is present
// - Check browser console for errors
// - Ensure IndexedDB is not full
// - Clear IndexedDB and try again

// Priority not working:
// - Verify data-table-name="transactions" is set
// - Check IndexedDB to see priority values
// - Ensure offline-sync.js is loaded
// - Clear browser cache and reload

// Sync failing:
// - Check Network tab for error responses
// - Verify API endpoints are accessible
// - Check CSRF token validity
// - Review server logs for errors

// ============================================================================
// EXAMPLE: COMPLETE TRANSACTION FORM
// ============================================================================

<form id="paymentForm" 
      action="/api/submit-payment.php" 
      method="POST" 
      data-offline-sync 
      data-table-name="transactions">
    
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="member_id" value="<?= $member_id ?>">
    
    <div class="form-group">
        <label>Amount</label>
        <input type="number" name="amount" required>
    </div>
    
    <div class="form-group">
        <label>Payment Method</label>
        <select name="payment_method" required>
            <option value="cash">Cash</option>
            <option value="gcash">GCash</option>
            <option value="bank">Bank Transfer</option>
        </select>
    </div>
    
    <button type="submit">Submit Payment</button>
</form>

<script>
// Optional: Show feedback when queued
document.getElementById('paymentForm').addEventListener('submit', (e) => {
    if (!navigator.onLine) {
        // Form will be queued automatically
        setTimeout(() => {
            alert('You are offline. Payment will be submitted when connection is restored.');
        }, 100);
    }
});
</script>
