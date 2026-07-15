<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * Notification helper functions for IECEP-LSC MEMSYS.
 */

if (!function_exists('createNotification')) {
    function createNotification(string $userId, string $title, string $message, string $type = 'reminder', string $link = null): array
    {
        try {
            $supabase = new \App\Lib\Supabase();

            $notificationData = [
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'action_url' => $link,
                'read' => false,
                'created_at' => date('c'),
            ];

            $result = $supabase->from('notifications')
                ->insert($notificationData, true);

            if (!empty($result['error'])) {
                throw new \Exception($result['message'] ?? 'Failed to insert notification');
            }

            return ['success' => true, 'notification' => $notificationData];
        } catch (\Exception $e) {
            error_log('createNotification error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
