/**
 * IECEP-LSC MEMSYS - Real-Time Data Subscriptions
 * Powered by Supabase Real-Time
 * 
 * This module handles all real-time subscriptions for live data updates across the platform.
 * Uses @supabase/supabase-js client library loaded from CDN.
 */

// ====================================================================
// Initialize Supabase Client
// ====================================================================

let supabaseClient = null;

// Wait for IECEP_CONFIG to be available (set in head-meta.php)
function initializeSupabase() {
    if (!window.IECEP_CONFIG) {
        console.warn('IECEP_CONFIG not found, retrying...');
        setTimeout(initializeSupabase, 500);
        return;
    }

    try {
        // Initialize Supabase client using the anon key
        supabaseClient = supabase.createClient(
            window.IECEP_CONFIG.SUPABASE_URL,
            window.IECEP_CONFIG.SUPABASE_ANON_KEY
        );
        console.log('Supabase client initialized for real-time subscriptions');
        
        // Start all subscriptions after client is ready
        subscribeToAllRealTimeUpdates();
    } catch (err) {
        console.error('Failed to initialize Supabase client:', err);
    }
}

// ====================================================================
// Real-Time Subscription Management
// ====================================================================

// Store active subscriptions for cleanup
const activeSubscriptions = {};

/**
 * Subscribe to real-time updates from a table
 * @param {string} table - Table name
 * @param {object} options - Subscription options (event, filter, callback)
 */
function subscribeToTable(table, options = {}) {
    if (!supabaseClient) {
        console.error('Supabase client not initialized');
        return;
    }

    const { event = '*', filter = null, callback = null } = options;
    const channelName = `public:${table}`;

    try {
        const channel = supabaseClient.channel(channelName)
            .on('postgres_changes', {
                event: event,
                schema: 'public',
                table: table,
                ...(filter && { filter })
            }, (payload) => {
                console.log(`Real-time update on ${table}:`, payload);
                if (callback && typeof callback === 'function') {
                    callback(payload);
                }
                // Broadcast custom event for page-specific handlers
                const eventDetail = new CustomEvent(`realtime:${table}`, {
                    detail: payload,
                    bubbles: true
                });
                window.dispatchEvent(eventDetail);
            })
            .subscribe();

        activeSubscriptions[channelName] = channel;
        console.log(`Subscribed to ${table} real-time updates`);
    } catch (err) {
        console.error(`Failed to subscribe to ${table}:`, err);
    }
}

/**
 * Unsubscribe from a table's real-time updates
 * @param {string} table - Table name
 */
function unsubscribeFromTable(table) {
    const channelName = `public:${table}`;
    if (activeSubscriptions[channelName]) {
        supabaseClient.removeChannel(activeSubscriptions[channelName]);
        delete activeSubscriptions[channelName];
        console.log(`Unsubscribed from ${table} real-time updates`);
    }
}

// ====================================================================
// Subscribe to Core Real-Time Updates
// ====================================================================

