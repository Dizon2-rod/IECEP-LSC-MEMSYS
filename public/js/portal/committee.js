// Committee Portal JS (shared by all committees)
const CommitteePortal = {
    committeeName: '',

    async init() {
        const user = await Auth.requireAuth([
            'committee_creatives', 'committee_documentation', 'committee_logistics',
            'committee_marketing', 'committee_registration', 'committee_technical',
        ]);
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        this.committeeName = user.role.replace('committee_', '');
        ContextSwitcher.init(user);
        this.loadTasks();
    },

    async loadTasks() {
        const result = await App.api('committee', 'tasks', { method: 'GET' });
        if (result.success) {
            this.renderTasks(result.data || []);
        }
    },

    renderTasks(tasks) {
        const container = document.getElementById('committee-tasks');
        if (!container) return;

        if (tasks.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="icon">📋</div><h3>No Tasks</h3></div>';
            return;
        }

        let html = '<div class="table-container"><table><thead><tr><th>Title</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        tasks.forEach(t => {
            const statusBadge = t.status === 'completed' ? 'badge-success' : t.status === 'in_progress' ? 'badge-warning' : 'badge-info';
            html += `<tr>
                <td>${t.title}</td>
                <td>${t.description || '-'}</td>
                <td><span class="badge ${statusBadge}">${t.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-success" onclick="CommitteePortal.updateTask('${t.id}', 'completed')">Complete</button>
                    <button class="btn btn-sm btn-danger" onclick="CommitteePortal.deleteTask('${t.id}')">Delete</button>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    },

    async createTask(e) {
        e.preventDefault();
        const form = e.target;
        const result = await App.api('committee', 'tasks', {
            method: 'POST',
            body: { title: form.title.value, description: form.description?.value || '' },
        });

        if (result.success) {
            App.toast('Task created!', 'success');
            form.reset();
            this.loadTasks();
        } else {
            App.toast(result.message || 'Failed', 'error');
        }
    },

    async updateTask(taskId, status) {
        const result = await App.api('committee', 'tasks', {
            method: 'PUT',
            body: { task_id: taskId, status },
        });
        if (result.success) { App.toast('Task updated', 'success'); this.loadTasks(); }
    },

    async deleteTask(taskId) {
        if (!confirm('Delete this task?')) return;
        const result = await App.api('committee', 'tasks', {
            method: 'DELETE',
            body: { task_id: taskId },
        });
        if (result.success) { App.toast('Task deleted', 'success'); this.loadTasks(); }
    },

    async uploadAsset(e) {
        e.preventDefault();
        const form = e.target;
        const fileInput = form.querySelector('input[type="file"]');
        const file = fileInput.files[0];
        if (!file) { App.toast('Select a file', 'warning'); return; }

        const formData = new FormData();
        formData.append('file', file);

        const result = await App.api('committee', 'upload-asset', {
            method: 'POST',
            formData: true,
            body: formData,
        });

        if (result.success) {
            App.toast('File uploaded!', 'success');
            form.reset();
        } else {
            App.toast(result.message || 'Upload failed', 'error');
        }
    }
};
