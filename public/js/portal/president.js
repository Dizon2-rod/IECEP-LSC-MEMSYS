// President Portal JS
const PresidentPortal = {
    async init() {
        const user = await Auth.requireAuth('eb_president');
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
        this.loadDashboard();
    },

    async loadDashboard() {
        const [officers, affiliations, settings] = await Promise.all([
            App.api('super-admin', 'settings', { method: 'GET' }),
            App.api('registration', 'pending-affiliations', { method: 'GET' }),
            App.api('super-admin', 'settings', { method: 'GET' }),
        ]);
    },

    async createOfficer(e) {
        e.preventDefault();
        const form = e.target;
        const data = {
            email: form.email.value,
            full_name: form.full_name.value,
            role: form.role.value,
            member_type: form.member_type?.value || 'honorary',
        };

        const result = await App.api('super-admin', 'create-officer', {
            method: 'POST',
            body: data,
        });

        if (result.success) {
            App.toast('Officer account created successfully!', 'success');
            form.reset();
        } else {
            App.toast(result.message || 'Failed to create officer', 'error');
        }
    },

    async overrideAffiliation(pendingId, decision, reason) {
        const result = await App.api('super-admin', 'override-affiliation', {
            method: 'POST',
            body: { pending_id: pendingId, decision, reason },
        });

        if (result.success) {
            App.toast(`Affiliation ${decision}d by override`, 'success');
            this.loadDashboard();
        } else {
            App.toast(result.message || 'Override failed', 'error');
        }
    },

    async saveSettings(settings) {
        const result = await App.api('super-admin', 'settings', {
            method: 'PUT',
            body: { settings },
        });

        if (result.success) {
            App.toast('Settings saved', 'success');
        } else {
            App.toast(result.message || 'Failed to save settings', 'error');
        }
    }
};
