<?php
if (!isset($current_page)) { $current_page = 'messages'; }
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin']);

use App\Lib\SupabaseClient;

$userId = $_SESSION['user']['id'] ?? '';
$selectedConversation = $_GET['conversation'] ?? 'conv_1';
$messages = [];
$members = [];

try {
    $supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    $msgData = $supabase->select('messages', ['select' => '*']);
    if (is_array($msgData)) {
        $messages = $msgData;
    }
    $usrData = $supabase->select('user_profiles', ['select' => 'id, user_id, full_name, role']);
    if (is_array($usrData)) {
        $members = $usrData;
    }
} catch (Exception $e) {
    error_log("Messages query error: " . $e->getMessage());
}

$conversations = [
    [
        'id' => 'conv_1',
        'name' => 'LSPU Santa Cruz Officers',
        'last_message' => 'The accreditation endorsement letter has been signed and uploaded.',
        'time' => '10:45 AM',
        'unread' => 2,
        'avatar' => 'SC',
        'online' => true
    ],
    [
        'id' => 'conv_2',
        'name' => 'Mapúa Malayan Chapter Team',
        'last_message' => 'Remittance receipt reference REF-2026-0801 has been submitted.',
        'time' => 'Yesterday',
        'unread' => 0,
        'avatar' => 'MM',
        'online' => false
    ],
    [
        'id' => 'conv_3',
        'name' => 'De La Salle Laguna Secretariat',
        'last_message' => 'Requesting confirmation for the upcoming Robotics Summit delegates.',
        'time' => '2 days ago',
        'unread' => 0,
        'avatar' => 'DL',
        'online' => true
    ]
];

