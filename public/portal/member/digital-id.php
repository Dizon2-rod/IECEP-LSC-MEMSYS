<?php
require_once '../../../includes/auth_check.php';
require_role(['member']);
require_once '../../../includes/dashboard-layout.php';
require_once '../../../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$supabase = new App\Lib\SupabaseClient();
$userId = $_SESSION['user_id'];

// Fetch member data
$member = $supabase->from('members')
    ->select('*, institutions(name)')
    ->eq('user_id', $userId)
    ->single();

if (!$member['data']) {
    die('Member data not found');
}

$data = $member['data'];
$institution = $data['institutions'];

// Fetch blockchain hash for QR
$blockchain = $supabase->from('blockchain_records')
    ->select('data_hash')
    ->eq('record_type', 'digital_id')
    ->eq('reference_id', $data['id'])
    ->order('created_at', 'desc')
    ->limit(1)
    ->single();

$qrData = $blockchain['data']['data_hash'] ?? 'N/A';

// Generate QR code
$qrCode = QrCode::create($qrData);
$writer = new PngWriter();
$result = $writer->write($qrCode);
$qrDataUri = $result->getDataUri();

dashboard_layout('Digital Membership ID', function() use ($data, $institution, $qrDataUri) {
?>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Digital Membership ID</h1>

    <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
        <div class="text-center mb-6">
            <h2 class="text-xl font-semibold">IECEP Laguna Student Chapter</h2>
            <p class="text-gray-600">Membership Card</p>
        </div>

        <div class="flex items-center mb-4">
            <div class="w-16 h-16 bg-gray-300 rounded-full mr-4"></div> <!-- Placeholder for photo -->
            <div>
                <h3 class="font-semibold"><?php echo htmlspecialchars($data['full_name']); ?></h3>
                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($institution['name']); ?></p>
                <p class="text-sm text-gray-600">Member Type: <?php echo ucfirst($data['member_type'] ?? 'New'); ?></p>
            </div>
        </div>

        <div class="text-center">
            <img src="<?php echo $qrDataUri; ?>" alt="QR Code" class="mx-auto mb-4">
            <p class="text-xs text-gray-500">Scan to verify membership</p>
        </div>

        <div class="mt-6 text-center">
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Print Card
            </button>
        </div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden; }
    .container, .container * { visibility: visible; }
    .container { position: absolute; left: 0; top: 0; }
}
</style>
<?php
});
?>