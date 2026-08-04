<?php
// db.php — DISABLED for Railway (Supabase only)
// Local MySQL/PostgreSQL connections are not supported on Railway.
// Use App\Lib\SupabaseClient or the $supabase global for all database operations.
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;

    private function __construct() {
        throw new \RuntimeException('Local database connections are disabled. Use Supabase via App\Lib\SupabaseClient or the $supabase global.');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        throw new \RuntimeException('Local database connections are disabled. Use Supabase via App\Lib\SupabaseClient or the $supabase global.');
    }
}
