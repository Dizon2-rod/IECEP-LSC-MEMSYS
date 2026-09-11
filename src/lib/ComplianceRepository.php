<?php
declare(strict_types=1);

namespace App\Lib;

require_once dirname(__DIR__, 2) . '/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/SupabaseClient.php';
require_once __DIR__ . '/InstitutionRepository.php';

use App\Lib\SupabaseClient;
use App\Lib\InstitutionRepository;

/**
 * ComplianceRepository - Canonical Data Access for Compliance Scores & Institutional Standing
 */
class ComplianceRepository
{
    private static ?self $instance = null;
    private ?SupabaseClient $supabase = null;
    private InstitutionRepository $institutionRepo;

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
        $this->institutionRepo = InstitutionRepository::getInstance($this->supabase);
    }

    public static function getInstance(?SupabaseClient $supabase = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($supabase);
        }
        return self::$instance;
    }

    /**
     * Get compliance record for an institution
     */
    public function getScoresForInstitution(string $institutionId, ?int $year = null): ?array
    {
        if (empty($institutionId)) return null;
        $targetYear = $year ?: (int)date('Y');

        if ($this->supabase) {
            try {
                $rows = $this->supabase->select('compliance_scores', [
                    'institution_id' => 'eq.' . $institutionId,
                    'year'           => 'eq.' . $targetYear,
                    'limit'          => 1
                ]);
                if (is_array($rows) && !empty($rows[0])) {
                    return $rows[0];
                }
            } catch (\Throwable $e) {
                error_log('[ComplianceRepository] getScoresForInstitution error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM compliance_scores WHERE institution_id = :iid AND year = :yr LIMIT 1");
                $stmt->execute([':iid' => $institutionId, ':yr' => $targetYear]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ?: null;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Get all compliance scores for a given academic year
     */
    public function getAllScores(?int $year = null): array
    {
        $targetYear = $year ?: (int)date('Y');

        if ($this->supabase) {
            try {
                $rows = $this->supabase->select('compliance_scores', [
                    'year'  => 'eq.' . $targetYear,
                    'order' => 'overall_score.desc'
                ]);
                if (is_array($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                error_log('[ComplianceRepository] getAllScores error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM compliance_scores WHERE year = :yr ORDER BY overall_score DESC");
                $stmt->execute([':yr' => $targetYear]);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Get aggregate compliance statistics and status counts
     */
    public function getSummary(?int $year = null): array
    {
        $scores = $this->getAllScores($year);
        $total = count($scores);
        $compliant = 0;
        $atRisk = 0;
        $nonCompliant = 0;
        $sumParticipation = 0.0;
        $sumOverall = 0.0;

        foreach ($scores as $s) {
            $status = strtolower(trim((string)($s['compliance_status'] ?? 'compliant')));
            if ($status === 'compliant') {
                $compliant++;
            } elseif ($status === 'at_risk') {
                $atRisk++;
            } else {
                $nonCompliant++;
            }
            $sumParticipation += (float)($s['participation_rate'] ?? 0);
            $sumOverall += (float)($s['overall_score'] ?? 0);
        }

        return [
            'total'               => $total,
            'total_evaluated'     => $total,
            'compliant'           => $compliant,
            'at_risk'             => $atRisk,
            'non_compliant'       => $nonCompliant,
            'compliance_rate'     => $total > 0 ? round(($compliant / $total) * 100, 1) : 0.0,
            'avg_participation'   => $total > 0 ? round($sumParticipation / $total, 1) : 0.0,
            'avg_overall_score'   => $total > 0 ? round($sumOverall / $total, 1) : 0.0
        ];
    }

    /**
     * Save/upsert compliance score and update institutions.compliance_status atomically
     */
    public function saveScore(array $data): bool
    {
        $institutionId = $data['institution_id'] ?? null;
        if (empty($institutionId)) {
            return false;
        }

        $year = (int)($data['year'] ?? date('Y'));
        $participationRate = (float)($data['participation_rate'] ?? 0.0);
        $hostedEventCount = (int)($data['hosted_event_count'] ?? 0);
        $overallScore = (float)($data['overall_score'] ?? 0.0);
        $complianceStatus = strtolower(trim((string)($data['compliance_status'] ?? 'compliant')));

        // 1. Upsert into compliance_scores
        if ($this->supabase) {
            try {
                $payload = [
                    'institution_id'     => $institutionId,
                    'year'               => $year,
                    'participation_rate' => $participationRate,
                    'hosted_event_count' => $hostedEventCount,
                    'overall_score'      => $overallScore,
                    'compliance_status'  => $complianceStatus,
                    'last_updated'       => date('Y-m-d H:i:s')
                ];
                $this->supabase->upsert('compliance_scores', $payload);
            } catch (\Throwable $e) {
                error_log('[ComplianceRepository] saveScore Supabase error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("
                    INSERT INTO compliance_scores (institution_id, year, participation_rate, hosted_event_count, overall_score, compliance_status, last_updated)
                    VALUES (:iid, :yr, :pr, :hec, :os, :cs, NOW())
                    ON DUPLICATE KEY UPDATE
                        participation_rate = VALUES(participation_rate),
                        hosted_event_count = VALUES(hosted_event_count),
                        overall_score = VALUES(overall_score),
                        compliance_status = VALUES(compliance_status),
                        last_updated = NOW()
                ");
                $stmt->execute([
                    ':iid' => $institutionId,
                    ':yr'  => $year,
                    ':pr'  => $participationRate,
                    ':hec' => $hostedEventCount,
                    ':os'  => $overallScore,
                    ':cs'  => $complianceStatus
                ]);
            }
        } catch (\Throwable $e) {}

        // 2. Synchronize institutions.compliance_status
        $this->institutionRepo->updateComplianceStatus($institutionId, $complianceStatus);

        return true;
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
