<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_role(['admin']);

$current_page = 'featured-cards';
$user = $_SESSION['user'] ?? [];
$displayName = $user['user_metadata']['full_name'] ?? $user['email'] ?? 'Administrator';
$successMessage = '';
$errorMessage = '';

if (!empty($_SESSION['featured_cards_flash'])) {
    $flash = $_SESSION['featured_cards_flash'];
    $successMessage = $flash['success'] ?? '';
    $errorMessage = $flash['error'] ?? '';
    unset($_SESSION['featured_cards_flash']);
}

function storeFeaturedCardImage(array $uploadedFile): ?string {
    if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
        return null;
    }

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($uploadedFile['type'] ?? '', $allowedMime, true)) {
        return null;
    }

    $uploadsDir = dirname(__DIR__, 2) . '/assets/uploads/featured-cards';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

    $extension = strtolower(pathinfo($uploadedFile['name'] ?? 'image.jpg', PATHINFO_EXTENSION));
    $filename = uniqid('featured-card-', true) . '.' . $extension;
    $targetPath = $uploadsDir . '/' . $filename;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
        return null;
    }

    return BASE_URL . '/public/assets/uploads/featured-cards/' . $filename;
}

function uploadToSupabaseStorage(array $uploadedFile, array $supabaseConfig): ?string {
    // Requires service_role_key to be present in $supabaseConfig
    if (empty($supabaseConfig['service_role_key']) || empty($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
        return null;
    }

    $bucket = 'public';
    $pathDir = 'featured-cards';
    $extension = strtolower(pathinfo($uploadedFile['name'] ?? 'image.jpg', PATHINFO_EXTENSION));
    $filename = uniqid('featured-card-', true) . '.' . $extension;
    $objectPath = $pathDir . '/' . $filename;

    $uploadUrl = rtrim($supabaseConfig['url'], '/') . '/storage/v1/object/' . $bucket . '/' . $objectPath;

    $fileContents = file_get_contents($uploadedFile['tmp_name']);
    if ($fileContents === false) {
        return null;
    }

    $headers = [
        'Authorization: Bearer ' . $supabaseConfig['service_role_key'],
        'apikey: ' . $supabaseConfig['service_role_key'],
        'Content-Type: application/octet-stream',
        'x-upsert: false'
    ];

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContents);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode >= 400) {
        error_log('Supabase storage upload failed: ' . $curlErr . ' HTTP: ' . $httpCode . ' Resp: ' . $response);
        return null;
    }

    // Public URL for objects in the public bucket
    return rtrim($supabaseConfig['url'], '/') . '/storage/v1/object/public/' . $bucket . '/' . $objectPath;
}

try {
    $supabaseConfig = require dirname(__DIR__, 3) . '/includes/supabase.php';
    $supabaseClient = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
    if (!empty($supabaseConfig['service_role_key'])) {
        $supabaseClient->setServiceRoleKey($supabaseConfig['service_role_key']);
    }
} catch (Exception $e) {
    $supabaseClient = null;
    $errorMessage = 'Supabase is not available right now. Please verify the configuration before managing cards.';
}

$cards = [];
if ($supabaseClient) {
    try {
        $rawCards = $supabaseClient->select('featured_cards');
        if (is_array($rawCards)) {
            $cards = $rawCards;
            usort($cards, function ($left, $right) {
                $leftOrder = (int)($left['sort_order'] ?? 0);
                $rightOrder = (int)($right['sort_order'] ?? 0);
                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }
                return strcmp(($right['created_at'] ?? ''), ($left['created_at'] ?? ''));
            });
        }
    } catch (Exception $e) {
        $errorMessage = 'Unable to load featured cards from Supabase.';
    }
}

