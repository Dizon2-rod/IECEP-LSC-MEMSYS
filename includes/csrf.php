<?php
/**
 * CSRF Protection System
 * Generates and validates CSRF tokens for all state-changing operations
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token
 * @return string
 */
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token
 * @return bool
 */
function validate_csrf($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require CSRF token validation (call in API endpoints)
 * @param bool $exit Exit on failure
 * @return bool
 */
function require_csrf($exit = true) {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!validate_csrf($token)) {
        if ($exit) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        return false;
    }
    return true;
}

/**
 * Generate CSRF input field for forms
 * @return string
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Get CSRF meta tag for AJAX requests
 * @return string
 */
function csrf_meta() {
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token()) . '">';
}
