// Assistant Secretary Portal JS
const AsstSecretaryPortal = {
    async init() {
        const user = await Auth.requireAuth('eb_assistant_secretary');
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
        this.loadAnnouncements();
    },

    async loadAnnouncements() {
        const result = await App.api('secretary', 'announcements', { method: 'GET' });
        if (result.success) {
            this.renderAnnouncements(result.data || []);
        }
    },

    renderAnnouncements(announcements) {
        const container = document.getElementById('announcements-view');
        if (!container) return;

        let html = '';
        announcements.forEach(a => {
            const readCount = a.read_receipts?.length || 0;
            html += `<div class="card mb-2">
                <h4>${a.title}</h4>
                <div class="text-muted mb-1">${App.formatDateTime(a.sent_at)} | Read: ${readCount}</div>
                <div>${a.content}</div>
            </div>`;
        });
        container.innerHTML = html || '<div class="empty-state"><h3>No Announcements</h3></div>';
    }
};
