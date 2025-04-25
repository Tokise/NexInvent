<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Fetch user data
$user = fetchRow("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = false;
    
    // Get form data
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    } elseif ($email !== $user['email']) {
        // Check if email exists for another user
        $exists = fetchValue("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?", 
                           [$email, $_SESSION['user_id']]);
        if ($exists) {
            $errors['email'] = "Email already exists";
        }
    }
    
    // Validate full name
    if (empty($full_name)) {
        $errors['full_name'] = "Full name is required";
    }
    
    // Handle password change if requested
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors['current_password'] = "Current password is required to change password";
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors['current_password'] = "Current password is incorrect";
        }
        
        if (empty($new_password)) {
            $errors['new_password'] = "New password is required";
        } elseif (strlen($new_password) < 6) {
            $errors['new_password'] = "Password must be at least 6 characters";
        }
        
        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match";
        }
    }
    
    // If no errors, update user data
    if (empty($errors)) {
        try {
            $data = [
                'email' => $email,
                'full_name' => $full_name,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Add new password to data if changing password
            if (!empty($new_password)) {
                $data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            // Update user data
            update('users', $data, 'user_id = ?', [$_SESSION['user_id']]);
            
            // Update session data
            $_SESSION['full_name'] = $full_name;
            
            $success = "Account settings updated successfully!";
            
            // Refresh user data
            $user = fetchRow("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
            
        } catch (Exception $e) {
            error_log("Settings update error: " . $e->getMessage());
            $errors['general'] = "Failed to update settings. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - My Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">My Account Settings</h4>
                    </div>
                    
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $errors['general']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                       id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                       id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                <?php if (isset($errors['full_name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <hr>
                            <h5 class="mb-3">Change Password</h5>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                       id="current_password" name="current_password">
                                <?php if (isset($errors['current_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                       id="new_password" name="new_password">
                                <?php if (isset($errors['new_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                       id="confirm_password" name="confirm_password">
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>