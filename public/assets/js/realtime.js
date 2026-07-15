/**
 * IECEP-LSC MEMSYS — Realtime Engine
 * ─────────────────────────────────────────────────────────────────────────────
 * Single source-of-truth for all Supabase Realtime subscriptions.
 *
 * How it works
 * ─────────────
 * 1.  One Supabase JS client (v2) is created here and stored as
 *     `window.IECEP_REALTIME.client`.
 * 2.  Every portal page calls `window.IECEP_REALTIME.subscribe(table, events,
 *     callback, filter?)` and receives a channel it can unsubscribe from.
 * 3.  A stale-data guard: if a re-fetch API call fails, a small
 *     "⚠ Data might be stale" banner is shown and retried after 5 s.
 * 4.  The client is exposed as `window.supabaseClient` so that
 *     notifications.js (which already reads that global) works correctly.
 *
 * Usage on each page
 * ───────────────────
 *   // Subscribe to INSERT + UPDATE on the members table:
 *   window.IECEP_REALTIME.subscribe('members', ['INSERT','UPDATE'], payload => {
 *       if (!IECEP_REALTIME.validatePayload(payload, ['id','institution_id'])) return;
 *       // update your DOM here
 *   });
 *
 *   // With a server-side filter (Supabase Realtime filter syntax):
 *   window.IECEP_REALTIME.subscribe('members', ['INSERT'], handler,
 *       'institution_id=eq.' + myInstitutionId);
 *
 * DOM helpers exposed on window.IECEP_REALTIME
 * ──────────────────────────────────────────────
 *   .safeSetText(selector, value)   — sets textContent only when element exists
 *   .animateBump(selector)          — briefly highlights a stat counter
 *   .showStaleWarning(id)           — shows "data might be stale" chip
 *   .hideStaleWarning(id)           — removes it
 *   .showToast(msg, type)           — delegates to window.toast if present
 *   .refetchWithRetry(url, onData)  — fetch JSON, retry once after 5 s on error
 *   .validatePayload(payload, keys) — guard before touching the DOM
 */

