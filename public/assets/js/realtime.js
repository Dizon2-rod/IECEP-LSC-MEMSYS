// Realtime Service for IECEP-LSC MEMSYS
// Handles Supabase Realtime subscriptions for live data synchronization

class RealtimeService {
    constructor() {
        this.subscriptions = [];
        this.supabaseUrl = window.SUPABASE_URL;
        this.supabaseKey = window.SUPABASE_ANON_KEY;
        this.isConnected = false;
    }

    // Initialize Supabase Realtime client
    async init() {
        try {
            // Load Supabase Realtime client
            const { RealtimeClient } = await import('https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/+esm');
            
            this.client = new RealtimeClient(this.supabaseUrl, {
                params: {
                    apiKey: this.supabaseKey
                }
            });

            this.isConnected = true;
            console.log('Realtime service initialized');
            
            // Handle connection state changes
            this.client.onOpen(() => {
                console.log('Realtime connection opened');
            });

            this.client.onClose(() => {
                console.log('Realtime connection closed');
                this.isConnected = false;
                // Attempt to reconnect after 5 seconds
                setTimeout(() => this.reconnect(), 5000);
            });

            await this.client.connect();
        } catch (error) {
            console.error('Failed to initialize realtime service:', error);
        }
    }

    // Reconnect to realtime
    async reconnect() {
        console.log('Attempting to reconnect to realtime...');
        await this.init();
    }

    // Subscribe to table changes
    async subscribeToTable(tableName, callback, filter = null) {
        if (!this.isConnected) {
            await this.init();
        }

        try {
            const channelName = `${tableName}_changes`;
            
            let channel = this.client.channel(channelName);
            
            const config = {
                event: 'postgres_changes',
                schema: 'public',
                table: tableName,
                filter: filter
            };

            channel
                .on(config, (payload) => {
                    console.log(`Realtime update for ${tableName}:`, payload);
                    callback(payload);
                })
                .subscribe((status) => {
                    console.log(`Subscription status for ${tableName}:`, status);
                });

            this.subscriptions.push(channel);
            return channel;
        } catch (error) {
            console.error(`Failed to subscribe to ${tableName}:`, error);
            return null;
        }
    }

    // Subscribe to pending_affiliations changes
    async subscribeToAffiliations(callback) {
        return await this.subscribeToTable('pending_affiliations', callback);
    }

    // Subscribe to institutions changes
    async subscribeToInstitutions(callback) {
        return await this.subscribeToTable('institutions', callback);
    }

    // Subscribe to user_profiles changes
    async subscribeToUserProfiles(callback) {
        return await this.subscribeToTable('user_profiles', callback);
    }

    // Unsubscribe from all channels
    async unsubscribeAll() {
        for (const channel of this.subscriptions) {
            await channel.unsubscribe();
        }
        this.subscriptions = [];
    }

    // Unsubscribe from specific channel
    async unsubscribe(channel) {
        await channel.unsubscribe();
        this.subscriptions = this.subscriptions.filter(ch => ch !== channel);
    }
}

// Export singleton instance
window.realtimeService = new RealtimeService();