$editingCard = null;
$editingId = trim((string)($_GET['edit'] ?? ''));
if ($editingId !== '') {
    foreach ($cards as $card) {
        if ((string)($card['id'] ?? '') === $editingId) {
            $editingCard = $card;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    $id = trim((string)($_POST['id'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $imageUrl = trim((string)($_POST['image_url'] ?? ''));
    $gradientFrom = trim((string)($_POST['gradient_from'] ?? '#0B1D4A'));
    $gradientTo = trim((string)($_POST['gradient_to'] ?? '#132a5e'));
    $buttonColor = trim((string)($_POST['button_color'] ?? '#0B1D4A'));
    $buttonText = trim((string)($_POST['button_text'] ?? ''));
    $buttonUrl = trim((string)($_POST['button_url'] ?? ''));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = !empty($_POST['is_active']);

    if ($title === '') {
        $errorMessage = 'A title is required.';
    } elseif (!$supabaseClient) {
        $errorMessage = 'Supabase is not available right now.';
    } else {
        // Handle image upload: validate size/type, then try Supabase storage (service role), fall back to local storage
        $uploadedImageUrl = null;
        $file = $_FILES['image_file'] ?? null;
        if ($file && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
            $maxSize = 5 * 1024 * 1024;
            $fileType = $file['type'] ?? '';
            if ($fileType === '' && function_exists('mime_content_type')) {
                $fileType = mime_content_type($file['tmp_name']) ?: '';
            }
            $fileSize = $file['size'] ?? 0;
            if (!in_array($fileType, $allowedMime, true)) {
                $errorMessage = 'Uploaded image must be jpg, png, or webp.';
            } elseif ($fileSize > $maxSize) {
                $errorMessage = 'Uploaded image must be 5MB or smaller.';
            } else {
    $supabaseConfig = require dirname(__DIR__, 3) . '/includes/supabase.php';
                if (!empty($supabaseConfig['service_role_key'])) {
                    $uploadedImageUrl = uploadToSupabaseStorage($file, $supabaseConfig);
                }
                if ($uploadedImageUrl === null) {
                    $uploadedImageUrl = storeFeaturedCardImage($file);
                }
            }
        }
        if (!empty($uploadedImageUrl)) {
            $imageUrl = $uploadedImageUrl;
        } elseif ($id !== '' && $imageUrl === '' && !empty($editingCard['image_url'])) {
            $imageUrl = $editingCard['image_url'];
        }

        $payload = [
            'title' => $title,
            'description' => $description,
            'image_url' => $imageUrl,
            'gradient_from' => $gradientFrom ?: '#0B1D4A',
            'gradient_to'   => $gradientTo ?: '#132a5e',
            'button_color'  => $buttonColor ?: '#0B1D4A',
            'button_text' => $buttonText !== '' ? $buttonText : 'Learn More',
            'button_url' => $buttonUrl !== '' ? $buttonUrl : '#',
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z')
        ];

        if ($action === 'delete' && $id !== '') {
            $supabaseClient->delete('featured_cards', $id);
            $successMessage = 'The featured card was removed.';
        } else {
            if ($id !== '') {
                if (!empty($uploadedImageUrl)) {
                    $payload['image_url'] = $uploadedImageUrl;
                } elseif ($imageUrl === '' && !empty($editingCard['image_url'])) {
                    $payload['image_url'] = $editingCard['image_url'];
                }
                $supabaseClient->update('featured_cards', $payload, $id);
                $successMessage = 'The featured card was updated.';
            } else {
                if (!empty($uploadedImageUrl)) {
                    $payload['image_url'] = $uploadedImageUrl;
                }
                $payload['created_at'] = gmdate('Y-m-d\TH:i:s\Z');
                $supabaseClient->insert('featured_cards', $payload);
                $successMessage = 'A new featured card was added.';
            }
        }
    }

    $_SESSION['featured_cards_flash'] = [
        'success' => $successMessage,
        'error' => $errorMessage,
    ];

    header('Location: ' . BASE_URL . '/public/portal/admin/featured-cards.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Featured Cards | IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/styles.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .portal-shell { display: flex; min-height: 100vh; }
        .portal-main { flex: 1; padding: 2rem; margin-left: 260px; }
        .portal-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(11, 29, 74, 0.08); border: 1px solid #eef2f7; padding: 1.5rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.25rem; }
        .page-title { margin: 0; color: #0B1D4A; font-size: 1.55rem; font-weight: 700; }
        .page-subtitle { margin: 0.25rem 0 0; color: #6b7280; }
        .badge-pill { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.7rem; border-radius: 999px; background: rgba(212, 175, 55, 0.15); color: #0B1D4A; font-size: 0.8rem; font-weight: 700; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { font-size: 0.9rem; font-weight: 600; color: #0B1D4A; }
        .form-control { border-radius: 10px; border: 1px solid #dbe3ef; padding: 0.7rem 0.8rem; }
        .table-responsive { overflow-x: auto; }
        .featured-thumb { width: 72px; height: 48px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; }
        .thumb-placeholder { width: 72px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(11, 29, 74, 0.08), rgba(212, 175, 55, 0.16)); color: #0B1D4A; font-size: 1rem; }
        .actions { display: flex; gap: 0.5rem; }
        .btn-gold-outline { border: 1px solid #D4AF37; color: #0B1D4A; background: transparent; border-radius: 999px; padding: 0.45rem 0.8rem; font-weight: 600; text-decoration: none; }
        .btn-gold-outline:hover { background: #D4AF37; color: #fff; }
        .btn-danger-outline { border: 1px solid #dc3545; color: #dc3545; background: transparent; border-radius: 999px; padding: 0.45rem 0.8rem; font-weight: 600; }
        .btn-danger-outline:hover { background: #dc3545; color: #fff; }
        .ql-container { border-radius: 8px; border: 1px solid #e6eef8; }
        .ql-editor { min-height: 140px; }
        @media (max-width: 992px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="portal-shell">
    <?php require_once dirname(__DIR__, 3) . '/includes/sidebar.php'; ?>
    <main class="portal-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Featured Cards</h1>
                <p class="page-subtitle">Manage the landing page cards that highlight chapter news and opportunities.</p>
            </div>
            <span class="badge-pill"><i class="fas fa-star"></i> Admin-managed</span>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success mb-3"><i class="fas fa-circle-check me-2"></i><?= h($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger mb-3"><i class="fas fa-exclamation-circle me-2"></i><?= h($errorMessage) ?></div>
        <?php endif; ?>

        <div class="portal-card mb-4">
            <div class="page-header" style="margin-bottom: 1rem;">
                <div>
                    <h2 class="page-title" style="font-size: 1.2rem;"><?= $editingCard ? 'Edit card' : 'Add a new card' ?></h2>
                    <p class="page-subtitle">Upload an image or provide an image URL, then publish the card on the landing page.</p>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= h($editingCard['id'] ?? '') ?>">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label" for="title">Title</label>
                        <input class="form-control" id="title" name="title" value="<?= h($editingCard['title'] ?? '') ?>" required>
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="description">Description</label>
                        <div id="editor-container" style="min-height:140px; background:#fff; border:1px solid #e6eef8; border-radius:8px;"></div>
                        <textarea class="form-control mt-2" id="description" name="description" rows="6" style="min-height:140px;"><?php echo htmlspecialchars($editingCard['description'] ?? '', ENT_QUOTES); ?></textarea>
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="image_file">Upload image</label>
                        <input class="form-control" type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted">Max size: 5MB. Allowed types: jpg, png, webp.</small>
                        <?php if (!empty($editingCard['image_url'])): ?>
                            <div class="mt-2">
                                <label class="form-label">Current image preview</label>
                                <div><img src="<?= h($editingCard['image_url']) ?>" alt="Current image" class="featured-thumb"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="image_url">Or use an image URL</label>
                        <input class="form-control" id="image_url" name="image_url" value="<?= h($editingCard['image_url'] ?? '') ?>" placeholder="https://example.com/image.jpg">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="button_text">Button text</label>
                        <input class="form-control" id="button_text" name="button_text" value="<?= h($editingCard['button_text'] ?? 'Learn More') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="button_url">Button URL</label>
                        <input class="form-control" id="button_url" name="button_url" value="<?= h($editingCard['button_url'] ?? '#') ?>">
                    </div>
                        <div class="form-group">
                            <label class="form-label" for="gradient_from">Header Gradient Start</label>
                            <input class="form-control" type="text" id="gradient_from" name="gradient_from" value="<?= h($editingCard['gradient_from'] ?? '#0B1D4A') ?>" placeholder="#0B1D4A">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="gradient_to">Header Gradient End</label>
                            <input class="form-control" type="text" id="gradient_to" name="gradient_to" value="<?= h($editingCard['gradient_to'] ?? '#132a5e') ?>" placeholder="#132a5e">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="button_color">Button Color</label>
                            <input class="form-control" type="text" id="button_color" name="button_color" value="<?= h($editingCard['button_color'] ?? '#0B1D4A') ?>" placeholder="#0B1D4A">
                        </div>
                    <div class="form-group">
                        <label class="form-label" for="sort_order">Sort order</label>
                        <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?= h((string)($editingCard['sort_order'] ?? 0)) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="is_active">Status</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($editingCard['is_active']) || !$editingCard ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Active on landing page</label>
                        </div>
                    </div>
                    <div class="form-group full">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-save me-2"></i><?= $editingCard ? 'Update card' : 'Create card' ?></button>
                    </div>
                </div>
            </form>
        </div>

        <div class="portal-card">
            <div class="page-header" style="margin-bottom: 1rem;">
                <div>
                    <h2 class="page-title" style="font-size: 1.2rem;">Existing cards</h2>
                    <p class="page-subtitle">Adjust the order or disable cards without affecting the rest of the landing page.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cards)): ?>
                            <tr><td colspan="5" class="text-muted py-4">No featured cards have been created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cards as $card): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($card['image_url'])): ?>
                                            <img class="featured-thumb" src="<?= h($card['image_url']) ?>" alt="<?= h($card['title'] ?? '') ?>">
                                        <?php else: ?>
                                            <div class="thumb-placeholder"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= h($card['title'] ?? '') ?></strong>
                                        <div class="text-muted small">Sort <?= (int)($card['sort_order'] ?? 0) ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($card['is_active'])): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <div class="actions">
                                            <a class="btn-gold-outline" href="<?= BASE_URL ?>/public/portal/admin/featured-cards.php?edit=<?= h((string)($card['id'] ?? '')) ?>"><i class="fas fa-edit me-1"></i>Edit</a>
                                            <form method="POST" onsubmit="return confirm('Delete this featured card?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= h((string)($card['id'] ?? '')) ?>">
                                                <button class="btn-danger-outline" type="submit"><i class="fas fa-trash me-1"></i>Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var quill = new Quill('#editor-container', {
        theme: 'snow',
        placeholder: 'Enter a rich description...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ header: [1, 2, 3, false] }],
                ['link'],
                [{ list: 'ordered' }, { list: 'bullet' }]
            ]
        }
    });

    var textarea = document.getElementById('description');
    if (textarea && textarea.value.trim().length) {
        quill.root.innerHTML = textarea.value;
    }

    var form = document.querySelector('form[method="POST"][enctype="multipart/form-data"]');
    if (form) {
        form.addEventListener('submit', function () {
            if (textarea) {
                textarea.value = quill.root.innerHTML;
            }
        });
    }

    var fileInput = document.getElementById('image_file');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var selectedFile = fileInput.files[0];
            if (!selectedFile) {
                return;
            }
            var allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(selectedFile.type)) {
                alert('Allowed image types: jpg, png, webp');
                fileInput.value = '';
                return;
            }
            if (selectedFile.size > 5 * 1024 * 1024) {
                alert('Image must be 5MB or smaller');
                fileInput.value = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function (event) {
                var preview = document.createElement('img');
                preview.src = event.target.result;
                preview.className = 'featured-thumb mt-2';
                var wrapper = fileInput.parentElement.querySelector('.file-preview');
                if (!wrapper) {
                    wrapper = document.createElement('div');
                    wrapper.className = 'file-preview mt-2';
                    fileInput.parentElement.appendChild(wrapper);
                } else {
                    wrapper.innerHTML = '';
                }
                wrapper.appendChild(preview);
            };
            reader.readAsDataURL(selectedFile);
        });
    }
});
</script>
</body>
</html>
