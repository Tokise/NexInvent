<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!hasPermission('manage_employees')) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to delete employees.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['employee_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit();
}

$employee_id = intval($_POST['employee_id']);

try {
    $pdo = getDBConnection();
    // Optionally, check for related user account and handle accordingly
    $stmt = $pdo->prepare('DELETE FROM employee_details WHERE employee_id = ?');
    $stmt->execute([$employee_id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Employee not found or could not be deleted.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error deleting employee: ' . $e->getMessage()]);
} 