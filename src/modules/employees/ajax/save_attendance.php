<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';

// Check if user is logged in and has permission to manage employees
if (!isset($_SESSION['user_id']) || !hasPermission('manage_employees')) {
    $_SESSION['error'] = 'You do not have permission to perform this action.';
    header('Location: ../index.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['employee_id', 'date', 'status'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception('Please fill in all required fields.');
            }
        }

        // Validate employee exists
        $sql = "SELECT employee_id FROM employee_details WHERE employee_id = ?";
        $employee = fetchOne($sql, [$_POST['employee_id']]);
        if (!$employee) {
            throw new Exception('Invalid employee selected.');
        }

        // Validate date format
        if (!strtotime($_POST['date'])) {
            throw new Exception('Invalid date format.');
        }

        // Validate status
        $valid_statuses = ['present', 'absent', 'late'];
        if (!in_array($_POST['status'], $valid_statuses)) {
            throw new Exception('Invalid status selected.');
        }

        // Check for existing attendance record
        $sql = "SELECT attendance_id FROM attendance WHERE employee_id = ? AND date = ?";
        $existing = fetchOne($sql, [$_POST['employee_id'], $_POST['date']]);
        if ($existing) {
            throw new Exception('An attendance record already exists for this employee on the selected date.');
        }

        // Prepare attendance data
        $attendance_data = [
            'employee_id' => $_POST['employee_id'],
            'date' => $_POST['date'],
            'status' => $_POST['status'],
            'notes' => !empty($_POST['notes']) ? $_POST['notes'] : null
        ];

        // Add time_in and time_out if provided and status is not 'absent'
        if ($_POST['status'] !== 'absent') {
            if (!empty($_POST['time_in'])) {
                $attendance_data['time_in'] = date('Y-m-d H:i:s', strtotime($_POST['date'] . ' ' . $_POST['time_in']));
            }
            if (!empty($_POST['time_out'])) {
                $attendance_data['time_out'] = date('Y-m-d H:i:s', strtotime($_POST['date'] . ' ' . $_POST['time_out']));
            }
        }

        // Insert attendance record
        $attendance_id = insert('attendance', $attendance_data);

        $_SESSION['success'] = 'Attendance record has been saved successfully.';
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

header('Location: ../index.php');
exit(); 