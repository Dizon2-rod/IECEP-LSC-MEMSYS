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
 * MemberRepository - Canonical Data Access for Members
 * 
 * Unifies member counts, dues standing ('isPaid'), and roster operations across all portals.
 */
class MemberRepository
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
     * Canonical check if a member has paid their dues
     * 
     * Uses PaymentStatus::isMemberPaid() to unify validation across all portals.
     */
    public function isPaid(?array $member): bool
    {
        if (!$member) {
            return false;
        }
        $status = $member['payment_status'] ?? null;
        return PaymentStatus::isMemberPaid($status);
    }

    /**
     * Get member record by ID
     */
    public function getById(string $id): ?array
    {
        if (empty($id)) return null;

        if ($this->supabase) {
            try {
                $rows = $this->supabase->select('members', ['id' => 'eq.' . $id]);
                if (is_array($rows) && !empty($rows[0])) {
                    return $rows[0];
                }
            } catch (\Throwable $e) {
                error_log('[MemberRepository] getById Supabase error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM members WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ?: null;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Get member record by Membership ID (e.g. 2026-LSC-0001)
     */
    public function getByMembershipId(string $membershipId): ?array
    {
        if (empty($membershipId)) return null;

        if ($this->supabase) {
            try {
                $rows = $this->supabase->select('members', ['membership_id' => 'eq.' . $membershipId]);
                if (is_array($rows) && !empty($rows[0])) {
                    return $rows[0];
                }
            } catch (\Throwable $e) {}
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM members WHERE membership_id = :mid LIMIT 1");
                $stmt->execute([':mid' => $membershipId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ?: null;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Get members for a specific institution
     */
    public function getByInstitution(string $institutionId, array $options = []): array
    {
        if (empty($institutionId)) return [];

        if ($this->supabase) {
            try {
                $filters = array_merge([
                    'institution_id' => 'eq.' . $institutionId,
                    'order' => 'created_at.desc'
                ], $options);
                $rows = $this->supabase->select('members', $filters);
                if (is_array($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                error_log('[MemberRepository] getByInstitution error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM members WHERE institution_id = :iid ORDER BY created_at DESC");
                $stmt->execute([':iid' => $institutionId]);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Get standardized statistics for an institution's roster
     */
    public function getStatsForInstitution(string $institutionId): array
    {
        $members = $this->getByInstitution($institutionId);
        $total = count($members);
        $paid = 0;
        $digitalIds = 0;
        $newCount = 0;
        $returningCount = 0;

        foreach ($members as $m) {
            if ($this->isPaid($m)) {
                $paid++;
            }
            if (!empty($m['membership_id'])) {
                $digitalIds++;
            }
            $type = strtolower((string)($m['member_type'] ?? ''));
            if ($type === 'new') {
                $newCount++;
            } elseif ($type === 'returning') {
                $returningCount++;
            }
        }

        return [
            'total'       => $total,
            'paid'        => $paid,
            'pending'     => max(0, $total - $paid),
            'digital_ids' => $digitalIds,
            'new'         => $newCount,
            'returning'   => $returningCount
        ];
    }

    /**
     * Get global member statistics across all institutions
     */
    public function getGlobalStats(): array
    {
        $allMembers = [];
        if ($this->supabase) {
            try {
                $rows = $this->supabase->select('members', ['select' => 'id,payment_status,membership_id,member_type,status']);
                if (is_array($rows)) {
                    $allMembers = $rows;
                }
            } catch (\Throwable $e) {
                error_log('[MemberRepository] getGlobalStats error: ' . $e->getMessage());
            }
        }

        if (empty($allMembers)) {
            try {
                $pdo = $this->getMySQLConnection();
                if ($pdo) {
                    $stmt = $pdo->query("SELECT id, payment_status, membership_id, member_type, status FROM members");
                    if ($stmt) {
                        $allMembers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    }
                }
            } catch (\Throwable $e) {}
        }

        $total = count($allMembers);
        $paid = 0;
        $digitalIds = 0;

        foreach ($allMembers as $m) {
            if ($this->isPaid($m)) {
                $paid++;
            }
            if (!empty($m['membership_id'])) {
                $digitalIds++;
            }
        }

        return [
            'total'       => $total,
            'paid'        => $paid,
            'pending'     => max(0, $total - $paid),
            'digital_ids' => $digitalIds
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
