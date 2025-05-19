<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

requirePermission('manage_employees');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $attendance_id = $_POST['attendance_id'] ?? 0;
        
        if (empty($attendance_id)) {
            throw new Exception("Attendance ID is required");
        }
        
        // Delete attendance record
        $sql = "DELETE FROM attendance WHERE attendance_id = ?";
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$attendance_id]);

        if (!$result) {
            throw new Exception("Failed to delete attendance record");
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Invalid request method']);
exit(); 