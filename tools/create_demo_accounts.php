<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Lib\SupabaseClient;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This setup tool may only be run from the command line.');
}

if (SUPABASE_SERVICE_ROLE_KEY === '') {
    throw new RuntimeException('SUPABASE_SERVICE_ROLE_KEY is required.');
}

$client = new SupabaseClient(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY);
$client->setServiceRoleKey(SUPABASE_SERVICE_ROLE_KEY);

$institutions = $client->select('institutions', [
    'select' => 'id,name',
    'status' => 'eq.active',
    'order' => 'created_at.asc',
    'limit' => '1',
]);

if (!is_array($institutions) || empty($institutions)) {
    $created = $client->insert('institutions', [
        'email' => 'demo-school@iecep-lsc.test',
        'name' => 'IECEP-LSC Demo School',
        'acronym' => 'ILDS',
        'type' => 'school',
        'city' => 'Laguna',
        'province' => 'Laguna',
        'country' => 'Philippines',
        'contact_person' => 'IECEP-LSC Demo Officer',
        'contact_email' => 'demo-school@iecep-lsc.test',
        'status' => 'active',
    ]);
    $institution = $created[0] ?? $created;
} else {
    $institution = $institutions[0];
}

$institutionId = (string) ($institution['id'] ?? '');
if ($institutionId === '') {
    throw new RuntimeException('Unable to resolve an institution for demo accounts.');
}

$accounts = [
    [
        'email' => 'demo.admin@iecep-lsc.test',
        'name' => 'IECEP-LSC Demo Administrator',
        'role' => 'admin',
        'member' => false,
    ],
    [
        'email' => 'demo.officer@iecep-lsc.test',
        'name' => 'IECEP-LSC Demo School Officer',
        'role' => 'school_officer',
        'member' => false,
    ],
    [
        'email' => 'demo.member@iecep-lsc.test',
        'name' => 'IECEP-LSC Demo Member',
        'role' => 'member',
        'member' => true,
    ],
];

$createdAccounts = [];
foreach ($accounts as $account) {
    $profiles = $client->select('user_profiles', [
        'select' => 'user_id,role',
        'full_name' => 'eq.' . $account['name'],
        'limit' => '1',
    ]);

    if (is_array($profiles) && !empty($profiles)) {
        $createdAccounts[] = [
            'email' => $account['email'],
            'role' => $account['role'],
            'status' => 'already_exists',
        ];
        continue;
    }

    $temporaryPassword = 'Iecep!' . bin2hex(random_bytes(6));
    $authUser = $client->authSignUp($account['email'], $temporaryPassword, [
        'full_name' => $account['name'],
        'role' => $account['role'],
    ]);
    $userId = (string) ($authUser['id'] ?? $authUser['user']['id'] ?? '');
    if ($userId === '') {
        throw new RuntimeException('Supabase did not return a user ID for ' . $account['email']);
    }

    $client->insert('user_profiles', [
        'user_id' => $userId,
        'institution_id' => $institutionId,
        'role' => $account['role'],
        'full_name' => $account['name'],
        'school_name' => $institution['name'] ?? 'IECEP-LSC Demo School',
        'membership_status' => 'active',
        'membership_type' => 'student',
        'force_password_change' => true,
    ]);

    if ($account['member']) {
        $membershipId = 'MEM-' . date('Y') . '-DEMO-001';
        $client->insert('members', [
            'institution_id' => $institutionId,
            'user_id' => $userId,
            'full_name' => $account['name'],
            'email' => $account['email'],
            'membership_id' => $membershipId,
            'member_type' => 'new',
            'payment_status' => true,
            'school_affiliate' => $institution['name'] ?? 'IECEP-LSC Demo School',
            'is_new' => true,
            'membership_expiry' => date('Y-12-31'),
        ]);
    }

    $createdAccounts[] = [
        'email' => $account['email'],
        'role' => $account['role'],
        'temporary_password' => $temporaryPassword,
        'status' => 'created',
    ];
}

echo json_encode([
    'institution_id' => $institutionId,
    'institution_name' => $institution['name'] ?? '',
    'accounts' => $createdAccounts,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
