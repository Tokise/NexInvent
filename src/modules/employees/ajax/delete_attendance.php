<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

requirePermission('manage_employees');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $attendance_id = $_POST['attendance_id'] ?? 0;
        
        // Validate input
        if (empty($attendance_id)) {
            throw new Exception("Attendance ID is required");
        }
        
        // Delete attendance record
        delete('attendance', ['attendance_id' => $attendance_id]);
        
        $_SESSION['success'] = "Attendance record deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

header("Location: ../attendance.php");
exit(); 