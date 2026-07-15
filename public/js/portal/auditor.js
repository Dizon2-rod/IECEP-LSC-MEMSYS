// Auditor Portal JS
const AuditorPortal = {
    async init() {
        const user = await Auth.requireAuth('eb_auditor');
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
        this.loadTransactions();
    },

    async loadTransactions() {
        const result = await App.api('treasurer', 'transactions', { method: 'GET' });
        if (result.success) {
            this.renderTransactions(result.data || []);
        }
    },

    renderTransactions(transactions) {
        const container = document.getElementById('transactions-list');
        if (!container) return;

        let html = '<div class="table-container"><table><thead><tr><th>Receipt ID</th><th>Payer</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        transactions.forEach(tx => {
            const payer = tx.members ? .full_name || tx.institutions ? .name || 'N/A';
            const status = tx.status === 'paid' ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-warning">Pending</span>';
            html += `<tr>
                <td><code>${tx.receipt_id}</code></td>
                <td>${payer}</td>
                <td>${App.formatCurrency(tx.amount)}</td>
                <td>${App.formatDate(tx.paid_at)}</td>
                <td>${status}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="AuditorPortal.showFlagModal('${tx.id}', '${tx.receipt_id}')">Flag</button>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    },

    showFlagModal(transactionId, receiptId) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `<div class="modal">
            <h2>Flag Discrepancy</h2>
            <p>Flagging receipt: <code>${receiptId}</code></p>
            <div class="form-group">
                <label>Reason</label>
                <textarea class="form-control" id="flag-reason" rows="3" placeholder="Describe the discrepancy..."></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                <button class="btn btn-danger" onclick="AuditorPortal.flagDiscrepancy('${transactionId}')">Flag</button>
            </div>
        </div>`;
        document.body.appendChild(modal);
    },

    async flagDiscrepancy(transactionId) {
        const reason = document.getElementById('flag-reason') ? .value;
        if (!reason) { App.toast('Please provide a reason', 'warning'); return; }

        const result = await App.api('auditor', 'flag-discrepancy', {
            method: 'POST',
            body: { transaction_id: transactionId, reason },
        });

        if (result.success) {
            App.toast('Discrepancy flagged', 'success');
            document.querySelector('.modal-overlay') ? .remove();
        } else {
            App.toast(result.message || 'Failed to flag', 'error');
        }
    },

    async loadAuditLogs() {
        const result = await App.api('auditor', 'audit-logs', { method: 'GET' });
        if (result.success) {
            this.renderAuditLogs(result.data || []);
        }
    },

    renderAuditLogs(logs) {
        const container = document.getElementById('audit-logs');
        if (!container) return;

        let html = '<div class="table-container"><table><thead><tr><th>Date</th><th>Action</th><th>Details</th></tr></thead><tbody>';
        logs.forEach(log => {
            html += `<tr>
                <td>${App.formatDateTime(log.created_at)}</td>
                <td><span class="badge badge-danger">${log.action}</span></td>
                <td>${JSON.stringify(log.details)}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }
};