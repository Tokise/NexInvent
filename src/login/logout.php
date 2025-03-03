<?php
session_start();

// Store role before clearing session
$was_customer = isset($_SESSION['role']) && $_SESSION['role'] === 'customer';

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Redirect to appropriate login page
if ($was_customer) {
    header("Location: ../login/index.php");
} else {
    header("Location: index.php");
}
exit();
?> 