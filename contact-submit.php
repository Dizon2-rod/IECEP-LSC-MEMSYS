<?php
session_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    
    error_log("Contact form submission - Name: $name, Email: $email");
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($name) && !empty($message)) {
        try {
            require_once __DIR__ . '/src/lib/SupabaseClient.php';
            $config = require __DIR__ . '/includes/supabase.php';
            $supabase = new SupabaseClient($config['url'], $config['anon_key']);
            
            // Save to database
            $data = [
                'name' => $name,
                'email' => $email,
                'message' => $message
            ];
            
            error_log("Attempting to insert into contact_messages");
            $result = $supabase->insert('contact_messages', $data);
            error_log("Insert result: " . ($result ? 'success' : 'failed'));
            
            if ($result) {
                header('Location: /index.php?contact=success');
            } else {
                error_log("Insert returned false");
                header('Location: /index.php?contact=error');
            }
        } catch (Exception $e) {
            error_log("Contact form error: " . $e->getMessage());
            header('Location: /index.php?contact=error');
        }
    } else {
        error_log("Validation failed - email valid: " . (filter_var($email, FILTER_VALIDATE_EMAIL) ? 'yes' : 'no') . ", name empty: " . (empty($name) ? 'yes' : 'no') . ", message empty: " . (empty($message) ? 'yes' : 'no'));
        header('Location: /index.php?contact=error');
    }
    exit;
}

// If not POST, redirect to home
header('Location: /index.php');
exit;
