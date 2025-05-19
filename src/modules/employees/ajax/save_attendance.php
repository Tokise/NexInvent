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
        $employee_id = $_POST['employee_id'] ?? 0;
        $date = $_POST['date'] ?? '';
        $time_in = $_POST['time_in'] ?? '';
        $time_out = $_POST['time_out'] ?? '';
        $status = $_POST['status'] ?? 'present';
        $notes = $_POST['notes'] ?? '';
        
        // Validate inputs
        if (empty($employee_id) || empty($date)) {
            throw new Exception("Required fields are missing");
        }
        
        // Combine date with time for timestamps
        $time_in_timestamp = !empty($time_in) ? date('Y-m-d H:i:s', strtotime("$date $time_in")) : null;
        $time_out_timestamp = !empty($time_out) ? date('Y-m-d H:i:s', strtotime("$date $time_out")) : null;
        
        // Check if attendance record already exists for this employee and date
        $sql = "SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND date = ?";
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee_id, $date]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            throw new Exception("Attendance record already exists for this employee on this date");
        }
        
        // Insert new attendance record
        $sql = "INSERT INTO attendance (employee_id, date, time_in, time_out, status, notes, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        
        $params = [
            $employee_id,
            $date,
            $time_in_timestamp,
            $time_out_timestamp,
            $status,
            $notes
        ];

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        if (!$result) {
            throw new Exception("Failed to save attendance record");
        }
        
        $_SESSION['success'] = "Attendance record saved successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

header("Location: ../index.php");
exit(); 