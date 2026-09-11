<?php
declare(strict_types=1);

namespace App\Lib;

require_once dirname(__DIR__, 2) . '/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/SupabaseClient.php';
require_once __DIR__ . '/PaymentStatus.php';

use App\Lib\SupabaseClient;
use App\Lib\PaymentStatus;

/**
 * PaymentRepository - Canonical Data Access for Payments, Transactions & Collections
 */
class PaymentRepository
{
    private static ?self $instance = null;
    private ?SupabaseClient $supabase = null;

    public function __construct(?SupabaseClient $supabase = null)
    {
        if ($supabase) {
            $this->supabase = $supabase;
        } else {
            $config = require dirname(__DIR__, 2) . '/includes/supabase.php';
            if (!empty($config['url']) && (!empty($config['service_role_key']) || !empty($config['anon_key']))) {
                $key = !empty($config['service_role_key']) ? $config['service_role_key'] : $config['anon_key'];
                $this->supabase = new SupabaseClient($config['url'], $key);
            }
        }
    }

    public static function getInstance(?SupabaseClient $supabase = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($supabase);
        }
        return self::$instance;
    }

    /**
     * Canonical validation for whether a transaction is considered paid
     * 
     * Uses PaymentStatus::isTransactionPaid() to unify payment status logic.
     */
    public function isPaid(?array $transaction): bool
    {
        if (!$transaction) {
            return false;
        }
        $status = $transaction['status'] ?? null;
        return PaymentStatus::isTransactionPaid($status);
    }

    /**
     * Get all transactions matching filters
     */
    public function getAll(array $filters = []): array
    {
        if ($this->supabase) {
            try {
                $options = array_merge(['select' => '*', 'order' => 'created_at.desc'], $filters);
                $rows = $this->supabase->select('transactions', $options);
                if (is_array($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                error_log('[PaymentRepository] getAll error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->query("SELECT * FROM transactions ORDER BY created_at DESC");
                if ($stmt) {
                    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Get transactions for a specific institution
     */
    public function getByInstitution(string $institutionId, int $limit = 50): array
    {
        if (empty($institutionId)) return [];

        if ($this->supabase) {
            try {
                $rows = $this->supabase->select('transactions', [
                    'institution_id' => 'eq.' . $institutionId,
                    'order' => 'created_at.desc',
                    'limit' => $limit
                ]);
                if (is_array($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                error_log('[PaymentRepository] getByInstitution error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM transactions WHERE institution_id = :iid ORDER BY created_at DESC LIMIT :lim");
                $stmt->bindValue(':iid', $institutionId, \PDO::PARAM_STR);
                $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Calculate total settled collections for a specific institution
     */
    public function getTotalCollectedForInstitution(string $institutionId): float
    {
        $transactions = $this->getByInstitution($institutionId, 500);
        $total = 0.0;

        foreach ($transactions as $tx) {
            if ($this->isPaid($tx)) {
                $total += (float)($tx['amount'] ?? 0);
            }
        }

        return $total;
    }

    public function getPaidTotalForInstitution(string $institutionId): float
    {
        return $this->getTotalCollectedForInstitution($institutionId);
    }

    public function getTotalCollections(): float
    {
        return $this->getCollectionsSummary()['total'];
    }

    public function getCategoryBreakdown(): array
    {
        return $this->getCollectionsSummary()['categories'];
    }

    /**
     * Get global collections summary categorized by official IECEP revenue streams
     */
    public function getCollectionsSummary(): array
    {
        $transactions = $this->getAll();
        $totalCollections = 0.0;
        $categoryCollections = [
            'Membership Dues'      => 0.0,
            'Chapter Affiliations' => 0.0,
            'Events & Summits'     => 0.0,
            'Merchandise'          => 0.0,
            'Other Collections'    => 0.0
        ];

        foreach ($transactions as $tx) {
            if ($this->isPaid($tx)) {
                $amt = (float)($tx['amount'] ?? 0);
                $totalCollections += $amt;

                $type = strtolower((string)($tx['transaction_type'] ?? $tx['type'] ?? $tx['description'] ?? 'other'));
                if (strpos($type, 'member') !== false || strpos($type, 'due') !== false) {
                    $categoryCollections['Membership Dues'] += $amt;
                } elseif (strpos($type, 'affil') !== false || strpos($type, 'school') !== false || strpos($type, 'charter') !== false) {
                    $categoryCollections['Chapter Affiliations'] += $amt;
                } elseif (strpos($type, 'event') !== false || strpos($type, 'summit') !== false || strpos($type, 'ticket') !== false) {
                    $categoryCollections['Events & Summits'] += $amt;
                } elseif (strpos($type, 'merch') !== false || strpos($type, 'item') !== false || strpos($type, 'shirt') !== false) {
                    $categoryCollections['Merchandise'] += $amt;
                } else {
                    $categoryCollections['Other Collections'] += $amt;
                }
            }
        }

        return [
            'total'      => $totalCollections,
            'categories' => $categoryCollections,
            'count'      => count($transactions)
        ];
    }

    private function getMySQLConnection(): ?\PDO
    {
        try {
            $dbHost = env('DB_HOST', '127.0.0.1');
            $dbName = env('DB_NAME', 'iecep_lsc_memsys');
            return new \PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", env('DB_USER', 'root'), env('DB_PASS', ''), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
                \PDO::ATTR_TIMEOUT => 2
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
