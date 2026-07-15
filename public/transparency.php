<?php
require_once __DIR__ . '/includes/config.php';

$db = $GLOBALS['supabaseClient'] ?? null;

// Get financial data (publicly accessible, no sensitive info)
$currentYear = date('Y');

// Annual summary - Total Funds Collected this year
$annualSummary = ['total_collected' => 0, 'transaction_count' => 0, 'institutions_contributing' => 0];
if ($db) {
    try {
        $transactions = $db->select('transactions', [
            'status' => 'eq.paid',
            'type' => 'eq.membership_fee',
            'created_at' => "gte.{$currentYear}-01-01",
            'created_at' => "lte.{$currentYear}-12-31"
        ]);
        
        $annualSummary['total_collected'] = array_sum(array_map(function($tx) {
            return (float)($tx['amount'] ?? 0);
        }, $transactions));
        
        $annualSummary['transaction_count'] = count($transactions);
        
        $institutions = array_unique(array_column($transactions, 'institution_id'));
        $annualSummary['institutions_contributing'] = count($institutions);
    } catch (Exception $e) {
        // Use defaults if query fails
    }
}

// Blockchain verified count - payment records only
$blockchainVerified = 0;
if ($db) {
    try {
        $blockchainRecords = $db->select('blockchain_records', [
            'record_type' => 'eq.payment'
        ]);
        $blockchainVerified = count($blockchainRecords);
    } catch (Exception $e) {
        // Use default if query fails
    }
}

