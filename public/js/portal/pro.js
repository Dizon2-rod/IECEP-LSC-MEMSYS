// PRO Portal JS (shared by PRO 1 and PRO 2)
const ProPortal = {
    async init() {
        const user = await Auth.requireAuth(['eb_pro_1', 'eb_pro_2']);
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
        this.loadAnnouncements();
    },

    async loadAnnouncements() {
        const result = await App.api('pro', 'announcements', { method: 'GET' });
        if (result.success) {
            this.renderAnnouncements(result.data || []);
        }
    },

    renderAnnouncements(announcements) {
        const container = document.getElementById('pro-announcements');
        if (!container) return;

        let html = '';
        announcements.forEach(a => {
            html += `<div class="card mb-2">
                <div class="card-header">
                    <h3>${a.title}</h3>
                    <button class="btn btn-danger btn-sm" onclick="ProPortal.deleteAnnouncement('${a.id}')">Delete</button>
                </div>
                <div>${a.content}</div>
                <p class="text-muted mt-1">${App.formatDateTime(a.sent_at)}</p>
            </div>`;
        });
        container.innerHTML = html || '<div class="empty-state"><h3>No Public Announcements</h3></div>';
    },

    async createAnnouncement(e) {
        e.preventDefault();
        const form = e.target;
        const result = await App.api('pro', 'announcements', {
            method: 'POST',
            body: { title: form.title.value, content: form.content.value },
        });

        if (result.success) {
            App.toast('Announcement published!', 'success');
            form.reset();
            this.loadAnnouncements();
        } else {
            App.toast(result.message || 'Failed', 'error');
        }
    },

    async deleteAnnouncement(id) {
        if (!confirm('Delete this announcement?')) return;
        const result = await App.api('pro', 'announcements', {
            method: 'DELETE',
            body: { id },
        });
        if (result.success) { App.toast('Deleted', 'success'); this.loadAnnouncements(); }
    }
};
