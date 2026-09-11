<?php
declare(strict_types=1);

namespace App\Lib;

require_once __DIR__ . '/../../bootstrap.php';

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
     * - Minimum 40% member participation rate (attending members / total members)
     * - Host at least 1 sanctioned event OR serve as official venue per academic year
     * 
     * System-Computed Compliance Statuses:
     * - compliant: participation >= 40% AND (hosted_events >= 1 OR venue_events >= 1)
     * - at_risk: approaching threshold (e.g. participation >= 20% OR met hosting/venue but under 40%)
     * - non_compliant: participation < 20% AND neither hosted nor served as venue
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

        // 4. Count events where institution served as official venue (CBL Art. V Sec. 3)
        $venueEvents = 0;
        try {
            $venueRecords = $this->db->select('events', [
                'venue_institution_id' => 'eq.' . $institutionId,
                'status' => 'eq.completed'
            ]);
            if (is_array($venueRecords)) {
                $venueEvents = count($venueRecords);
            }
        } catch (\Throwable $ve) {
            // Graceful fallback if venue_institution_id column was recently added
            $venueEvents = 0;
        }

        $totalHostingCredit = $hostedEvents + $venueEvents;

        // 5. Determine 3 compliance statuses per Constitution Art. V Sec. 3
        // Read thresholds centrally from SettingsService
        $settings = SettingsService::getInstance($this->db);
        $minPart = $settings->getMinParticipationRate();
        $atRiskPart = $settings->getAtRiskParticipationRate();
        $minEvents = $settings->getMinHostedEvents();

        if ($participationRate >= $minPart && $totalHostingCredit >= $minEvents) {
            $complianceStatus = 'compliant';
        } elseif ($participationRate >= $atRiskPart || $totalHostingCredit >= $minEvents) {
            $complianceStatus = 'at_risk';
        } else {
            $complianceStatus = 'non_compliant';
        }

        // 6. Calculate score (50% participation weight, 50% hosting/venue weight)
        $participationScore = $participationRate >= $minPart ? 50.0 : (($participationRate / max(1.0, $minPart)) * 50.0);
        $hostingScore = $totalHostingCredit >= $minEvents ? 50.0 : 0.0;
        $overallScore = min($participationScore + $hostingScore, 100.0);

        // 7. Upsert compliance score record & sync institution status via ComplianceRepository
        ComplianceRepository::getInstance($this->db)->saveScore($institutionId, $year, [
            'participation_rate' => round($participationRate, 2),
            'hosted_event_count' => $totalHostingCredit,
            'overall_score' => round($overallScore, 2),
            'compliance_status' => $complianceStatus,
            'last_updated' => date('Y-m-d H:i:s')
        ]);

        // 8. Record in blockchain (tamper-evident, hash-chained audit trail)
        $this->blockchain->record('compliance_attendance', $institutionId . '-' . $year, [
            'institution_id' => $institutionId,
            'year' => $year,
            'score' => round($overallScore, 2),
            'participation_rate' => round($participationRate, 2),
            'hosted_events' => $hostedEvents,
            'venue_events' => $venueEvents,
            'total_hosting_credit' => $totalHostingCredit,
            'compliance_status' => $complianceStatus
        ]);

        // 9. Send alert if at risk or non-compliant
        if ($complianceStatus === 'at_risk' || $complianceStatus === 'non_compliant') {
            $this->sendComplianceAlert($institutionId, $overallScore, $complianceStatus);
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
     * Send compliance monitoring reminder to school officers
     * @param string $institutionId
     * @param float $score
     * @param string $status
     */
    private function sendComplianceAlert(string $institutionId, float $score, string $status = 'at_risk'): void
    {
        // Get school officers
        $officers = $this->db->select('user_profiles', [
            'institution_id' => 'eq.' . $institutionId,
            'role' => 'eq.school_officer'
        ]);

        $statusLabel = ($status === 'non_compliant') ? 'Needs Improvement' : 'At Risk';
        $statusTitle = "IECEP-LSC Chapter Compliance Monitoring Reminder";
        $statusMsg = ($status === 'non_compliant')
            ? "Friendly monitoring reminder: Your chapter's compliance standing is currently {$score}% ({$statusLabel}). We encourage inviting more student members to upcoming regional events or coordinating to host/serve as venue to reach the 40% participation benchmark."
            : "Friendly monitoring reminder: Your chapter's compliance standing is currently {$score}% ({$statusLabel}). Please encourage member attendance in upcoming activities or coordinate with the Executive Board for chapter event hosting.";

        foreach ($officers as $officer) {
            try {
                $this->db->insert('notifications', [
                    'user_id' => $officer['id'],
                    'title' => $statusTitle,
                    'message' => $statusMsg,
                    'type' => 'warning',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (\Throwable $ne) {
                error_log("Notice sending compliance notification: " . $ne->getMessage());
            }
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
        return ComplianceRepository::getInstance($this->db)->getScoresForInstitution($institutionId, $year);
    }
}
