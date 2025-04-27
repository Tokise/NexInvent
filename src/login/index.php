<?php
// Start session first
session_start();

// If user is already logged in, redirect to dashboard
// This must be done before any output or headers
if (isset($_SESSION['user_id'])) {
    // Set no-cache headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Immediately redirect to dashboard
    header("Location: ../modules/dashboard/index.php");
    
    // Send special Javascript-based redirect that removes this page from history
    echo '<!DOCTYPE html><html><head>
    <script>
    // Use replace state to completely remove this page from history
    window.location.replace("../modules/dashboard/index.php");
    </script>
    <meta http-equiv="refresh" content="0;url=../modules/dashboard/index.php">
    </head><body></body></html>';
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

// Check if there's a session expiration message
if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $error = "Your session has expired. Please log in again.";
}

// Check if there's a security issue message
if (isset($_GET['security']) && $_GET['security'] == 1) {
    $error = "Your session has been terminated for security reasons. Please log in again.";
}

// Check if there's a success message from registration
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        // Get user by username
        $sql = "SELECT * FROM users WHERE username = ?";
        $user = fetchRow($sql, [$username]);
        
        if (!$user) {
            $error = "Invalid username or password";
        } elseif ($user['status'] !== 'active') {
            $error = "Your account is not active. Please contact support.";
        } elseif (!password_verify($password, $user['password'])) {
            error_log("Login failed for user $username - Password verification failed");
            error_log("Provided password hash: " . password_hash($password, PASSWORD_DEFAULT));
            error_log("Stored password hash: " . $user['password']);
            $error = "Invalid username or password";
        } else {
            // Start transaction
            $conn = getDBConnection();
            $conn->beginTransaction();

            try {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Generate and store security token
                $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                $ip_segments = explode('.', $_SERVER['REMOTE_ADDR']);
                $ip_partial = '';
                
                // Use first 3 segments of IP for dynamic IP environments
                if (count($ip_segments) >= 3) {
                    $ip_partial = $ip_segments[0] . '.' . $ip_segments[1] . '.' . $ip_segments[2];
                }
                
                $_SESSION['security_token'] = hash('sha256', $user_agent . $ip_partial . $user['user_id']);
                
                // Set last activity timestamp
                $_SESSION['last_activity'] = time();
                
                if ($user['role'] === 'customer') {
                    // Check if customer record exists by email
                    $customer = fetchRow("SELECT customer_id FROM customers WHERE email = ?", [$user['email']]);
                    
                    if (!$customer) {
                        // Only create customer record if it doesn't exist
                        $customer_data = [
                            'name' => $user['full_name'],
                            'email' => $user['email'],
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        $customer_id = insert('customers', $customer_data);
                        $_SESSION['customer_id'] = $customer_id;
                    } else {
                        $_SESSION['customer_id'] = $customer['customer_id'];
                    }

                    // Check if customer profile exists
                    $profile = fetchRow("SELECT * FROM customer_profiles WHERE user_id = ?", [$user['user_id']]);
                    if (!$profile) {
                        $profile_data = [
                            'user_id' => $user['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        insert('customer_profiles', $profile_data);
                    }
                }
                
                // Update last login timestamp
                execute("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?", [$user['user_id']]);
                
                // Detect and set currency based on user's IP address
                require_once __DIR__ . '/../includes/settings.php';
                if ($user['role'] === 'admin') {
                    // Only detect currency for admins - they can change it in settings
                    autoUpdateCurrencyFromIP();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Clear any existing error messages
                unset($error);
                
                // After successful login, add JavaScript to clear history
                echo "<script>
                    // Clear browser history to prevent back button to login
                    if (window.history && window.history.pushState) {
                        window.history.pushState(null, null, window.location.href);
                        window.onpopstate = function () {
                            window.history.pushState(null, null, window.location.href);
                        };
                    }
                </script>";
                
                // Redirect based on stored redirect URL or role
                if (isset($_SESSION['redirect_url'])) {
                    $redirect_url = $_SESSION['redirect_url'];
                    unset($_SESSION['redirect_url']);
                    header("Location: " . $redirect_url);
                } else {
                    if ($user['role'] === 'customer') {
                        header("Location: ../modules/customer/index.php");
                    } else {
                        header("Location: ../modules/dashboard/index.php");
                    }
                }
                exit();
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Login error: " . $e->getMessage());
                $error = "An error occurred during login. Please try again.";
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
}

// Add script to prevent login page from being in history
echo "<script>
    // Add event to disable back button to login page
    window.addEventListener('load', function() {
        window.history.pushState(null, document.title, window.location.href);
        window.addEventListener('popstate', function(event) {
            window.history.pushState(null, document.title, window.location.href);
        });
    });
</script>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NexInvent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --font-primary: 'Poppins', sans-serif;
            --font-secondary: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f6f7ff 0%, #eef1ff 100%);
            font-family: var(--font-secondary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            border-radius: 24px;
            padding: 2.5rem;
            transition: transform 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .logo-container img {
            width: 200px;
            height: auto;
            margin-bottom: 2.5rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
           
        }

        .form-label {
            font-family: var(--font-primary);
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control {
            font-family: var(--font-secondary);
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .input-group-text {
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            background-color: #f9fafb;
            color: #6b7280;
        }

        .btn-primary {
            font-family: var(--font-primary);
            font-weight: 600;
            padding: 0.875rem;
            border-radius: 12px;
            background: linear-gradient(145deg, var(--primary-color), var(--primary-dark));
            border: none;
            letter-spacing: 0.025em;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.2);
        }

        .alert {
            border-radius: 12px;
            font-family: var(--font-secondary);
            font-size: 0.95rem;
            border: none;
        }

        a {
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        a:hover {
            color: var(--primary-dark);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="../../assets/LOGO.png" alt="NexInvent Logo">
        </div>
      
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Sign In</button>
        </form>
        <div class="text-center mt-3">
            <p class="mb-0">Don't have an account? <a href="../register/index.php">Register here</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>