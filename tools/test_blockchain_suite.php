<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/lib/BlockchainService.php';

use App\Lib\BlockchainService;

echo "=======================================================\n";
echo "  IECEP-LSC MEMSYS - BLOCKCHAIN COMPREHENSIVE TEST SUITE\n";
echo "=======================================================\n\n";

$supabase = getSupabaseClient();
$blockchain = new BlockchainService($supabase);

$testsPassed = 0;
$totalTests = 0;

function runTest(string $testName, callable $fn) {
    global $testsPassed, $totalTests;
    $totalTests++;
    echo "[TEST $totalTests] $testName... ";
    try {
        $result = $fn();
        if ($result === true || (is_array($result) && ($result['valid'] ?? true))) {
            echo "PASSED [OK]\n";
            $testsPassed++;
        } else {
            echo "FAILED!\n";
            if (is_array($result)) {
                echo "  Details: " . json_encode($result) . "\n";
            }
        }
    } catch (Throwable $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
    }
}

// 1. Test Keypair Provisioning & Asymmetric RSA Digital Signature
runTest("Asymmetric RSA-2048 Keypair Provisioning & Signing", function() use ($blockchain) {
    $testDataHash = hash('sha256', 'IECEP-TEST-PAYLOAD-' . time());
    $sig = $blockchain->signData($testDataHash);
    if (empty($sig)) return false;
    $isValid = $blockchain->verifySignature($testDataHash, $sig);
    return $isValid === true;
});

// 2. Test Anti-Tampering (Signature Verification Rejection on altered hash)
runTest("Signature Verification Rejection on Altered Data", function() use ($blockchain) {
    $originalHash = hash('sha256', 'ORIGINAL_DATA');
    $sig = $blockchain->signData($originalHash);
    $tamperedHash = hash('sha256', 'TAMPERED_DATA');
    $isValid = $blockchain->verifySignature($tamperedHash, $sig);
    return $isValid === false; // Must be false to pass
});

// 3. Test Merkle Root Computation
runTest("Merkle Tree Binary Root Generation", function() {
    $items = ['doc1_hash', 'doc2_hash', 'doc3_hash', 'doc4_hash', 'doc5_hash', 'doc6_hash'];
    $root = \App\Lib\MerkleTree::buildRoot($items);
    return !empty($root) && strlen($root) === 64;
});

// 4. Test Affiliation Requirements & Document Anchoring
runTest("Affiliation Submission & 6-Document Hashes Recording", function() use ($blockchain) {
    $testAppId = 'APP-TEST-' . uniqid();
    $affData = [
        'institution_name' => 'Laguna State Polytechnic University - Test',
        'contact_person' => 'Juan Dela Cruz',
        'contact_email' => 'juan@lspu.edu.ph',
        'total_members' => 45,
        'total_fee' => 4500.00,
        'receipt_number' => 'RCP-TEST-' . time(),
    ];
    $docHashes = [
        'letter_of_intent' => hash('sha256', 'LOI-CONTENT'),
        'endorsement_letter' => hash('sha256', 'ENDORSEMENT-CONTENT'),
        'constitution_by_laws' => hash('sha256', 'CBL-CONTENT'),
        'officers_cvs' => hash('sha256', 'CVS-CONTENT'),
        'organizational_chart' => hash('sha256', 'ORG-CHART-CONTENT'),
        'member_directory' => hash('sha256', 'DIRECTORY-CONTENT'),
    ];

    $result = $blockchain->recordAffiliation($testAppId, $affData, $docHashes);
    return !empty($result['master_block_hash']) && !empty($result['docs_merkle_root']);
});

// 5. Test Member Batch Roster Push & Merkle Root
runTest("Member Roster Push & Batch Merkle Root Anchoring", function() use ($blockchain) {
    $testInstId = 'INST-TEST-' . uniqid();
    $members = [
        ['student_number' => '2023-0001', 'full_name' => 'Maria Santos', 'email' => 'maria@test.edu.ph'],
        ['student_number' => '2023-0002', 'full_name' => 'Pedro Reyes', 'email' => 'pedro@test.edu.ph'],
        ['student_number' => '2023-0003', 'full_name' => 'Ana Lim', 'email' => 'ana@test.edu.ph'],
    ];

    $res = $blockchain->recordMemberBatch($members, $testInstId);
    return $res['total_recorded'] === 3 && !empty($res['merkle_root']);
});

// 6. Test Member State Pull & Chronological History Reconstruction
runTest("Member Blockchain History Pull & Verification", function() use ($blockchain) {
    $memberId = 'STU-' . uniqid();
    // 1st block: registration
    $blockchain->recordMember($memberId, ['full_name' => 'Carlo Gomez', 'action' => 'register', 'status' => 'pending']);
    // 2nd block: update to active
    $blockchain->recordMember($memberId, ['full_name' => 'Carlo Gomez', 'action' => 'verified_active', 'status' => 'active']);

    $history = $blockchain->pullMemberHistory($memberId);
    return $history['verified'] === true && $history['total_blocks'] >= 2 && $history['latest_state']['status'] === 'active';
});

// 7. Test Financial Receipt & Transaction Ledger
runTest("Financial Receipt & Transaction Tamper-Evident Anchoring", function() use ($blockchain) {
    $rcpNo = 'RCP-' . time() . '-' . rand(100, 999);
    $rcpRes = $blockchain->recordReceipt($rcpNo, [
        'payer_name' => 'Letran Calamba Officer',
        'amount' => 5000.00,
        'purpose' => 'Annual Chapter Affiliation Fee',
        'reference_no' => 'GCASH-REF-' . time(),
    ]);

    $txRes = $blockchain->recordFinancialTransaction('TX-' . uniqid(), [
        'type' => 'affiliation_payment',
        'amount' => 5000.00,
        'status' => 'confirmed'
    ]);

    return !empty($rcpRes['hash']) && !empty($txRes['hash']);
});

// 8. Test Universal Hash Verification
runTest("Universal Hash Existence & Signature Lookup", function() use ($blockchain) {
    $rcpNo = 'RCP-LOOKUP-' . uniqid();
    $recorded = $blockchain->recordReceipt($rcpNo, ['amount' => 1200]);
    $lookup = $blockchain->hashExists($recorded['hash']);
    return $lookup['exists'] === true && $lookup['signature_valid'] === true;
});

// 9. Test Standalone Block Proof Certificate Export (W3C format)
runTest("W3C Block Proof Certificate Generation", function() use ($blockchain) {
    $blocks = $blockchain->getLatestBlocks(1);
    if (empty($blocks)) return false;
    $proof = $blockchain->exportBlockProof($blocks[0]['id']);
    return !empty($proof['cryptographic_hash']) && isset($proof['signature']['publicKey']);
});

echo "\n=======================================================\n";
echo "  TEST RESULTS: $testsPassed / $totalTests PASSED (" . round(($testsPassed / $totalTests) * 100) . "%)\n";
echo "=======================================================\n";
