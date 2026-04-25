// IECEP-LSC MEMSYS - Supabase Authentication Module
// Load Supabase client
const supabaseUrl = 'https://kfvlbjvtwtxnpmmswadf.supabase.co';
const supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtmdmxianZ0d3R4bnBtbXN3YWRmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY0MDY0ODEsImV4cCI6MjA5MTk4MjQ4MX0.4o-RyygAaEnM61wfvc24xWGXMe3jVqZLPvh8bXUYxkg';

// Load Supabase from CDN
let supabase;
(async function() {
    try {
        const { createClient } = await
        import ('https://cdn.skypack.dev/@supabase/supabase-js');
        supabase = createClient(supabaseUrl, supabaseKey);
    } catch (error) {
        console.error('Failed to load Supabase:', error);
    }
})();

const SupabaseAuth = {
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
        supabase.auth.signOut();
        window.location.href = '/login.php';
    },

    isLoggedIn() {
        return !!this.getToken();
    },

    async login(email, password) {
        try {
            const { data, error } = await supabase.auth.signInWithPassword({
                email: email,
                password: password
            });

            if (error) {
                return { success: false, message: error.message };
            }

            // Get user profile from our database
            const { data: profileData, error: profileError } = await supabase
                .from('user_profiles')
                .select('role, full_name, force_password_change')
                .eq('user_id', data.user.id)
                .single();

            if (profileError && profileError.code !== 'PGRST116') {
                console.error('Profile fetch error:', profileError);
            }

            // Get member details
            const { data: memberData, error: memberError } = await supabase
                .from('members')
                .select('full_name, email, member_type, payment_status, year_level, institutions(name)')
                .eq('user_id', data.user.id)
                .single();

            if (memberError && memberError.code !== 'PGRST116') {
                console.error('Member fetch error:', memberError);
            }

            const userWithProfile = {
                ...data.user,
                profile: profileData || null,
                member: memberData || null
            };

            this.setSession(data.session.access_token, userWithProfile);
            return { success: true, user: userWithProfile };

        } catch (err) {
            console.error('Login error:', err);
            return { success: false, message: 'Login failed. Please try again.' };
        }
    },

    async changePassword(newPassword) {
        try {
            const { data, error } = await supabase.auth.updateUser({
                password: newPassword
            });

            if (error) {
                return { success: false, message: error.message };
            }

            // Update force_password_change flag in profile
            const user = this.getUser();
            if (user && user.profile) {
                const { error: updateError } = await supabase
                    .from('user_profiles')
                    .update({ force_password_change: false })
                    .eq('user_id', user.id);

                if (updateError) {
                    console.error('Profile update error:', updateError);
                }

                // Update local user data
                user.profile.force_password_change = false;
                this.setSession(this.getToken(), user);
            }

            return { success: true };

        } catch (err) {
            console.error('Password change error:', err);
            return { success: false, message: 'Failed to change password' };
        }
    },

    async getContext() {
        try {
            const { data: { session }, error } = await supabase.auth.getSession();

            if (error || !session) {
                return { success: false, error: 'No active session' };
            }

            // Get fresh profile data
            const { data: profileData } = await supabase
                .from('user_profiles')
                .select('role, full_name, force_password_change')
                .eq('user_id', session.user.id)
                .single();

            const { data: memberData } = await supabase
                .from('members')
                .select('full_name, email, member_type, payment_status, year_level, institutions(name)')
                .eq('user_id', session.user.id)
                .single();

            return {
                success: true,
                user: {
                    ...session.user,
                    profile: profileData,
                    member: memberData
                }
            };

        } catch (err) {
            console.error('Context error:', err);
            return { success: false, error: 'Failed to get user context' };
        }
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

        const path = this.getPortalPath(profile.role);
        window.location.href = path;
    },

    // Get portal path based on role
    getPortalPath(role) {
        const paths = {
            // Executive Board
            'eb_president': '/portal/president/',
            'eb_vp_internal': '/portal/vp-internal/',
            'eb_vp_external': '/portal/vp-external/',
            'eb_vp_academic': '/portal/vp-academic/',
            'eb_secretary_general': '/portal/secretary/',
            'eb_assistant_secretary': '/portal/asst-secretary/',
            'eb_treasurer': '/portal/treasurer/',
            'eb_auditor': '/portal/auditor/',
            'eb_pro_1': '/portal/pro/',
            'eb_pro_2': '/portal/pro/',

            // Committee Members
            'committee_creatives': '/portal/committee/creatives/',
            'committee_documentation': '/portal/committee/documentation/',
            'committee_logistics': '/portal/committee/logistics/',
            'committee_marketing': '/portal/committee/marketing/',
            'committee_registration': '/portal/committee/registration/',
            'committee_technical': '/portal/committee/technical/',

            // School Officers
            'school_officer': '/portal/school/',

            // Regular Members
            'member': '/portal/member/'
        };

        return paths[role] || '/portal/member/';
    },

    // Check if on correct portal page
    async requireAuth(requiredRole = null) {
        if (!this.isLoggedIn()) {
            window.location.href = '/login.php';
            return null;
        }

        const result = await this.getContext();
        if (!result.success) {
            this.logout();
            return null;
        }

        if (requiredRole) {
            const roles = Array.isArray(requiredRole) ? requiredRole : [requiredRole];
            const userRole = result.user.profile && result.user.profile.role;
            if (!roles.includes(userRole)) {
                App.toast('Access denied', 'error');
                const path = this.getPortalPath(userRole);
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
        if (result.success && result.user.profile && result.user.profile.force_password_change) {
            return true;
        }
        return false;
    }
};

// Export for use in other modules
window.SupabaseAuth = SupabaseAuth;