<?php
require_once dirname(__DIR__, 2) . '/auth_check.php';

require_once __DIR__ . '/../../bootstrap.php';
$current_page = basename(__FILE__, '.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('BASE_PUBLIC_URL', '/IECEP-LSC-MEMSYS/public');

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_PUBLIC_URL . '/login.php');
    exit;
}

// Role check
$allowed_roles = ['admin', 'super_admin'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header('Location: ' . BASE_PUBLIC_URL . '/portal/member/dashboard.php');
    exit;
}

include_once __DIR__ . '/../../../../includes/head-meta.php';
include_once __DIR__ . '/../../../../includes/sidebar.php';

// Fetch members grouped by school
$membersBySchool = [];
$totalMembers = 0;
$totalSchools = 0;
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$filterSchool = isset($_GET['school']) ? $_GET['school'] : '';

try {
    require_once SRC_PATH . 'lib/SupabaseClient.php';
    $supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    
    // Get all members with their profiles
    $members = $supabase->select('user_profiles', null, 'created_at', 'DESC');
    
    if (!empty($members)) {
        // Get all active institutions for school names
        $institutions = $supabase->select('institutions', ['status' => 'eq.active']);
        $schoolNames = [];
        if ($institutions) {
            foreach ($institutions as $inst) {
                $schoolNames[$inst['id']] = $inst['name'];
            }
        }
        
        foreach ($members as $member) {
            if (($member['role'] ?? '') !== 'member') continue;
            
            $schoolId = $member['institution_id'] ?? null;
            $schoolName = $schoolNames[$schoolId] ?? ($member['school_name'] ?? 'Independent / Unaffiliated');
            
            // Apply search filter
            if ($searchQuery) {
                $searchLower = strtolower($searchQuery);
                $nameMatch = stripos($member['full_name'] ?? '', $searchQuery) !== false;
                $emailMatch = stripos($member['email'] ?? '', $searchQuery) !== false;
                $schoolMatch = stripos($schoolName, $searchQuery) !== false;
                if (!$nameMatch && !$emailMatch && !$schoolMatch) continue;
            }
            
            // Apply school filter
            if ($filterSchool && $schoolId !== $filterSchool) continue;
            
            $membersBySchool[$schoolName][] = $member;
            $totalMembers++;
        }
        
        $totalSchools = count($membersBySchool);
    }
} catch (Exception $e) {
    error_log("Members Load Error: " . $e->getMessage());
}

