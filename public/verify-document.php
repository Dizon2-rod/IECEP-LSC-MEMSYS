<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/paths.php';
use App\Lib\SupabaseClient;

$verificationResult = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documentId = $_POST['document_id'] ?? '';
    $blockchainHash = $_POST['blockchain_hash'] ?? '';
    
    try {
        $supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
        if (!empty($documentId)) {
            $docs = $supabase->select('documents', ['id' => 'eq.' . $documentId]);
            if (is_array($docs) && !empty($docs)) {
                $document = $docs[0];
                $bcs = $supabase->select('blockchain_records', ['entity_id' => 'eq.' . $documentId]);
                $blockchainRecord = (is_array($bcs) && !empty($bcs)) ? $bcs[0] : null;
                
                $verificationResult = [
                    'document' => $document,
                    'blockchain_verified' => !empty($blockchainRecord['confirmed']),
                    'blockchain_hash' => $blockchainRecord['transaction_hash'] ?? null,
                    'verified' => true
                ];
            } else {
                $error = 'Document not found';
            }
        } elseif (!empty($blockchainHash)) {
            $bcs = $supabase->select('blockchain_records', ['transaction_hash' => 'eq.' . $blockchainHash]);
            if (is_array($bcs) && !empty($bcs)) {
                $blockchainRecord = $bcs[0];
                $document = null;
                if (!empty($blockchainRecord['entity_id'])) {
                    $docs = $supabase->select('documents', ['id' => 'eq.' . $blockchainRecord['entity_id']]);
                    if (is_array($docs) && !empty($docs)) {
                        $document = $docs[0];
                    }
                }
                $verificationResult = [
                    'document' => $document,
                    'blockchain_verified' => !empty($blockchainRecord['confirmed']),
                    'blockchain_hash' => $blockchainRecord['transaction_hash'] ?? null,
                    'verified' => true
                ];
            } else {
                $error = 'Blockchain record not found';
            }
        }
    } catch (Exception $e) {
        $error = 'Verification failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Verification - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        .verification-header {
            background: linear-gradient(135deg, #0B1D4A 0%, #1a365d 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
        }
        .verification-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .verification-header p {
            opacity: 0.9;
            font-size: 1.125rem;
        }
        .container {
            max-width: 800px;
            margin: -3rem auto 2rem;
            padding: 0 1rem;
        }
        .verification-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: var(--font-weight-semibold);
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 1rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-navy);
        }
        .btn {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
        }
        .result-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-top: 2rem;
        }
        .result-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-200);
        }
        .result-icon {
            font-size: 2rem;
        }
        .result-icon.success { color: var(--success); }
        .result-icon.error { color: var(--error); }
        .result-title {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .result-title.success { color: var(--success); }
        .result-title.error { color: var(--error); }
        .result-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .detail-item {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--radius-md);
        }
        .detail-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-bottom: 0.25rem;
        }
        .detail-value {
            font-weight: var(--font-weight-semibold);
            color: var(--gray-900);
        }
        .blockchain-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            font-size: var(--font-size-sm);
        }
        .error-message {
            background: var(--error-light);
            color: var(--error-dark);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="verification-header">
        <h1>Document Verification</h1>
        <p>Verify the authenticity of IECEP-LSC documents using blockchain technology</p>
    </div>

    <div class="container">
        <div class="verification-card">
            <form method="POST">
                <div class="form-group">
                    <label>Document ID</label>
                    <input type="text" name="document_id" placeholder="Enter document ID (e.g., UUID)">
                </div>
                <div class="form-group">
                    <label>OR Blockchain Hash</label>
                    <input type="text" name="blockchain_hash" placeholder="Enter blockchain hash (SHA-256)">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-shield-alt"></i> Verify Document
                </button>
            </form>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($verificationResult): ?>
            <div class="result-card">
                <div class="result-header">
                    <div class="result-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="result-title success">
                        Document Verified
                    </div>
                </div>

                <?php if ($verificationResult['blockchain_verified']): ?>
                    <div style="margin-bottom: 1.5rem;">
                        <span class="blockchain-badge">
                            <i class="fas fa-link"></i> Blockchain Verified
                        </span>
                    </div>
                <?php endif; ?>

                <div class="result-details">
                    <div class="detail-item">
                        <div class="detail-label">Document Title</div>
                        <div class="detail-value"><?php echo htmlspecialchars($verificationResult['document']['title']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Category</div>
                        <div class="detail-value"><?php echo htmlspecialchars($verificationResult['document']['category'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Institution</div>
                        <div class="detail-value"><?php echo htmlspecialchars($verificationResult['document']['institution_name'] ?? 'General'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Uploaded By</div>
                        <div class="detail-value"><?php echo htmlspecialchars($verificationResult['document']['uploaded_by_name'] ?? 'System'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Version</div>
                        <div class="detail-value">v<?php echo $verificationResult['document']['version']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Upload Date</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($verificationResult['document']['created_at'])); ?></div>
                    </div>
                    <?php if ($verificationResult['blockchain_hash']): ?>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <div class="detail-label">Blockchain Hash</div>
                            <div class="detail-value" style="font-family: monospace; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($verificationResult['blockchain_hash']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
