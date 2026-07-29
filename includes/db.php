<?php
// db.php - XAMPP MySQL Database Connection
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $defaultHost = !empty(DB_HOST) ? DB_HOST : '127.0.0.1';
        $defaultPort = !empty(DB_PORT) ? DB_PORT : '3306';

        $hosts = [];
        $ports = array_unique(array_filter([$defaultPort, '3306', '3307']));
        $hostCandidates = array_unique(array_filter([$defaultHost]));

        if (in_array($defaultHost, ['localhost', '127.0.0.1'], true)) {
            $hostCandidates = array_unique(array_merge($hostCandidates, ['127.0.0.1', 'localhost']));
        }

        foreach ($hostCandidates as $host) {
            foreach ($ports as $port) {
                $hosts[] = ['host' => $host, 'port' => $port];
            }
        }

        $lastException = null;
        foreach ($hosts as $server) {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                    $server['host'],
                    $server['port'],
                    DB_NAME
                );

                $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                return;
            } catch (PDOException $e) {
                $lastException = $e;
                error_log(sprintf(
                    'Database connection failed for %s:%s - %s',
                    $server['host'],
                    $server['port'],
                    $e->getMessage()
                ));
            }
        }

        throw new RuntimeException(
            'Database connection failed: ' . ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            throw new RuntimeException("Query failed: " . $e->getMessage());
        }
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $this->query($sql, array_values($data));
        return $this->connection->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "$column = ?";
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $set),
            $where
        );
        
        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $where, $params = []) {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollBack();
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}
