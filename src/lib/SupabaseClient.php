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
    
    // Generic request method
    private function request($method, $table, $data = null, $id = null) {
        $endpoint = $this->url . '/rest/v1/' . $table;
        if ($id) {
            $endpoint .= '/' . $id;
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
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("Supabase API Error: HTTP $httpCode - $response");
        }
        
        return json_decode($response, true);
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
}
