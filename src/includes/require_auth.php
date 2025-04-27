<?php
// Start or resume session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Not logged in - use aggressive cache control to prevent back button access after logout
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    
    // Save the requested URL for redirect after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: /NexInvent/src/login/index.php");
    exit();
} else {
    // User is logged in - use less aggressive cache control to allow normal browsing
    // This enables normal back button navigation within the protected area
    header("Cache-Control: private, must-revalidate");
    
    // Check if auth_check.php exists and include it
    if (file_exists(__DIR__ . '/auth_check.php')) {
        require_once __DIR__ . '/auth_check.php';
    }

    // Add a timestamp check to ensure session hasn't expired
    $session_timeout = 30 * 60; // 30 minutes in seconds
    if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] > $session_timeout)) {
        // Session has expired - destroy session and redirect to login
        session_unset();
        session_destroy();
        
        // Redirect to login page with expired message
        header("Location: /NexInvent/src/login/index.php?expired=1");
        exit();
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    // Include settings and helper functions
    require_once __DIR__ . '/settings.php';
    require_once __DIR__ . '/helpers.php';
}
?> 