function subscribeToAllRealTimeUpdates() {
    // Subscribe to pending affiliations (for registration committee)
    subscribeToTable('pending_affiliations', {
        event: 'INSERT',
        callback: (payload) => {
            console.log('New pending affiliation:', payload.new);
            // Emit event for admin dashboard to update pending count
            if (window.onNewPendingAffiliation) {
                window.onNewPendingAffiliation(payload.new);
            }
        }
    });

    // Subscribe to pending affiliation status changes
    subscribeToTable('pending_affiliations', {
        event: 'UPDATE',
        filter: 'status=neq.previous.status',
        callback: (payload) => {
            console.log('Affiliation status changed:', payload.new);
            if (window.onAffiliationStatusChanged) {
                window.onAffiliationStatusChanged(payload.new);
            }
        }
    });

    // Subscribe to member changes (for compliance tracking)
    subscribeToTable('members', {
        event: 'UPDATE',
        filter: 'compliance_status=neq.previous.compliance_status',
        callback: (payload) => {
            console.log('Member compliance changed:', payload.new);
            if (window.onMemberComplianceChanged) {
                window.onMemberComplianceChanged(payload.new);
            }
        }
    });

    // Subscribe to attendance records (for live attendance tracking)
    subscribeToTable('attendance', {
        event: 'INSERT',
        callback: (payload) => {
            console.log('New attendance record:', payload.new);
            if (window.onNewAttendance) {
                window.onNewAttendance(payload.new);
            }
        }
    });

    // Subscribe to transactions (for treasurer dashboard)
    subscribeToTable('transactions', {
        event: 'INSERT',
        callback: (payload) => {
            console.log('New transaction:', payload.new);
            if (window.onNewTransaction) {
                window.onNewTransaction(payload.new);
            }
        }
    });

    // Subscribe to creatives announcements
    subscribeToTable('creatives_announcements', {
        event: 'INSERT',
        callback: (payload) => {
            console.log('New announcement:', payload.new);
            if (window.onNewAnnouncement) {
                window.onNewAnnouncement(payload.new);
            }
        }
    });

    // Subscribe to blockchain records (for audit trail)
    subscribeToTable('blockchain_records', {
        event: 'INSERT',
        callback: (payload) => {
            console.log('New blockchain record:', payload.new);
            if (window.onNewBlockchainRecord) {
                window.onNewBlockchainRecord(payload.new);
            }
        }
    });

    // Subscribe to general announcements
    subscribeToTable('announcements', {
        event: 'INSERT',
        callback: (payload) => {
            console.log('New system announcement:', payload.new);
            if (window.onNewSystemAnnouncement) {
                window.onNewSystemAnnouncement(payload.new);
            }
        }
    });

    // Subscribe to institution compliance changes
    subscribeToTable('institutions', {
        event: 'UPDATE',
        filter: 'compliance_status=neq.previous.compliance_status',
        callback: (payload) => {
            console.log('Institution compliance changed:', payload.new);
            if (window.onInstitutionComplianceChanged) {
                window.onInstitutionComplianceChanged(payload.new);
            }
        }
    });
}

// ====================================================================
// Real-Time Updates Handler Functions
// ====================================================================
// These are called by the subscription callbacks and can be overridden by page-specific code

/**
 * Handle new pending affiliations (for admin/registration dashboard)
 * @param {object} newAffiliation - New affiliation record
 */
window.onNewPendingAffiliation = function(newAffiliation) {
    // Update pending count
    const pendingCountEl = document.getElementById('pending-affiliations-count');
    if (pendingCountEl) {
        const currentCount = parseInt(pendingCountEl.textContent) || 0;
        pendingCountEl.textContent = currentCount + 1;
    }

    // Show notification
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('New Affiliation Application', {
            body: `${newAffiliation.institution_name} has submitted an affiliation application.`,
            icon: '/IECEP-LSC-MEMSYS/public/assets/icons/icon-192.png'
        });
    }

    // Refresh affiliations list if visible
    const affiliationsList = document.getElementById('affiliations-list');
    if (affiliationsList && typeof refreshAffiliationsList === 'function') {
        refreshAffiliationsList();
    }
};

/**
 * Handle affiliation status changes
 * @param {object} updatedAffiliation - Updated affiliation record
 */
window.onAffiliationStatusChanged = function(updatedAffiliation) {
    // Update the UI to reflect status change
    const affiliationElement = document.querySelector(`[data-affiliation-id="${updatedAffiliation.id}"]`);
    if (affiliationElement) {
        const statusEl = affiliationElement.querySelector('[data-field="status"]');
        if (statusEl) {
            statusEl.textContent = updatedAffiliation.status;
            affiliationElement.classList.remove('status-pending', 'status-approved', 'status-rejected');
            affiliationElement.classList.add(`status-${updatedAffiliation.status}`);
        }
    }

    // Show notification
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Affiliation Status Updated', {
            body: `Application for ${updatedAffiliation.institution_name} is now ${updatedAffiliation.status}.`,
            icon: '/IECEP-LSC-MEMSYS/public/assets/icons/icon-192.png'
        });
    }
};

/**
 * Handle member compliance changes
 * @param {object} updatedMember - Updated member record
 */
window.onMemberComplianceChanged = function(updatedMember) {
    // Update compliance badge
    const memberElement = document.querySelector(`[data-member-id="${updatedMember.id}"]`);
    if (memberElement) {
        const complianceBadge = memberElement.querySelector('[data-field="compliance"]');
        if (complianceBadge) {
            complianceBadge.textContent = updatedMember.compliance_status;
            complianceBadge.classList.remove('badge-compliant', 'badge-at-risk', 'badge-non-compliant');
            complianceBadge.classList.add(`badge-${updatedMember.compliance_status}`);
        }
    }
};

/**
 * Handle new attendance records
 * @param {object} newAttendance - New attendance record
 */
