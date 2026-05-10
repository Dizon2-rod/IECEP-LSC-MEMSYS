<?php
namespace App\Lib;

use App\Lib\SupabaseClient;

class FeeCalculator
{
    private SupabaseClient $supabase;

    public function __construct(SupabaseClient $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Calculate membership fee based on member count
     *
     * @param int $memberCount Number of members in the institution
     * @return float Calculated fee amount
     * @throws \Exception If fee calculation fails
     */
    public function calculate(int $memberCount): float
    {
        try {
            // Query fee_brackets table for applicable bracket
            $result = $this->supabase->from('fee_brackets')
                ->select('*')
                ->lte('min_members', $memberCount)
                ->gte('max_members', $memberCount)
                ->single();

            if (!$result) {
                // Fallback to highest bracket if no exact match
                $result = $this->supabase->from('fee_brackets')
                    ->select('*')
                    ->order('max_members', 'desc')
                    ->limit(1)
                    ->single();

                if (!$result) {
                    throw new \Exception('No fee brackets configured');
                }
            }

            return (float) $result['fee_amount'];

        } catch (\Exception $e) {
            // Log error and return default fee
            error_log('Fee calculation error: ' . $e->getMessage());
            return 500.00; // Default fallback fee
        }
    }

    /**
     * Get all fee brackets for display/admin purposes
     *
     * @return array Array of fee bracket data
     */
    public function getAllBrackets(): array
    {
        try {
            $result = $this->supabase->from('fee_brackets')
                ->select('*')
                ->order('min_members', 'asc');

            return $result ?? [];

        } catch (\Exception $e) {
            error_log('Error fetching fee brackets: ' . $e->getMessage());
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
                    ->eq('id', $bracket['id']);
            } else {
                // Create new
                $this->supabase->from('fee_brackets')
                    ->insert($bracket);
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
     * @param int $bracketId Bracket ID to delete
     * @return bool Success status
     */
    public function deleteBracket(int $bracketId): bool
    {
        try {
            $this->supabase->from('fee_brackets')
                ->delete()
                ->eq('id', $bracketId);

            return true;

        } catch (\Exception $e) {
            error_log('Error deleting fee bracket: ' . $e->getMessage());
            return false;
        }
    }
}