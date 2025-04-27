<?php
session_start();

// Add aggressive cache control headers to prevent access to protected pages after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

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

// Use JavaScript to handle history and redirect
echo "<!DOCTYPE html>
<html>
<head>
    <title>Logging Out</title>
    <script>
        // Replace the current history entry with the login page
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, 'Login', '/NexInvent/src/login/index.php');
        }
        
        // Redirect to login page
        window.location.href = '/NexInvent/src/login/index.php';
    </script>
    <meta http-equiv='refresh' content='0;URL=/NexInvent/src/login/index.php'>
</head>
<body>
    <p>Logging out...</p>
    <p>If you are not redirected, <a href='/NexInvent/src/login/index.php'>click here</a>.</p>
</body>
</html>";

// Ensure no further code is executed
exit();
?> 