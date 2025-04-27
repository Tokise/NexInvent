<?php
// Require authentication
require_once '../../../../includes/require_auth.php';
require_once '../../../../config/db.php';

// Set headers
header('Content-Type: application/json');

// Check if notification ID is provided
if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit;
}

// Get notification ID and user role
$notificationId = (int)$_POST['notification_id'];
$userRole = $_SESSION['role'] ?? '';

// Check if user has permission to mark this notification as read based on role
$conn = getDBConnection();
$query = "SELECT * FROM notifications WHERE notification_id = ? AND (for_role = ? OR for_role = 'admin')";
$stmt = $conn->prepare($query);
$stmt->execute([$notificationId, $userRole]);
$notification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notification) {
    echo json_encode(['success' => false, 'message' => 'Notification not found or access denied']);
    exit;
}

// Mark notification as read
$query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ?";
$stmt = $conn->prepare($query);
$result = $stmt->execute([$notificationId]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
} 