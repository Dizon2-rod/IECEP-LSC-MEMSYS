<?php
require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/src/lib/BlockchainService.php';

use App\Lib\Supabase;
use App\Lib\BlockchainService;

$sb = new Supabase();
$blockchain = new BlockchainService($sb->getClient());

$searchResult = null;
$searchError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hash = trim($_POST['hash'] ?? '');
    
    if (empty($hash)) {
        $searchError = 'Please enter a hash to verify';
    } else {
        try {
            // Use BlockchainService to check if hash exists
            $hashCheck = $blockchain->hashExists($hash);
            
            if ($hashCheck['exists']) {
                // Get additional details about the record
                $record = $hashCheck['record'];
                $searchResult = [
                    'found' => true,
                    'count' => 1,
                    'records' => [$record],
                ];
            } else {
                $searchResult = [
                    'found' => false,
                    'count' => 0,
                ];
            }
        } catch (Exception $e) {
            $searchError = 'Error searching blockchain: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Hash - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --navy: #0B1D4A;
            --gold: #D4AF37;
            --neutral-100: #f8fafc;
            --neutral-200: #e2e8f0;
            --neutral-500: #64748b;
            --neutral-700: #334155;
        }
        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            width: 100%;
        }
        .card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--navy) 0%, #1E3A6E 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .logo i {
            color: var(--gold);
            font-size: 32px;
        }
        .header h1 {
            color: var(--navy);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .header p {
            color: var(--neutral-500);
            font-size: 16px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            color: var(--neutral-700);
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--neutral-200);
            border-radius: 12px;
            font-size: 16px;
            font-family: "Inter", sans-serif;
            transition: all 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.1);
        }
        .btn {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, var(--navy) 0%, #1E3A6E 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            font-family: "Inter", sans-serif;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(11, 29, 74, 0.3);
        }
        .result {
            margin-top: 24px;
            padding: 20px;
            border-radius: 12px;
            display: none;
        }
        .result.success {
            background: #dcfce7;
            border: 1px solid #86efac;
        }
        .result.error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
        }
        .result.info {
            background: #e0f2fe;
            border: 1px solid #7dd3fc;
        }
        .result-icon {
            font-size: 24px;
            margin-bottom: 12px;
        }
        .result.success .result-icon { color: #166534; }
        .result.error .result-icon { color: #991b1b; }
        .result.info .result-icon { color: #0369a1; }
        .result-title {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .result.success .result-title { color: #166534; }
        .result.error .result-title { color: #991b1b; }
        .result.info .result-title { color: #0369a1; }
        .result-message {
            color: var(--neutral-700);
            margin-bottom: 16px;
        }
        .record-item {
            background: white;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid var(--neutral-200);
        }
        .record-item:last-child {
            margin-bottom: 0;
        }
        .record-type {
            display: inline-block;
            padding: 4px 12px;
            background: var(--navy);
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .record-details {
            color: var(--neutral-500);
            font-size: 14px;
        }
        .record-details div {
            margin-bottom: 4px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--navy);
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>Blockchain Hash Verification</h1>
                <p>Verify the integrity of any hash stored in the IECEP-LSC blockchain</p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="hash">Enter Hash to Verify</label>
                    <input 
                        type="text" 
                        id="hash" 
                        name="hash" 
                        placeholder="Paste SHA-256 hash here..."
                        value="<?php echo htmlspecialchars($_POST['hash'] ?? ''); ?>"
                        required
                    >
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-search me-2"></i>Verify Hash
                </button>
            </form>

            <?php if ($searchError): ?>
                <div class="result error" style="display: block;">
                    <div class="result-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="result-title">Verification Error</div>
                    <div class="result-message"><?php echo htmlspecialchars($searchError); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($searchResult): ?>
                <?php if ($searchResult['found']): ?>
                    <div class="result success" style="display: block;">
                        <div class="result-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="result-title">Hash Found in Blockchain</div>
                        <div class="result-message">
                            Found <?php echo $searchResult['count']; ?> record(s) matching this hash.
                        </div>
                        
                        <?php foreach ($searchResult['records'] as $record): ?>
                            <div class="record-item">
                                <span class="record-type">
                                    <?php echo ucfirst(str_replace('_', ' ', $record['entity_type'] ?? $record['record_type'] ?? 'Unknown')); ?>
                                </span>
                                <div class="record-details">
                                    <div><strong>Entity ID:</strong> <?php echo htmlspecialchars($record['entity_id'] ?? $record['reference_id'] ?? 'N/A'); ?></div>
                                    <div><strong>Recorded:</strong> <?php echo $record['created_at'] ? date('F d, Y g:i A', strtotime($record['created_at'])) : 'N/A'; ?></div>
                                    <div><strong>Hash:</strong> <code><?php echo htmlspecialchars(substr($record['data_hash'] ?? $record['record_hash'] ?? $record['transaction_hash'] ?? '', 0, 32)); ?>...</code></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="result info" style="display: block;">
                        <div class="result-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="result-title">Hash Not Found</div>
                        <div class="result-message">
                            This hash was not found in any blockchain records. Please verify that you entered the correct hash.
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <a href="<?php echo BASE_URL; ?>/index.php" class="back-link">
                <i class="fas fa-arrow-left me-2"></i>Return to Home
            </a>
        </div>
    </div>
</body>
</html>