window.onNewAttendance = function(newAttendance) {
    // Update attendance count
    const attendanceCountEl = document.getElementById('attendance-count');
    if (attendanceCountEl) {
        const currentCount = parseInt(attendanceCountEl.textContent) || 0;
        attendanceCountEl.textContent = currentCount + 1;
    }

    // Update event attendance summary if visible
    const eventElement = document.querySelector(`[data-event-id="${newAttendance.event_id}"]`);
    if (eventElement && typeof refreshEventAttendance === 'function') {
        refreshEventAttendance(newAttendance.event_id);
    }
};

/**
 * Handle new transactions
 * @param {object} newTransaction - New transaction record
 */
window.onNewTransaction = function(newTransaction) {
    // Update transaction count
    const transactionCountEl = document.getElementById('transaction-count');
    if (transactionCountEl) {
        const currentCount = parseInt(transactionCountEl.textContent) || 0;
        transactionCountEl.textContent = currentCount + 1;
    }

    // Update transaction total
    const transactionTotalEl = document.getElementById('transaction-total');
    if (transactionTotalEl && newTransaction.amount) {
        const currentTotal = parseFloat(transactionTotalEl.textContent.replace(/[^0-9.-]+/g, '')) || 0;
        const newTotal = currentTotal + parseFloat(newTransaction.amount);
        transactionTotalEl.textContent = '₱' + newTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    }

    // Refresh transactions list
    if (typeof refreshTransactionsList === 'function') {
        refreshTransactionsList();
    }
};

/**
 * Handle new announcements
 * @param {object} newAnnouncement - New announcement record
 */
window.onNewAnnouncement = function(newAnnouncement) {
    // Add to announcements list
    const announcementsList = document.getElementById('announcements-list');
    if (announcementsList) {
        const newElement = document.createElement('div');
        newElement.className = 'announcement-item';
        newElement.innerHTML = `
            <h3>${newAnnouncement.title}</h3>
            <p>${newAnnouncement.content}</p>
            <small>${new Date(newAnnouncement.created_at).toLocaleDateString()}</small>
        `;
        announcementsList.insertBefore(newElement, announcementsList.firstChild);
    }

    // Show notification
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(newAnnouncement.title, {
            body: newAnnouncement.content,
            icon: '/IECEP-LSC-MEMSYS/public/assets/icons/icon-192.png'
        });
    }
};

/**
 * Handle new blockchain records
 * @param {object} newRecord - New blockchain record
 */
window.onNewBlockchainRecord = function(newRecord) {
    console.log('Blockchain record added to audit trail:', newRecord);
    // Blockchain records are immutable and added for audit trail
    // Refresh blockchain view if visible
    if (typeof refreshBlockchainRecords === 'function') {
        refreshBlockchainRecords();
    }
};

/**
 * Handle new system announcements
 * @param {object} newAnnouncement - New system announcement
 */
window.onNewSystemAnnouncement = function(newAnnouncement) {
    // Show system-wide notification banner
    const notificationBanner = document.createElement('div');
    notificationBanner.className = 'notification-banner notification-info';
    notificationBanner.innerHTML = `
        <strong>${newAnnouncement.title}</strong>
        <p>${newAnnouncement.content}</p>
        <button onclick="this.parentElement.remove()" class="btn-close">✕</button>
    `;
    const pageContent = document.getElementById('page-content') || document.body;
    pageContent.insertBefore(notificationBanner, pageContent.firstChild);
};

/**
 * Handle institution compliance changes
 * @param {object} updatedInstitution - Updated institution record
 */
window.onInstitutionComplianceChanged = function(updatedInstitution) {
    // Update institution compliance badge
    const institutionElement = document.querySelector(`[data-institution-id="${updatedInstitution.id}"]`);
    if (institutionElement) {
        const complianceBadge = institutionElement.querySelector('[data-field="compliance"]');
        if (complianceBadge) {
            complianceBadge.textContent = updatedInstitution.compliance_status;
        }
    }
};

// ====================================================================
// Initialization
// ====================================================================

// Initialize Supabase client when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeSupabase);
} else {
    initializeSupabase();
}

// Export for use in other scripts
window.RealtimeAPI = {
    subscribe: subscribeToTable,
    unsubscribe: unsubscribeFromTable,
    getClient: () => supabaseClient
};

console.log('Real-time subscriptions module loaded');
