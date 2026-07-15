// Secretary General Portal JS
const SecretaryPortal = {
    async init() {
        const user = await Auth.requireAuth('eb_secretary_general');
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
        const container = document.getElementById('announcements-list');
        if (!container) return;

        if (announcements.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="icon">📢</div><h3>No Announcements</h3></div>';
            return;
        }

        let html = '';
        announcements.forEach(a => {
            const readCount = a.read_receipts?.length || 0;
            html += `<div class="card mb-2">
                <div class="card-header">
                    <h3>${a.title}</h3>
                    <span class="text-muted">${App.formatDateTime(a.sent_at)}</span>
                </div>
                <div>${a.content}</div>
                <p class="mt-1 text-muted">Read by: ${readCount} member(s)</p>
            </div>`;
        });
        container.innerHTML = html;
    },

    async sendAnnouncement(e) {
        e.preventDefault();
        const form = e.target;
        const data = {
            title: form.title.value,
            content: form.content.value,
            send_email: form.send_email?.checked || false,
        };

        const result = await App.api('secretary', 'send-announcement', {
            method: 'POST', body: data,
        });

        if (result.success) {
            App.toast('Announcement sent!', 'success');
            form.reset();
            this.loadAnnouncements();
        } else {
            App.toast(result.message || 'Failed', 'error');
        }
    }
};
