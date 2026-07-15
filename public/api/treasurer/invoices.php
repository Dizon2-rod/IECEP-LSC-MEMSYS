<?php
/**
 * Invoice Generation and Tracking API
 * Handles invoice creation, management, and PDF generation
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    $db = $GLOBALS['supabaseClient'] ?? null;
    if (!$db) {
        throw new Exception('Database connection not available');
    }
    
    switch ($method) {
        case 'POST':
            if ($action === 'create') {
                // Create new invoice
                $input = json_decode(file_get_contents('php://input'), true);
                
                $memberId = $input['member_id'] ?? '';
                $amount = $input['amount'] ?? 0;
                $description = $input['description'] ?? '';
                $dueDate = $input['due_date'] ?? '';
                $userId = $_SESSION['user']['id'] ?? '';
                
                if (empty($memberId) || empty($amount)) {
                    throw new Exception('member_id and amount are required');
                }
                
                // Generate invoice number
                $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Check if invoice number already exists
                $existing = $db->select('invoices', [
                    'invoice_number' => 'eq.' . $invoiceNumber
                ]);
                
                while (!empty($existing)) {
                    $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $existing = $db->select('invoices', [
                        'invoice_number' => 'eq.' . $invoiceNumber
                    ]);
                }
                
                $invoiceId = generateUUID();
                
                // Create invoice
                $result = $db->insert('invoices', [
                    'id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'member_id' => $memberId,
                    'amount' => $amount,
                    'description' => $description,
                    'issue_date' => date('Y-m-d'),
                    'due_date' => $dueDate ?: date('Y-m-d', strtotime('+30 days')),
                    'status' => 'draft',
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                echo json_encode([
                    'success' => true,
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'message' => 'Invoice created successfully'
                ]);
                
            } elseif ($action === 'send') {
                // Send invoice to member
                $input = json_decode(file_get_contents('php://input'), true);
                
                $invoiceId = $input['invoice_id'] ?? '';
                
                if (empty($invoiceId)) {
                    throw new Exception('invoice_id is required');
                }
                
                // Update invoice status
                $db->update('invoices', [
                    'status' => 'sent'
                ])->eq('id', $invoiceId)->update();
                
                // Get invoice details
                $invoice = $db->select('invoices', [
                    'id' => 'eq.' . $invoiceId
                ]);
                
                if (empty($invoice)) {
                    throw new Exception('Invoice not found');
                }
                
                $invoiceData = $invoice[0];
                
                // Get member details
                $member = $db->select('members', [
                    'id' => 'eq.' . $invoiceData['member_id']
                ]);
                
                if (!empty($member)) {
                    // Send email notification
                    $emailService = new \App\Lib\EmailService();
                    $emailService->sendInvoiceNotification(
                        $member[0]['email'],
                        $invoiceData['invoice_number'],
                        $invoiceData['amount'],
                        $invoiceData['due_date']
                    );
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Invoice sent successfully'
                ]);
                
            } elseif ($action === 'mark-paid') {
                // Mark invoice as paid
                $input = json_decode(file_get_contents('php://input'), true);
                
                $invoiceId = $input['invoice_id'] ?? '';
                $paymentReference = $input['payment_reference'] ?? '';
                
                if (empty($invoiceId)) {
                    throw new Exception('invoice_id is required');
                }
                
                // Update invoice status
                $db->update('invoices', [
                    'status' => 'paid'
                ])->eq('id', $invoiceId)->update();
                
                // Create transaction record
                $invoice = $db->select('invoices', [
                    'id' => 'eq.' . $invoiceId
                ]);
                
                if (!empty($invoice)) {
                    $invoiceData = $invoice[0];
                    $db->insert('transactions', [
                        'id' => generateUUID(),
                        'receipt_number' => 'RCPT-' . date('YmdHis') . rand(100, 999),
                        'member_id' => $invoiceData['member_id'],
                        'amount' => $invoiceData['amount'],
                        'type' => 'membership_fee',
                        'status' => 'paid',
                        'reference_number' => $paymentReference,
                        'transaction_date' => date('Y-m-d H:i:s'),
                        'paid_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Invoice marked as paid'
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'list') {
                // Get invoices
                $status = $_GET['status'] ?? '';
                $memberId = $_GET['member_id'] ?? '';
                
                $filters = [];
                if (!empty($status)) {
                    $filters['status'] = 'eq.' . $status;
                }
                if (!empty($memberId)) {
                    $filters['member_id'] => 'eq.' . $memberId;
                }
                $filters['order'] = 'created_at.desc';
                
                $invoices = $db->select('invoices', $filters);
                
                // Get member names
                foreach ($invoices as &$invoice) {
                    $memberData = $db->select('members', [
                        'id' => 'eq.' . $invoice['member_id'],
                        'select' => 'full_name,email'
                    ]);
                    $invoice['member_name'] = $memberData[0]['full_name'] ?? 'Unknown';
                    $invoice['member_email'] = $memberData[0]['email'] ?? '';
                }
                
                echo json_encode([
                    'success' => true,
                    'invoices' => $invoices
                ]);
                
            } elseif ($action === 'detail') {
                // Get invoice details
                $invoiceId = $_GET['invoice_id'] ?? '';
                
                if (empty($invoiceId)) {
                    throw new Exception('invoice_id parameter is required');
                }
                
                $invoices = $db->select('invoices', [
                    'id' => 'eq.' . $invoiceId
                ]);
                
                if (empty($invoices)) {
                    throw new Exception('Invoice not found');
                }
                
                $invoice = $invoices[0];
                
                // Get member details
                $memberData = $db->select('members', [
                    'id' => 'eq.' . $invoice['member_id']
                ]);
                $invoice['member'] = $memberData[0] ?? null;
                
                echo json_encode([
                    'success' => true,
                    'invoice' => $invoice
                ]);
                
            } elseif ($action === 'generate-pdf') {
                // Generate PDF for invoice
                $invoiceId = $_GET['invoice_id'] ?? '';
                
                if (empty($invoiceId)) {
                    throw new Exception('invoice_id parameter is required');
                }
                
                // Get invoice details
                $invoices = $db->select('invoices', [
                    'id' => 'eq.' . $invoiceId
                ]);
                
                if (empty($invoices)) {
                    throw new Exception('Invoice not found');
                }
                
                $invoice = $invoices[0];
                
                // Get member details
                $memberData = $db->select('members', [
                    'id' => 'eq.' . $invoice['member_id']
                ]);
                $member = $memberData[0] ?? null;
                
                // Generate PDF using DOMPDF
                require_once __DIR__ . '/../../src/lib/pdf.php';
                $pdfService = new \App\Lib\PDFService();
                
                $html = generateInvoiceHTML($invoice, $member);
                $pdfPath = $pdfService->generatePDF($html, 'invoice-' . $invoice['invoice_number']);
                
                // Update invoice with PDF path
                $db->update('invoices', [
                    'pdf_path' => $pdfPath
                ])->eq('id', $invoiceId)->update();
                
                echo json_encode([
                    'success' => true,
                    'pdf_path' => $pdfPath
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function generateInvoiceHTML($invoice, $member) {
    $logoUrl = APP_URL . '/public/assets/icons/iecep-logo.png';
    
    return "
    <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <img src='{$logoUrl}' alt='IECEP-LSC Logo' style='height: 80px;'>
            <h1 style='color: #0B1D4A; margin: 10px 0;'>INVOICE</h1>
        </div>
        
        <div style='display: flex; justify-content: space-between; margin-bottom: 30px;'>
            <div>
                <h3 style='color: #0B1D4A; margin-bottom: 10px;'>Bill To:</h3>
                <p style='margin: 5px 0;'><strong>{$member['full_name']}</strong></p>
                <p style='margin: 5px 0;'>{$member['email']}</p>
            </div>
            <div style='text-align: right;'>
                <p style='margin: 5px 0;'><strong>Invoice Number:</strong> {$invoice['invoice_number']}</p>
                <p style='margin: 5px 0;'><strong>Issue Date:</strong> {$invoice['issue_date']}</p>
                <p style='margin: 5px 0;'><strong>Due Date:</strong> {$invoice['due_date']}</p>
            </div>
        </div>
        
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 30px;'>
            <thead>
                <tr style='background-color: #0B1D4A; color: white;'>
                    <th style='padding: 12px; text-align: left;'>Description</th>
                    <th style='padding: 12px; text-align: right;'>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style='padding: 12px; border-bottom: 1px solid #ddd;'>{$invoice['description']}</td>
                    <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>₱{$invoice['amount']}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style='background-color: #f8f9fa;'>
                    <td style='padding: 12px; font-weight: bold;'>Total</td>
                    <td style='padding: 12px; font-weight: bold; text-align: right;'>₱{$invoice['amount']}</td>
                </tr>
            </tfoot>
        </table>
        
        <div style='text-align: center; color: #6c757d; font-size: 12px; margin-top: 40px;'>
            <p>© 2026 IECEP-LSC MEMSYS – All rights reserved</p>
            <p>Institute of Electronics Engineers of the Philippines – Laguna State Chapter</p>
        </div>
    </div>";
}
