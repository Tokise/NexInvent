<?php
// Prevent any output before JSON response
ob_start();

require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';
session_start();

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Clear any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

try {
    if (!hasPermission('manage_suppliers')) {
        throw new Exception('Permission denied');
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Execute reset operations
    try {
        // Disable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Reset tables in order
        $tables = [
            'supplier_ratings',
            'po_items',
            'purchase_orders',
            'suppliers'
        ];

        foreach ($tables as $table) {
            $pdo->exec("DELETE FROM {$table}");
            $pdo->exec("ALTER TABLE {$table} AUTO_INCREMENT = 1");
        }

        // Re-enable foreign key checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'All suppliers have been reset successfully'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Make sure nothing else is output
exit;
