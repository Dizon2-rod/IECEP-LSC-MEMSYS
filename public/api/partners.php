<?php
require_once __DIR__ . '/bootstrap.php';
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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

try {
    switch ($action) {
        case 'list_partners':
            // List all partner chapters
            $partners = $supabaseClient->from('partner_chapters')
                ->select('*', ['count' => 'exact'])
                ->order('name', ['ascending' => true])
                ->execute();

            echo json_encode([
                'success' => true,
                'partners' => $partners,
                'total' => $partners['count'] ?? 0
            ]);
            break;

        case 'get_partner':
            // Get single partner details
            $partnerId = $_GET['id'] ?? '';
            if (empty($partnerId)) {
                throw new Exception('Partner ID required');
            }

            $partner = $supabaseClient->from('partner_chapters')
                ->select('*')
                ->eq('id', $partnerId)
                ->single()
                ->execute();

            if (!$partner) {
                throw new Exception('Partner not found');
            }

            echo json_encode(['success' => true, 'partner' => $partner]);
            break;

        case 'create_partner':
            // Create new partner chapter
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            // Check permissions - only admins and president can create partners
            if (!in_array($userRole, ['eb_admin', 'eb_president'])) {
                throw new Exception('Permission denied');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Invalid JSON data');
            }

            // Validate required fields
            if (empty($data['name']) || empty($data['region'])) {
                throw new Exception('Name and region are required');
            }

            $partnerData = [
                'name' => trim($data['name']),
                'region' => trim($data['region']),
                'description' => trim($data['description'] ?? ''),
                'contact_person' => trim($data['contact_person'] ?? ''),
                'contact_email' => trim($data['contact_email'] ?? ''),
                'contact_phone' => trim($data['contact_phone'] ?? ''),
                'website' => trim($data['website'] ?? ''),
                'partnership_type' => $data['partnership_type'] ?? 'academic',
                'status' => $data['status'] ?? 'active',
                'member_count' => intval($data['member_count'] ?? 0),
                'established_year' => intval($data['established_year'] ?? date('Y')),
                'address' => trim($data['address'] ?? ''),
                'social_media' => json_encode($data['social_media'] ?? []),
                'created_by' => $userId,
                'created_at' => date('c'),
                'updated_at' => date('c')
            ];

            $result = $supabaseClient->from('partner_chapters')->insert($partnerData)->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Partner chapter created successfully',
                'partner' => $result[0] ?? null
            ]);
            break;

        case 'update_partner':
            // Update existing partner chapter
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
                throw new Exception('Method not allowed');
            }

            // Check permissions
            if (!in_array($userRole, ['eb_admin', 'eb_president', 'eb_vp_internal'])) {
                throw new Exception('Permission denied');
            }

            $partnerId = $_GET['id'] ?? '';
            if (empty($partnerId)) {
                throw new Exception('Partner ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Invalid JSON data');
            }

            $updateData = [
                'name' => trim($data['name']) ?? null,
                'region' => trim($data['region']) ?? null,
                'description' => trim($data['description']) ?? null,
                'contact_person' => trim($data['contact_person']) ?? null,
                'contact_email' => trim($data['contact_email']) ?? null,
                'contact_phone' => trim($data['contact_phone']) ?? null,
                'website' => trim($data['website']) ?? null,
                'partnership_type' => $data['partnership_type'] ?? null,
                'status' => $data['status'] ?? null,
                'member_count' => isset($data['member_count']) ? intval($data['member_count']) : null,
                'established_year' => isset($data['established_year']) ? intval($data['established_year']) : null,
                'address' => trim($data['address']) ?? null,
                'social_media' => isset($data['social_media']) ? json_encode($data['social_media']) : null,
                'updated_at' => date('c')
            ];

            // Remove null values
            $updateData = array_filter($updateData, function($value) {
                return $value !== null;
            });

            $result = $supabaseClient->from('partner_chapters')
                ->update($updateData)
                ->eq('id', $partnerId)
                ->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Partner chapter updated successfully'
            ]);
            break;

        case 'delete_partner':
            // Delete partner chapter
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                throw new Exception('Method not allowed');
            }

            // Check permissions - only admins can delete
            if (!in_array($userRole, ['eb_admin', 'eb_president'])) {
                throw new Exception('Permission denied');
            }

            $partnerId = $_GET['id'] ?? '';
            if (empty($partnerId)) {
                throw new Exception('Partner ID required');
            }

            $supabaseClient->from('partner_chapters')->delete()->eq('id', $partnerId)->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Partner chapter deleted successfully'
            ]);
            break;

        case 'get_partner_stats':
            // Get partner statistics
            $stats = [];

            // Total partners
            $totalPartners = $supabaseClient->from('partner_chapters')
                ->select('count', ['count' => 'exact'])
                ->execute();
            $stats['total_partners'] = $totalPartners['count'] ?? 0;

            // Partners by region
            $regionStats = $supabaseClient->from('partner_chapters')
                ->select('region, count', ['count' => 'exact'])
                ->execute();

            $stats['partners_by_region'] = [];
            foreach ($regionStats as $stat) {
                $stats['partners_by_region'][$stat['region']] = $stat['count'] ?? 0;
            }

            // Partners by type
            $typeStats = $supabaseClient->from('partner_chapters')
                ->select('partnership_type, count', ['count' => 'exact'])
                ->execute();

            $stats['partners_by_type'] = [];
            foreach ($typeStats as $stat) {
                $stats['partners_by_type'][$stat['partnership_type']] = $stat['count'] ?? 0;
            }

            // Total member count across all partners
            $memberCount = $supabaseClient->from('partner_chapters')
                ->select('member_count')
                ->execute();

            $totalMembers = 0;
            foreach ($memberCount as $partner) {
                $totalMembers += intval($partner['member_count'] ?? 0);
            }
            $stats['total_partner_members'] = $totalMembers;

            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;

        case 'get_regions':
            // Get available regions
            $regions = [
                'NCR' => 'National Capital Region',
                'Region I' => 'Ilocos Region',
                'Region II' => 'Cagayan Valley',
                'Region III' => 'Central Luzon',
                'Region IV-A' => 'CALABARZON',
                'Region IV-B' => 'MIMAROPA',
                'Region V' => 'Bicol Region',
                'Region VI' => 'Western Visayas',
                'Region VII' => 'Central Visayas',
                'Region VIII' => 'Eastern Visayas',
                'Region IX' => 'Zamboanga Peninsula',
                'Region X' => 'Northern Mindanao',
                'Region XI' => 'Davao Region',
                'Region XII' => 'SOCCSKSARGEN',
                'Region XIII' => 'Caraga',
                'BARMM' => 'Bangsamoro Autonomous Region'
            ];

            echo json_encode([
                'success' => true,
                'regions' => $regions
            ]);
            break;

        case 'get_partnership_types':
            // Get available partnership types
            $types = [
                'academic' => 'Academic Institution',
                'industry' => 'Industry Partner',
                'government' => 'Government Agency',
                'ngo' => 'Non-Government Organization',
                'international' => 'International Organization',
                'professional' => 'Professional Organization'
            ];

            echo json_encode([
                'success' => true,
                'partnership_types' => $types
            ]);
            break;

        case 'export_partners':
            // Export partners data
            if (!in_array($userRole, ['eb_admin', 'eb_president', 'eb_vp_internal'])) {
                throw new Exception('Permission denied');
            }

            $partners = $supabaseClient->from('partner_chapters')
                ->select('*')
                ->order('name', ['ascending' => true])
                ->execute();

            // Generate CSV
            $csvData = "Name,Region,Partnership Type,Status,Member Count,Contact Person,Contact Email,Website\n";

            foreach ($partners as $partner) {
                $csvData .= '"' . str_replace('"', '""', $partner['name']) . '",';
                $csvData .= '"' . str_replace('"', '""', $partner['region']) . '",';
                $csvData .= '"' . str_replace('"', '""', $partner['partnership_type']) . '",';
                $csvData .= '"' . str_replace('"', '""', $partner['status']) . '",';
                $csvData .= '"' . ($partner['member_count'] ?? 0) . '",';
                $csvData .= '"' . str_replace('"', '""', $partner['contact_person'] ?? '') . '",';
                $csvData .= '"' . str_replace('"', '""', $partner['contact_email'] ?? '') . '",';
                $csvData .= '"' . str_replace('"', '""', $partner['website'] ?? '') . '"';
                $csvData .= "\n";
            }

            // Set headers for file download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="partner_chapters_' . date('Y-m-d') . '.csv"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $csvData;
            exit;
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
    error_log('Partners API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>