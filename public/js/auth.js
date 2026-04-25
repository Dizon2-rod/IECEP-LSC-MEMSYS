// IECEP-LSC MEMSYS - Authentication Module
const Auth = {
    TOKEN_KEY: 'iecep_token',
    USER_KEY: 'iecep_user',

    getToken() {
        return localStorage.getItem(this.TOKEN_KEY);
    },

    getUser() {
        const data = localStorage.getItem(this.USER_KEY);
        return data ? JSON.parse(data) : null;
    },

    setSession(token, user) {
        localStorage.setItem(this.TOKEN_KEY, token);
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
    },

    logout() {
        localStorage.removeItem(this.TOKEN_KEY);
        localStorage.removeItem(this.USER_KEY);
        window.location.href = '/login.html';
    },

    isLoggedIn() {
        return !!this.getToken();
    },

    async login(email, password) {
        const result = await App.api('auth', 'login', {
            method: 'POST',
            body: { email, password },
        });

        if (result.error) {
            return result;
        }

        this.setSession(result.access_token, result.user);
        return { success: true, user: result.user };
    },

    async changePassword(newPassword) {
        const result = await App.api('auth', 'change-password', {
            method: 'POST',
            body: { new_password: newPassword },
        });
        return result;
    },

    async getContext() {
        const result = await App.api('auth', 'context', { method: 'GET' });
        return result;
    },

    // Redirect based on role after login
    redirectAfterLogin(user) {
        if (!user) return;
        const profile = user.profile;
        if (!profile) return;

        if (profile.force_password_change) {
            window.location.href = '/login.html?force_change=1';
            return;
        }

        const path = App.getPortalPath(profile.role);
        window.location.href = path;
    },

    // Check if on correct portal page
    async requireAuth(requiredRole = null) {
        if (!this.isLoggedIn()) {
            window.location.href = '/login.html';
            return null;
        }

        const result = await this.getContext();
        if (result.error) {
            this.logout();
            return null;
        }

        if (requiredRole) {
            const roles = Array.isArray(requiredRole) ? requiredRole : [requiredRole];
            if (!roles.includes(result.role)) {
                App.toast('Access denied', 'error');
                const path = App.getPortalPath(result.role);
                window.location.href = path;
                return null;
            }
        }

        return result;
    },

    // Force password change check
    async checkForcePasswordChange() {
        const user = this.getUser();
        if (user && user.profile && user.profile.force_password_change) {
            return true;
        }

        const result = await this.getContext();
        if (result.success && result.force_password_change) {
            return true;
        }
        return false;
    }
};