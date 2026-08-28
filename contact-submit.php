<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING) ?? '');
    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? '/IECEP-LSC-MEMSYS/contact.php';
    $baseUrl = strtok($redirectUrl, '?');
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($name) && !empty($message)) {
        try {
            require_once __DIR__ . '/src/lib/SupabaseClient.php';
            $config = require __DIR__ . '/includes/supabase.php';
            $supabase = new SupabaseClient($config['url'], $config['anon_key']);
            
            // Save to database
            $data = [
                'name' => $name,
                'email' => $email,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $supabase->insert('contact_messages', $data);
            header("Location: {$baseUrl}?contact=success");
            exit;
        } catch (Exception $e) {
            error_log("Contact form error: " . $e->getMessage());
            header("Location: {$baseUrl}?contact=success");
            exit;
        }
    } else {
        header("Location: {$baseUrl}?contact=error");
        exit;
    }
}

// If not POST, redirect to contact page
header('Location: /IECEP-LSC-MEMSYS/contact.php');
exit;
