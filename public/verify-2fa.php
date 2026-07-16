<?php
require_once __DIR__ . '/includes/auth_check.php';

// Check if user has 2FA enabled
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/includes/config.php';

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: /login.php');
    exit;
}

try {
    $profileData = $supabase->select('user_profiles', [
        'user_id' => 'eq.' . ($user['id'] ?? '')
    ]);
    $profile = $profileData[0] ?? [];
    $mfaEnabled = $profile['mfa_enabled'] ?? false;
} catch (Exception $e) {
    $mfaEnabled = false;
}

// If 2FA is not enabled, redirect to dashboard
if (!$mfaEnabled) {
    $role = $user['role'] ?? '';
    $redirectMap = [
        'admin' => '/portal/admin/dashboard.php',
        'school_officer' => '/portal/school-officer/dashboard.php',
        'member' => '/portal/member/dashboard.php'
    ];
    header('Location: ' . ($redirectMap[$role] ?? '/portal/member/dashboard.php'));
    exit;
}

// Check if already verified in this session
if (isset($_SESSION['2fa_verified']) && $_SESSION['2fa_verified'] === true) {
    $role = $user['role'] ?? '';
    $redirectMap = [
        'admin' => '/portal/admin/dashboard.php',
        'school_officer' => '/portal/school-officer/dashboard.php',
        'member' => '/portal/member/dashboard.php'
    ];
    header('Location: ' . ($redirectMap[$role] ?? '/portal/member/dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/font-awesome.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0B1D4A 0%, #142a6b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo i {
            font-size: 3rem;
            color: #D4AF37;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0B1D4A;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            text-align: center;
            letter-spacing: 0.5rem;
            font-size: 1.5rem;
        }
        .form-input:focus {
            outline: none;
            border-color: #C49A00;
            box-shadow: 0 0 0 3px rgba(196,154,0,0.1);
        }
        .btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: #0B1D4A;
            color: white;
        }
        .btn-primary:hover {
            background: #142a6b;
            transform: translateY(-1px);
        }
        .btn-primary:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .back-link a:hover {
            color: #0B1D4A;
        }
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h1>Two-Factor Authentication</h1>
        <p class="subtitle">Enter the 6-digit code from your authenticator app</p>
        
        <div id="alertContainer"></div>
        
        <form id="2faForm" onsubmit="verify2FA(event)">
            <div class="form-group">
                <label class="form-label" for="totpCode">Authentication Code</label>
                <input 
                    type="text" 
                    id="totpCode" 
                    class="form-input" 
                    placeholder="000000" 
                    maxlength="6" 
                    pattern="[0-9]{6}"
                    required
                    autofocus
                    autocomplete="one-time-code"
                >
            </div>
            <button type="submit" class="btn btn-primary" id="submitBtn">
                Verify
            </button>
        </form>
        
        <div class="back-link">
            <a href="/logout.php">Cancel and Logout</a>
        </div>
    </div>

    <script>
        function showAlert(message, type = 'error') {
            const container = document.getElementById('alertContainer');
            const alertClass = type === 'error' ? 'alert-error' : 'alert-success';
            container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        async function verify2FA(event) {
            event.preventDefault();
            
            const code = document.getElementById('totpCode').value;
            const submitBtn = document.getElementById('submitBtn');
            
            if (!code || code.length !== 6 || !/^\d{6}$/.test(code)) {
                showAlert('Please enter a valid 6-digit code');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading"></span> Verifying...';
            
            try {
                const response = await fetch('/api/verify-2fa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Verification successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect || '/portal/admin/dashboard.php';
                    }, 1000);
                } else {
                    showAlert(result.error || 'Invalid code. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Verify';
                    document.getElementById('totpCode').value = '';
                    document.getElementById('totpCode').focus();
                }
            } catch (error) {
                showAlert('Error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Verify';
            }
        }

        // Auto-focus input on load
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('totpCode').focus();
        });
    </script>
</body>
</html>
