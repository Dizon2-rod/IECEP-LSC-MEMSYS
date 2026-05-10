// IECEP-LSC MEMSYS - Global Helpers
const API_BASE = '/api';

const App = {
    // API call helper
    async api(endpoint, action, options = {}) {
        const url = `${API_BASE}/${endpoint}?action=${action}`;
        const token = (typeof SupabaseAuth !== 'undefined') ? SupabaseAuth.getToken() : (typeof Auth !== 'undefined' ? Auth.getToken() : null);

        const fetchOptions = {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
            },
        };

        if (options.body && fetchOptions.method !== 'GET') {
            if (options.formData) {
                delete fetchOptions.headers['Content-Type'];
                fetchOptions.body = options.body;
            } else {
                fetchOptions.body = JSON.stringify(options.body);
            }
        }

        try {
            const response = await fetch(url, fetchOptions);
            const data = await response.json();

            if (response.status === 401) {
                if (typeof SupabaseAuth !== 'undefined') {
                    SupabaseAuth.logout();
                } else if (typeof Auth !== 'undefined') {
                    Auth.logout();
                }
                window.location.href = '/login.html';
                return { error: true, message: 'Session expired' };
            }

            if (!response.ok) {
                return { error: true, message: data.error || data.message || 'Request failed', status: response.status };
            }

            return data;
        } catch (err) {
            console.error('API Error:', err);
            return { error: true, message: 'Network error. Please check your connection.' };
        }
    },

    // Toast notifications
    toast(message, type = 'info', duration = 4000) {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span>${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.animation = 'slideIn 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }
        }, duration);
    },

    // Show/hide elements
    show(el) { if (typeof el === 'string') el = document.getElementById(el); if (el) el.classList.remove('hidden'); },
    hide(el) { if (typeof el === 'string') el = document.getElementById(el); if (el) el.classList.add('hidden'); },

    // Loading state
    setLoading(el, loading = true) {
        if (typeof el === 'string') el = document.getElementById(el);
        if (!el) return;
        if (loading) {
            el.dataset.originalContent = el.innerHTML;
            el.innerHTML = '<div class="spinner"></div>';
            el.disabled = true;
        } else {
            if (el.dataset.originalContent) {
                el.innerHTML = el.dataset.originalContent;
            }
            el.disabled = false;
        }
    },

    // Format currency
    formatCurrency(amount) {
        return '₱' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    // Format date
    formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
    },

    // Format datetime
    formatDateTime(dateStr) {
        if (!dateStr) return 'N/A';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    },

    // Role display names
    roleNames: {
        'eb_president': 'President',
        'eb_vp_internal': 'VP Internal',
        'eb_vp_external': 'VP External',
        'eb_vp_academic': 'VP Academic',
        'eb_secretary_general': 'Secretary General',
        'eb_assistant_secretary': 'Asst. Secretary',
        'eb_treasurer': 'Treasurer',
        'eb_auditor': 'Auditor',
        'eb_pro_1': 'PRO 1',
        'eb_pro_2': 'PRO 2',
        'committee_creatives': 'Creatives Committee',
        'committee_documentation': 'Documentation Committee',
        'committee_logistics': 'Logistics Committee',
        'committee_marketing': 'Marketing Committee',
        'committee_registration': 'Registration Committee',
        'committee_technical': 'Technical Committee',
        'school_officer': 'School Officer',
        'member': 'Member',
    },

    getRoleName(role) {
        return this.roleNames[role] || role;
    },

    // Get portal path for role
    getPortalPath(role) {
        const paths = {
            'eb_president': '/portal/president/',
            'eb_vp_internal': '/portal/vp-internal/',
            'eb_vp_external': '/portal/vp-external/',
            'eb_vp_academic': '/portal/vp-academic/',
            'eb_secretary_general': '/portal/secretary/',
            'eb_assistant_secretary': '/portal/asst-secretary/',
            'eb_treasurer': '/portal/treasurer/',
            'eb_auditor': '/portal/auditor/',
            'eb_pro_1': '/portal/pro/',
            'eb_pro_2': '/portal/pro/',
            'committee_creatives': '/portal/committee/creatives/',
            'committee_documentation': '/portal/committee/documentation/',
            'committee_logistics': '/portal/committee/logistics/',
            'committee_marketing': '/portal/committee/marketing/',
            'committee_registration': '/portal/committee/registration/',
            'committee_technical': '/portal/committee/technical/',
            'school_officer': '/portal/officer/',
            'member': '/portal/member/',
        };
        return paths[role] || '/portal/member/';
    },

    // Navbar toggle
    initNavbar() {
        const toggle = document.querySelector('.navbar-toggle');
        const nav = document.querySelector('.navbar-nav');
        if (toggle && nav) {
            toggle.addEventListener('click', () => nav.classList.toggle('show'));
        }
    },

    // Initialize PWA helpers
    initPWA() {
        this.updateNetworkStatus();

        window.addEventListener('online', () => {
            this.updateNetworkStatus();
            this.toast('Connectivity restored', 'success');
        });

        window.addEventListener('offline', () => {
            this.updateNetworkStatus();
            this.toast('You are offline. Some features may be unavailable.', 'error');
        });
    },

    updateNetworkStatus() {
        const statusId = 'networkStatusBadge';
        let badge = document.getElementById(statusId);
        if (!badge) {
            badge = document.createElement('div');
            badge.id = statusId;
            badge.className = 'network-status';
            document.body.appendChild(badge);
        }

        const online = navigator.onLine;
        badge.textContent = online ? 'Online' : 'Offline — limited access';
        badge.style.background = online ? 'var(--accent)' : '#DC2626';
    },

    // Initialize
    init() {
        this.initNavbar();
        this.initPWA();
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());