<?php

error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

/**
 * Professional UI Renderer
 * Ito ang ipapakita kapag ang user ay na-redirect dito pagkatapos ng success
 */
function renderProfessionalUI($title, $message, $type = 'success') {
    $isSuccess = ($type === 'success');
    $statusTitle  = $isSuccess ? 'Submission Successful' : 'Action Required';
    $icon         = $isSuccess ? 'fa-circle-check'       : 'fa-triangle-exclamation';
    
    $homeURL      = 'http://localhost/IECEP-LSC-MEMSYS/index.php'; 
    $assetsUrl    = 'http://localhost/IECEP-LSC-MEMSYS/public'; 
    $logoPath     = $assetsUrl . '/uploads/features/1776563416_iecep-logo.png';
    $heroPath     = $assetsUrl . '/uploads/features/1776563415_hero.png';

    header_remove('Content-Type');
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $statusTitle . ' — IECEP-LSC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --navy: #0B1D4A; --gold: #C5A059; --gold-light: #D9BB80; --gold-pale: #F5ECD8; --white: #FFFFFF; --text-body: #4A5568; }
        html, body { min-height: 100vh; font-family: "DM Sans", sans-serif; }
        body {
            background: linear-gradient(135deg, rgba(11, 29, 74, 0.85) 0%, rgba(18, 36, 96, 0.75) 100%), url("' . $heroPath . '");
            background-size: cover; background-position: center; background-attachment: fixed;
            display: flex; align-items: center; justify-content: center; padding: 24px; position: relative; overflow: hidden;
        }
        body::after { content: ""; position: fixed; bottom: -120px; right: -120px; width: 380px; height: 380px; border-radius: 50%; border: 60px solid rgba(197,160,89,0.07); z-index: 1; pointer-events: none; }
        .card { position: relative; z-index: 10; background: var(--white); border-radius: 24px; width: 100%; max-width: 500px; box-shadow: 0 30px 70px rgba(0,0,0,0.5); overflow: hidden; animation: cardIn 0.55s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .card-header-bar { height: 5px; background: linear-gradient(90deg, var(--navy) 0%, var(--gold) 50%, var(--navy) 100%); }
        .logo-strip { background: var(--navy); padding: 35px 0 30px; display: flex; flex-direction: column; align-items: center; gap: 15px; }
        .logo-wrap img { max-width: 150px; height: auto; display: block; object-fit: contain; }
        .org-label { color: var(--gold-light); font-size: 11.5px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; text-align: center; padding: 0 20px; }
        .org-label small { display: block; color: rgba(217, 187, 128, 0.60); font-size: 10px; margin-top: 2px; }
        .card-body { padding: 36px 44px 40px; text-align: center; }
        .status-icon { width: 72px; height: 72px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 22px; font-size: 28px; position: relative; }
        .status-icon.success { background: linear-gradient(135deg, #C5A059 0%, #e0c07a 100%); color: var(--white); box-shadow: 0 8px 24px rgba(197,160,89,0.40); }
        .status-icon.error { background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%); color: var(--white); }
        .gold-divider { width: 50px; height: 3px; background: linear-gradient(90deg, transparent, var(--gold), transparent); margin: 0 auto 18px; }
        h1 { font-family: "Playfair Display", serif; color: var(--navy); font-size: 26px; font-weight: 700; margin-bottom: 8px; }
        .subtitle { color: var(--gold); font-size: 11px; font-weight: 600; letter-spacing: 0.16em; text-transform: uppercase; margin-bottom: 20px; }
        .message-box { background: ' . ($isSuccess ? 'linear-gradient(135deg,#F0FFF4,#E6FFED)' : 'linear-gradient(135deg,#FFF5F5,#FFE8E8)') . '; border: 1px solid ' . ($isSuccess ? 'rgba(72,187,120,0.30)' : 'rgba(245,101,101,0.30)') . '; border-radius: 12px; padding: 16px 20px; margin-bottom: 28px; }
        .message-box p { color: var(--text-body); font-size: 14.5px; line-height: 1.65; }
        .btn-home { display: inline-flex; align-items: center; justify-content: center; gap: 9px; background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); color: var(--white); font-weight: 600; font-size: 14px; padding: 14px 36px; border-radius: 50px; text-decoration: none; transition: all 0.3s ease; }
        .btn-home:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(11,29,74,0.45); }
        .card-footer { border-top: 1px solid #EDF2F7; padding: 14px 44px; text-align: center; font-size: 12px; color: var(--text-muted); }
        @keyframes cardIn { from { opacity: 0; transform: translateY(28px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header-bar"></div >
    <div class="logo-strip">
        <div class="logo-wrap"><img src="' . $logoPath . '" alt="IECEP-LSC Logo"></div>
        <span class="org-label">Institute of Electronics Engineers of the Philippines <small>Laguna Student Chapter</small></span>
    </div>
    <div class="card-body">
        <div class="status-icon ' . ($isSuccess ? 'success' : 'error') . '"><i class="fas ' . $icon . '"></i></div>
        <p class="subtitle">' . ($isSuccess ? 'Application Status' : 'Submission Notice') . '</p>
        <h1>' . $statusTitle . '</h1>
        <div class="gold-divider"></div >
        <div class="message-box"><p>' . htmlspecialchars($message) . '</p></div>
        <a href="' . $homeURL . '" class="btn-home"><i class="fas fa-house"></i> Return to Home</a>
    </div>
    <div class="card-footer"><p>Need help? Contact <span>iecep.lsc@support.edu.ph</span></p></div>
</div>
</body>
</html>';
    exit;
}

// ============================================================
// MAIN LOGIC
// ============================================================

// 1. HANDLE VIEW PAGE (GET Request)
// Kapag na-redirect ang user dito, ipakita ang success page.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderProfessionalUI('Success', 'Application submitted successfully! The Registration Committee will review your application within 5-7 business days.', 'success');
    exit;
}

// 2. HANDLE SUBMISSION (POST Request)
try {
    @session_start();
    
    ob_start();
    require_once __DIR__ . '/../../autoload.php';
    require_once __DIR__ . '/../../includes/paths.php';
    require_once __DIR__ . '/../../src/lib/SupabaseClient.php';
    ob_end_clean();
    
    // Field Validation
    $institution_name = trim($_POST['institution_name'] ?? '');
    $institution_address = trim($_POST['institution_address'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $contact_position = trim($_POST['contact_position'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    
    if (empty($institution_name) || empty($institution_address) || empty($contact_person) || empty($contact_position) || empty($contact_email) || empty($contact_phone)) {
        throw new Exception('All fields are required.');
    }
    
    // File Validation
    $required_files = ['letter_of_intent', 'endorsement_letter', 'constitution_by_laws', 'officers_cvs', 'organizational_chart', 'member_directory'];
    foreach ($required_files as $file_key) {
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File '$file_key' is missing or corrupted.");
        }
    }
    
    // Database Logic
    $config = require __DIR__ . '/../../includes/supabase.php';
    $sb = new SupabaseClient($config['url'], $config['anon_key']);
    
    $uploadsDir = __DIR__ . '/../uploads/affiliations';
    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
    
    $uploadedFiles = [];
    $documentHashes = [];
    foreach ($required_files as $file_key) {
        $file = $_FILES[$file_key];
        $fileName = uniqid() . '_' . basename($file['name']);
        $filePath = $uploadsDir . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $filePath)) throw new Exception("Failed to upload file: $file_key");
        $uploadedFiles[$file_key] = '/IECEP-LSC-MEMSYS/public/uploads/affiliations/' . $fileName;
        $documentHashes[$file_key] = hash_file('sha256', $filePath);
    }
    
    $totalMembers = intval($_POST['total_members'] ?? 0);
    $newMembers = intval($_POST['new_members'] ?? 0);
    $oldMembers = intval($_POST['old_members'] ?? 0);
    $affiliationFee = floatval($_POST['affiliation_fee'] ?? 0);
    $membershipTotal = floatval($_POST['membership_total'] ?? 0);
    $totalFee = floatval($_POST['total_fee'] ?? 0);
    $receiptNumber = $_SESSION['affiliation_payment']['receipt_number'] ?? 'RCP-GEN-' . uniqid();
    
    $affiliationData = [
        'institution_name' => $institution_name, 'institution_address' => $institution_address,
        'contact_person' => $contact_person, 'contact_position' => $contact_position,
        'contact_email' => $contact_email, 'email' => $contact_email, 'contact_phone' => $contact_phone,
        'letter_of_intent' => $uploadedFiles['letter_of_intent'], 'endorsement_letter' => $uploadedFiles['endorsement_letter'],
        'constitution_by_laws' => $uploadedFiles['constitution_by_laws'], 'officers_cvs' => $uploadedFiles['officers_cvs'],
        'organizational_chart' => $uploadedFiles['organizational_chart'], 'member_directory' => $uploadedFiles['member_directory'],
        'status' => 'pending', 'submitted_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown', 'total_members' => $totalMembers,
        'new_members' => $newMembers, 'old_members' => $oldMembers, 'affiliation_fee' => $affiliationFee,
        'membership_total' => $membershipTotal, 'total_fee' => $totalFee, 'receipt_number' => $receiptNumber
    ];
    
    $result = $sb->insert('pending_affiliations', $affiliationData);
    $applicationId = $result[0]['id'] ?? null;
    if (!$applicationId) throw new Exception('Failed to save application.');
    
    // Blockchain Recording
    $blockchainData = ['application_id' => $applicationId, 'institution_name' => $institution_name, 'total_fee' => $totalFee, 'document_hashes' => $documentHashes, 'submitted_at' => date('Y-m-d H:i:s')];
    $dataHash = hash('sha256', json_encode($blockchainData));
    $sb->insert('blockchain_records', [
        'record_type' => 'affiliation', 'reference_id' => $applicationId, 'data_hash' => $dataHash,
        'previous_hash' => '0', 'data_json' => json_encode($blockchainData), 'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if (isset($_SESSION['affiliation_payment'])) unset($_SESSION['affiliation_payment']);
    
    // ✅ ANG PINAKA-IMPORTANTENG PAGBABAGO:
    // Imbes na echo json_encode, i-redirect ang user sa sarili niyang page (GET request)
    header('Location: submit-affiliation.php?status=success');
    exit;
    
} catch (Exception $e) {
    error_log('Submit affiliation error: ' . $e->getMessage());
    // Kung may error, ibalik siya sa index na may error message sa URL
    header('Location: /IECEP-LSC-MEMSYS/index.php?error=' . urlencode($e->getMessage()));
    exit;
}
