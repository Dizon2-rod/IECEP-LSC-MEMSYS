/**
 * IECEP-LSC MEMSYS — public/js/realtime.js
 * ─────────────────────────────────────────
 * Thin compatibility shim.
 * The canonical engine lives in public/assets/js/realtime.js which is
 * already loaded by includes/head-meta.php.
 *
 * This file keeps the legacy shape (window.RealtimeAPI, subscribeToTable,
 * subscribeToAllRealTimeUpdates) intact so that pages that call those
 * functions directly do not break.
 */

(function () {
    'use strict';

    /* ── Compatibility: expose legacy subscribeToTable ───────────────────── */
    /**
     * @param {string} table
     * @param {{event?: string, filter?: string|null, callback?: function}} options
     */
    window.subscribeToTable = function (table, options) {
        const { event = '*', filter = null, callback = null } = options || {};
        if (!window.IECEP_REALTIME) {
            console.warn('[realtime.js shim] IECEP_REALTIME not yet ready for', table);
            return;
        }
        return window.IECEP_REALTIME.subscribe(table, event, (payload) => {
            // Fire old-style custom DOM event (used by pages that listen with
            // window.addEventListener('realtime:<table>', …))
            window.dispatchEvent(new CustomEvent('realtime:' + table, {
                detail: {
                    action: payload.eventType,
                    new: payload.new,
                    old: payload.old
                },
                bubbles: true
            }));
            // Also call direct callback if provided
            if (typeof callback === 'function') callback(payload);
        }, filter);
    };

    /* ── Compatibility: old subscribeToAllRealTimeUpdates ───────────────── */
    window.subscribeToAllRealTimeUpdates = function () {
        if (!window.IECEP_REALTIME) {
            window.addEventListener('iecep:realtime:ready', window.subscribeToAllRealTimeUpdates, { once: true });
            return;
        }

        const RT = window.IECEP_REALTIME;

        // pending_affiliations — INSERT
        RT.subscribe('pending_affiliations', 'INSERT', (payload) => {
            _dispatch('pending_affiliations', payload);
            if (typeof window.onNewPendingAffiliation === 'function' && payload.new) {
                window.onNewPendingAffiliation(payload.new);
            }
        });

        // pending_affiliations — UPDATE
        RT.subscribe('pending_affiliations', 'UPDATE', (payload) => {
            _dispatch('pending_affiliations', payload);
            if (typeof window.onAffiliationStatusChanged === 'function' && payload.new) {
                window.onAffiliationStatusChanged(payload.new);
            }
        });

        // members — UPDATE
        RT.subscribe('members', 'UPDATE', (payload) => {
            _dispatch('members', payload);
            if (typeof window.onMemberComplianceChanged === 'function' && payload.new) {
                window.onMemberComplianceChanged(payload.new);
            }
        });

        // members — INSERT / DELETE
        RT.subscribe('members', ['INSERT', 'DELETE'], (payload) => {
            _dispatch('members', payload);
        });

        // attendance — INSERT
        RT.subscribe('attendance', 'INSERT', (payload) => {
            _dispatch('attendance', payload);
            if (typeof window.onNewAttendance === 'function' && payload.new) {
                window.onNewAttendance(payload.new);
            }
        });

        // transactions — INSERT
        RT.subscribe('transactions', 'INSERT', (payload) => {
            _dispatch('transactions', payload);
            if (typeof window.onNewTransaction === 'function' && payload.new) {
                window.onNewTransaction(payload.new);
            }
        });

        // creatives_announcements — INSERT
        RT.subscribe('creatives_announcements', 'INSERT', (payload) => {
            _dispatch('creatives_announcements', payload);
            if (typeof window.onNewAnnouncement === 'function' && payload.new) {
                window.onNewAnnouncement(payload.new);
            }
        });

        // events — INSERT / UPDATE
        RT.subscribe('events', ['INSERT', 'UPDATE'], (payload) => {
            _dispatch('events', payload);
        });

        // institutions — INSERT / UPDATE
        RT.subscribe('institutions', ['INSERT', 'UPDATE'], (payload) => {
            _dispatch('institutions', payload);
        });
    };

    function _dispatch(table, payload) {
        window.dispatchEvent(new CustomEvent('realtime:' + table, {
            detail: {
                action: payload.eventType,
                new: payload.new,
                old: payload.old
            },
            bubbles: true
        }));
    }

    /* ── Compatibility: old RealtimeAPI shape ───────────────────────────── */
    window.RealtimeAPI = {
        subscribe: window.subscribeToTable,
        unsubscribe: (ch) => window.IECEP_REALTIME && window.IECEP_REALTIME.unsubscribe(ch),
        getClient: () => window.IECEP_REALTIME ? window.IECEP_REALTIME.client : null
    };

    /* ── Boot ────────────────────────────────────────────────────────────── */
    function _boot() {
        if (window.IECEP_REALTIME) {
            window.subscribeToAllRealTimeUpdates();
        } else {
            // Wait for the engine to be ready
            window.addEventListener('iecep:realtime:ready', window.subscribeToAllRealTimeUpdates, { once: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _boot);
    } else {
        _boot();
    }
})();
