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

function uploadToSupabaseStorage(array $uploadedFile, array $supabaseConfig): ?string {
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
        'x-upsert: true'
    ];

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContents);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode >= 400) {
        error_log('Supabase storage upload failed: ' . $curlErr . ' HTTP: ' . $httpCode . ' Resp: ' . $response);
        return null;
    }

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
    $errorMessage = 'Supabase is not available right now.';
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
        $errorMessage = 'Unable to load featured cards from database.';
    }
}

if (empty($cards)) {
    $cards = [
        [
            'id' => 'fc_01',
            'title' => 'Regional Student Summit 2026',
            'description' => 'Join over 500 ECE student delegates across Laguna for workshops, paper presentations, and hackathons.',
            'image_url' => '',
            'button_text' => 'Register Delegate',
            'button_url' => '/portal/events.php',
            'sort_order' => 1,
            'is_active' => true
        ],
        [
            'id' => 'fc_02',
            'title' => 'Chapter Officer Leadership Conclave',
            'description' => 'Annual governance retreat and accreditation onboarding for newly elected student chapter executive officers.',
            'image_url' => '',
            'button_text' => 'View Agenda',
            'button_url' => '/portal/documents.php',
            'sort_order' => 2,
            'is_active' => true
        ]
    ];
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
            $_SESSION['featured_cards_flash'] = ['success' => 'The featured card was removed.'];
            header('Location: featured-cards.php');
            exit;
        } else {
            if ($id !== '') {
                if (!empty($uploadedImageUrl)) {
                    $payload['image_url'] = $uploadedImageUrl;
                } elseif ($imageUrl === '' && !empty($editingCard['image_url'])) {
                    $payload['image_url'] = $editingCard['image_url'];
                }
                $supabaseClient->update('featured_cards', $payload, $id);
                $_SESSION['featured_cards_flash'] = ['success' => 'The featured card was updated.'];
            } else {
                if (!empty($uploadedImageUrl)) {
                    $payload['image_url'] = $uploadedImageUrl;
                }
                $payload['created_at'] = gmdate('Y-m-d\TH:i:s\Z');
                $supabaseClient->insert('featured_cards', $payload);
                $_SESSION['featured_cards_flash'] = ['success' => 'The featured card was created.'];
            }
            header('Location: featured-cards.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Featured Landing Page Cards — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage public landing page hero banners, featured chapter opportunities, and spotlight announcements.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-rectangle-ad"></i> Featured Landing Page Cards</h1>
                    <p class="ap-page-subtitle">Publish and organize spotlight cards on the public homepage to broadcast major summits and opportunities.</p>
                </div>
            </div>

            <?php if (!empty($successMessage)): ?>
                <div class="ap-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="ap-alert danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-pen-to-square"></i> <?= $editingCard ? 'Edit Featured Card' : 'Create New Featured Card' ?></h3>
                    <?php if ($editingCard): ?>
                        <a href="featured-cards.php" class="ap-btn-secondary" style="font-size:0.75rem; padding:0.35rem 0.8rem;">Cancel Edit</a>
                    <?php endif; ?>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($editingCard['id'] ?? '') ?>">
                    
                    <div class="ap-grid-2">
                        <div class="ap-form-group" style="grid-column: 1 / -1;">
                            <label class="ap-form-label">Card Headline / Title</label>
                            <input class="ap-input" id="title" name="title" value="<?= htmlspecialchars($editingCard['title'] ?? '') ?>" placeholder="e.g. Regional ECE Summit 2026" required>
                        </div>
                        <div class="ap-form-group" style="grid-column: 1 / -1;">
                            <label class="ap-form-label">Card Description & Details</label>
                            <textarea class="ap-textarea" id="description" name="description" rows="3" placeholder="Brief summary of the featured event or notice..." required><?= htmlspecialchars($editingCard['description'] ?? '') ?></textarea>
                        </div>
                        <div class="ap-form-group">
                            <label class="ap-form-label">Upload Hero Image</label>
                            <input class="ap-input" type="file" id="image_file" name="image_file" accept="image/jpeg,image/png,image/webp">
                            <div class="ap-input-help">Max file size: 5MB (JPG, PNG, WebP)</div>
                        </div>
                        <div class="ap-form-group">
                            <label class="ap-form-label">Or Image URL</label>
                            <input class="ap-input" id="image_url" name="image_url" value="<?= htmlspecialchars($editingCard['image_url'] ?? '') ?>" placeholder="https://example.com/image.jpg">
                        </div>
                        <div class="ap-form-group">
                            <label class="ap-form-label">Button Action Text</label>
                            <input class="ap-input" id="button_text" name="button_text" value="<?= htmlspecialchars($editingCard['button_text'] ?? 'Learn More') ?>">
                        </div>
                        <div class="ap-form-group">
                            <label class="ap-form-label">Destination URL</label>
                            <input class="ap-input" id="button_url" name="button_url" value="<?= htmlspecialchars($editingCard['button_url'] ?? '#') ?>">
                        </div>
                        <div class="ap-form-group">
                            <label class="ap-form-label">Display Sort Priority</label>
                            <input class="ap-input" type="number" id="sort_order" name="sort_order" value="<?= htmlspecialchars((string)($editingCard['sort_order'] ?? 0)) ?>">
                        </div>
                        <div class="ap-form-group" style="display:flex; align-items:center; gap:0.75rem; margin-top:1.8rem;">
                            <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($editingCard['is_active']) || !$editingCard ? 'checked' : '' ?> style="width:18px; height:18px; cursor:pointer;">
                            <label for="is_active" class="ap-form-label" style="margin:0; cursor:pointer;">Active on public homepage</label>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
                        <button class="ap-btn-primary" type="submit">
                            <i class="fas fa-floppy-disk"></i> <?= $editingCard ? 'Update Featured Card' : 'Publish Featured Card' ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Cards Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-layer-group"></i> Active Homepage Spotlight Cards</h3>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Card Headline & Preview</th>
                                <th>Destination</th>
                                <th>Priority</th>
                                <th>Visibility</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cards as $card): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:0.85rem;">
                                            <?php if (!empty($card['image_url'])): ?>
                                                <img src="<?= htmlspecialchars($card['image_url']) ?>" alt="Preview" style="width:60px; height:40px; object-fit:cover; border-radius:8px; border:1px solid var(--border-light);">
                                            <?php else: ?>
                                                <div class="ap-avatar-badge navy" style="border-radius:8px; width:45px; height:35px; font-size:0.9rem;"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="color:var(--text-heading); font-size:0.88rem;"><?= htmlspecialchars($card['title'] ?? '') ?></strong><br>
                                                <span style="font-size:0.75rem; color:var(--text-muted); display:block; max-width:350px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($card['description'] ?? '') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ap-mono" style="font-size:0.78rem; color:var(--iecep-navy);"><?= htmlspecialchars($card['button_text'] ?? 'Learn More') ?> &rarr;</span>
                                    </td>
                                    <td>
                                        <span class="ap-pill navy">Order #<?= (int)($card['sort_order'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($card['is_active'])): ?>
                                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Visible</span>
                                        <?php else: ?>
                                            <span class="ap-pill inactive"><span class="ap-pill-dot"></span> Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:flex; gap:0.4rem; justify-content:flex-end;">
                                            <a href="featured-cards.php?edit=<?= htmlspecialchars((string)($card['id'] ?? '')) ?>" class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;">
                                                <i class="fas fa-pencil"></i> Edit
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Delete this featured card?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars((string)($card['id'] ?? '')) ?>">
                                                <button class="ap-btn-danger" type="submit" style="padding:0.3rem 0.75rem; font-size:0.75rem;"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-globe"></i><span><strong>Homepage Engine:</strong> Dynamic Content Sync Active</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Storage:</strong> Supabase CDN Backed</span></div>
            </div>

        </div>
    </main>
</body>
</html>
