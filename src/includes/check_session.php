<?php
// Start session
session_start();

// Set the response header to JSON
header('Content-Type: application/json');

// Check if user is logged in with a valid session
$valid = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && 
         isset($_SESSION['security_token']) && !empty($_SESSION['security_token']);

// Return JSON response
echo json_encode(['valid' => $valid]);
?> 