// Get all schools for filter dropdown
$allSchools = [];
try {
    $institutions = $supabase->select('institutions', ['status' => 'eq.active']);
    if ($institutions) {
        foreach ($institutions as $inst) {
            $allSchools[$inst['id']] = $inst['name'];
        }
    }
} catch (Exception $e) {
    $allSchools = [];
}
?>
<div class="main-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> Member Management</h1>
            <p class="text-muted">Members grouped by affiliated school</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-blue"><i class="fas fa-users"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?php echo number_format($totalMembers); ?></div>
                <div class="stat-label">Total Members</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-emerald"><i class="fas fa-school"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?php echo number_format($totalSchools); ?></div>
                <div class="stat-label">Active Schools</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-gold"><i class="fas fa-user-plus"></i></div>
            <div class="stat-details">
                <div class="stat-value"><?php echo $totalMembers > 0 ? number_format(round($totalMembers / max($totalSchools, 1), 1)) : 0; ?></div>
                <div class="stat-label">Avg Members/School</div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <form method="GET" action="" class="d-flex gap-2 flex-wrap" style="margin-bottom: 1.5rem;">
            <div style="flex: 1; min-width: 250px;">
                <label class="form-label">Search Members</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by name, email, or school..." class="form-control">
            </div>
            <div style="min-width: 200px;">
                <label class="form-label">Filter by School</label>
                <select name="school" onchange="this.form.submit()" class="form-select">
                    <option value="">All Schools</option>
                    <?php foreach ($allSchools as $id => $name): ?>
                        <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $filterSchool === $id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="align-self: flex-end;">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($searchQuery || $filterSchool): ?>
                <a href="?" class="btn btn-outline" style="align-self: flex-end; text-decoration: none;">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>

    <!-- Members by School -->
    <?php if (empty($membersBySchool)): ?>
        <div class="content-card" style="text-align: center; padding: 60px;">
            <i class="fas fa-users" style="font-size: 4rem; color: var(--slate-200); margin-bottom: 20px;"></i>
            <h3 style="color: var(--slate-400); font-weight: 500;">No members found</h3>
            <p style="color: var(--slate-400);">Try adjusting your search or filter criteria.</p>
        </div>
    <?php else: ?>
        <?php 
        // Sort schools by member count descending
        uasort($membersBySchool, function($a, $b) {
            return count($b) - count($a);
        });
        ?>
        
        <?php foreach ($membersBySchool as $schoolName => $members): ?>
            <div class="content-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; cursor: pointer;" 
                     onclick="toggleSchool('<?php echo htmlspecialchars($schoolName); ?>')">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #0B1D4A 0%, #1E3A6E 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.25rem;">
                            <i class="fas fa-school"></i>
                        </div>
                        <div>
                            <h2 style="margin: 0; font-size: 1.1rem; color: var(--portal-navy); font-weight: 600;">
                                <?php echo htmlspecialchars($schoolName); ?>
                            </h2>
                            <p style="margin: 2px 0 0 0; color: var(--portal-text-muted); font-size: 0.85rem;">
                                <?php echo count($members); ?> member<?php echo count($members) !== 1 ? 's' : ''; ?>
                            </p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="badge badge-info"><?php echo count($members); ?></span>
                        <i id="arrow-<?php echo md5($schoolName); ?>" class="fas fa-chevron-down" style="color: var(--portal-text-muted); transition: transform 0.3s;"></i>
                    </div>
                </div>

                <div id="school-<?php echo md5($schoolName); ?>" style="display: block;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Member ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Membership Type</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): 
                                    $status = strtolower($member['membership_status'] ?? 'active');
                                    $statusClass = [
                                        'active' => 'badge-success',
                                        'inactive' => 'badge-secondary',
                                        'suspended' => 'badge-danger',
                                        'pending' => 'badge-warning'
                                    ];
                                    $badgeClass = $statusClass[$status] ?? 'badge-secondary';
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($member['member_id'] ?? 'N/A'); ?></strong></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #0B1D4A, #D4AF37); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">
                                                    <?php echo strtoupper(substr($member['full_name'] ?? '?', 0, 1)); ?>
                                                </div>
                                                <span style="font-weight: 500;"><?php echo htmlspecialchars($member['full_name'] ?? 'N/A'); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo ucfirst($member['membership_type'] ?? 'regular'); ?></td>
                                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span></td>
                                        <td><?php echo $member['last_login'] ? date('M j, Y g:i A', strtotime($member['last_login'])) : 'Never'; ?></td>
                                        <td>
                                            <a href="profile.php?member_id=<?php echo urlencode($member['id']); ?>" class="btn btn-sm btn-outline" title="View Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleSchool(schoolName) {
    const id = 'school-' + md5(schoolName);
    const arrow = document.getElementById('arrow-' + md5(schoolName));
    const content = document.getElementById(id);
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        arrow.style.transform = 'rotate(0deg)';
    } else {
        content.style.display = 'none';
        arrow.style.transform = 'rotate(-90deg)';
    }
}

