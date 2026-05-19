/**
 * Robust API Handler with JSON Response Validation
 * Handles non-JSON responses gracefully with detailed debugging
 */

class APIHandler {
    static async fetchJSON(url, options = {}) {
        try {
            const response = await fetch(url, options);
            
            // Check Content-Type header
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const responseText = await response.text();
                const preview = responseText.substring(0, 200);
                
                console.error('Non-JSON Response Detected:', {
                    status: response.status,
                    statusText: response.statusText,
                    contentType: contentType,
                    preview: preview,
                    fullResponse: responseText
                });
                
                throw new Error(
                    `Server returned non-JSON response (${response.status}). ` +
                    `Expected: application/json, Got: ${contentType}. ` +
                    `Response preview: ${preview}`
                );
            }
            
            // Parse JSON
            let data;
            try {
                data = await response.json();
            } catch (parseError) {
                const responseText = await response.text();
                console.error('JSON Parse Error:', {
                    error: parseError.message,
                    responseText: responseText.substring(0, 200)
                });
                throw new Error(`Failed to parse JSON response: ${parseError.message}`);
            }
            
            // Check HTTP status
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
            }
            
            return data;
        } catch (error) {
            console.error('API Request Failed:', error.message);
            throw error;
        }
    }
    
    static async getAuditLogs(page = 1, limit = 50, filters = {}) {
        const params = new URLSearchParams({
            action: 'list',
            page,
            limit,
            ...filters
        });
        
        return this.fetchJSON(`/api/super-admin/audit-logs.php?${params}`);
    }
    
    static async getAuditDetail(logId) {
        const params = new URLSearchParams({ action: 'detail', id: logId });
        return this.fetchJSON(`/api/super-admin/audit-logs.php?${params}`);
    }
    
    static async searchAuditLogs(query) {
        const params = new URLSearchParams({ action: 'search', q: query });
        return this.fetchJSON(`/api/super-admin/audit-logs.php?${params}`);
    }
}

// Usage Example:
/*
try {
    const logs = await APIHandler.getAuditLogs(1, 50, { user_id: '123' });
    console.log('Logs retrieved:', logs);
} catch (error) {
    console.error('Submission error:', error);
    // Display user-friendly error message
}
*/
