<?php
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
            throw new Exception("Supabase cURL Error: $curlError");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("Supabase API Error: HTTP $httpCode - $response");
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Supabase JSON Decode Error: " . json_last_error_msg());
            throw new Exception("Supabase JSON Decode Error: " . json_last_error_msg());
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
    public function update($table, $data, $id) {
        // Always use filter approach (works for both integer and UUID IDs)
        $endpoint = $this->url . '/rest/v1/' . $table . '?id=eq.' . $id;
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
            throw new Exception("Supabase API Error: HTTP $httpCode - $response");
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
        
        // Use service role key for admin user creation
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
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("Supabase Auth Error: HTTP $httpCode - $response");
        }
        
        return json_decode($response, true);
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
            throw new Exception("Supabase Auth Error: HTTP $httpCode - $response");
        }
        
        return json_decode($response, true);
    }
    
    // Supabase Auth - Update user password (requires service role key)
    public function authUpdatePassword($userId, $newPassword) {
        $endpoint = $this->url . '/auth/v1/admin/users/' . $userId;
        $data = ['password' => $newPassword];
        
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
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("Supabase Auth Error: HTTP $httpCode - $response");
        }
        
        return json_decode($response, true);
    }
}
