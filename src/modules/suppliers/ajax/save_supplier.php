<?php
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';
session_start();

ob_clean();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    if (!hasPermission('manage_suppliers')) {
        throw new Exception('Permission denied');
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    try {
        // Clean and validate input data
        $data = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'contact_name' => trim($_POST['contact_name'] ?? ''),
            'email' => strtolower(trim($_POST['email'] ?? '')),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? '')
        ];

        // Validate required fields
        foreach ($data as $key => $value) {
            if ($key != 'address' && empty($value)) {
                throw new Exception("Field '$key' is required");
            }
        }

        // Check for duplicates using prepared statement
        $stmt = $pdo->prepare("
            SELECT supplier_id, company_name, email 
            FROM suppliers 
            WHERE LOWER(email) = LOWER(?) 
            OR LOWER(company_name) = LOWER(?)
            LIMIT 1
        ");
        
        $stmt->execute([$data['email'], $data['company_name']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (strtolower($existing['company_name']) === strtolower($data['company_name'])) {
                throw new Exception("A supplier with this company name already exists");
            }
            if (strtolower($existing['email']) === strtolower($data['email'])) {
                throw new Exception("A supplier with this email already exists");
            }
        }

        // Insert the new supplier
        $stmt = $pdo->prepare("
            INSERT INTO suppliers (company_name, contact_name, email, phone, address)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['company_name'],
            $data['contact_name'],
            $data['email'],
            $data['phone'],
            $data['address']
        ]);

        $supplier_id = $pdo->lastInsertId();

        // Verify the insertion
        $stmt = $pdo->prepare("
            SELECT 
                s.supplier_id,
                s.company_name,
                s.contact_name,
                s.email,
                s.phone,
                s.address,
                s.created_at,
                COUNT(po.po_id) as total_orders,
                COALESCE(AVG(NULLIF(po.total_amount, 0)), 0) as avg_order_amount,
                MAX(po.created_at) as last_order_date,
                COUNT(CASE WHEN po.status = 'received' THEN 1 END) as completed_orders
            FROM suppliers s
            LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
            WHERE s.supplier_id = ?
            GROUP BY 
                s.supplier_id,
                s.company_name,
                s.contact_name,
                s.email,
                s.phone,
                s.address,
                s.created_at
        ");
        
        $stmt->execute([$supplier_id]);
        $newSupplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$newSupplier) {
            throw new Exception("Failed to verify supplier creation");
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Supplier added successfully',
            'supplier' => $newSupplier
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;
