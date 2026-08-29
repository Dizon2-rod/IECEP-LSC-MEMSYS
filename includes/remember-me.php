<?php
/**
 * remember-me.php - Secure Remember Me & Persistent Session Management
 * IECEP-LSC MEMSYS
 */

if (!defined('REMEMBER_ME_SECRET')) {
    $secret = defined('SUPABASE_JWT_SECRET') && !empty(SUPABASE_JWT_SECRET) 
        ? SUPABASE_JWT_SECRET 
        : (defined('SUPABASE_ANON_KEY') ? substr(SUPABASE_ANON_KEY, 0, 32) : 'iecep_lsc_memsys_secret_key_2026_prod');
    define('REMEMBER_ME_SECRET', $secret);
}

/**
 * Set Remember Me cookie with cryptographically signed HMAC token
 */
if (!function_exists('setRememberMeCookie')) {
    function setRememberMeCookie(array $userData, int $days = 30): void {
        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        
        $expire = time() + ($days * 86400);

        // 1. Remember Email cookie (for login form prefill)
        $email = $userData['email'] ?? '';
        if ($email) {
            setcookie('remember_email', $email, $expire, '/', '', $isSecure, true);
        }

        // 2. Cryptographically signed auth token (for seamless auto-login)
        $payload = [
            'uid'            => $userData['id'] ?? '',
            'email'          => $email,
            'name'           => $userData['name'] ?? ($userData['full_name'] ?? ''),
            'role'           => $userData['role'] ?? 'member',
            'institution_id' => $userData['institution_id'] ?? null,
            'exp'            => $expire,
            'issued_at'      => time()
        ];

        $payloadJson = json_encode($payload);
        $encodedPayload = base64_encode($payloadJson);
        $signature = hash_hmac('sha256', $encodedPayload, REMEMBER_ME_SECRET);
        $token = $encodedPayload . '.' . $signature;

        setcookie('memsys_remember_token', $token, $expire, '/', '', $isSecure, true);

        // 3. Extend PHP session cookie lifetime
        if (session_id()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), $expire, $params['path'] ?? '/', $params['domain'] ?? '', $isSecure, $params['httponly'] ?? true);
        }
    }
}

/**
 * Validate and restore user session from Remember Me cookie
 */
if (!function_exists('restoreRememberMeSession')) {
    function restoreRememberMeSession(): bool {
        // If already logged in, return true
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && !empty($_SESSION['user'])) {
            return true;
        }

        if (empty($_COOKIE['memsys_remember_token'])) {
            return false;
        }

        $token = $_COOKIE['memsys_remember_token'];
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            clearRememberMeCookie();
            return false;
        }

        [$encodedPayload, $providedSignature] = $parts;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, REMEMBER_ME_SECRET);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            clearRememberMeCookie();
            return false;
        }

        $payloadJson = base64_decode($encodedPayload, true);
        if (!$payloadJson) {
            clearRememberMeCookie();
            return false;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || empty($payload['exp']) || $payload['exp'] < time()) {
            clearRememberMeCookie();
            return false;
        }

        if (empty($payload['uid']) || empty($payload['email'])) {
            clearRememberMeCookie();
            return false;
        }

        // Restore Session State
        $_SESSION['logged_in']      = true;
        $_SESSION['user_id']        = $payload['uid'];
        $_SESSION['email']          = $payload['email'];
        $_SESSION['full_name']      = $payload['name'] ?? '';
        $_SESSION['role']           = $payload['role'] ?? 'member';
        $_SESSION['institution_id'] = $payload['institution_id'] ?? null;

        $_SESSION['user'] = [
            'id'                   => $payload['uid'],
            'email'                => $payload['email'],
            'name'                 => $payload['name'] ?? '',
            'role'                 => $payload['role'] ?? 'member',
            'institution_id'       => $payload['institution_id'] ?? null,
            'must_change_password' => false
        ];

        return true;
    }
}

/**
 * Clear Remember Me cookies
 */
if (!function_exists('clearRememberMeCookie')) {
    function clearRememberMeCookie(bool $clearEmail = false): void {
        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (isset($_COOKIE['memsys_remember_token'])) {
            setcookie('memsys_remember_token', '', time() - 86400, '/', '', $isSecure, true);
            unset($_COOKIE['memsys_remember_token']);
        }

        if ($clearEmail && isset($_COOKIE['remember_email'])) {
            setcookie('remember_email', '', time() - 86400, '/', '', $isSecure, true);
            unset($_COOKIE['remember_email']);
        }
    }
}
