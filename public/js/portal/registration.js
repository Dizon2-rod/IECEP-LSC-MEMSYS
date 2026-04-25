// Registration Committee Portal JS
const RegistrationPortal = {
    async init() {
        const user = await Auth.requireAuth('committee_registration');
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
    },

    async loadPendingAffiliations() {
        const result = await App.api('registration', 'pending-affiliations', { method: 'GET' });
        if (result.success) {
            this.renderPendingAffiliations(result.data || []);
        }
    },

    renderPendingAffiliations(pending) {
        const container = document.getElementById('pending-affiliations');
        if (!container) return;

        if (pending.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="icon">📝</div><h3>No Pending Affiliations</h3><p>All applications have been reviewed.</p></div>';
            return;
        }

        let html = '';
        pending.forEach(pa => {
            const docs = pa.documents ? Object.keys(pa.documents) : [];
            html += `<div class="card mb-3">
                <div class="card-header">
                    <h3>${pa.institution_name}</h3>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success btn-sm" onclick="RegistrationPortal.approveAffiliation('${pa.id}')">Approve</button>
                        <button class="btn btn-danger btn-sm" onclick="RegistrationPortal.showRejectModal('${pa.id}')">Reject</button>
                    </div>
                </div>
                <p><strong>Email:</strong> ${pa.email}</p>
                <p><strong>Contact:</strong> ${pa.contact_person}</p>
                <p><strong>Address:</strong> ${pa.address || 'N/A'}</p>
                <p><strong>Submitted:</strong> ${App.formatDateTime(pa.submitted_at)}</p>
                <p><strong>Documents:</strong> ${docs.length} file(s)</p>
            </div>`;
        });
        container.innerHTML = html;
    },

    async approveAffiliation(pendingId) {
        if (!confirm('Approve this affiliation? This will create a school officer account.')) return;

        const result = await App.api('registration', 'approve-affiliation', {
            method: 'POST',
            body: { pending_id: pendingId },
        });

        if (result.success) {
            App.toast('Affiliation approved! School officer account created.', 'success');
            this.loadPendingAffiliations();
        } else {
            App.toast(result.message || 'Approval failed', 'error');
        }
    },

    showRejectModal(pendingId) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `<div class="modal">
            <h2>Reject Affiliation</h2>
            <div class="form-group">
                <label>Reason for Rejection</label>
                <textarea class="form-control" id="reject-reason" rows="3" placeholder="Provide reason..."></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                <button class="btn btn-danger" onclick="RegistrationPortal.rejectAffiliation('${pendingId}')">Reject</button>
            </div>
        </div>`;
        document.body.appendChild(modal);
    },

    async rejectAffiliation(pendingId) {
        const reason = document.getElementById('reject-reason')?.value;
        if (!reason) { App.toast('Please provide a reason', 'warning'); return; }

        const result = await App.api('registration', 'reject-affiliation', {
            method: 'POST',
            body: { pending_id: pendingId, reason },
        });

        if (result.success) {
            App.toast('Affiliation rejected', 'success');
            document.querySelector('.modal-overlay')?.remove();
            this.loadPendingAffiliations();
        } else {
            App.toast(result.message || 'Rejection failed', 'error');
        }
    },

    async loadPendingMembers() {
        const result = await App.api('registration', 'pending-members', { method: 'GET' });
        if (result.success) {
            this.renderPendingMembers(result.data || []);
        }
    },

    renderPendingMembers(batches) {
        const container = document.getElementById('pending-members');
        if (!container) return;

        if (batches.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="icon">👥</div><h3>No Pending Member Lists</h3></div>';
            return;
        }

        let html = '';
        batches.forEach(batch => {
            const instName = batch.institutions?.name || 'Unknown';
            const members = batch.pending_members || [];
            html += `<div class="card mb-3">
                <div class="card-header">
                    <h3>${instName}</h3>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success btn-sm" onclick="RegistrationPortal.approveAllMembers('${batch.id}')">Approve All</button>
                    </div>
                </div>
                <p><strong>File:</strong> ${batch.file_name} | <strong>Uploaded:</strong> ${App.formatDateTime(batch.uploaded_at)}</p>
                <div class="table-container">
                    <table><thead><tr><th><input type="checkbox" onchange="RegistrationPortal.toggleAllMembers(this.checked)"></th><th>Name</th><th>Email</th><th>Type</th><th>Year</th><th>Action</th></tr></thead><tbody>`;
            members.forEach(pm => {
                html += `<tr>
                    <td><input type="checkbox" class="pm-select" data-id="${pm.id}" checked></td>
                    <td>${pm.full_name}</td>
                    <td>${pm.email}</td>
                    <td><span class="badge badge-info">${pm.member_type}</span></td>
                    <td>${pm.year_level || '-'}</td>
                    <td><button class="btn btn-danger btn-sm" onclick="RegistrationPortal.rejectMember('${pm.id}', '${batch.id}')">Reject</button></td>
                </tr>`;
            });
            html += '</tbody></table></div></div>';
        });
        container.innerHTML = html;
    },

    toggleAllMembers(checked) {
        document.querySelectorAll('.pm-select').forEach(cb => cb.checked = checked);
    },

    async approveAllMembers(batchId) {
        const selected = document.querySelectorAll('.pm-select:checked');
        const ids = Array.from(selected).map(cb => cb.dataset.id);

        if (ids.length === 0) { App.toast('No members selected', 'warning'); return; }

        const result = await App.api('registration', 'approve-members', {
            method: 'POST',
            body: { batch_id: batchId, approved_ids: ids },
        });

        if (result.success) {
            App.toast('Members approved! Fees pending collection by Treasurer.', 'success');
            this.loadPendingMembers();
        } else {
            App.toast(result.message || 'Approval failed', 'error');
        }
    },

    async rejectMember(pmId, batchId) {
        const result = await App.api('registration', 'approve-members', {
            method: 'POST',
            body: { batch_id: batchId, approved_ids: [], rejected_ids: [pmId] },
        });

        if (result.success) {
            App.toast('Member rejected', 'success');
            this.loadPendingMembers();
        }
    }
};
