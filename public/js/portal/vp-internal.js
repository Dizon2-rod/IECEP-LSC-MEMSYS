// VP Internal Portal JS
const VpInternalPortal = {
    async init() {
        const user = await Auth.requireAuth('eb_vp_internal');
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
        this.loadCompliance();
    },

    async loadCompliance() {
        const result = await App.api('compliance', 'status', { method: 'GET' });
        if (result.success) {
            this.renderCompliance(result.data || []);
        }
    },

    renderCompliance(institutions) {
        const container = document.getElementById('compliance-table');
        if (!container) return;

        if (institutions.length === 0) {
            container.innerHTML = '<div class="empty-state"><h3>No Institutions</h3></div>';
            return;
        }

        let html = '<div class="table-container"><table><thead><tr><th>Institution</th><th>Compliance</th><th>Rate</th><th>Affiliation Fee</th></tr></thead><tbody>';
        institutions.forEach(inst => {
            const rate = inst.latest_record?.participation_rate || 0;
            const status = inst.compliance_status || 'non_compliant';
            const badge = status === 'compliant' ? 'badge-success' : status === 'at_risk' ? 'badge-warning' : 'badge-danger';
            const feeBadge = inst.affiliation_fee_paid ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-danger">Unpaid</span>';

            html += `<tr>
                <td>${inst.institution_name}</td>
                <td><span class="badge ${badge}">${status.replace('_', ' ').toUpperCase()}</span></td>
                <td>${rate}%</td>
                <td>${feeBadge}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    },

    async recalculate() {
        const result = await App.api('compliance', 'calculate', { method: 'POST' });
        if (result.success) {
            App.toast('Compliance recalculated!', 'success');
            this.loadCompliance();
        }
    }
};
