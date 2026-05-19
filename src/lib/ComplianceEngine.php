<?php

require_once __DIR__ . '/../../bootstrap.php';
declare(strict_types=1);

namespace App\Lib;

class ComplianceEngine
{
    private \App\Lib\SupabaseClient $db;
    private \App\Lib\BlockchainService $blockchain;

    public function __construct(\App\Lib\SupabaseClient $db, \App\Lib\BlockchainService $blockchain)
    {
        $this->db = $db;
        $this->blockchain = $blockchain;
    }

    /**
     * Calculate compliance score for an institution
     * 
     * Constitution Art. V Sec. 3 Requirements:
     * - 40% participation rate (attending members / total members)
     * - At least 1 hosted event per year
     * 
     * Compliance Status:
     * - compliant: participation >= 40% AND hosted_events >= 1
     * - at_risk: participation < 40% OR hosted_events < 1
     * 
     * @param string $institutionId
     * @param int $year
     * @return float
     */
    public function calculateForInstitution(string $institutionId, int $year): float
    {
        // 1. Get total active members
        $totalMembers = count($this->db->select('members', [
            'institution_id' => 'eq.' . $institutionId,
            'status' => 'eq.active'
        ]));

        // 2. Get distinct members who attended events
        $attendanceRecords = $this->db->select('attendance', [
            'institution_id' => 'eq.' . $institutionId,
            'select' => 'user_id'
        ]);
        
        $uniqueAttendees = [];
        foreach ($attendanceRecords as $record) {
            if (isset($record['user_id'])) {
                $uniqueAttendees[$record['user_id']] = true;
            }
        }
        $attendedCount = count($uniqueAttendees);

        $participationRate = $totalMembers > 0 ? ($attendedCount / $totalMembers) * 100 : 0;

        // 3. Count hosted events (completed status)
        $hostedEvents = count($this->db->select('events', [
            'institution_id' => 'eq.' . $institutionId,
            'status' => 'eq.completed'
        ]));

        // 4. Determine compliance status per Constitution Art. V Sec. 3
        // Both conditions must be met: participation >= 40% AND hosted_events >= 1
        $isCompliant = ($participationRate >= 40 && $hostedEvents >= 1);
        $complianceStatus = $isCompliant ? 'compliant' : 'at_risk';

        // 5. Calculate score (50% participation, 50% hosting)
        $participationScore = $participationRate >= 40 ? 50 : ($participationRate / 40) * 50;
        $hostingScore = $hostedEvents >= 1 ? 50 : 0;
        $overallScore = min($participationScore + $hostingScore, 100);

        // 6. Upsert compliance score
        $this->db->upsert('compliance_scores', [
            'institution_id' => $institutionId,
            'year' => $year,
            'participation_rate' => round($participationRate, 2),
            'hosted_event_count' => $hostedEvents,
            'overall_score' => round($overallScore, 2),
            'compliance_status' => $complianceStatus,
            'last_updated' => date('Y-m-d H:i:s')
        ]);

        // 7. Record in blockchain (hash-chained audit trail)
        $this->blockchain->record('compliance_attendance', $institutionId . '-' . $year, [
            'institution_id' => $institutionId,
            'year' => $year,
            'score' => round($overallScore, 2),
            'participation_rate' => round($participationRate, 2),
            'hosted_events' => $hostedEvents,
            'compliance_status' => $complianceStatus
        ]);

        // 8. Send alert if at risk
        if ($complianceStatus === 'at_risk') {
            $this->sendComplianceAlert($institutionId, $overallScore);
        }

        return round($overallScore, 2);
    }

    /**
     * Calculate compliance for all institutions
     * @param int $year
     * @return array
     */
    public function calculateAll(int $year): array
    {
        $institutions = $this->db->select('institutions', ['status' => 'eq.active']);
        $results = [];

        foreach ($institutions as $institution) {
            $score = $this->calculateForInstitution($institution['id'], $year);
            $results[$institution['id']] = [
                'name' => $institution['name'],
                'score' => $score
            ];
        }

        return $results;
    }

    /**
     * Send compliance alert to school officer and admin
     * @param string $institutionId
     * @param float $score
     */
    private function sendComplianceAlert(string $institutionId, float $score): void
    {
        // Get school officers
        $officers = $this->db->select('user_profiles', [
            'institution_id' => 'eq.' . $institutionId,
            'role' => 'eq.school_officer'
        ]);

        foreach ($officers as $officer) {
            $this->db->insert('notifications', [
                'user_id' => $officer['id'],
                'title' => 'Compliance Alert',
                'message' => "Your institution's compliance score is {$score}%. Please take action to improve.",
                'type' => 'warning',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Get compliance report for institution
     * @param string $institutionId
     * @param int $year
     * @return array|null
     */
    public function getReport(string $institutionId, int $year): ?array
    {
        $scores = $this->db->select('compliance_scores', [
            'institution_id' => 'eq.' . $institutionId,
            'year' => 'eq.' . $year
        ]);

        return $scores[0] ?? null;
    }
}
