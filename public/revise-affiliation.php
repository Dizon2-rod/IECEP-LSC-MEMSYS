<?php
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
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
