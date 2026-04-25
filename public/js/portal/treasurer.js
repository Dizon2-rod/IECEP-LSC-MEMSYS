// Treasurer Portal JS
const TreasurerPortal = {
    pendingMembers: [],
    transactions: [],

    async init() {
        const user = await Auth.requireAuth('eb_treasurer');
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
        this.loadPendingPayments();
    },

    async loadPendingPayments() {
        const result = await App.api('treasurer', 'pending-member-payments', { method: 'GET' });
        if (result.success) {
            this.pendingMembers = result.data || [];
            this.renderPendingPayments();
        }
    },

    renderPendingPayments() {
        const container = document.getElementById('pending-payments');
        if (!container) return;

        if (this.pendingMembers.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="icon">💰</div><h3>No Pending Payments</h3><p>All member fees have been processed.</p></div>';
            return;
        }

        // Group by institution
        const grouped = {};
        this.pendingMembers.forEach(pm => {
            const instName = pm.member_upload_batches?.institutions?.name || 'Unknown';
            if (!grouped[instName]) grouped[instName] = [];
            grouped[instName].push(pm);
        });

        let html = '';
        for (const [instName, members] of Object.entries(grouped)) {
            html += `<div class="card mb-3">
                <div class="card-header">
                    <h3>${instName}</h3>
                    <button class="btn btn-primary btn-sm" onclick="TreasurerPortal.selectSchool('${instName}')">Select All</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead><tr>
                            <th><input type="checkbox" id="check-all-${instName.replace(/\s/g,'')}" onchange="TreasurerPortal.toggleAll('${instName.replace(/\s/g,'')}', this.checked)"></th>
                            <th>Name</th><th>Email</th><th>Type</th><th>Fee</th><th>Action</th>
                        </tr></thead>
                        <tbody>`;
            members.forEach(pm => {
                const fee = pm.member_type === 'new' ? '₱250' : pm.member_type === 'honorary' ? '₱300' : '₱200';
                html += `<tr>
                    <td><input type="checkbox" class="pm-check inst-${instName.replace(/\s/g,'')}" data-id="${pm.id}" checked></td>
                    <td>${pm.full_name}</td>
                    <td>${pm.email}</td>
                    <td><span class="badge badge-info">${pm.member_type}</span></td>
                    <td>${fee}</td>
                    <td><button class="btn btn-success btn-sm" onclick="TreasurerPortal.markPaid(['${pm.id}'])">Mark Paid</button></td>
                </tr>`;
            });
            html += `</tbody></table></div></div>`;
        }

        container.innerHTML = html + `
            <div class="mt-3">
                <button class="btn btn-primary btn-lg" onclick="TreasurerPortal.markSelectedPaid()">Mark Selected as Paid</button>
            </div>`;
    },

    selectSchool(instKey) {
        document.querySelectorAll(`.inst-${instKey}`).forEach(cb => cb.checked = true);
    },

    toggleAll(instKey, checked) {
        document.querySelectorAll(`.inst-${instKey}`).forEach(cb => cb.checked = checked);
    },

    getSelectedIds() {
        const checked = document.querySelectorAll('.pm-check:checked');
        return Array.from(checked).map(cb => cb.dataset.id);
    },

    async markPaid(ids) {
        if (ids.length === 0) {
            App.toast('Select at least one member', 'warning');
            return;
        }

        if (!confirm(`Mark ${ids.length} member(s) as paid? This will create accounts and record blockchain transactions.`)) return;

        const result = await App.api('treasurer', 'mark-members-paid', {
            method: 'POST',
            body: { pending_member_ids: ids },
        });

        if (result.success) {
            const count = result.results?.length || 0;
            App.toast(`${count} member(s) processed successfully!`, 'success');
            if (result.errors?.length > 0) {
                App.toast(`${result.errors.length} error(s) occurred`, 'warning');
            }
            this.loadPendingPayments();
        } else {
            App.toast(result.message || 'Payment processing failed', 'error');
        }
    },

    markSelectedPaid() {
        const ids = this.getSelectedIds();
        this.markPaid(ids);
    },

    async loadTransactions() {
        const result = await App.api('treasurer', 'transactions', { method: 'GET' });
        if (result.success) {
            this.transactions = result.data || [];
            this.renderTransactions();
        }
    },

    renderTransactions() {
        const container = document.getElementById('transactions-list');
        if (!container) return;

        if (this.transactions.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="icon">📋</div><h3>No Transactions</h3></div>';
            return;
        }

        let html = '<div class="table-container"><table><thead><tr><th>Receipt ID</th><th>Payer</th><th>Institution</th><th>Amount</th><th>Date</th><th>Blockchain</th></tr></thead><tbody>';
        this.transactions.forEach(tx => {
            const payer = tx.members?.full_name || tx.institutions?.name || 'N/A';
            const inst = tx.institutions?.name || '-';
            const onChain = tx.blockchain_tx_hash ? '<span class="badge badge-onchain">On-Chain</span>' : '<span class="badge badge-warning">Pending</span>';
            html += `<tr>
                <td><code>${tx.receipt_id}</code></td>
                <td>${payer}</td>
                <td>${inst}</td>
                <td>${App.formatCurrency(tx.amount)}</td>
                <td>${App.formatDate(tx.paid_at)}</td>
                <td>${onChain}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    },

    async generateReport() {
        const result = await App.api('treasurer', 'report', { method: 'POST' });
        // Response is a PDF download
    }
};