// Simple md5 function for unique IDs
function md5(string) {
    function rotateLeft(lValue, iShiftBits) {
        return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
    }
    function addUnsigned(lX, lY) {
        const lX8 = lX & 0x80000000, lY8 = lY & 0x80000000;
        const lX4 = lX & 0x40000000, lY4 = lY & 0x40000000;
        const lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
        if (lX4 & lY4) return lResult ^ 0x80000000 ^ lX8 ^ lY8;
        if (lX4 | lY4) {
            if (lResult & 0x40000000) return lResult ^ 0xC0000000 ^ lX8 ^ lY8;
            return lResult ^ 0x40000000 ^ lX8 ^ lY8;
        }
        return lResult ^ lX8 ^ lY8;
    }
    function f(x, y, z) { return (x & y) | ((~x) & z); }
    function g(x, y, z) { return (x & z) | (y & (~z)); }
    function h(x, y, z) { return x ^ y ^ z; }
    function i(x, y, z) { return y ^ (x | (~z)); }
    function ff(a, b, c, d, x, s, ac) {
        a = addUnsigned(a, addUnsigned(addUnsigned(f(b, c, d), x), ac));
        return addUnsigned(rotateLeft(a, s), b);
    }
    function gg(a, b, c, d, x, s, ac) {
        a = addUnsigned(a, addUnsigned(addUnsigned(g(b, c, d), x), ac));
        return addUnsigned(rotateLeft(a, s), b);
    }
    function hh(a, b, c, d, x, s, ac) {
        a = addUnsigned(a, addUnsigned(addUnsigned(h(b, c, d), x), ac));
        return addUnsigned(rotateLeft(a, s), b);
    }
    function ii(a, b, c, d, x, s, ac) {
        a = addUnsigned(a, addUnsigned(addUnsigned(i(b, c, d), x), ac));
        return addUnsigned(rotateLeft(a, s), b);
    }
    function convertToWordArray(string) {
        let lWordCount, lMessageLength = string.length;
        const lNumberOfWordsTemp1 = lMessageLength + 8;
        const lNumberOfWordsTemp2 = (lNumberOfWordsTemp1 - (lNumberOfWordsTemp1 % 64)) / 64;
        const lNumberOfWords = (lNumberOfWordsTemp2 + 1) * 16;
        const lWordArray = new Array(lNumberOfWords - 1);
        let lBytePosition = 0, lByteCount = 0;
        while (lByteCount < lMessageLength) {
            lWordCount = (lByteCount - (lByteCount % 4)) / 4;
            lBytePosition = (lByteCount % 4) * 8;
            lWordArray[lWordCount] = lWordArray[lWordCount] | (string.charCodeAt(lByteCount) << lBytePosition);
            lByteCount++;
        }
        lWordCount = (lByteCount - (lByteCount % 4)) / 4;
        lBytePosition = (lByteCount % 4) * 8;
        lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
        lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
        lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
        return lWordArray;
    }
    function wordToHex(lValue) {
        let wordToHexValue = "", wordToHexValue_temp = "", lByte;
        for (let lCount = 0; lCount <= 3; lCount++) {
            lByte = (lValue >>> (lCount * 8)) & 255;
            wordToHexValue_temp = "0" + lByte.toString(16);
            wordToHexValue = wordToHexValue + wordToHexValue_temp.substr(wordToHexValue_temp.length - 2, 2);
        }
        return wordToHexValue;
    }
    let x = convertToWordArray(string);
    let a = 0x67452301, b = 0xEFCDAB89, c = 0x98BADCFE, d = 0x10325476;
    let S11 = 7, S12 = 12, S13 = 17, S14 = 22;
    let S21 = 5, S22 = 9, S23 = 14, S24 = 20;
    let S31 = 4, S32 = 11, S33 = 16, S34 = 23;
    let S41 = 6, S42 = 10, S43 = 15, S44 = 23;
    for (let k = 0; k < x.length; k += 16) {
        let AA = a, BB = b, CC = c, DD = d;
        a = ff(a, b, c, d, x[k + 0], S11, 0xD76AA478);
        d = ff(d, a, b, c, x[k + 1], S12, 0xE8C7B756);
        c = ff(c, d, a, b, x[k + 2], S13, 0x242070DB);
        b = ff(b, c, d, a, x[k + 3], S14, 0xC1BDCEEE);
        a = ff(a, b, c, d, x[k + 4], S11, 0xF57C0FAF);
        d = ff(d, a, b, c, x[k + 5], S12, 0x4787C62A);
        c = ff(c, d, a, b, x[k + 6], S13, 0xA8304613);
        b = ff(b, c, d, a, x[k + 7], S14, 0xFD469501);
        a = ff(a, b, c, d, x[k + 8], S11, 0x698098D8);
        d = ff(d, a, b, c, x[k + 9], S12, 0x8B44F7AF);
        c = ff(c, d, a, b, x[k + 10], S13, 0xFFFF5BB1);
        b = ff(b, c, d, a, x[k + 11], S14, 0x895CD7BE);
        a = ff(a, b, c, d, x[k + 12], S11, 0x6B901122);
        d = ff(d, a, b, c, x[k + 13], S12, 0xFD987193);
        c = ff(c, d, a, b, x[k + 14], S13, 0xA679438E);
        b = ff(b, c, d, a, x[k + 15], S14, 0x49B40821);
        a = gg(a, b, c, d, x[k + 1], S21, 0xF61E2562);
        d = gg(d, a, b, c, x[k + 6], S22, 0xC040B340);
        c = gg(c, d, a, b, x[k + 11], S23, 0x265E5A51);
        b = gg(b, c, d, a, x[k + 0], S24, 0xE9B6C7AA);
        a = gg(a, b, c, d, x[k + 5], S21, 0xD62F105D);
        d = gg(d, a, b, c, x[k + 10], S22, 0x2441453);
        c = gg(c, d, a, b, x[k + 15], S23, 0xD8A1E681);
        b = gg(b, c, d, a, x[k + 4], S24, 0xE7D3FBC8);
        a = gg(a, b, c, d, x[k + 9], S21, 0x21E1CDE6);
        d = gg(d, a, b, c, x[k + 14], S22, 0xC33707D6);
        c = gg(c, d, a, b, x[k + 3], S23, 0xF4D50D87);
        b = ff(b, c, d, a, x[k + 8], S24, 0x455A14ED);
        a = gg(a, b, c, d, x[k + 13], S21, 0xA9E3E905);
        d = gg(d, a, b, c, x[k + 2], S22, 0xD2F6F937);
        c = gg(c, d, a, b, x[k + 7], S23, 0x728C3D7D);
        b = gg(b, c, d, a, x[k + 12], S24, 0x81FC2CA6);
        a = hh(a, b, c, d, x[k + 5], S23, 0xFDE5380C);
        d = hh(d, a, b, c, x[k + 8], S24, 0xA4BEEA44);
        c = hh(c, d, a, b, x[k + 1], S33, 0x4BDECFA9);
        b = hh(b, c, d, a, x[k + 14], S34, 0xF6BB4B60);
        a = hh(a, b, c, d, x[k + 7], S22, 0xBEBFBC70);
        d = hh(d, a, b, c, x[k + 10], S23, 0x0B7D7CEE);
        c = hh(c, d, a, b, x[k + 13], S33, 0x9D8A2B83);
        b = hh(b, c, d, a, x[k + 0], S34, 0x0D2DAD97);
        a = hh(a, b, c, x[k + 1], S11, 0x57E5A2A3);
        b = hh(b, c, d, x[k + 2], S12, 0x36159D22);
        c = hh(c, d, a, x[k + 3], S13, 0x5F1E32A8);
        d = hh(d, a, b, x[k + 4], S14, 0xAB9423A7);
        a = hh(a, b, c, d, x[k + 9], S11, 0xFC6A8D90);
        d = hh(d, a, b, c, x[k + 14], S12, 0x8BCC7A66);
        c = hh(c, d, a, x[k + 3], S33, 0x7A6D76E9);
        b = hh(b, c, d, x[k + 6], S34, 0xFD6A2D96);
        a = hh(a, b, c, d, x[k + 9], S21, 0x5B428C5);
        d = hh(h, a, b, c, x[k + 12], S22, 0x6B27D7F);
        c = hh(c, d, a, b, x[k + 15], S23, 0xCA4D6D96);
        b = hh(b, c, d, a, x[k + 8], S14, 0xCD9D4C7);
        a = hh(a, b, c, d, x[k + 11], S21, 0xEA6983E);
        d = hh(d, a, b, c, x[k + 14], S22, 0x8A1C60D);
        c = hh(c, d, a, b, x[k + 1], S23, 0xDDD1839);
        b = hh(b, c, d, a, x[k + 4], S24, 0xCD9D4C7);
        a = ii(a, b, c, d, x[k + 13], S41, 0x654BE30);
        d = ii(d, a, b, c, x[k + 0], S42, 0x788E097E);
        c = ii(c, d, a, b, x[k + 5], S43, 0x6B901122);
        b = ii(b, c, d, a, x[k + 6], S44, 0xFD6A2D96);
        a = ii(a, b, c, d, x[k + 9], S41, 0xEDBD883D);
        d = ii(d, a, b, c, x[k + 10], S42, 0xCDDDA939);
        c = ii(c, d, a, b, x[k + 3], S43, 0x0D2DAD97);
        b = ii(b, c, d, a, x[k + 12], S44, 0x455A14ED);
        a = addUnsigned(a, AA); b = addUnsigned(b, BB); c = addUnsigned(c, CC); d = addUnsigned(d, DD);
    }
    let temp = wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d);
    return temp.toLowerCase();
}
</script>
