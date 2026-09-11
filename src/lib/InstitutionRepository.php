<?php
declare(strict_types=1);

namespace App\Lib;

require_once dirname(__DIR__, 2) . '/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/SupabaseClient.php';

use App\Lib\SupabaseClient;

/**
 * InstitutionRepository - Canonical Data Access for Institutions & Chapters
 */
class InstitutionRepository
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
     * Get institution record by ID
     */
    public function getById(string $id): ?array
    {
        if (empty($id)) return null;

        if ($this->supabase) {
            try {
                $rows = $this->supabase->select('institutions', ['id' => 'eq.' . $id]);
                if (is_array($rows) && !empty($rows[0])) {
                    return $rows[0];
                }
            } catch (\Throwable $e) {
                error_log('[InstitutionRepository] getById Supabase error: ' . $e->getMessage());
            }
        }

        // MySQL fallback
        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM institutions WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ?: null;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Get all institutions matching filters
     */
    public function getAll(array $filters = []): array
    {
        if ($this->supabase) {
            try {
                $options = array_merge(['select' => '*', 'order' => 'name.asc'], $filters);
                $rows = $this->supabase->select('institutions', $options);
                if (is_array($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                error_log('[InstitutionRepository] getAll Supabase error: ' . $e->getMessage());
            }
        }

        // MySQL fallback
        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->query("SELECT * FROM institutions ORDER BY name ASC");
                if ($stmt) {
                    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Get all active / chartered institutions
     */
    public function getActive(): array
    {
        return $this->getAll(['status' => 'eq.active']);
    }

    /**
     * Get standardized compliance summary across all active institutions
     */
    public function getComplianceSummary(): array
    {
        $institutions = $this->getAll();
        $summary = [
            'total'          => count($institutions),
            'compliant'      => 0,
            'at_risk'        => 0,
            'non_compliant'  => 0,
            'compliance_rate'=> 0.0
        ];

        foreach ($institutions as $inst) {
            $status = strtolower(trim((string)($inst['compliance_status'] ?? 'compliant')));
            if ($status === 'compliant') {
                $summary['compliant']++;
            } elseif ($status === 'at_risk') {
                $summary['at_risk']++;
            } else {
                $summary['non_compliant']++;
            }
        }

        if ($summary['total'] > 0) {
            $summary['compliance_rate'] = round(($summary['compliant'] / $summary['total']) * 100, 1);
        }

        return $summary;
    }

    /**
     * Get comprehensive statistics for a single institution
     */
    public function getInstitutionStats(string $id): array
    {
        $institution = $this->getById($id);
        if (!$institution) {
            return [
                'exists' => false,
                'name' => '',
                'membership_count' => 0,
                'compliance_status' => 'non_compliant',
                'status' => 'inactive'
            ];
        }

        return [
            'exists' => true,
            'id' => $institution['id'],
            'name' => $institution['name'] ?? '',
            'acronym' => $institution['acronym'] ?? 'IECEP-SC',
            'membership_count' => (int)($institution['membership_count'] ?? 0),
            'compliance_status' => strtolower((string)($institution['compliance_status'] ?? 'compliant')),
            'status' => strtolower((string)($institution['status'] ?? 'pending')),
            'address' => $institution['address'] ?? '',
            'contact_person' => $institution['contact_person'] ?? '',
            'contact_email' => $institution['email'] ?? $institution['contact_email'] ?? ''
        ];
    }

    /**
     * Update compliance status for an institution
     */
    public function updateComplianceStatus(string $id, string $status): bool
    {
        $normalized = strtolower(trim($status));
        if (!in_array($normalized, ['compliant', 'at_risk', 'non_compliant'], true)) {
            $normalized = 'compliant';
        }

        if ($this->supabase) {
            try {
                $this->supabase->update('institutions', ['compliance_status' => $normalized], $id);
                return true;
            } catch (\Throwable $e) {
                error_log('[InstitutionRepository] updateComplianceStatus Supabase error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("UPDATE institutions SET compliance_status = :st WHERE id = :id");
                return $stmt->execute([':st' => $normalized, ':id' => $id]);
            }
        } catch (\Throwable $e) {}

        return false;
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
