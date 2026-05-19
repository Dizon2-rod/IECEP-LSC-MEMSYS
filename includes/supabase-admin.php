<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * Supabase Admin API Helpers
 * Uses SERVICE ROLE KEY — never expose to frontend
 */

function createSupabaseUser(string $email, string $password, string $fullName): ?array {
    $url = SUPABASE_URL . '/auth/v1/admin/users';
    $payload = [
        'email' => $email,
        'password' => $password,
        'email_confirm' => true,
        'user_metadata' => ['full_name' => $fullName],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        return json_decode($response, true);
    }

    error_log("[Supabase] createUser failed ($httpCode): $response");
    return null;
}

function checkSupabaseUserByEmail(string $email): ?array {
    $url = SUPABASE_URL . '/auth/v1/admin/users?email=' . urlencode($email);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['users'])) {
            foreach ($data['users'] as $user) {
                if (strtolower($user['email']) === strtolower($email)) {
                    return $user;
                }
            }
        }
    }

    return null;
}