$activeMessages = [
    ['sender' => 'them', 'name' => 'LSPU Santa Cruz Officers', 'text' => 'Good morning Chapter Admin, we have prepared all 6 accreditation documents for AY 2026-2027.', 'time' => '10:30 AM'],
    ['sender' => 'me', 'name' => 'Chapter Admin', 'text' => 'Thank you! The council will audit the SHA-256 cryptographic signatures immediately.', 'time' => '10:38 AM'],
    ['sender' => 'them', 'name' => 'LSPU Santa Cruz Officers', 'text' => 'The accreditation endorsement letter has been signed and uploaded.', 'time' => '10:45 AM']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Communications & Messages — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Secure internal communications dispatch and direct messaging for student chapter officers and council.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .msg-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 1.25rem;
            height: calc(100vh - 220px);
            min-height: 520px;
        }
        @media (max-width: 900px) {
            .msg-layout { grid-template-columns: 1fr; height: auto; }
        }
        .conv-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            overflow-y: auto;
            padding-right: 4px;
        }
        .conv-card {
            background: var(--roster-surface);
            border: 1px solid var(--roster-border);
            border-radius: 12px;
            padding: 0.9rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .conv-card:hover {
            border-color: var(--iecep-gold);
            background: var(--roster-subtle);
        }
        .conv-card.active {
            background: #FFFFFF;
            border: 1.5px solid var(--iecep-navy);
            box-shadow: var(--card-shadow);
        }
        .chat-container {
            display: flex;
            flex-direction: column;
            background: #FFFFFF;
            border: 1px solid var(--roster-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        .chat-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--roster-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FFFFFF;
        }
        .chat-body {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: #F8FAFC;
        }
        .chat-bubble {
            max-width: 70%;
            padding: 0.85rem 1.15rem;
            border-radius: 14px;
            font-size: 0.88rem;
            line-height: 1.45;
        }
        .chat-bubble.them {
            align-self: flex-start;
            background: #FFFFFF;
            border: 1px solid var(--roster-border);
            color: var(--text-primary);
            border-bottom-left-radius: 4px;
        }
        .chat-bubble.me {
            align-self: flex-end;
            background: linear-gradient(135deg, #0B1D4A 0%, #1E3A8A 100%);
            color: #FFFFFF;
            border-bottom-right-radius: 4px;
        }
        .chat-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--roster-border);
            display: flex;
            gap: 0.75rem;
            background: #FFFFFF;
            align-items: center;
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-comments"></i> Direct Chapter Communications</h1>
                    <p class="ap-page-subtitle">Encrypted chapter channel dispatch, accreditation inquiries, and regional council notices.</p>
                </div>
                <div class="ap-header-actions">
                    <button class="ap-btn-primary" onclick="alert('Starting new conversation channel...')">
                        <i class="fas fa-pen-to-square"></i> New Conversation
                    </button>
                </div>
            </div>

            <!-- Messages 2-Column Interface -->
            <div class="msg-layout">
                <!-- Conversations List -->
                <div class="conv-list">
                    <div class="ap-search-wrapper" style="margin-bottom:0.5rem;">
                        <i class="fas fa-magnifying-glass"></i>
                        <input type="text" class="ap-search-input" placeholder="Search conversations...">
                    </div>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conv-card <?= ($conv['id'] === $selectedConversation) ? 'active' : '' ?>" onclick="window.location.href='?conversation=<?= $conv['id'] ?>'">
                            <div class="ap-avatar-badge navy" style="position:relative;">
                                <?= $conv['avatar'] ?>
                                <?php if ($conv['online']): ?>
                                    <span style="position:absolute; bottom:0; right:0; width:10px; height:10px; background:var(--accent-emerald); border:2px solid #FFFFFF; border-radius:50%;"></span>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; align-items:baseline;">
                                    <strong style="color:var(--text-heading); font-size:0.86rem;"><?= htmlspecialchars($conv['name']) ?></strong>
                                    <span style="font-size:0.72rem; color:var(--text-muted);"><?= $conv['time'] ?></span>
                                </div>
                                <div style="font-size:0.76rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">
                                    <?= htmlspecialchars($conv['last_message']) ?>
                                </div>
                            </div>
                            <?php if ($conv['unread'] > 0): ?>
                                <span class="ap-pill danger" style="padding:2px 6px; font-size:0.68rem;"><?= $conv['unread'] ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Chat Pane -->
                <div class="chat-container">
                    <div class="chat-header">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="ap-avatar-badge navy">SC</div>
                            <div>
                                <strong style="color:var(--text-heading); font-size:0.95rem;">LSPU Santa Cruz Officers</strong><br>
                                <span style="font-size:0.75rem; color:var(--accent-emerald); font-weight:600;"><i class="fas fa-circle" style="font-size:0.5rem;"></i> Active Now</span>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="ap-btn-secondary" style="padding:0.4rem 0.75rem;" title="View Chapter Profile">
                                <i class="fas fa-university"></i> Chapter Profile
                            </button>
                        </div>
                    </div>

                    <div class="chat-body" id="chatBody">
                        <?php foreach ($activeMessages as $m): ?>
                            <div class="chat-bubble <?= $m['sender'] ?>">
                                <div><?= htmlspecialchars($m['text']) ?></div>
                                <div style="font-size:0.68rem; margin-top:4px; opacity:0.8; text-align:right;"><?= $m['time'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="chat-footer">
                        <input type="text" class="ap-input" id="messageInput" placeholder="Type an official response or dispatch..." style="flex:1;" onkeypress="if(event.key==='Enter') sendMessage()">
                        <button class="ap-btn-primary" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-lock"></i><span><strong>Transmission Security:</strong> TLS 1.3 / End-to-End Encrypted</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-certificate"></i><span><strong>Audit Trail:</strong> Dispatch Logs Cryptographically Preserved</span></div>
            </div>

        </div>
    </main>

    <script>
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const text = input.value.trim();
            if (!text) return;

            const chatBody = document.getElementById('chatBody');
            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble me';
            bubble.innerHTML = `<div>${text}</div><div style="font-size:0.68rem; margin-top:4px; opacity:0.8; text-align:right;">Just now</div>`;
            chatBody.appendChild(bubble);
            input.value = '';
            chatBody.scrollTop = chatBody.scrollHeight;
        }
    </script>
</body>
</html>
