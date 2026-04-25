// VP External Portal JS
const VpExternalPortal = {
    async init() {
        const user = await Auth.requireAuth('eb_vp_external');
        if (!user) return;
        document.getElementById('user-name').textContent = user.full_name || user.email;
        document.getElementById('user-role').textContent = App.getRoleName(user.role);
        ContextSwitcher.init(user);
        this.loadInstitutions();
    },

    async loadInstitutions() {
        const result = await App.api('compliance', 'status', { method: 'GET' });
        if (result.success) {
            this.renderInstitutions(result.data || []);
        }
    },

    renderInstitutions(institutions) {
        const container = document.getElementById('institutions-list');
        if (!container) return;

        let html = '<div class="table-container"><table><thead><tr><th>Institution</th><th>Status</th><th>Compliance</th><th>Affiliation Fee</th></tr></thead><tbody>';
        institutions.forEach(inst => {
            const status = inst.compliance_status || 'non_compliant';
            const badge = status === 'compliant' ? 'badge-success' : status === 'at_risk' ? 'badge-warning' : 'badge-danger';
            const fee = inst.affiliation_fee_paid ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-warning">Unpaid</span>';

            html += `<tr>
                <td>${inst.institution_name}</td>
                <td><span class="badge ${badge}">${status.replace('_', ' ')}</span></td>
                <td>${inst.latest_record?.participation_rate || 0}%</td>
                <td>${fee}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html || '<div class="empty-state"><h3>No Institutions</h3></div>';
    }
};
