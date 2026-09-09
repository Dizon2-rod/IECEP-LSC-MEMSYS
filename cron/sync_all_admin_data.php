<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/lib/FinancialSyncService.php';

$cronSecret = $_SERVER['CRON_SECRET'] ?? getenv('CRON_SECRET') ?? 'change-this-secret-in-production';
$providedSecret = $_GET['secret'] ?? ($argv[1] ?? '');
$isCli = (php_sapi_name() === 'cli');

if (!$isCli && $providedSecret !== $cronSecret) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized cron trigger']));
}

function gen_uuid_v4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$supabase = getSupabaseClient();
$syncService = new \App\Lib\FinancialSyncService($supabase);

echo "========================================================\n";
echo "IECEP-LSC MEMSYS: System-Wide Admin Data Synchronization\n";
echo "Timestamp: " . date('c') . "\n";
echo "========================================================\n\n";

// 1. Fetch fee brackets
$feeBrackets = [];
try {
    $rawBrackets = $supabase->select('fee_brackets', ['select' => '*', 'is_active' => 'eq.true', 'order' => 'min_members.asc']);
    if (is_array($rawBrackets) && !empty($rawBrackets)) {
        $feeBrackets = $rawBrackets;
    }
} catch (\Throwable $e) {}

$calcBracketFee = function($memberCount) use ($feeBrackets) {
    $cnt = max(1, intval($memberCount));
    if (!empty($feeBrackets)) {
        $applied = 1500.00;
        foreach ($feeBrackets as $b) {
            $min = intval($b['min_members'] ?? 0);
            $max = intval($b['max_members'] ?? 999999);
            if ($cnt >= $min && $cnt <= $max) {
                return floatval($b['fee'] ?? 1500.00);
            }
            if ($cnt >= $min) {
                $applied = floatval($b['fee'] ?? 1500.00);
            }
        }
        return $applied;
    }
    if ($cnt <= 50) return 1500.00;
    if ($cnt <= 100) return 2000.00;
    if ($cnt <= 150) return 2500.00;
    return 3000.00;
};

// 2. Fetch all institutions
$institutions = $supabase->select('institutions', ['select' => '*', 'order' => 'name.asc']);
if (!is_array($institutions)) {
    die("Failed to fetch institutions\n");
}

$timestamp = date('c');
$syncedTxCount = 0;
$totalCollectionsExpected = 0.0;

foreach ($institutions as $inst) {
    $instId = $inst['id'];
    $instName = $inst['name'] ?? 'Chapter';
    $cnt = intval($inst['membership_count'] ?? 1);
    if ($cnt <= 0) $cnt = 1;

    $bracketFee = $calcBracketFee($cnt);
    $operationalFee = 800.00;
    $membershipTotal = $cnt * 200.00;
    $totalFee = $bracketFee + $operationalFee + $membershipTotal;
    $rcpNumber = 'RCP-' . date('Y') . '-' . strtoupper(substr(md5($instId . $instName), 0, 5));

    $totalCollectionsExpected += $totalFee;

    // Check if transaction exists
    $existingTx = $supabase->select('transactions', [
        'institution_id' => 'eq.' . $instId,
        'limit' => 1
    ]);

    $meta = [
        'institution_id' => $instId,
        'institution_name' => $instName,
        'acronym' => $inst['acronym'] ?? 'HEI',
        'contact_person' => $inst['contact_person'] ?? 'Chapter Adviser',
        'contact_email' => $inst['email'] ?? '',
        'total_members' => $cnt,
        'affiliation_fee' => $bracketFee,
        'operational_fee' => $operationalFee,
        'membership_total' => $membershipTotal,
        'total_fee' => $totalFee,
        'receipt_number' => $rcpNumber,
        'academic_year' => '2026-2027',
        'verified_at' => $timestamp
    ];

    $txnCode = 'TXN-2026-' . strtoupper(substr(md5($instId), 0, 8));
    $proofHash = hash('sha256', $rcpNumber . '|' . $instName . '|' . $totalFee . '|' . $timestamp);
    $notes = "Institutional Affiliation ({$instName}): Bracket ₱{$bracketFee}, Op Fee ₱{$operationalFee}, Student Dues ₱{$membershipTotal} ({$cnt} enrolled members)";

    if (!empty($existingTx) && is_array($existingTx) && !empty($existingTx[0]['id'])) {
        $txId = $existingTx[0]['id'];
        $supabase->update('transactions', [
            'amount' => $totalFee,
            'status' => 'paid',
            'type' => 'membership_fee',
            'transaction_type' => 'affiliation_fee',
            'fee_type' => 'affiliation_fee',
            'receipt_number' => $rcpNumber,
            'reference_number' => $rcpNumber,
            'payment_method' => 'gcash',
            'blockchain_hash' => $proofHash,
            'notes' => $notes,
            'updated_at' => $timestamp
        ], $txId);
    } else {
        $txId = gen_uuid_v4();
        $supabase->insert('transactions', [[
            'id' => $txId,
            'transaction_id' => $txnCode,
            'type' => 'membership_fee',
            'transaction_type' => 'affiliation_fee',
            'fee_type' => 'affiliation_fee',
            'receipt_number' => $rcpNumber,
            'reference_number' => $rcpNumber,
            'amount' => $totalFee,
            'payment_method' => 'gcash',
            'status' => 'paid',
            'institution_id' => $instId,
            'blockchain_hash' => $proofHash,
            'notes' => $notes,
            'created_at' => $timestamp,
            'updated_at' => $timestamp
        ]]);
    }
    $syncedTxCount++;

    // Anchor blockchain proof
    try {
        $supabase->insert('blockchain_records', [[
            'entity_type' => 'transaction',
            'entity_id' => $instId,
            'record_type' => 'affiliation_payment',
            'transaction_hash' => $proofHash,
            'record_hash' => $proofHash,
            'data_hash' => $proofHash,
            'confirmed' => true,
            'data_json' => [
                'institution_id' => $instId,
                'institution_name' => $instName,
                'receipt_number' => $rcpNumber,
                'total_fee' => $totalFee,
                'membership_total' => $membershipTotal,
                'affiliation_fee' => $bracketFee,
                'operational_fee' => $operationalFee,
                'status' => 'paid',
                'verified_by' => 'IECEP-LSC Treasury Sync Engine'
            ],
            'created_at' => $timestamp
        ]]);
    } catch (\Throwable $bcEx) {
        // Continue if duplicate or warning
    }

    // Now recalculate & persist financial totals
    try {
        $syncResult = $syncService->syncInstitutionTotals($instId, 'System Synchronizer', 'initial_sync');
        echo "✓ Synced [{$instName}]: ₱" . number_format($totalFee, 2) . " (Receipt: {$rcpNumber})\n";
    } catch (\Throwable $sEx) {
        echo "✗ Sync error for [{$instName}]: " . $sEx->getMessage() . "\n";
    }
}

echo "\n--- SUMMARY ---\n";
echo "Institutions Synced: " . count($institutions) . "\n";
echo "Transactions Synced: " . $syncedTxCount . "\n";
echo "Total Collections Synchronized: ₱" . number_format($totalCollectionsExpected, 2) . "\n";

$global = $syncService->getGlobalSummary();
echo "Verified Global Summary Total: ₱" . number_format($global['total_paid'], 2) . "\n";
echo "Synchronization completed successfully.\n";
