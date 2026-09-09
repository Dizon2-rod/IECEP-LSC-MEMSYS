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

    /**
     * Recalculate and persist financial totals for a single institution.
     * Logs an audit entry if the stored totals differ from the calculated ones.
     */
    public function syncInstitutionTotals(string $institutionId, ?string $performedBy = null, string $auditAction = 'sync_correction'): array
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

            if (in_array($status, ['paid', 'completed', 'verified', 'settled', 'success', 'approved'], true)) {
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

        // Read old stored totals for audit comparison
        $existing = $this->supabase->select('institution_financial_totals', [
            'institution_id' => 'eq.' . $institutionId,
            'limit' => 1
        ]);
        $oldStored = $this->isRecordList($existing) ? ($existing[0] ?? []) : [];

        $payload = array_merge($totals, [
            'institution_id' => $institutionId,
            'last_synced_at' => $now
        ]);

        if (!empty($existing[0]['id'])) {
            $this->supabase->update('institution_financial_totals', $payload, $existing[0]['id']);
        } else {
            $this->supabase->insert('institution_financial_totals', $payload);
        }

        // Log audit entry if any monetary field changed
        $auditFields = ['total_paid', 'total_pending', 'total_refunded', 'total_cancelled', 'grand_total_all_time', 'current_year_total'];
        $corrections = [];
        foreach ($auditFields as $field) {
            $oldVal = round((float)($oldStored[$field] ?? 0), 2);
            $newVal = round((float)($totals[$field] ?? 0), 2);
            if (abs($oldVal - $newVal) > 0.009) {
                $corrections[$field] = ['old' => $oldVal, 'new' => $newVal, 'delta' => round($newVal - $oldVal, 2)];
            }
        }
        if (!empty($corrections)) {
            $this->logAuditEntry(
                $institutionId,
                null,
                $auditAction,
                array_intersect_key($oldStored, array_flip($auditFields)),
                $totals,
                $performedBy
            );
        }

        // Mark transactions as synchronized
        foreach ($transactions as $transaction) {
            if (!empty($transaction['id'])) {
                try {
                    $this->supabase->update('transactions', ['synchronized_at' => $now], $transaction['id']);
                } catch (\Throwable $e) {
                    error_log('Financial sync transaction timestamp notice: ' . $e->getMessage());
                }
            }
        }

        $payload['corrections'] = $corrections;
        return $payload;
    }

    /**
     * Sync all institutions and return per-institution results with correction details.
     */
    public function syncAllInstitutions(?string $performedBy = null): array
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
                    $this->syncInstitutionTotals((string)$institution['id'], $performedBy)
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

    /**
     * Verify stored totals against calculated totals for a single institution.
     */
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

    /**
     * Batch-verify all institutions. Returns an array of verification results.
     */
    public function verifyAllInstitutions(): array
    {
        $institutions = $this->supabase->select('institutions', ['select' => 'id,name', 'order' => 'name.asc']);
        if (!$this->isRecordList($institutions)) {
            return [];
        }

        $results = [];
        foreach ($institutions as $institution) {
            if (empty($institution['id'])) {
                continue;
            }
            try {
                $v = $this->verifyTotals((string)$institution['id']);
                $v['institution_name'] = $institution['name'] ?? 'Institution';
                $results[] = $v;
            } catch (\Throwable $e) {
                $results[] = [
                    'institution_id' => $institution['id'],
                    'institution_name' => $institution['name'] ?? 'Institution',
                    'match' => false,
                    'mismatches' => ['error' => ['expected' => '', 'actual' => $e->getMessage()]]
                ];
            }
        }

        return $results;
    }

    /**
     * Return system-wide totals aggregated from all institution_financial_totals rows.
     */
    public function getGlobalSummary(): array
    {
        $rows = $this->supabase->select('institution_financial_totals', ['select' => '*']);
        $summary = [
            'total_paid' => 0.0,
            'total_pending' => 0.0,
            'total_refunded' => 0.0,
            'total_cancelled' => 0.0,
            'grand_total_all_time' => 0.0,
            'current_year_total' => 0.0,
            'transaction_count' => 0,
            'institution_count' => 0,
            'last_synced_at' => null,
            'institutions' => []
        ];

        foreach ($this->isRecordList($rows) ? $rows : [] as $row) {
            $summary['total_paid'] += (float)($row['total_paid'] ?? 0);
            $summary['total_pending'] += (float)($row['total_pending'] ?? 0);
            $summary['total_refunded'] += (float)($row['total_refunded'] ?? 0);
            $summary['total_cancelled'] += (float)($row['total_cancelled'] ?? 0);
            $summary['grand_total_all_time'] += (float)($row['grand_total_all_time'] ?? 0);
            $summary['current_year_total'] += (float)($row['current_year_total'] ?? 0);
            $summary['transaction_count'] += (int)($row['transaction_count'] ?? 0);
            $summary['institution_count']++;

            $syncedAt = $row['last_synced_at'] ?? null;
            if ($syncedAt && (!$summary['last_synced_at'] || strtotime($syncedAt) > strtotime($summary['last_synced_at']))) {
                $summary['last_synced_at'] = $syncedAt;
            }

            $summary['institutions'][] = $row;
        }

        // If institution_financial_totals has no records or 0 paid, cross-check transactions directly
        if ($summary['institution_count'] === 0 || $summary['total_paid'] === 0.0) {
            try {
                $txRows = $this->supabase->select('transactions', ['select' => '*']);
                if ($this->isRecordList($txRows) && !empty($txRows)) {
                    $paidAliases = ['paid', 'completed', 'verified', 'settled', 'success', 'approved'];
                    $txPaid = 0.0;
                    $txPending = 0.0;
                    $txGrand = 0.0;
                    $uniqueInsts = [];
                    foreach ($txRows as $tx) {
                        $amt = round((float)($tx['amount'] ?? 0), 2);
                        $st = strtolower(trim((string)($tx['status'] ?? 'pending')));
                        $txGrand += $amt;
                        if (in_array($st, $paidAliases, true)) {
                            $txPaid += $amt;
                        } elseif (!in_array($st, ['refunded', 'cancelled', 'canceled', 'rejected'], true)) {
                            $txPending += $amt;
                        }
                        if (!empty($tx['institution_id'])) {
                            $uniqueInsts[$tx['institution_id']] = true;
                        }
                    }
                    if ($txPaid > 0 || count($txRows) > 0) {
                        $summary['total_paid'] = round($txPaid, 2);
                        $summary['total_pending'] = round($txPending, 2);
                        $summary['grand_total_all_time'] = round($txGrand, 2);
                        $summary['current_year_total'] = round($txPaid, 2);
                        $summary['transaction_count'] = count($txRows);
                        if ($summary['institution_count'] === 0) {
                            $summary['institution_count'] = count($uniqueInsts);
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('FinancialSyncService transactions fallback error: ' . $e->getMessage());
            }
        }

        foreach (['total_paid', 'total_pending', 'total_refunded', 'total_cancelled', 'grand_total_all_time', 'current_year_total'] as $k) {
            $summary[$k] = round($summary[$k], 2);
        }

        return $summary;
    }

    /**
     * Fetch recent financial audit log entries.
     */
    public function getAuditLog(?string $institutionId = null, int $limit = 50): array
    {
        $filters = [
            'select' => '*',
            'order' => 'created_at.desc',
            'limit' => $limit
        ];
        if ($institutionId) {
            $filters['institution_id'] = 'eq.' . $institutionId;
        }

        $rows = $this->supabase->select('financial_audit_logs', $filters);
        return $this->isRecordList($rows) ? $rows : [];
    }

    /**
     * Insert a financial audit log entry from PHP (supplements DB-level triggers).
     */
    public function logAuditEntry(
        ?string $institutionId,
        ?string $transactionId,
        string  $action,
        $oldValue = null,
        $newValue = null,
        ?string $performedBy = null
    ): void {
        try {
            $perfUuid = $performedBy ?? ($_SESSION['user']['id'] ?? null);
            if ($perfUuid && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $perfUuid)) {
                $perfUuid = null;
            }

            $this->supabase->insert('financial_audit_logs', [
                'institution_id' => $institutionId,
                'transaction_id' => $transactionId,
                'action' => $action,
                'old_value' => $oldValue !== null ? json_encode($oldValue) : null,
                'new_value' => $newValue !== null ? json_encode($newValue) : null,
                'performed_by' => $perfUuid,
                'created_at' => date('c')
            ]);
        } catch (\Throwable $e) {
            error_log('Financial audit log entry failed: ' . $e->getMessage());
        }
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
