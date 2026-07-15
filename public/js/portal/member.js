// Member Portal JS
const MemberPortal = {
        memberData: null,

        async init() {
            const user = await Auth.requireAuth();
            if (!user) return;
            document.getElementById('user-name').textContent = user.full_name || user.email;
            document.getElementById('user-role').textContent = App.getRoleName(user.role);
            this.loadProfile();
        },

        async loadProfile() {
            const result = await App.api('member', 'profile', { method: 'GET' });
            if (result.success) {
                this.memberData = result.data;
                this.renderProfile(result.data);
            }
        },

        renderProfile(member) {
            const container = document.getElementById('member-profile');
            if (!container) return;

            const inst = member.institutions ? .name || 'N/A';
            const payBadge = member.payment_status ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-danger">Unpaid</span>';

            container.innerHTML = `
            <div class="card">
                <div class="card-header"><h3>Personal Information</h3></div>
                <div class="d-flex gap-2 flex-wrap">
                    <div class="form-group" style="flex:1;min-width:200px">
                        <label>Full Name</label>
                        <input type="text" class="form-control" id="edit-name" value="${member.full_name}">
                    </div>
                    <div class="form-group" style="flex:1;min-width:200px">
                        <label>Email</label>
                        <input type="email" class="form-control" value="${member.email}" disabled>
                    </div>
                    <div class="form-group" style="flex:1;min-width:200px">
                        <label>Year Level</label>
                        <input type="text" class="form-control" id="edit-year" value="${member.year_level || ''}">
                    </div>
                </div>
                <div class="mt-2">
                    <p><strong>Institution:</strong> ${inst}</p>
                    <p><strong>Member Type:</strong> <span class="badge badge-info">${member.member_type}</span></p>
                    <p><strong>Payment Status:</strong> ${payBadge}</p>
                </div>
                <button class="btn btn-primary mt-2" onclick="MemberPortal.saveProfile()">Save Changes</button>
            </div>`;
        },

        async saveProfile() {
            const fullName = document.getElementById('edit-name') ? .value;
            const yearLevel = document.getElementById('edit-year') ? .value;

            const result = await App.api('member', 'profile', {
                method: 'PUT',
                body: { full_name: fullName, year_level: yearLevel },
            });

            if (result.success) {
                App.toast('Profile updated!', 'success');
            } else {
                App.toast(result.message || 'Update failed', 'error');
            }
        },

        async loadDigitalId() {
            const result = await App.api('member', 'digital-id', { method: 'GET' });
            if (result.success) {
                this.renderDigitalId(result.data);
            }
        },

        renderDigitalId(data) {
            const container = document.getElementById('digital-id');
            if (!container) return;

            if (!data.digital_id_url) {
                container.innerHTML = '<div class="empty-state"><div class="icon">🪪</div><h3>Digital ID Not Available</h3><p>Your digital ID will be available after payment is confirmed.</p></div>';
                return;
            }

            container.innerHTML = `
            <div class="card text-center">
                <img src="${data.digital_id_url}" alt="Digital ID" style="max-width:600px;width:100%;border-radius:12px;box-shadow:var(--shadow-lg)">
                <div class="mt-2">
                    <a href="${data.digital_id_url}" download class="btn btn-primary">Download ID</a>
                    <button class="btn btn-outline" onclick="window.print()">Print</button>
                </div>
            </div>`;
        },

        async loadAttendance() {
            const result = await App.api('attendance', 'my-attendance', { method: 'GET' });
            if (result.success) {
                this.renderAttendance(result);
            }
        },

        renderAttendance(data) {
            const container = document.getElementById('attendance-history');
            if (!container) return;

            const attendance = data.attendance || [];
            if (attendance.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="icon">📅</div><h3>No Attendance Records</h3></div>';
                return;
            }

            let html = `<div class="stats-grid">
            <div class="stat-card"><div class="stat-icon primary">📅</div><div class="stat-info"><h4>${data.events_attended}</h4><p>Events Attended</p></div></div>
            <div class="stat-card"><div class="stat-icon success">🏫</div><div class="stat-info"><h4>${data.institution_total_paid}</h4><p>Total Paid Members</p></div></div>
        </div>`;

            html += '<div class="table-container"><table><thead><tr><th>Event</th><th>Date</th><th>Status</th></tr></thead><tbody>';
            attendance.forEach(a => {
                html += `<tr>
                <td>${a.events?.name || 'Unknown'}</td>
                <td>${App.formatDate(a.events?.date)}</td>
                <td><span class="badge badge-success">Present</span></td>
            </tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        },

        async loadFeeStatus() {
            const result = await App.api('member', 'fee-status', { method: 'GET' });
            if (result.success) {
                this.renderFeeStatus(result);
            }
        },

        renderFeeStatus(data) {
            const container = document.getElementById('fee-status');
            if (!container) return;

            const paid = data.payment_status;
            const tx = data.transaction;

            container.innerHTML = `<div class="card">
            <h3>Membership Fee Status</h3>
            <div class="mt-2">
                ${paid
                    ? `<p><span class="badge badge-success">Paid</span></p>
                       ${tx ? `
                       <p class="mt-1"><strong>Receipt:</strong> <code>${tx.receipt_id}</code></p>
                       <p><strong>Amount:</strong> ${App.formatCurrency(tx.amount)}</p>
                       <p><strong>Date:</strong> ${App.formatDate(tx.paid_at)}</p>
                       ${tx.receipt_url ? `<a href="${tx.receipt_url}" target="_blank" class="btn btn-outline btn-sm mt-1">Download Receipt</a>` : ''}
                       ` : ''}`
                    : `<p><span class="badge badge-danger">Unpaid</span></p><p class="mt-1 text-muted">Please coordinate with your school officer or treasurer for payment.</p>`
                }
            </div>
        </div>`;
    },

    async loadAnnouncements() {
        const result = await App.api('member', 'announcements', { method: 'GET' });
        if (result.success) {
            this.renderAnnouncements(result.data || []);
        }
    },

    renderAnnouncements(announcements) {
        const container = document.getElementById('announcements');
        if (!container) return;

        if (announcements.length === 0) {
            container.innerHTML = '<div class="empty-state"><h3>No Announcements</h3></div>';
            return;
        }

        let html = '';
        announcements.forEach(a => {
            html += `<div class="card mb-2">
                <h4>${a.title}</h4>
                <div class="text-muted mb-1">${App.formatDateTime(a.sent_at)}</div>
                <div>${a.content}</div>
            </div>`;
        });
        container.innerHTML = html;
    }
};