<?php
// This file is included by require_auth.php
// Its primary purpose is to do additional security checks

// Add additional security check for session fixation
// We do this by validating user agent and IP address
function validateSession() {
    // Check if we have the security token stored
    if (!isset($_SESSION['security_token'])) {
        return false;
    }
    
    // Create a fingerprint from user agent and partial IP 
    // (partial IP to account for dynamic IPs)
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $ip_segments = explode('.', $_SERVER['REMOTE_ADDR']);
    $ip_partial = '';
    
    // Use first 3 segments of IP for dynamic IP environments
    if (count($ip_segments) >= 3) {
        $ip_partial = $ip_segments[0] . '.' . $ip_segments[1] . '.' . $ip_segments[2];
    }
    
    $current_fingerprint = hash('sha256', $user_agent . $ip_partial . $_SESSION['user_id']);
    
    // Compare with stored token
    return hash_equals($_SESSION['security_token'], $current_fingerprint);
}

// If session validation fails, destroy the session
if (isset($_SESSION['user_id']) && !validateSession()) {
    // Session appears to be hijacked or fixated
    session_unset();
    session_destroy();
    
    // Force new session ID
    session_start();
    
    // Redirect to login with security warning
    header("Location: /NexInvent/src/login/index.php?security=1");
    exit();
}

// The dashboard URL is always the default dashboard
$dashboardUrl = '/NexInvent/src/modules/dashboard/index.php';

// Add aggressive browser history manipulation
echo "
<script>
// Prevent access to login page via back button
(function() {
    // If this is loaded from cache (back button navigation)
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // Force check session status via AJAX
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/NexInvent/src/includes/check_session.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (!response.valid) {
                                // Session invalid, go to login
                                window.location.replace('/NexInvent/src/login/index.php');
                            }
                        } catch(e) {
                            // JSON parse error, assume session invalid
                            window.location.replace('/NexInvent/src/login/index.php');
                        }
                    }
                }
            };
            xhr.send();
        }
    });

    // When page first loads
    document.addEventListener('DOMContentLoaded', function() {
        // Prevent back button to login page when already logged in
        history.pushState(null, document.title, window.location.href);
        
        // If back button is pressed, prevent navigation to login
        window.addEventListener('popstate', function() {
            history.pushState(null, document.title, window.location.href);
        });
    });
})();
</script>
";
?> 