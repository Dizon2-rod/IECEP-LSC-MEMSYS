<?php
/**
 * EMAIL TEMPLATES - Admin API
 * Manage email templates for system notifications
 * Created: May 17, 2026
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

// Verify admin access
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                handleList();
            } elseif ($action === 'get') {
                handleGet();
            } else {
                handleList();
            }
            break;
        case 'POST':
            handleCreate();
            break;
        case 'PUT':
            handleUpdate();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * List all email templates
 */
function handleList() {
    global $supabase;
    
    try {
        $response = $supabase->from('email_templates')
            ->select('*')
            ->order('created_at', 'desc')
            ->execute();
        
        echo json_encode([
            'success' => true,
            'templates' => $response->data ?? []
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get single template
 */
function handleGet() {
    global $supabase;
    
    $template_id = $_GET['id'] ?? '';
    if (!$template_id) {
        http_response_code(400);
        return json_encode(['error' => 'Template ID required']);
    }
    
    try {
        $response = $supabase->from('email_templates')
            ->select('*')
            ->eq('id', $template_id)
            ->single()
            ->execute();
        
        echo json_encode([
            'success' => true,
            'template' => $response->data
        ]);
    } catch (Exception $e) {
        http_response_code(404);
        echo json_encode(['error' => 'Template not found']);
    }
}

/**
 * Create new template
 */
function handleCreate() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data['template_key'] || !$data['subject'] || !$data['html_body']) {
        http_response_code(400);
        return json_encode(['error' => 'Missing required fields']);
    }
    
    try {
        $template = [
            'template_key' => $data['template_key'],
            'subject' => $data['subject'],
            'html_body' => $data['html_body'],
            'text_body' => $data['text_body'] ?? null,
            'variables' => $data['variables'] ?? [],
            'created_by' => $_SESSION['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $response = $supabase->from('email_templates')->insert([$template])->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Template created',
            'template' => $response->data[0] ?? null
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Update template
 */
function handleUpdate() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $template_id = $_GET['id'] ?? '';
    
    if (!$template_id) {
        http_response_code(400);
        return json_encode(['error' => 'Template ID required']);
    }
    
    try {
        $update_data = [
            'subject' => $data['subject'] ?? null,
            'html_body' => $data['html_body'] ?? null,
            'text_body' => $data['text_body'] ?? null,
            'variables' => $data['variables'] ?? null,
            'updated_by' => $_SESSION['user_id'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Remove null values
        $update_data = array_filter($update_data, fn($v) => $v !== null);
        
        $response = $supabase->from('email_templates')
            ->update($update_data)
            ->eq('id', $template_id)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Template updated',
            'template' => $response->data[0] ?? null
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Delete template
 */
function handleDelete() {
    global $supabase;
    
    $template_id = $_GET['id'] ?? '';
    if (!$template_id) {
        http_response_code(400);
        return json_encode(['error' => 'Template ID required']);
    }
    
    try {
        $supabase->from('email_templates')->delete()->eq('id', $template_id)->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Template deleted'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
