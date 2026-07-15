// VP Academic Portal JS
const VpAcademicPortal = {
    async init() {
        const user = await Auth.requireAuth('eb_vp_academic');
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
        this.loadEvents();
    },

    async loadEvents() {
        const result = await App.api('vp-academic', 'list-events', { method: 'GET' });
        if (result.success) {
            this.renderEvents(result.data || []);
        }
    },

    renderEvents(events) {
        const container = document.getElementById('events-list');
        if (!container) return;

        if (events.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="icon">📅</div><h3>No Events</h3><p>Create your first event.</p></div>';
            return;
        }

        let html = '<div class="table-container"><table><thead><tr><th>Name</th><th>Date</th><th>Year</th><th>Actions</th></tr></thead><tbody>';
        events.forEach(ev => {
            html += `<tr>
                <td>${ev.name}</td>
                <td>${App.formatDate(ev.date)}</td>
                <td>${ev.academic_year}</td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="VpAcademicPortal.editEvent('${ev.id}')">Edit</button>
                    <button class="btn btn-sm btn-primary" onclick="VpAcademicPortal.showAttendance('${ev.id}')">Attendance</button>
                    <button class="btn btn-sm btn-danger" onclick="VpAcademicPortal.deleteEvent('${ev.id}')">Delete</button>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    },

    async createEvent(e) {
        e.preventDefault();
        const form = e.target;
        const data = {
            name: form.name.value,
            description: form.description?.value || '',
            date: form.date.value,
        };

        const result = await App.api('vp-academic', 'create-event', {
            method: 'POST', body: data,
        });

        if (result.success) {
            App.toast('Event created!', 'success');
            form.reset();
            this.loadEvents();
        } else {
            App.toast(result.message || 'Failed', 'error');
        }
    },

    async deleteEvent(eventId) {
        if (!confirm('Delete this event?')) return;
        const result = await App.api('vp-academic', 'delete-event', {
            method: 'POST', body: { event_id: eventId },
        });
        if (result.success) { App.toast('Event deleted', 'success'); this.loadEvents(); }
    },

    async showAttendance(eventId) {
        const result = await App.api('attendance', 'event-attendance', { method: 'GET' });
        // Load attendance for specific event
        const url = `${API_BASE}/attendance?action=event-attendance&event_id=${eventId}`;
        const token = Auth.getToken();
        const resp = await fetch(url, { headers: { 'Authorization': `Bearer ${token}` } });
        const data = await resp.json();

        const container = document.getElementById('attendance-view');
        if (container && data.success) {
            let html = '<div class="table-container"><table><thead><tr><th>Member</th><th>Institution</th><th>Time</th></tr></thead><tbody>';
            (data.data || []).forEach(a => {
                html += `<tr><td>${a.members?.full_name || 'N/A'}</td><td>${a.members?.institutions?.name || '-'}</td><td>${App.formatDateTime(a.recorded_at)}</td></tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }
    },

    async recordAttendance(memberId, eventId) {
        const result = await App.api('attendance', 'record', {
            method: 'POST',
            body: { member_id: memberId, event_id: eventId },
        });

        if (result.success) {
            App.toast('Attendance recorded!', 'success');
        } else {
            App.toast(result.message || 'Failed', 'error');
        }
    }
};
