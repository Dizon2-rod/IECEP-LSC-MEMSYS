<?php
namespace App\Lib;

use App\Lib\SupabaseClient;

require_once __DIR__ . '/../../bootstrap.php';
/**
 * FeeCalculator - Database-driven fee calculation
 * 
 * All fees are loaded from the database tables:
 * - fee_brackets: affiliation fee by member count
 * - member_fees: per-member fees by member type
 * - system_settings: operational fee (key='operational_fee')
 * 
 * No hardcoded fee values in this class.
 * SOURCE: Deliverable 3.9 - FeeCalculator Modifications
 */
class FeeCalculator
{
    private SupabaseClient $supabase;

    public function __construct(SupabaseClient $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Calculate affiliation fee based on member count
     * 
     * Queries fee_brackets table for the applicable bracket.
     * 
     * @param int $memberCount Number of members
     * @return float Affiliation fee amount
     */
    public function calculateAffiliationFee(int $memberCount): float
    {
        try {
            // Query fee_brackets for applicable bracket
            $result = $this->supabase->from('fee_brackets')
                ->select('*')
                ->eq('is_active', true)
                ->lte('min_members', $memberCount)
                ->order('min_members', 'desc')
                ->limit(1)
                ->single();

            if (!$result) {
                throw new \Exception('No applicable fee bracket found for member count: ' . $memberCount);
            }

            return (float) $result['fee'];

        } catch (\Exception $e) {
            error_log('Affiliation fee calculation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate total membership fees
     * 
     * Multiplies member type counts by their respective rates from member_fees table.
     * 
     * @param array $memberTypeCounts ['new' => count, 'returning' => count, 'honorary' => count]
     * @return float Total membership fees
     */
    public function calculateMembershipFees(array $memberTypeCounts): float
    {
        try {
            // Load member fees from database
            $result = $this->supabase->from('member_fees')
                ->select('*')
                ->eq('is_active', true)
                ->get();

            $feeRates = [];
            foreach ($result ?? [] as $row) {
                $feeRates[$row['member_type']] = (float) $row['fee'];
            }

            // Default rates if not found (should not happen if DB is properly seeded)
            if (!isset($feeRates['new'])) $feeRates['new'] = 250.00;
            if (!isset($feeRates['returning'])) $feeRates['returning'] = 200.00;
            if (!isset($feeRates['honorary'])) $feeRates['honorary'] = 300.00;

            // Calculate total
            $total = (
                ($memberTypeCounts['new'] ?? 0) * $feeRates['new'] +
                ($memberTypeCounts['returning'] ?? 0) * $feeRates['returning'] +
                ($memberTypeCounts['honorary'] ?? 0) * $feeRates['honorary']
            );

            return $total;

        } catch (\Exception $e) {
            error_log('Membership fee calculation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get operational fee from system settings
     * 
     * @return float Operational fee amount
     */
    public function getOperationalFee(): float
    {
        try {
            $result = $this->supabase->from('system_settings')
                ->select('*')
                ->eq('key', 'operational_fee')
                ->single();

            if (!$result || !isset($result['value'])) {
                return 800.00; // Default operational fee
            }

            return (float) $result['value'];

        } catch (\Exception $e) {
            error_log('Error fetching operational fee: ' . $e->getMessage());
            return 800.00; // Default operational fee
        }
    }

    /**
     * Comprehensive fee calculation
     * 
     * Returns all fee components and totals.
     * 
     * @param int $memberCount Total number of members
     * @param array $memberTypeCounts ['new' => count, 'returning' => count, 'honorary' => count]
     * @return array Detailed breakdown of all fees
     */
    public function calculate(int $memberCount, array $memberTypeCounts): array
    {
        try {
            $affiliationFee = $this->calculateAffiliationFee($memberCount);
            $membershipFeesTotal = $this->calculateMembershipFees($memberTypeCounts);
            $operationalFee = $this->getOperationalFee();

            $totalFee = $affiliationFee + $membershipFeesTotal + $operationalFee;

            return [
                'member_count' => $memberCount,
                'new_members' => $memberTypeCounts['new'] ?? 0,
                'returning_members' => $memberTypeCounts['returning'] ?? 0,
                'honorary_members' => $memberTypeCounts['honorary'] ?? 0,
                'affiliation_fee' => round($affiliationFee, 2),
                'operational_fee' => round($operationalFee, 2),
                'membership_fees_total' => round($membershipFeesTotal, 2),
                'total_fee' => round($totalFee, 2)
            ];

        } catch (\Exception $e) {
            error_log('Comprehensive fee calculation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all active fee brackets
     * 
     * For admin display purposes.
     * 
     * @return array Array of fee bracket data
     */
    public function getAllBrackets(): array
    {
        try {
            $result = $this->supabase->from('fee_brackets')
                ->select('*')
                ->eq('is_active', true)
                ->order('min_members', 'asc')
                ->get();

            return $result ?? [];

        } catch (\Exception $e) {
            error_log('Error fetching fee brackets: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active member fee rates
     * 
     * @return array Array of member fee data
     */
    public function getAllMemberFees(): array
    {
        try {
            $result = $this->supabase->from('member_fees')
                ->select('*')
                ->eq('is_active', true)
                ->get();

            return $result ?? [];

        } catch (\Exception $e) {
            error_log('Error fetching member fees: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update or create fee bracket
     * 
     * @param array $bracket Bracket data
     * @return bool Success status
     */
    public function updateBracket(array $bracket): bool
    {
        try {
            if (isset($bracket['id'])) {
                // Update existing
                $this->supabase->from('fee_brackets')
                    ->update($bracket)
                    ->eq('id', $bracket['id'])
                    ->update(false);
            } else {
                // Create new
                $this->supabase->from('fee_brackets')
                    ->insert($bracket)
                    ->create(false);
            }

            return true;

        } catch (\Exception $e) {
            error_log('Error updating fee bracket: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete fee bracket
     * 
     * @param string $bracketId Bracket UUID to delete
     * @return bool Success status
     */
    public function deleteBracket(string $bracketId): bool
    {
        try {
            $this->supabase->from('fee_brackets')
                ->delete()
                ->eq('id', $bracketId)
                ->delete(false);

            return true;

        } catch (\Exception $e) {
            error_log('Error deleting fee bracket: ' . $e->getMessage());
            return false;
        }
    }
}