(function () {
    'use strict';

    /* ─────────────────────────── internal state ─────────────────────────── */
    let _client = null;
    let _initAttempts = 0;
    const MAX_INIT_ATTEMPTS = 20; // 10 s total @ 500 ms each
    const _channels = [];         // all active channels for cleanup
    const _pendingSubscriptions = []; // queued before client is ready

    /* ─────────────────────────── stale-data banner ──────────────────────── */
    function _ensureStaleStyles() {
        if (document.getElementById('iecep-rt-styles')) return;
        const s = document.createElement('style');
        s.id = 'iecep-rt-styles';
        s.textContent = `
            .iecep-stale-banner{
                display:inline-flex;align-items:center;gap:6px;
                background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;
                border-radius:6px;padding:4px 10px;font-size:0.78rem;font-weight:600;
                position:fixed;bottom:16px;left:50%;transform:translateX(-50%);
                z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,.15);
                animation:iecep-fadein .25s ease;
            }
            @keyframes iecep-fadein{from{opacity:0;transform:translateX(-50%) translateY(6px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
            .iecep-rt-bump{
                animation:iecep-bump .6s ease;
            }
            @keyframes iecep-bump{
                0%{transform:scale(1)}30%{transform:scale(1.18);color:#D4AF37}
                70%{transform:scale(1.05)}100%{transform:scale(1)}
            }
        `;
        document.head.appendChild(s);
    }

    /* ─────────────────────────── helpers (public) ───────────────────────── */
    const API = {

        /** The live Supabase client — available once init completes */
        get client() { return _client; },

        /**
         * Safely set text content of a DOM element.
         * @param {string} selector
         * @param {string|number} value
         */
        safeSetText(selector, value) {
            const el = document.querySelector(selector);
            if (el) el.textContent = String(value);
        },

        /**
         * Briefly scale a stat element to signal a live update.
         * @param {string} selector
         */
        animateBump(selector) {
            const el = document.querySelector(selector);
            if (!el) return;
            el.classList.remove('iecep-rt-bump');
            // Force reflow so animation restarts
            void el.offsetWidth;
            el.classList.add('iecep-rt-bump');
            el.addEventListener('animationend', () => el.classList.remove('iecep-rt-bump'), { once: true });
        },

        /**
         * Show "data might be stale" banner with a given id.
         * Calling it a second time with the same id is a no-op.
         * @param {string} id  unique suffix
         */
        showStaleWarning(id = 'global') {
            _ensureStaleStyles();
            const bannerId = `iecep-stale-${id}`;
            if (document.getElementById(bannerId)) return;
            const div = document.createElement('div');
            div.id = bannerId;
            div.className = 'iecep-stale-banner';
            div.innerHTML = '⚠️ Data might be stale — retrying…';
            document.body.appendChild(div);
        },

        /**
         * Remove stale banner by id.
         * @param {string} id
         */
        hideStaleWarning(id = 'global') {
            const el = document.getElementById(`iecep-stale-${id}`);
            if (el) el.remove();
        },

        /**
         * Delegate to window.toast (from toast.js) if available.
         * @param {string} message
         * @param {'success'|'error'|'warning'|'info'} type
         */
        showToast(message, type = 'info') {
            if (window.toast && typeof window.toast[type] === 'function') {
                window.toast[type](message);
            } else {
                console.info(`[IECEP RT] ${type.toUpperCase()}: ${message}`);
            }
        },

        /**
         * Fetch JSON from a relative API path with one auto-retry after 5 s.
         * @param {string}   url      Absolute or root-relative path
         * @param {function} onData   Called with the parsed JSON on success
         * @param {string}   [staleId] ID for the stale-data banner
         */
        refetchWithRetry(url, onData, staleId = 'global') {
            const attempt = () =>
                fetch(url, { credentials: 'same-origin' })
                    .then(r => {
                        if (!r.ok) throw new Error(`HTTP ${r.status}`);
                        return r.json();
                    })
                    .then(data => {
                        API.hideStaleWarning(staleId);
                        onData(data);
                    })
                    .catch(err => {
                        console.warn(`[IECEP RT] Refetch failed for ${url}:`, err);
                        API.showStaleWarning(staleId);
                        setTimeout(() => {
                            attempt(); // single retry
                        }, 5000);
                    });
            attempt();
        },

        /**
         * Guard: confirm payload is non-null and contains all expected fields.
         * @param {object}   payload     The realtime payload object
         * @param {string[]} [requiredKeys]  Fields expected in payload.new or payload.old
         * @returns {boolean}
         */
        validatePayload(payload, requiredKeys = []) {
            if (!payload) return false;
            const record = payload.new || payload.old || {};
            return requiredKeys.every(k => k in record);
        },

        /**
         * Subscribe to one or more events on a Supabase table.
         * If the client is not yet ready the subscription is queued
         * and replayed automatically once init completes.
         *
         * @param {string}          table     e.g. 'members'
         * @param {string|string[]} events    '*' | 'INSERT' | ['INSERT','UPDATE']
         * @param {function}        callback  (payload) => void
         * @param {string}          [filter]  Supabase filter string e.g. 'status=eq.pending_review'
         * @returns {object|null}   Channel (can be passed to .unsubscribe())
         */
        subscribe(table, events, callback, filter = null) {
            if (!_client) {
                // Queue and replay once the client is ready
                _pendingSubscriptions.push({ table, events, callback, filter });
                return null;
            }

            const eventsArr = Array.isArray(events) ? events : [events];

            // Build a deterministic channel name
            const filterKey = filter ? encodeURIComponent(filter) : 'all';
            const channelName = `iecep:${table}:${eventsArr.join('+')}:${filterKey}`;

            try {
                let ch = _client.channel(channelName);

                eventsArr.forEach(evt => {
                    const pgOptions = { event: evt, schema: 'public', table };
                    if (filter) pgOptions.filter = filter;

                    ch = ch.on('postgres_changes', pgOptions, (payload) => {
                        // Validate non-null before firing user callback
                        if (!payload) return;
                        try {
                            callback(payload);
                        } catch (e) {
                            console.error(`[IECEP RT] Callback error on ${table}:`, e);
                        }
                    });
                });

                ch.subscribe(status => {
                    if (status === 'SUBSCRIBED') {
                        console.info(`[IECEP RT] ✓ Subscribed to ${channelName}`);
                    } else if (status === 'CHANNEL_ERROR') {
                        console.warn(`[IECEP RT] Channel error on ${channelName}`);
                    }
                });

                _channels.push(ch);
                return ch;
            } catch (err) {
                console.error(`[IECEP RT] Failed to subscribe to ${table}:`, err);
                return null;
            }
        },

        /**
         * Remove a specific channel returned by .subscribe().
         * @param {object} channel
         */
        unsubscribe(channel) {
            if (!channel || !_client) return;
            _client.removeChannel(channel);
            const idx = _channels.indexOf(channel);
            if (idx !== -1) _channels.splice(idx, 1);
        },

        /**
         * Remove ALL active channels (called automatically on pagehide).
         */
        unsubscribeAll() {
            if (!_client) return;
            _channels.forEach(ch => _client.removeChannel(ch));
            _channels.length = 0;
        }
    };

    /* ─────────────────────────── client bootstrap ───────────────────────── */
    function _tryInit() {
        _initAttempts++;

        if (!window.IECEP_CONFIG || !window.IECEP_CONFIG.SUPABASE_URL || !window.IECEP_CONFIG.SUPABASE_ANON_KEY) {
            if (_initAttempts < MAX_INIT_ATTEMPTS) {
                setTimeout(_tryInit, 500);
            } else {
                console.warn('[IECEP RT] IECEP_CONFIG not found after 10 s — realtime disabled.');
            }
            return;
        }

        // The CDN bundle exposes window.supabase.createClient
        const lib = window.supabase;
        if (!lib || typeof lib.createClient !== 'function') {
            if (_initAttempts < MAX_INIT_ATTEMPTS) {
                setTimeout(_tryInit, 500);
            } else {
                console.warn('[IECEP RT] Supabase CDN not loaded after 10 s — realtime disabled.');
            }
            return;
        }

        try {
            _client = lib.createClient(
                window.IECEP_CONFIG.SUPABASE_URL,
                window.IECEP_CONFIG.SUPABASE_ANON_KEY,
                {
                    realtime: {
                        params: { eventsPerSecond: 10 }
                    }
                }
            );

            // Expose globally so notifications.js can reuse the same client
            window.supabaseClient = _client;

            console.info('[IECEP RT] Supabase client ready — replaying queued subscriptions…');

            // Flush queued subscriptions
            const queue = _pendingSubscriptions.splice(0);
            queue.forEach(({ table, events, callback, filter }) => {
                API.subscribe(table, events, callback, filter);
            });

            // Dispatch an event so page-level code can react if needed
            window.dispatchEvent(new CustomEvent('iecep:realtime:ready'));
        } catch (err) {
            console.error('[IECEP RT] Client creation failed:', err);
        }
    }

    /* Cleanup on page unload */
    window.addEventListener('pagehide', () => API.unsubscribeAll(), { once: true });

    /* ─────────────────────────── public export ──────────────────────────── */
    window.IECEP_REALTIME = API;

    /* ─────────────────────────── start ──────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _tryInit);
    } else {
        _tryInit();
    }

})();
