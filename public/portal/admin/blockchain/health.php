<?php
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../../../../includes/role-config.php';
require_once __DIR__ . '/../../../../bootstrap.php';

$current_page = 'health';

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Get blockchain record types and their stats
$recordTypes = [
    'membership',
    'affiliation',
    'document_hash',
    'payment',
    'digital_id',
    'event_attendance',
    'transaction',
    'compliance_attendance',
    'certificate',
];

$blockchainStats = [];
foreach ($recordTypes as $type) {
    try {
        $records = $supabase->select('blockchain_records', [
            'entity_type' => 'eq.' . $type,
            'order' => 'created_at.desc',
            'limit' => 1,
        ]);
        
        $totalResult = $supabase->select('blockchain_records', [
            'entity_type' => 'eq.' . $type,
        ]);
        
        $total = count($totalResult);
        $lastRecord = !empty($records) ? $records[0] : null;
        $lastDate = $lastRecord['created_at'] ?? null;
        
        $blockchainStats[$type] = [
            'total' => $total,
            'last_date' => $lastDate,
            'last_record' => $lastRecord,
        ];
    } catch (Exception $e) {
        $blockchainStats[$type] = [
            'total' => 0,
            'last_date' => null,
            'error' => $e->getMessage(),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <title>Blockchain Health Check - Admin Portal</title>
    <style>
        .health-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            background: white;
        }
        .health-card.valid {
            border-left: 4px solid #22c55e;
        }
        .health-card.invalid {
            border-left: 4px solid #ef4444;
        }
        .health-card.pending {
            border-left: 4px solid #f59e0b;
        }
        .stat-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .stat-badge.valid {
            background: #dcfce7;
            color: #166534;
        }
        .stat-badge.invalid {
            background: #fee2e2;
            color: #991b1b;
        }
        .stat-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        .verify-btn {
            background: #0B1D4A;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .verify-btn:hover {
            background: #1E3A6E;
        }
        .verify-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }
        .verification-result {
            margin-top: 16px;
            padding: 12px;
            border-radius: 8px;
            display: none;
        }
        .verification-result.success {
            background: #dcfce7;
            color: #166534;
        }
        .verification-result.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .tampered-list {
            margin-top: 12px;
            padding: 12px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
        }
        .tampered-item {
            padding: 8px;
            margin-bottom: 8px;
            background: white;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #0B1D4A;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2">Blockchain Health Check</h1>
                    <p class="text-muted">Monitor and verify blockchain integrity across all record types</p>
                </div>

                <div class="grid-2">
                    <?php foreach ($blockchainStats as $type => $stats): ?>
                        <div class="health-card" id="card-<?php echo $type; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <i class="fas fa-link me-2" style="color: var(--accent)"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                </h5>
                                <span class="stat-badge pending" id="badge-<?php echo $type; ?>">
                                    <?php echo $stats['total'] > 0 ? 'Pending' : 'No Records'; ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Total Records:</span>
                                    <strong><?php echo $stats['total']; ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Last Record:</span>
                                    <small><?php echo $stats['last_date'] ? date('M d, Y g:i A', strtotime($stats['last_date'])) : 'Never'; ?></small>
                                </div>
                            </div>
                            
                            <button class="verify-btn" onclick="verifyChain('<?php echo $type; ?>')" <?php echo $stats['total'] === 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-shield-alt me-2"></i>Verify Chain
                            </button>
                            
                            <div class="verification-result" id="result-<?php echo $type; ?>"></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-info-circle me-2" style="color: var(--accent)"></i>
                            About Blockchain Verification
                        </h5>
                        <p class="text-muted mb-2">
                            The blockchain verification process checks the integrity of hash-chained records by:
                        </p>
                        <ul class="text-muted small">
                            <li>Recomputing SHA-256 hashes for all records of each type</li>
                            <li>Verifying that each record's hash matches the stored hash</li>
                            <li>Checking that the <code>previous_hash</code> field correctly chains to the previous record</li>
                            <li>Identifying any tampered or broken links in the chain</li>
                        </ul>
                        <p class="text-muted small mb-0">
                            If tampering is detected, the affected records will be listed with their expected vs stored values.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        async function verifyChain(recordType) {
            const btn = document.querySelector(`#card-${recordType} .verify-btn`);
            const resultDiv = document.getElementById(`result-${recordType}`);
            const badge = document.getElementById(`badge-${recordType}`);
            const card = document.getElementById(`card-${recordType}`);
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner"></span> Verifying...';
            resultDiv.style.display = 'none';
            
            try {
                const response = await fetch('/api/blockchain/verify-chain.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        entity_type: recordType
                    })
                });
                
                const data = await response.json();
                
                // Update UI based on result
                if (data.valid) {
                    badge.className = 'stat-badge valid';
                    badge.textContent = 'Valid';
                    card.className = 'health-card valid';
                    
                    resultDiv.className = 'verification-result success';
                    resultDiv.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Chain Integrity Verified</strong>
                        <p class="mb-0 mt-1">${data.total_records} records verified successfully.</p>
                    `;
                } else {
                    badge.className = 'stat-badge invalid';
                    badge.textContent = 'Tampered';
                    card.className = 'health-card invalid';
                    
                    let tamperedHtml = '<div class="tampered-list"><strong>Tampered Records:</strong>';
                    data.tampered.forEach(record => {
                        tamperedHtml += `
                            <div class="tampered-item">
                                <strong>ID:</strong> ${record.id}<br>
                                <strong>Reference:</strong> ${record.reference_id}<br>
                                <strong>Expected Hash:</strong> <code>${record.expected_hash.substring(0, 16)}...</code><br>
                                <strong>Stored Hash:</strong> <code>${record.stored_hash.substring(0, 16)}...</code>
                            </div>
                        `;
                    });
                    tamperedHtml += '</div>';
                    
                    resultDiv.className = 'verification-result error';
                    resultDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Chain Integrity Compromised</strong>
                        <p class="mb-1 mt-1">${data.tampered.length} tampered record(s) detected.</p>
                        ${tamperedHtml}
                    `;
                }
                
                resultDiv.style.display = 'block';
                
            } catch (error) {
                console.error('Verification error:', error);
                resultDiv.className = 'verification-result error';
                resultDiv.innerHTML = `
                    <i class="fas fa-times-circle me-2"></i>
                    <strong>Verification Failed</strong>
                    <p class="mb-0 mt-1">${error.message}</p>
                `;
                resultDiv.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shield-alt me-2"></i>Verify Chain';
            }
        }
    </script>
</body>
</html>