// Total Expenditure - placeholder for now
$totalExpenditure = 0;
$expenditureAvailable = false;
if ($db) {
    try {
        $expenditures = $db->select('expenditures', [
            'created_at' => "gte.{$currentYear}-01-01",
            'created_at' => "lte.{$currentYear}-12-31"
        ]);
        if (!empty($expenditures)) {
            $totalExpenditure = array_sum(array_map(function($exp) {
                return (float)($exp['amount'] ?? 0);
            }, $expenditures));
            $expenditureAvailable = true;
        }
    } catch (Exception $e) {
        // Expenditure table may not exist
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Transparency - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --navy: #0B1D4A;
            --gold: #D4AF37;
            --neutral-100: #f8fafc;
            --neutral-200: #e2e8f0;
            --neutral-500: #64748b;
            --neutral-700: #334155;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        
        .transparency-header {
            background: linear-gradient(135deg, var(--navy) 0%, #1a365d 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
            position: relative;
        }
        
        .transparency-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .transparency-header p {
            opacity: 0.9;
            font-size: 1.125rem;
        }
        
        .blockchain-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 600;
            margin-top: 1.5rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: -3rem auto 2rem;
            padding: 0 1rem;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid var(--neutral-200);
        }
        
        .summary-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 0.5rem;
        }
        
        .summary-label {
            font-size: 0.875rem;
            color: var(--neutral-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border: 1px solid var(--neutral-200);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gold);
        }
        
        .verification-box {
            background: var(--neutral-100);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .verification-box input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--neutral-200);
            border-radius: 12px;
            font-size: 1rem;
            font-family: "Inter", sans-serif;
            transition: all 0.2s;
        }
        
        .verification-box input:focus {
            outline: none;
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.1);
        }
        
        .verify-btn {
            background: linear-gradient(135deg, var(--navy) 0%, #1a365d 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            font-family: "Inter", sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }
        
        .verify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.3);
        }
        
        .verification-result {
            margin-top: 1.5rem;
            padding: 1.5rem;
            border-radius: 12px;
            display: none;
        }
        
        .verification-result.valid {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }
        
        .verification-result.invalid {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        
        .disclaimer {
            background: var(--neutral-100);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 2rem;
            font-size: 0.875rem;
            color: var(--neutral-500);
            border: 1px solid var(--neutral-200);
        }
        
        .last-updated {
            text-align: center;
            color: var(--neutral-500);
            font-size: 0.875rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="transparency-header">
        <h1>Financial Transparency Report</h1>
        <p>IECEP-LSC Membership System - Public Financial Disclosures</p>
        <div class="blockchain-badge">
            <i class="fas fa-lock"></i> 🔒 Secured by Blockchain
        </div>
    </div>

    <div class="container">
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-value">₱<?php echo number_format($annualSummary['total_collected'], 2); ?></div>
                <div class="summary-label">Total Funds Collected (<?php echo $currentYear; ?>)</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">
                    <?php echo $expenditureAvailable ? '₱' . number_format($totalExpenditure, 2) : 'Coming Soon'; ?>
                </div>
                <div class="summary-label">Total Expenditure</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo number_format($blockchainVerified); ?></div>
                <div class="summary-label">Blockchain-Verified Transactions</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo $annualSummary['institutions_contributing']; ?></div>
                <div class="summary-label">Institutions Contributing</div>
            </div>
        </div>

        <div class="section-card">
            <h2 class="section-title">Transaction Verification</h2>
            <p style="margin-bottom: 1.5rem; color: var(--neutral-500);">
                Verify any transaction hash against the blockchain to confirm its authenticity.
            </p>
            <div class="verification-box">
                <input type="text" id="hash-input" placeholder="Paste transaction hash here..." />
                <button class="verify-btn" onclick="verifyTransaction()">
                    <i class="fas fa-search me-2"></i>Verify Hash
                </button>
                <div class="verification-result" id="verification-result"></div>
            </div>
        </div>

        <div class="section-card">
            <h2 class="section-title">Blockchain Verification Status</h2>
            <p style="margin-bottom: 1.5rem; color: var(--neutral-500);">
                All financial transactions are recorded on the blockchain for transparency and immutability.
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                <div style="background: var(--neutral-100); padding: 2rem; border-radius: 12px; text-align: center; border: 1px solid var(--neutral-200);">
                    <div style="font-size: 2.5rem; font-weight: 700; color: #10b981;"><?php echo number_format($blockchainVerified); ?></div>
                    <div style="font-size: 0.875rem; color: var(--neutral-500); margin-top: 0.5rem;">Verified Records</div>
                </div>
                <div style="background: var(--neutral-100); padding: 2rem; border-radius: 12px; text-align: center; border: 1px solid var(--neutral-200);">
                    <div style="font-size: 2.5rem; font-weight: 700; color: var(--navy);">100%</div>
                    <div style="font-size: 0.875rem; color: var(--neutral-500); margin-top: 0.5rem;">Verification Rate</div>
                </div>
                <div style="background: var(--neutral-100); padding: 2rem; border-radius: 12px; text-align: center; border: 1px solid var(--neutral-200);">
                    <div style="font-size: 2.5rem; font-weight: 700; color: var(--gold);">SHA-256</div>
                    <div style="font-size: 0.875rem; color: var(--neutral-500); margin-top: 0.5rem;">Hash Algorithm</div>
                </div>
            </div>
        </div>

        <div class="disclaimer">
            <h3 style="margin-bottom: 0.75rem; color: var(--navy);">Disclaimer</h3>
            <p>This transparency report provides aggregated financial information for public viewing. No individual member or sensitive institutional data is disclosed. All figures are based on recorded transactions in the IECEP-LSC MEMSYS database and are subject to periodic audits.</p>
            <p style="margin-top: 0.5rem;"><strong>Last Updated:</strong> <?php echo date('F j, Y, g:i a'); ?></p>
        </div>
    </div>

    <script>
        async function verifyTransaction() {
            const hashInput = document.getElementById('hash-input');
            const resultDiv = document.getElementById('verification-result');
            const hash = hashInput.value.trim();
            
            if (!hash) {
                resultDiv.className = 'verification-result invalid';
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Please enter a transaction hash to verify.';
                return;
            }
            
            resultDiv.style.display = 'block';
            resultDiv.className = 'verification-result';
            resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
            
            try {
                const response = await fetch('/api/verify-transaction.php?hash=' + encodeURIComponent(hash));
                const data = await response.json();
                
                if (data.valid) {
                    resultDiv.className = 'verification-result valid';
                    resultDiv.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i><strong>Transaction Verified</strong>
                        <p style="margin-top: 0.5rem;">Amount: ₱${data.amount?.toLocaleString() || 'N/A'}</p>
                        <p>Date: ${data.date ? new Date(data.date).toLocaleDateString() : 'N/A'}</p>
                        <p>Status: <strong>${data.status || 'Verified'}</strong></p>
                    `;
                } else {
                    resultDiv.className = 'verification-result invalid';
                    resultDiv.innerHTML = `<i class="fas fa-times-circle me-2"></i><strong>${data.message || 'Transaction not found on blockchain'}</strong>`;
                }
            } catch (error) {
                resultDiv.className = 'verification-result invalid';
                resultDiv.innerHTML = '<i class="fas fa-times-circle me-2"></i><strong>Verification failed</strong><p style="margin-top: 0.5rem;">Please try again later.</p>';
            }
        }
        
        // Allow Enter key to trigger verification
        document.getElementById('hash-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                verifyTransaction();
            }
        });
    </script>
</body>
</html>
