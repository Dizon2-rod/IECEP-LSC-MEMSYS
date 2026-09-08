<?php
namespace App\Lib;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/SupabaseClient.php';

class FinancialSyncService
{
    private SupabaseClient $supabase;

    public function __construct(?SupabaseClient $supabase = null)
    {
        if ($supabase) {
            $this->supabase = $supabase;
            return;
        }

        $config = require __DIR__ . '/../../includes/supabase.php';
        $this->supabase = new SupabaseClient($config['url'], $config['service_role_key']);
    }

    public function syncInstitutionTotals(string $institutionId): array
    {
        $transactions = $this->supabase->select('transactions', [
            'institution_id' => 'eq.' . $institutionId,
            'select' => 'id,transaction_id,amount,status,created_at,updated_at'
        ]);

        if (!$this->isRecordList($transactions)) {
            throw new \RuntimeException('Unable to read institution transactions');
        }

        $totals = [
            'total_paid' => 0.0,
            'total_pending' => 0.0,
            'total_refunded' => 0.0,
            'total_cancelled' => 0.0,
            'grand_total_all_time' => 0.0,
            'current_year_total' => 0.0,
            'transaction_count' => count($transactions)
        ];
        [$yearStart, $yearEnd] = $this->getAcademicYearRange();

        foreach ($transactions as $transaction) {
            $amount = round((float)($transaction['amount'] ?? 0), 2);
            $status = strtolower(trim((string)($transaction['status'] ?? 'pending')));
            $totals['grand_total_all_time'] += $amount;

            if (in_array($status, ['paid', 'completed', 'verified'], true)) {
                $totals['total_paid'] += $amount;
                if ($this->isWithinRange($transaction['created_at'] ?? null, $yearStart, $yearEnd)) {
                    $totals['current_year_total'] += $amount;
                }
            } elseif ($status === 'refunded') {
                $totals['total_refunded'] += $amount;
            } elseif (in_array($status, ['cancelled', 'canceled', 'rejected'], true)) {
                $totals['total_cancelled'] += $amount;
            } else {
                $totals['total_pending'] += $amount;
            }
        }

        foreach ($totals as $key => $value) {
            if (is_float($value)) {
                $totals[$key] = round($value, 2);
            }
        }

        $now = date('c');
        $existing = $this->supabase->select('institution_financial_totals', [
            'institution_id' => 'eq.' . $institutionId,
            'limit' => 1
        ]);
        $payload = array_merge($totals, [
            'institution_id' => $institutionId,
            'last_synced_at' => $now
        ]);

        if (!empty($existing[0]['id'])) {
            $this->supabase->update('institution_financial_totals', $payload, $existing[0]['id']);
        } else {
            $this->supabase->insert('institution_financial_totals', $payload);
        }

        foreach ($transactions as $transaction) {
            if (!empty($transaction['id'])) {
                try {
                    $this->supabase->update('transactions', ['synchronized_at' => $now], $transaction['id']);
                } catch (\Throwable $e) {
                    error_log('Financial sync transaction timestamp notice: ' . $e->getMessage());
                }
            }
        }

        return $payload;
    }

