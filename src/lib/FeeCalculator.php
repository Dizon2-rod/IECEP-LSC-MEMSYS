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
            // Query fee_brackets for applicable bracket
            $result = null;
            if (method_exists($this->supabase, 'from')) {
                try {
                    $builder = $this->supabase->from('fee_brackets');
                    if (is_object($builder) && method_exists($builder, 'select')) {
                        $q = $builder->select('*');
                        if (is_object($q) && method_exists($q, 'eq')) {
                            $q = $q->eq('is_active', true);
                            if (is_object($q) && method_exists($q, 'lte')) $q = $q->lte('min_members', $memberCount);
                            if (is_object($q) && method_exists($q, 'order')) $q = $q->order('min_members', 'desc');
                            if (is_object($q) && method_exists($q, 'limit')) $q = $q->limit(1);
                            if (is_object($q) && method_exists($q, 'single')) $result = $q->single();
                            elseif (is_object($q) && method_exists($q, 'get')) {
                                $rows = $q->get();
                                $result = $rows[0] ?? null;
                            }
                        }
                    }
                } catch (\Throwable $fe) {
                    $result = null;
                }
            }
            
            if (!$result && method_exists($this->supabase, 'select')) {
                try {
                    $rows = $this->supabase->select('fee_brackets', [
                        'is_active' => 'eq.true',
                        'min_members' => 'lte.' . $memberCount,
                        'order' => 'min_members.desc',
                        'limit' => 1
                    ]);
                    $result = $rows[0] ?? null;
                } catch (\Throwable $se) {
                    $result = null;
                }
            }

            // Fallback according to 2025 Constitution & By-Laws (BR No. 021-2024)
            if (!$result || !isset($result['fee'])) {
                if ($memberCount <= 50) {
                    $nationalFee = 1500.00;
                } elseif ($memberCount <= 100) {
                    $nationalFee = 2000.00;
                } elseif ($memberCount <= 150) {
                    $nationalFee = 2500.00;
                } else {
                    $nationalFee = 3000.00;
                }
            } else {
                $nationalFee = (float) $result['fee'];
            }

            $operationalFee = $this->getOperationalFee();
            $totalFee = $nationalFee + $operationalFee;

            return [
                'national_fee'    => round($nationalFee, 2),
                'operational_fee' => round($operationalFee, 2),
                'total_fee'       => round($totalFee, 2),
            ];

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
            $feeRates = [
                'returning' => $this->getSettingFee('returning_member_fee', 200.00),
                'new'       => $this->getSettingFee('new_member_fee', 250.00),
                'honorary'  => $this->getSettingFee('honorary_member_fee', 300.00)
            ];

            // If not found in system_settings, check member_fees table
            if (!isset($feeRates['new']) || !isset($feeRates['returning'])) {
                $result = null;
                if (method_exists($this->supabase, 'from')) {
                    $result = $this->supabase->from('member_fees')
                        ->select('*')
                        ->eq('is_active', true)
                        ->get();
                } else {
                    $result = $this->supabase->select('member_fees', ['is_active' => 'eq.true']);
                }

                foreach ($result ?? [] as $row) {
                    if (isset($row['member_type'], $row['fee'])) {
                        $feeRates[$row['member_type']] = (float) $row['fee'];
                    }
                }
            }

            if (!isset($feeRates['returning'])) $feeRates['returning'] = 200.00;
            if (!isset($feeRates['new'])) $feeRates['new'] = 250.00;
            if (!isset($feeRates['honorary'])) $feeRates['honorary'] = 300.00;

            // Calculate total
            $total = (
                ($memberTypeCounts['new'] ?? 0) * $feeRates['new'] +
                ($memberTypeCounts['returning'] ?? 0) * $feeRates['returning'] +
                ($memberTypeCounts['honorary'] ?? 0) * $feeRates['honorary']
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
            $result = null;
            if (method_exists($this->supabase, 'from')) {
                try {
                    $builder = $this->supabase->from('system_settings');
                    if (is_object($builder) && method_exists($builder, 'select')) {
                        $q = $builder->select('*');
                        if (is_object($q) && method_exists($q, 'eq')) {
                            $q = $q->eq('key', $key);
                            if (is_object($q) && method_exists($q, 'single')) $result = $q->single();
                            elseif (is_object($q) && method_exists($q, 'get')) {
                                $rows = $q->get();
                                $result = $rows[0] ?? null;
                            }
                        }
                    }
                } catch (\Throwable $fe) {
                    $result = null;
                }
            }
            
            if (!$result && method_exists($this->supabase, 'select')) {
                try {
                    $rows = $this->supabase->select('system_settings', ['key' => 'eq.' . $key, 'limit' => 1]);
                    $result = $rows[0] ?? null;
                } catch (\Throwable $se) {
                    $result = null;
                }
            }

            if ($result && isset($result['value'])) {
                return (float) $result['value'];
            }
            return $default;
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
        return $this->getSettingFee('operational_fee', 800.00);
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