<?php
/**
 * Notification helper functions for IECEP-LSC MEMSYS.
 */

if (!function_exists('createNotification')) {
    function createNotification(
        string $title,
        string $message,
        string $role,
        string $type = 'info',
        string $link = '/',
        ?string $userId = null
    ): array {
        global $supabase;

        $notificationData = [
            'title' => $title,
            'message' => $message,
            'role' => $role,
            'type' => $type,
            'link' => $link,
            'user_id' => $userId,
            'status' => 'unread',
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            if (!isset($supabase) || !$supabase instanceof \App\Lib\SupabaseClient) {
                $config = require __DIR__ . '/supabase.php';
                $supabase = new \App\Lib\SupabaseClient($config['url'], $config['service_role_key']);
            }

            $result = $supabase->from('notifications')
                ->insert($notificationData);

            if (!$result) {
                throw new Exception('Failed to insert notification');
            }

            return $notificationData;
        } catch (Exception $e) {
            error_log('Notification creation error: ' . $e->getMessage());
            return $notificationData;
        }
    }
}
