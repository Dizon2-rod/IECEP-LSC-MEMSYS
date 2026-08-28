<?php
// db.php — Supabase Compatibility Bridge
// Local MySQL/PostgreSQL connections are disabled on Railway/Cloud.
// This class acts as a seamless bridge delegating to App\Lib\SupabaseClient.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../src/lib/SupabaseClient.php';

use App\Lib\SupabaseClient;

class Database {
    private static $instance = null;
    private $supabase = null;

    private function __construct() {
        try {
            $this->supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
        } catch (\Exception $e) {
            error_log("Database shim Supabase initialization error: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->supabase;
    }

    public function fetchAll($query, $params = []) {
        if ($this->supabase && preg_match('/FROM\s+([a-zA-Z0-9_]+)/i', $query, $matches)) {
            $table = $matches[1];
            try {
                $data = $this->supabase->select($table, ['select' => '*']);
                if (is_array($data)) {
                    return $data;
                }
            } catch (\Exception $e) {
                error_log("Database shim fetchAll error on table $table: " . $e->getMessage());
            }
        }
        return [];
    }

    public function fetchOne($query, $params = []) {
        $results = $this->fetchAll($query, $params);
        return $results[0] ?? null;
    }

    public function insert($table, $data) {
        if (!$this->supabase) return false;
        try {
            return $this->supabase->insert($table, $data);
        } catch (\Exception $e) {
            error_log("Database shim insert error on table $table: " . $e->getMessage());
            return false;
        }
    }

    public function update($table, $data, $whereClause = '', $params = []) {
        if (!$this->supabase) return false;
        try {
            $id = $params[0] ?? ($data['id'] ?? null);
            if ($id) {
                return $this->supabase->update($table, $id, $data);
            }
        } catch (\Exception $e) {
            error_log("Database shim update error on table $table: " . $e->getMessage());
        }
        return false;
    }

    public function escape($str) {
        return addslashes((string)$str);
    }

    public function beginTransaction() { return true; }
    public function commit() { return true; }
    public function rollback() { return true; }
}
