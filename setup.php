<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/config/db.php';

try {
    $conn = getDBConnection();
    
    // Initialize admin account with proper password hash
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, email, full_name, role, status) 
        VALUES ('admin', ?, 'admin@nexinvent.local', 'System Administrator', 'admin', 'active')
        ON DUPLICATE KEY UPDATE 
        password = ?, 
        status = 'active'
    ");
    $stmt->execute([$adminPassword, $adminPassword]);

    // Initialize manager account
    $managerPassword = password_hash('manager123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, email, full_name, role, status)
        VALUES ('manager', ?, 'manager@nexinvent.local', 'Test Manager', 'manager', 'active')
        ON DUPLICATE KEY UPDATE 
        password = ?,
        status = 'active'
    ");
    $stmt->execute([$managerPassword, $managerPassword]);

    // Initialize employee account
    $employeePassword = password_hash('employee123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, email, full_name, role, status)
        VALUES ('employee', ?, 'employee@nexinvent.local', 'Test Employee', 'employee', 'active')
        ON DUPLICATE KEY UPDATE 
        password = ?,
        status = 'active'
    ");
    $stmt->execute([$employeePassword, $employeePassword]);
   
    $success = true;
    $message = "Setup completed successfully! You can now log in with the provided credentials.";

} catch (Exception $e) {
    $success = false;
    $message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --success-color: #10b981;
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

        .setup-container {
            width: 100%;
            max-width: 600px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            padding: 2.5rem;
        }

        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .setup-header img {
            width: 180px;
            margin-bottom: 1.5rem;
        }

        .setup-header h1 {
            font-family: var(--font-primary);
            font-weight: 600;
            color: #1f2937;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .setup-step {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .step-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .step-title {
            font-family: var(--font-primary);
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .success-icon {
            color: var(--success-color);
            font-size: 1.25rem;
        }

        .credential-box {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin: 0.5rem 0;
            border: 1px solid #e5e7eb;
        }

        .credential-box h3 {
            font-family: var(--font-primary);
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0.5rem 0;
            font-family: var(--font-secondary);
        }

        .btn-primary {
            font-family: var(--font-primary);
            font-weight: 600;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            background: linear-gradient(145deg, var(--primary-color), var(--primary-dark));
            border: none;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.2);
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <img src="assets/LOGO.png" alt="NexInvent Logo">
            <h1>System Setup</h1>
            <p>Initialize your NexInvent Inventory Management System</p>
        </div>

        <div class="setup-step">
            <div class="step-header">
                <div class="step-icon">
                    <i class="bi bi-database"></i>
                </div>
                <h2 class="step-title">Database Connection</h2>
            </div>
            <div class="step-content">
                <p class="mb-2">
                    <i class="bi bi-check-circle-fill success-icon"></i>
                    Database connection successful
                </p>
            </div>
        </div>

        <div class="setup-step">
            <div class="step-header">
                <div class="step-icon">
                    <i class="bi bi-person-plus"></i>
                </div>
                <h2 class="step-title">User Accounts</h2>
            </div>
            <div class="step-content">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        User accounts initialized successfully
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="credential-box">
                    <h3>Administrator Account</h3>
                    <div class="credential-item">
                        <span>Username:</span>
                        <strong>admin</strong>
                    </div>
                    <div class="credential-item">
                        <span>Password:</span>
                        <strong>admin123</strong>
                    </div>
                    <div class="credential-item">
                        <span>Status:</span>
                        <strong class="text-success">Active</strong>
                    </div>
                </div>

                <div class="credential-box">
                    <h3>Manager Account</h3>
                    <div class="credential-item">
                        <span>Username:</span>
                        <strong>manager</strong>
                    </div>
                    <div class="credential-item">
                        <span>Password:</span>
                        <strong>manager123</strong>
                    </div>
                    <div class="credential-item">
                        <span>Status:</span>
                        <strong class="text-success">Active</strong>
                    </div>
                </div>

                <div class="credential-box">
                    <h3>Employee Account</h3>
                    <div class="credential-item">
                        <span>Username:</span>
                        <strong>employee</strong>
                    </div>
                    <div class="credential-item">
                        <span>Password:</span>
                        <strong>employee123</strong>
                    </div>
                    <div class="credential-item">
                        <span>Status:</span>
                        <strong class="text-success">Active</strong>
                    </div>
                </div>

                <a href="/NexInvent/src/login/index.php" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Proceed to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>