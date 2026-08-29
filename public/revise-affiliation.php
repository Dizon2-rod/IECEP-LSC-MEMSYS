<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../src/lib/Supabase.php';

$token = filter_var($_GET['token'] ?? '', FILTER_SANITIZE_STRING);
$error = null;
$revision = null;
$affiliation = null;

if ($token) {
    $supabase = new Supabase();
    $revision = $supabase->select('revision_requests', '*', ['token' => $token, 'status' => 'pending'])[0] ?? null;
    
    if ($revision) {
        if (strtotime($revision['deadline']) < time()) {
            $supabase->update('revision_requests', ['status' => 'expired'], ['id' => $revision['id']]);
            $error = 'This revision request has expired.';
        } else {
            $affiliation = $supabase->select('pending_affiliations', '*', ['id' => $revision['affiliation_id']])[0] ?? null;
        }
    } else {
        $error = 'Invalid or expired revision request.';
    }
} else {
    $error = 'No token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revise Affiliation Application - IECEP-LSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/styles.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/professional.css">
    <style>
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #0B1D4A;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0B1D4A;
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.1);
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .alert-danger {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        
        .alert-info {
            background: #dbeafe;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 32px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #0B1D4A;
            color: white;
        }
        
        .btn-primary:hover {
            background: #091a3a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.3);
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Revise Affiliation Application</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($affiliation): ?>
            <div class="alert alert-info">
                <strong>Revision Required:</strong><br>
                <?= htmlspecialchars($revision['explanation']) ?><br>
                <strong>Deadline:</strong> <?= date('F j, Y g:i A', strtotime($revision['deadline'])) ?>
            </div>
            
            <form id="revisionForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="action" value="submit_revision">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="form-group">
                    <label>Institution Name</label>
                    <input type="text" name="institution_name" class="form-control" value="<?= htmlspecialchars($affiliation['institution_name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($affiliation['contact_person']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($affiliation['contact_email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($affiliation['contact_phone']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Memorandum of Agreement (MOA)</label>
                    <input type="file" name="moa_file" class="form-control" accept=".pdf,.doc,.docx">
                    <?php if (!empty($affiliation['moa_file'])): ?>
                        <small>Current: <?= htmlspecialchars($affiliation['moa_file']) ?></small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Accreditation Document</label>
                    <input type="file" name="accreditation_file" class="form-control" accept=".pdf,.doc,.docx">
                    <?php if (!empty($affiliation['accreditation_file'])): ?>
                        <small>Current: <?= htmlspecialchars($affiliation['accreditation_file']) ?></small>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-primary">Submit Revision</button>
            </form>
            
            <script>
            document.getElementById('revisionForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                
                try {
                    const response = await fetch('<?= BASE_URL ?>/public/api/affiliation-revision.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Revision submitted successfully!');
                        window.location.href = '<?= BASE_URL ?>/public/index.php';
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (error) {
                    alert('An error occurred. Please try again.');
                }
            });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
