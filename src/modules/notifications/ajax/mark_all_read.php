<?php
// Require authentication
require_once '../../../../includes/require_auth.php';
require_once '../../../../config/db.php';

// Set headers
header('Content-Type: application/json');

// Get user role
$userRole = $_SESSION['role'] ?? '';

// Verify the role parameter matches the session
if (isset($_POST['role']) && $_POST['role'] === $userRole) {
    // Mark all notifications as read for this role
    $conn = getDBConnection();
    $query = "UPDATE notifications SET is_read = 1 WHERE for_role = ? OR for_role = 'admin'";
    $stmt = $conn->prepare($query);
    $result = $stmt->execute([$userRole]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid role parameter']);
} 