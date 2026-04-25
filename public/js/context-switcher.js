// IECEP-LSC MEMSYS - Role Context Switcher (Officer/Member)
const ContextSwitcher = {
    currentContext: 'officer', // default for officers
    STORAGE_KEY: 'iecep_context',

    init(userContext) {
        if (!userContext || !userContext.portals) return;

        const saved = localStorage.getItem(this.STORAGE_KEY);
        if (saved) this.currentContext = saved;

        // Only show switcher for officers
        if (!userContext.portals.officer) {
            this.currentContext = 'member';
            return;
        }

        this.render(userContext);
    },

    render(userContext) {
        const container = document.getElementById('role-switcher');
        if (!container) return;

        container.innerHTML = `
            <div class="role-switcher">
                <button class="${this.currentContext === 'officer' ? 'active' : ''}" data-context="officer">
                    ⚡ Officer Portal
                </button>
                <button class="${this.currentContext === 'member' ? 'active' : ''}" data-context="member">
                    🪪 Member Portal
                </button>
            </div>
        `;

        container.querySelectorAll('.role-switcher button').forEach(btn => {
            btn.addEventListener('click', () => {
                const ctx = btn.dataset.context;
                this.switch(ctx, userContext);
            });
        });
    },

    switch(context, userContext) {
        this.currentContext = context;
        localStorage.setItem(this.STORAGE_KEY, context);

        if (context === 'member') {
            window.location.href = '/portal/member/';
        } else {
            const path = App.getPortalPath(userContext.role);
            window.location.href = path;
        }
    },

    getCurrent() {
        return this.currentContext;
    }
};
