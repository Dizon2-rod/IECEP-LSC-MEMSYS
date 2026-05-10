<?php
$current_page = basename(__FILE__, '.php');
require_once '../../../includes/auth_check.php';
require_role(['super_admin']);
require_once '../../../includes/dashboard-layout.php';

$supabase = new App\Lib\SupabaseClient();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        // Create Supabase Auth user
        $authResponse = $supabase->auth->signUp([
            'email' => $_POST['email'],
            'password' => $_POST['password']
        ]);

        if ($authResponse['user']) {
            // Create user profile
            $profileData = [
                'user_id' => $authResponse['user']['id'],
                'role' => $_POST['role'],
                'full_name' => $_POST['full_name']
            ];
            $supabase->from('user_profiles')->insert($profileData);
        }
    } elseif (isset($_POST['update_role'])) {
        $userId = $_POST['user_id'];
        $newRole = $_POST['role'];
        $supabase->from('user_profiles')->update(['role' => $newRole])->eq('user_id', $userId);
    } elseif (isset($_POST['deactivate_user'])) {
        $userId = $_POST['user_id'];
        // Note: Supabase doesn't have deactivate, so we could update role or delete
        $supabase->from('user_profiles')->delete()->eq('user_id', $userId);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch all users
$users = $supabase->from('user_profiles')->select('*')->execute();

dashboard_layout('User Management', function() use ($users) {
?>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">User Management</h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Create New User -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Create New User</h2>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Full Name</label>
                    <input type="text" name="full_name" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" name="email" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Password</label>
                    <input type="password" name="password" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Role</label>
                    <select name="role" class="w-full border rounded px-3 py-2" required>
                        <option value="member">Member</option>
                        <option value="school_officer">School Officer</option>
                        <option value="committee_registration">Registration Committee</option>
                        <option value="eb_treasurer">EB Treasurer</option>
                        <option value="eb_president">EB President</option>
                        <!-- Add other roles as needed -->
                    </select>
                </div>
                <button type="submit" name="create_user" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create User</button>
            </form>
        </div>

        <!-- Existing Users -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Manage Users</h2>
            <div class="space-y-4 max-h-96 overflow-y-auto">
                <?php foreach ($users['data'] ?? [] as $user): ?>
                <div class="border rounded p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-semibold"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                            <p class="text-sm text-gray-600">Role: <?php echo htmlspecialchars($user['role']); ?></p>
                        </div>
                        <div class="flex space-x-2">
                            <form method="POST" class="inline">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <select name="role" onchange="this.form.submit()" class="text-sm border rounded px-2 py-1">
                                    <option value="member" <?php echo $user['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
                                    <option value="school_officer" <?php echo $user['role'] === 'school_officer' ? 'selected' : ''; ?>>School Officer</option>
                                    <option value="committee_registration" <?php echo $user['role'] === 'committee_registration' ? 'selected' : ''; ?>>Registration</option>
                                    <option value="eb_treasurer" <?php echo $user['role'] === 'eb_treasurer' ? 'selected' : ''; ?>>Treasurer</option>
                                    <option value="eb_president" <?php echo $user['role'] === 'eb_president' ? 'selected' : ''; ?>>President</option>
                                </select>
                                <input type="hidden" name="update_role">
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <button type="submit" name="deactivate_user" onclick="return confirm('Deactivate this user?')" class="text-red-600 hover:text-red-800 text-sm">Deactivate</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php
});
?>
