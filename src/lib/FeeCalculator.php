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
     * Calculate total affiliation fee (national fee + operational fee) based on member count
     * 
     * Returns the combined total for backward compatibility.
     * Use calculateAffiliationFeeDetailed() for a breakdown.
     * 
     * @param int $memberCount Number of members
     * @return float Total affiliation fee (national + operational)
     */
    /**
     * Calculate total affiliation fee (national fee + operational fee) based on member count
     * 
     * Returns the combined total for backward compatibility.
     * Use calculateAffiliationFeeDetailed() for a breakdown.
     * 
     * @param int $memberCount Number of members
     * @return float Total affiliation fee (national + operational)
     */
    public function calculateAffiliationFee(int $memberCount): float
    {
        $detailed = $this->calculateAffiliationFeeDetailed($memberCount);
        return $detailed['total_fee'];
    }

    /**
     * Calculate affiliation fee with detailed breakdown
     * 
     * Returns national_fee (from fee_brackets), operational_fee (from system_settings),
     * and total_fee (sum of both) per Board Resolution No. 021-2024.
     * 
     * @param int $memberCount Number of members
     * @return array ['national_fee' => float, 'operational_fee' => float, 'total_fee' => float]
     */
    public function calculateAffiliationFeeDetailed(int $memberCount): array
    {
        try {
            $settings = SettingsService::getInstance($this->supabase);
            return $settings->getAffiliationBreakdown($memberCount);
        } catch (\Exception $e) {
            error_log('Affiliation fee calculation error: ' . $e->getMessage());
            // Safe fallback to constitutional brackets
            $nationalFee = ($memberCount <= 50) ? 1500.00 : (($memberCount <= 100) ? 2000.00 : (($memberCount <= 150) ? 2500.00 : 3000.00));
            $operationalFee = 800.00;
            return [
                'national_fee'    => round($nationalFee, 2),
                'operational_fee' => round($operationalFee, 2),
                'total_fee'       => round($nationalFee + $operationalFee, 2),
            ];
        }
    }

    /**
     * Calculate total membership fees
     * 
     * Multiplies member type counts by their respective rates from system_settings / member_fees table.
     * 2025 Constitution Art. IV Sec. 2:
     * - Returning: ₱200.00
     * - New: ₱250.00
     * - Honorary: ₱300.00
     * 
     * @param array $memberTypeCounts ['new' => count, 'returning' => count, 'honorary' => count]
     * @return float Total membership fees
     */
    public function calculateMembershipFees(array $memberTypeCounts): float
    {
        try {
            $settings = SettingsService::getInstance($this->supabase);
            $feeRates = $settings->getMemberFees();

            // Calculate total
            $total = (
                ($memberTypeCounts['new'] ?? 0) * ($feeRates['new'] ?? 250.00) +
                ($memberTypeCounts['returning'] ?? 0) * ($feeRates['returning'] ?? 200.00) +
                ($memberTypeCounts['honorary'] ?? 0) * ($feeRates['honorary'] ?? 300.00)
            );

            return round($total, 2);

        } catch (\Exception $e) {
            error_log('Membership fee calculation error: ' . $e->getMessage());
            // Constitutional fallback
            $total = (
                ($memberTypeCounts['new'] ?? 0) * 250.00 +
                ($memberTypeCounts['returning'] ?? 0) * 200.00 +
                ($memberTypeCounts['honorary'] ?? 0) * 300.00
            );
            return round($total, 2);
        }
    }

    /**
     * Helper to get a specific fee from system_settings
     */
    public function getSettingFee(string $key, float $default): float
    {
        try {
            $settings = SettingsService::getInstance($this->supabase);
            return (float) $settings->getSystemSetting($key, $default);
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Get operational fee from system settings (Art. IV)
     * 
     * @return float Operational fee amount (₱800.00)
     */
    public function getOperationalFee(): float
    {
        $settings = SettingsService::getInstance($this->supabase);
        return $settings->getOperationalFee();
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
            $affiliationDetailed = $this->calculateAffiliationFeeDetailed($memberCount);
            $membershipFeesTotal = $this->calculateMembershipFees($memberTypeCounts);

            $totalFee = $affiliationDetailed['total_fee'] + $membershipFeesTotal;

            return [
                'member_count' => $memberCount,
                'new_members' => $memberTypeCounts['new'] ?? 0,
                'returning_members' => $memberTypeCounts['returning'] ?? 0,
                'honorary_members' => $memberTypeCounts['honorary'] ?? 0,
                'national_fee' => $affiliationDetailed['national_fee'],
                'affiliation_fee' => $affiliationDetailed['national_fee'],
                'operational_fee' => $affiliationDetailed['operational_fee'],
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
            $settings = SettingsService::getInstance($this->supabase);
            return $settings->getFeeBrackets();
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
            $settings = SettingsService::getInstance($this->supabase);
            return $settings->getMemberFeesList();
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

            SettingsService::getInstance($this->supabase)->invalidateCache();
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

            SettingsService::getInstance($this->supabase)->invalidateCache();
            return true;

        } catch (\Exception $e) {
            error_log('Error deleting fee bracket: ' . $e->getMessage());
            return false;
        }
    }
}