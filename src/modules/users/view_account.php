<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

requirePermission('manage_employees');

// Check if user ID is provided
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    die("Invalid user ID");
}

$user_id = (int)$_GET['user_id'];

// Get user details
$sql = "SELECT * FROM users WHERE user_id = ?";
$user = fetchOne($sql, [$user_id]);

if (!$user) {
    die("User not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Account - <?php echo htmlspecialchars($user['username']); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">View Account</h4>
                </div>
                
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($user['full_name']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($user['role']); ?></p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="/stock/src/modules/users/edit_account.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-primary">
                            Edit Account
                        </a>
                        <a href="/stock/src/modules/employees/index.php" class="btn btn-secondary">
                            Back to Employee List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>