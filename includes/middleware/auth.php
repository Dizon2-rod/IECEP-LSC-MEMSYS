<?php
namespace App\Middleware;

class AuthMiddleware
{
    private $supabase;

    public function __construct()
    {
        require_once __DIR__ . '/../lib/supabase.php';
        $this->supabase = new \App\Lib\Supabase();
    }

    public function validateToken(): ?array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $jwt = substr($authHeader, 7);
        if (empty($jwt)) {
            return null;
        }

        // Verify JWT with Supabase
        $result = $this->supabase->auth()->getUser($jwt);
        if ($result['error']) {
            return null;
        }

        return [
            'user_id' => $result['data']['id'] ?? null,
            'email' => $result['data']['email'] ?? null,
            'jwt' => $jwt,
        ];
    }

    public function requireAuth(): array
    {
        $user = $this->validateToken();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        return $user;
    }

    public function requireRole(array $allowedRoles): array
    {
        $user = $this->requireAuth();

        // Get user profile
        $profileResult = $this->supabase->from('user_profiles')
            ->select('*')
            ->eq('user_id', $user['user_id'])
            ->get(true, $user['jwt']);

        if ($profileResult['error'] || empty($profileResult['data'])) {
            http_response_code(403);
            echo json_encode(['error' => 'No profile found']);
            exit;
        }

        $profile = $profileResult['data'][0];
        if (!in_array($profile['role'], $allowedRoles)) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions', 'required' => $allowedRoles, 'your_role' => $profile['role']]);
            exit;
        }

        return array_merge($user, ['profile' => $profile]);
    }

    public function getUserProfile(string $userId, string $jwt): ?array
    {
        $result = $this->supabase->from('user_profiles')
            ->select('*')
            ->eq('user_id', $userId)
            ->get(true, $jwt);

        if ($result['error'] || empty($result['data'])) {
            return null;
        }
        return $result['data'][0];
    }

    public function getMemberRecord(string $userId, string $jwt): ?array
    {
        $result = $this->supabase->from('members')
            ->select('*, institutions(name)')
            ->eq('user_id', $userId)
            ->get(true, $jwt);

        if ($result['error'] || empty($result['data'])) {
            return null;
        }
        return $result['data'][0];
    }
}