    public function syncAllInstitutions(): array
    {
        $institutions = $this->supabase->select('institutions', ['select' => 'id,name', 'order' => 'name.asc']);
        if (!$this->isRecordList($institutions)) {
            throw new \RuntimeException('Unable to read institutions');
        }

        $results = [];
        foreach ($institutions as $institution) {
            if (empty($institution['id'])) {
                continue;
            }
            try {
                $results[] = array_merge(
                    ['institution_id' => $institution['id'], 'institution_name' => $institution['name'] ?? 'Institution'],
                    $this->syncInstitutionTotals((string)$institution['id'])
                );
            } catch (\Throwable $e) {
                $results[] = [
                    'institution_id' => $institution['id'],
                    'institution_name' => $institution['name'] ?? 'Institution',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    public function verifyTotals(string $institutionId): array
    {
        $expected = $this->calculateTotals($institutionId);
        $storedRows = $this->supabase->select('institution_financial_totals', [
            'institution_id' => 'eq.' . $institutionId,
            'limit' => 1
        ]);
        $stored = $this->isRecordList($storedRows) ? ($storedRows[0] ?? []) : [];
        $fields = ['total_paid', 'total_pending', 'total_refunded', 'total_cancelled', 'grand_total_all_time', 'current_year_total'];
        $mismatches = [];

        foreach ($fields as $field) {
            $actual = round((float)($stored[$field] ?? 0), 2);
            $calculated = round((float)($expected[$field] ?? 0), 2);
            if (abs($actual - $calculated) > 0.009) {
                $mismatches[$field] = ['expected' => $calculated, 'actual' => $actual];
            }
        }

        return [
            'institution_id' => $institutionId,
            'match' => empty($mismatches) && !empty($stored),
            'expected' => $expected,
            'actual' => $stored,
            'mismatches' => $mismatches
        ];
    }

    private function calculateTotals(string $institutionId): array
    {
        $transactions = $this->supabase->select('transactions', [
            'institution_id' => 'eq.' . $institutionId,
            'select' => 'amount,status,created_at'
        ]);
        if (!$this->isRecordList($transactions)) {
            throw new \RuntimeException('Unable to read institution transactions');
        }

        $totals = [
            'total_paid' => 0.0,
            'total_pending' => 0.0,
            'total_refunded' => 0.0,
            'total_cancelled' => 0.0,
            'grand_total_all_time' => 0.0,
            'current_year_total' => 0.0,
            'transaction_count' => count($transactions)
        ];
        [$yearStart, $yearEnd] = $this->getAcademicYearRange();

        foreach ($transactions as $transaction) {
            $amount = round((float)($transaction['amount'] ?? 0), 2);
            $status = strtolower(trim((string)($transaction['status'] ?? 'pending')));
            $totals['grand_total_all_time'] += $amount;
            if (in_array($status, ['paid', 'completed', 'verified'], true)) {
                $totals['total_paid'] += $amount;
                if ($this->isWithinRange($transaction['created_at'] ?? null, $yearStart, $yearEnd)) {
                    $totals['current_year_total'] += $amount;
                }
            } elseif ($status === 'refunded') {
                $totals['total_refunded'] += $amount;
            } elseif (in_array($status, ['cancelled', 'canceled', 'rejected'], true)) {
                $totals['total_cancelled'] += $amount;
            } else {
                $totals['total_pending'] += $amount;
            }
        }

        foreach ($totals as $key => $value) {
            if (is_float($value)) {
                $totals[$key] = round($value, 2);
            }
        }
        return $totals;
    }

    private function getAcademicYearRange(): array
    {
        $settings = $this->supabase->select('system_settings', ['select' => 'key,value']);
        $values = [];
        foreach ($this->isRecordList($settings) ? $settings : [] as $setting) {
            if (!empty($setting['key'])) {
                $values[strtolower((string)$setting['key'])] = $setting['value'] ?? null;
            }
        }

        $start = $this->firstDate($values, ['academic_year_start', 'academic_year_start_date', 'current_academic_year_start']);
        $end = $this->firstDate($values, ['academic_year_end', 'academic_year_end_date', 'current_academic_year_end']);
        if (!$start || !$end) {
            $year = (int)date('Y');
            return ["$year-01-01T00:00:00+00:00", "$year-12-31T23:59:59+00:00"];
        }
        return [$start, $end];
    }

    private function firstDate(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!empty($values[$key]) && strtotime((string)$values[$key]) !== false) {
                return date('c', strtotime((string)$values[$key]));
            }
        }
        return null;
    }

    private function isWithinRange(?string $value, string $start, string $end): bool
    {
        $timestamp = $value ? strtotime($value) : false;
        return $timestamp !== false && $timestamp >= strtotime($start) && $timestamp <= strtotime($end);
    }

    private function isRecordList($value): bool
    {
        return is_array($value) && (empty($value) || array_is_list($value));
    }
}
