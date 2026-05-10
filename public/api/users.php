<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';

// Set headers for API responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['eb_admin', 'eb_president', 'eb_vp_internal'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

try {
    switch ($action) {
        case 'list_users':
            // List users with pagination and filtering
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $perPage = isset($_GET['per_page']) ? min(50, max(1, intval($_GET['per_page']))) : 20;
            $roleFilter = $_GET['role'] ?? 'all';
            $statusFilter = $_GET['status'] ?? 'all';
            $searchFilter = $_GET['search'] ?? '';

            $offset = ($page - 1) * $perPage;

            $query = $supabaseClient->from('user_profiles')
                ->select('*', ['count' => 'exact']);

            // Apply filters
            if ($roleFilter !== 'all') {
                $query->eq('role', $roleFilter);
            }

            if ($statusFilter !== 'all') {
                $query->eq('verification_status', $statusFilter);
            }

            if (!empty($searchFilter)) {
                $query->or("name.ilike.%{$searchFilter}%,email.ilike.%{$searchFilter}%");
            }

            $users = $query->order('created_at', ['ascending' => false])
                ->range($offset, $offset + $perPage - 1)
                ->execute();

            echo json_encode([
                'success' => true,
                'users' => $users,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $users['count'] ?? 0,
                    'total_pages' => ceil(($users['count'] ?? 0) / $perPage)
                ]
            ]);
            break;

        case 'get_user':
            // Get single user details
            $targetUserId = $_GET['id'] ?? '';
            if (empty($targetUserId)) {
                throw new Exception('User ID required');
            }

            $user = $supabaseClient->from('user_profiles')
                ->select('*')
                ->eq('id', $targetUserId)
                ->single()
                ->execute();

            if (!$user) {
                throw new Exception('User not found');
            }

            echo json_encode(['success' => true, 'user' => $user]);
            break;

        case 'create_user':
            // Create new user
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Invalid JSON data');
            }

            // Validate required fields
            if (empty($data['name']) || empty($data['email']) || empty($data['role'])) {
                throw new Exception('Name, email, and role are required');
            }

            // Check if email already exists
            $existingUser = $supabaseClient->from('user_profiles')
                ->select('id')
                ->eq('email', $data['email'])
                ->single()
                ->execute();

            if ($existingUser) {
                throw new Exception('Email already exists');
            }

            $userData = [
                'name' => trim($data['name']),
                'email' => trim($data['email']),
                'role' => $data['role'],
                'institution' => trim($data['institution'] ?? ''),
                'address' => trim($data['address'] ?? ''),
                'phone' => trim($data['phone'] ?? ''),
                'verification_status' => $data['verification_status'] ?? 'pending',
                'created_at' => date('c'),
                'updated_at' => date('c')
            ];

            $result = $supabaseClient->from('user_profiles')->insert($userData)->execute();

            echo json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'user' => $result[0] ?? null
            ]);
            break;

        case 'update_user':
            // Update existing user
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
                throw new Exception('Method not allowed');
            }

            $targetUserId = $_GET['id'] ?? '';
            if (empty($targetUserId)) {
                throw new Exception('User ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Invalid JSON data');
            }

            // Check if email is being changed and if it conflicts
            if (!empty($data['email'])) {
                $existingUser = $supabaseClient->from('user_profiles')
                    ->select('id')
                    ->eq('email', $data['email'])
                    ->neq('id', $targetUserId)
                    ->single()
                    ->execute();

                if ($existingUser) {
                    throw new Exception('Email already exists');
                }
            }

            $updateData = [
                'name' => trim($data['name']) ?? null,
                'email' => trim($data['email']) ?? null,
                'role' => $data['role'] ?? null,
                'institution' => trim($data['institution']) ?? null,
                'address' => trim($data['address']) ?? null,
                'phone' => trim($data['phone']) ?? null,
                'verification_status' => $data['verification_status'] ?? null,
                'updated_at' => date('c')
            ];

            // Remove null values
            $updateData = array_filter($updateData, function($value) {
                return $value !== null;
            });

            $result = $supabaseClient->from('user_profiles')
                ->update($updateData)
                ->eq('id', $targetUserId)
                ->execute();

            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully'
            ]);
            break;

        case 'delete_user':
            // Delete user
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                throw new Exception('Method not allowed');
            }

            $targetUserId = $_GET['id'] ?? '';
            if (empty($targetUserId)) {
                throw new Exception('User ID required');
            }

            // Prevent deletion of admin users by non-admin
            if ($userRole !== 'eb_admin') {
                $targetUser = $supabaseClient->from('user_profiles')
                    ->select('role')
                    ->eq('id', $targetUserId)
                    ->single()
                    ->execute();

                if ($targetUser && $targetUser['role'] === 'eb_admin') {
                    throw new Exception('Cannot delete admin users');
                }
            }

            $supabaseClient->from('user_profiles')->delete()->eq('id', $targetUserId)->execute();

            echo json_encode([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
            break;

        case 'verify_user':
            // Verify user
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $targetUserId = $_GET['id'] ?? '';
            if (empty($targetUserId)) {
                throw new Exception('User ID required');
            }

            $supabaseClient->from('user_profiles')
                ->update([
                    'verification_status' => 'verified',
                    'updated_at' => date('c')
                ])
                ->eq('id', $targetUserId)
                ->execute();

            echo json_encode([
                'success' => true,
                'message' => 'User verified successfully'
            ]);
            break;

        case 'revoke_user':
            // Revoke user access
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $targetUserId = $_GET['id'] ?? '';
            if (empty($targetUserId)) {
                throw new Exception('User ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $reason = $data['reason'] ?? 'Administrative revocation';

            $supabaseClient->from('user_profiles')
                ->update([
                    'verification_status' => 'revoked',
                    'updated_at' => date('c')
                ])
                ->eq('id', $targetUserId)
                ->execute();

            echo json_encode([
                'success' => true,
                'message' => 'User access revoked successfully'
            ]);
            break;

        case 'get_user_stats':
            // Get user statistics
            $stats = $supabaseClient->from('user_profiles')
                ->select('role, verification_status, created_at')
                ->execute();

            $userStats = [
                'total_users' => count($stats),
                'by_role' => [],
                'by_status' => [],
                'recent_registrations' => 0
            ];

            $oneWeekAgo = strtotime('-1 week');

            foreach ($stats as $user) {
                $role = $user['role'] ?? 'unknown';
                $status = $user['verification_status'] ?? 'pending';

                if (!isset($userStats['by_role'][$role])) {
                    $userStats['by_role'][$role] = 0;
                }
                $userStats['by_role'][$role]++;

                if (!isset($userStats['by_status'][$status])) {
                    $userStats['by_status'][$status] = 0;
                }
                $userStats['by_status'][$status]++;

                if (strtotime($user['created_at']) > $oneWeekAgo) {
                    $userStats['recent_registrations']++;
                }
            }

            echo json_encode([
                'success' => true,
                'stats' => $userStats
            ]);
            break;

        case 'export_users':
            // Export users data
            $users = $supabaseClient->from('user_profiles')
                ->select('*')
                ->order('created_at', ['ascending' => false])
                ->execute();

            // Generate CSV
            $csvData = "ID,Name,Email,Role,Institution,Phone,Address,Verification Status,Created At,Updated At\n";

            foreach ($users as $user) {
                $csvData .= '"' . ($user['id'] ?? '') . '",';
                $csvData .= '"' . str_replace('"', '""', $user['name'] ?? '') . '",';
                $csvData .= '"' . str_replace('"', '""', $user['email'] ?? '') . '",';
                $csvData .= '"' . str_replace('"', '""', $user['role'] ?? '') . '",';
                $csvData .= '"' . str_replace('"', '""', $user['institution'] ?? '') . '",';
                $csvData .= '"' . str_replace('"', '""', $user['phone'] ?? '') . '",';
                $csvData .= '"' . str_replace('"', '""', $user['address'] ?? '') . '",';
                $csvData .= '"' . str_replace('"', '""', $user['verification_status'] ?? '') . '",';
                $csvData .= '"' . ($user['created_at'] ?? '') . '",';
                $csvData .= '"' . ($user['updated_at'] ?? '') . '"';
                $csvData .= "\n";
            }

            // Set headers for file download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="users_' . date('Y-m-d') . '.csv"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $csvData;
            exit;
            break;

        case 'bulk_verify':
            // Bulk verify users
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['user_ids']) || !is_array($data['user_ids'])) {
                throw new Exception('User IDs array required');
            }

            $userIds = $data['user_ids'];
            $updated = 0;

            foreach ($userIds as $id) {
                try {
                    $supabaseClient->from('user_profiles')
                        ->update([
                            'verification_status' => 'verified',
                            'updated_at' => date('c')
                        ])
                        ->eq('id', $id)
                        ->execute();
                    $updated++;
                } catch (Exception $e) {
                    // Continue with other users
                    error_log('Error verifying user ' . $id . ': ' . $e->getMessage());
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Verified {$updated} out of " . count($userIds) . " users"
            ]);
            break;

        case 'bulk_revoke':
            // Bulk revoke users
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['user_ids']) || !is_array($data['user_ids'])) {
                throw new Exception('User IDs array required');
            }

            $userIds = $data['user_ids'];
            $reason = $data['reason'] ?? 'Bulk administrative revocation';
            $updated = 0;

            foreach ($userIds as $id) {
                try {
                    $supabaseClient->from('user_profiles')
                        ->update([
                            'verification_status' => 'revoked',
                            'updated_at' => date('c')
                        ])
                        ->eq('id', $id)
                        ->execute();
                    $updated++;
                } catch (Exception $e) {
                    // Continue with other users
                    error_log('Error revoking user ' . $id . ': ' . $e->getMessage());
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Revoked {$updated} out of " . count($userIds) . " users"
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log('Users API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>