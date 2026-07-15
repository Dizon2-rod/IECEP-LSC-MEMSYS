// Supabase Real-time Subscriptions
// This file handles real-time updates for creatives portal pages

// Load Supabase client from CDN
const script = document.createElement('script');
script.src = 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2';
script.onload = initSupabaseRealtime;
document.head.appendChild(script);

let supabase = null;
let subscriptions = [];

function initSupabaseRealtime() {
    // Get Supabase config from PHP (exposed as global variables)
    const supabaseUrl = window.SUPABASE_URL || 'https://your-project.supabase.co';
    const supabaseKey = window.SUPABASE_ANON_KEY || 'your-anon-key';
    
    supabase = window.supabase.createClient(supabaseUrl, supabaseKey);
    
    // Determine current page and subscribe accordingly
    const currentPage = window.location.pathname;
    
    if (currentPage.includes('announcements.php')) {
        subscribeToTable('creatives_announcements');
    } else if (currentPage.includes('graphics.php')) {
        subscribeToTable('creatives_graphics');
    } else if (currentPage.includes('publications.php')) {
        subscribeToTable('creatives_publications');
    } else if (currentPage.includes('team.php')) {
        subscribeToTable('creatives_team');
    } else if (currentPage.includes('features-manager.php')) {
        subscribeToTable('creatives_features');
    } else if (currentPage.includes('index.php') || currentPage === '/') {
        subscribeToTable('creatives_features');
    }
}

function subscribeToTable(tableName) {
    if (!supabase) return;
    
    // Subscribe to all changes on the table
    const subscription = supabase
        .channel(`realtime-${tableName}`)
        .on('postgres_changes', { event: '*', schema: 'public', table: tableName }, (payload) => {
            handleRealtimeUpdate(payload, tableName);
        })
        .subscribe((status) => {
            console.log(`Real-time subscription to ${tableName}:`, status);
        });
    
    subscriptions.push(subscription);
}

function handleRealtimeUpdate(payload, tableName) {
    console.log('Real-time update received:', payload);
    
    // Refresh the page to show updated data
    // For a smoother experience, you could implement partial UI updates here
    // but for simplicity, we'll refresh the page
    setTimeout(() => {
        location.reload();
    }, 500);
}

// Cleanup subscriptions when page is unloaded
window.addEventListener('beforeunload', () => {
    subscriptions.forEach(subscription => {
        if (subscription) {
            supabase.removeChannel(subscription);
        }
    });
});

// Expose config from PHP to JavaScript (add this to your PHP pages before including this script)
// window.SUPABASE_URL = 'your-project-url';
// window.SUPABASE_ANON_KEY = 'your-anon-key';
