// School Officer (HEI) Portal JS
const OfficerPortal = {
    async init() {
        const user = await Auth.requireAuth('school_officer');
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
        this.loadDashboard();
    },

    async loadDashboard() {
        const result = await App.api('officer', 'dashboard', { method: 'GET' });
        if (result.success) {
            this.renderDashboard(result);
        }
    },

    renderDashboard(data) {
        const inst = data.institution;
        const stats = data.stats;

        document.getElementById('institution-name').textContent = inst?.name || 'N/A';
        document.getElementById('stat-total').textContent = stats.total_members;
        document.getElementById('stat-paid').textContent = stats.paid_members;
        document.getElementById('stat-unpaid').textContent = stats.unpaid_members;

        if (data.compliance) {
            const statusEl = document.getElementById('compliance-status');
            const rate = data.compliance.participation_rate;
            statusEl.textContent = `${rate}% - ${data.compliance.status.replace('_', ' ').toUpperCase()}`;
            statusEl.className = `badge badge-${data.compliance.status === 'compliant' ? 'success' : data.compliance.status === 'at_risk' ? 'warning' : 'danger'}`;
        }
    },

    async uploadMembers(e) {
        e.preventDefault();
        const form = e.target;
        const fileInput = form.querySelector('input[type="file"]');
        const file = fileInput.files[0];

        if (!file) { App.toast('Select a CSV file', 'warning'); return; }
        if (!file.name.endsWith('.csv')) { App.toast('Only CSV files allowed', 'error'); return; }

        const formData = new FormData();
        formData.append('csv_file', file);

        const token = Auth.getToken();
        const result = await App.api('officer', 'upload-members', {
            method: 'POST',
            formData: true,
            body: formData,
        });

        if (result.success) {
            App.toast(`${result.count} members uploaded! Awaiting Registration Committee approval.`, 'success');
            form.reset();
        } else {
            App.toast(result.message || result.error || 'Upload failed', 'error');
        }
    },

    async loadMembers() {
        const result = await App.api('officer', 'my-members', { method: 'GET' });
        if (result.success) {
            this.renderMembers(result.data || []);
        }
    },

    renderMembers(members) {
        const container = document.getElementById('members-list');
        if (!container) return;

        if (members.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="icon">👥</div><h3>No Members Yet</h3><p>Upload a CSV to add members.</p></div>';
            return;
        }

        let html = '<div class="table-container"><table><thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Year</th><th>Payment</th><th>ID</th></tr></thead><tbody>';
        members.forEach(m => {
            const payBadge = m.payment_status ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-warning">Unpaid</span>';
            const idLink = m.digital_id_url ? `<a href="${m.digital_id_url}" target="_blank">View</a>` : '-';
            html += `<tr>
                <td>${m.full_name}</td>
                <td>${m.email}</td>
                <td><span class="badge badge-info">${m.member_type}</span></td>
                <td>${m.year_level || '-'}</td>
                <td>${payBadge}</td>
                <td>${idLink}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    },

    downloadTemplate() {
        window.location.href = '/api/officer?action=download-template';
    }
};
