<?php
namespace App\Lib;

// Supabase REST API Client for PHP
class SupabaseClient {
    private $url;
    private $key;
    private $headers;
    
    public function __construct($url = null, $key = null) {
        // Load from environment or config
        $this->url = $url ?? getenv('SUPABASE_URL') ?? 'https://your-project.supabase.co';
        $this->key = $key ?? getenv('SUPABASE_ANON_KEY') ?? 'your-anon-key';
        
        $this->headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }
    
    // Set service role key for admin operations (bypasses RLS)
    public function setServiceRoleKey($serviceKey) {
        $this->key = $serviceKey;
        $this->headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }
    
    // Generic request method
    private function request($method, $table, $data = null, $id = null) {
        $endpoint = $this->url . '/rest/v1/' . $table;
        if ($id) {
            $endpoint .= '/' . $id;
        }
        
        error_log("Supabase Request: $method $endpoint");
        if ($data !== null) {
            error_log("Supabase Data: " . json_encode($data));
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("Supabase Response: HTTP $httpCode, Response: $response");
        if ($curlError) {
            error_log("Supabase cURL Error: $curlError");
        }
        
        if ($curlError) {
            throw new \Exception("Supabase cURL Error: $curlError");
        }
        
        if ($httpCode >= 400) {
            throw new \Exception("Supabase API Error: HTTP $httpCode - $response");
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Supabase JSON Decode Error: " . json_last_error_msg());
            throw new \Exception("Supabase JSON Decode Error: " . json_last_error_msg());
        }
        
        return $decodedResponse;
    }
    
    // SELECT - Get all records
    public function select($table, $filters = []) {
        $endpoint = $this->url . '/rest/v1/' . $table;
        
        if (!empty($filters)) {
            $endpoint .= '?' . http_build_query($filters);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    // INSERT - Create new record
    public function insert($table, $data) {
        return $this->request('POST', $table, $data);
    }
    
    // UPDATE - Update record
    public function update($table, $data, $id = null, $filterColumn = 'id') {
        // Build filter query
        $filterQuery = '';
        
        if (is_array($id)) {
            // If $id is an array, it's a filter object
            $filters = [];
            foreach ($id as $key => $value) {
                // Add 'eq.' prefix if not already present
                if (!preg_match('/^(eq|neq|gt|gte|lt|lte|like|in|is)\./', $value)) {
                    $filters[$key] = 'eq.' . $value;
                } else {
                    $filters[$key] = $value;
                }
            }
            $filterQuery = http_build_query($filters);
        } else if (!empty($id)) {
            // Single ID filter
            $filterQuery = http_build_query([$filterColumn => 'eq.' . $id]);
        } else {
            throw new \Exception("Update requires an ID or filter");
        }
        
        $endpoint = $this->url . '/rest/v1/' . $table . '?' . $filterQuery;
        error_log("Supabase Update: URL = $endpoint, Data = " . json_encode($data));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        error_log("Supabase Update: HTTP Code = $httpCode, Response = $response");
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new \Exception("Supabase API Error: HTTP $httpCode - $response");
        }
        return json_decode($response, true);
    }
    
    // DELETE - Delete record
    public function delete($table, $id) {
        // For integer IDs, use filter instead of direct ID endpoint
        if (is_numeric($id)) {
            $endpoint = $this->url . '/rest/v1/' . $table . '?id=eq.' . $id;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            $response = curl_exec($ch);
            curl_close($ch);
            return json_decode($response, true);
        }
        return $this->request('DELETE', $table, null, $id);
    }
    
    // Upsert (insert or update)
    public function upsert($table, $data) {
        $headers = array_merge($this->headers, ['Prefer: resolution=ignore-duplicates,return=representation']);
        $endpoint = $this->url . '/rest/v1/' . $table;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    // Get single record by ID
    public function getById($table, $id) {
        return $this->request('GET', $table, null, $id);
    }
    
    // Supabase Auth - Sign up user (admin endpoint for auto-confirm)
    public function authSignUp($email, $password, $metadata = []) {
        $endpoint = $this->url . '/auth/v1/admin/users';
        $data = [
            'email' => $email,
            'password' => $password,
            'email_confirm' => true,
            'user_metadata' => $metadata
        ];
        
        error_log("Auth Sign Up: Endpoint = $endpoint, Email = $email");
        error_log("Auth Sign Up: Data = " . json_encode($data));
        error_log("Auth Sign Up: Headers = " . json_encode($this->headers));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("Auth Sign Up: HTTP Code = $httpCode");
        error_log("Auth Sign Up: Response = $response");
        if (!empty($curlError)) {
            error_log("Auth Sign Up: cURL Error = $curlError");
        }
        
        if ($httpCode >= 400) {
            throw new \Exception("Supabase Auth Error: HTTP $httpCode - $response");
        }
        
        $result = json_decode($response, true);
        error_log("Auth Sign Up: Decoded result = " . json_encode($result));
        
        return $result;
    }
    
    // Supabase Auth - Sign in user
    public function authSignIn($email, $password) {
        $endpoint = $this->url . '/auth/v1/token?grant_type=password';
        $data = [
            'email' => $email,
            'password' => $password
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new \Exception("Supabase Auth Error: HTTP $httpCode - $response");
        }
        
        return json_decode($response, true);
    }
    
    // Supabase Auth - Update user password (requires service role key)
    public function authUpdatePassword($userId, $newPassword) {
        error_log("authUpdatePassword called - userId: $userId, passwordLength: " . strlen($newPassword));
        
        $endpoint = $this->url . '/auth/v1/admin/users/' . $userId;
        $data = ['password' => $newPassword];
        
        error_log("Password update endpoint: $endpoint");
        
        // Use service role key for admin operations
        $config = require __DIR__ . '/../../includes/supabase.php';
        $serviceHeaders = [
            'apikey: ' . $config['service_role_key'],
            'Authorization: Bearer ' . $config['service_role_key'],
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $serviceHeaders);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("Password update response - HTTP $httpCode: $response");
        if ($curlError) {
            error_log("cURL error: $curlError");
        }
        
        if ($httpCode >= 400) {
            throw new \Exception("Supabase Auth Error: HTTP $httpCode - $response");
        }
        
        return json_decode($response, true);
    }

    /**
     * Get a user by email using Supabase Admin API
     * This uses the auth/v1/admin/users endpoint to look up users by email
     */
    public function authGetUserByEmail($email) {
        $config = require __DIR__ . '/../../includes/supabase.php';
        $serviceHeaders = [
            'apikey: ' . $config['service_role_key'],
            'Authorization: Bearer ' . $config['service_role_key'],
            'Content-Type: application/json'
        ];

        $attempts = [
            $this->url . '/auth/v1/admin/users?email=' . urlencode($email),
            $this->url . '/auth/v1/admin/users?email=eq.' . urlencode($email)
        ];

        foreach ($attempts as $endpoint) {
            error_log("Auth Get User by Email: Trying endpoint = $endpoint");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $serviceHeaders);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log("Auth Get User by Email: HTTP Code = $httpCode, Response = $response");
            if (!empty($curlError)) {
                error_log("Auth Get User by Email cURL error: $curlError");
                continue;
            }

            if ($httpCode >= 400) {
                error_log("Auth Get User by Email failed on endpoint $endpoint with HTTP $httpCode");
                continue;
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Auth Get User by Email JSON decode error: " . json_last_error_msg());
                continue;
            }

            if (is_array($result) && !empty($result)) {
                // Some Supabase versions may wrap user list inside a `data` key
                if (isset($result['data']) && is_array($result['data']) && !empty($result['data'])) {
                    return $result['data'][0];
                }

                return $result[0];
            }

            if (isset($result['id'])) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Get a user by ID using Supabase Admin API
     */
    public function authGetUserById($userId) {
        $endpoint = $this->url . '/auth/v1/admin/users/' . urlencode($userId);
        
        error_log("Auth Get User by ID: URL = $endpoint");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        error_log("Auth Get User by ID: HTTP Code = $httpCode, Response = $response");
        curl_close($ch);
        
        if ($httpCode >= 400) {
            if ($httpCode === 404) {
                return null; // User not found
            }
            throw new \Exception("Supabase Auth Error: HTTP $httpCode - $response");
        }
        
        $result = json_decode($response, true);
        return $result;
    }

    public function sendPushNotification(string $endpoint, array $payload, array $keys = []): bool {
        // This method assumes a server-side push gateway or external service is configured.
        // For Supabase-native push notifications, replace this with the correct API integration.
        if (empty($endpoint) || empty($payload)) {
            return false;
        }

        // Save a reusable notification log via Supabase only; actual push delivery may require an external service.
        try {
            $data = [
                'endpoint' => $endpoint,
                'payload' => json_encode($payload),
                'keys' => json_encode($keys),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $this->insert('notification_delivery_log', $data);
            return true;
        } catch (\Exception $e) {
            error_log('sendPushNotification error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!class_exists('SupabaseClient', false)) {
    class_alias(__NAMESPACE__ . '\\SupabaseClient', 'SupabaseClient');
}
