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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $department = $_POST['department'] ?? '';
    $position = $_POST['position'] ?? '';
    $hire_date = $_POST['hire_date'] ?? '';
    
    $errors = [];
    
    // Validate required fields
    if (empty($full_name)) {
        $errors['full_name'] = "Full name is required";
    }
    
    if (empty($department)) {
        $errors['department'] = "Department is required";
    }
    
    if (empty($position)) {
        $errors['position'] = "Position is required";
    }
    
    if (empty($hire_date)) {
        $errors['hire_date'] = "Hire date is required";
    } elseif (strtotime($hire_date) > time()) {
        $errors['hire_date'] = "Hire date cannot be in the future";
    }

    if (empty($errors)) {
        try {
            // Insert employee record
            $employee_data = [
                'temp_name' => $full_name,
                'department' => $department,
                'position' => $position,
                'hire_date' => $hire_date,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user_id']
            ];
            
            // Insert employee record
            $employee_id = insert('employee_details', $employee_data);
            
            // Set success message and redirect
            $_SESSION['success'] = "Employee added successfully! You can now create their account from the employee list.";
            header("Location: index.php");
            exit();
            
        } catch (Exception $e) {
            $errors['general'] = "Failed to add employee: " . $e->getMessage();
            error_log("Add employee error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Add Employee</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
                            <h4 class="mb-0">Add New Employee</h4>
                        </div>
                        
                        <div class="card-body">
                            <?php if (!empty($errors['general'])): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($errors['general']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                           id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                                    <?php if (isset($errors['full_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <input type="text" class="form-control <?php echo isset($errors['department']) ? 'is-invalid' : ''; ?>" 
                                               id="department" name="department" value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>">
                                        <?php if (isset($errors['department'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['department']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="position" class="form-label">Position</label>
                                        <input type="text" class="form-control <?php echo isset($errors['position']) ? 'is-invalid' : ''; ?>" 
                                               id="position" name="position" value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>">
                                        <?php if (isset($errors['position'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['position']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hire_date" class="form-label">Hire Date</label>
                                    <input type="date" class="form-control <?php echo isset($errors['hire_date']) ? 'is-invalid' : ''; ?>" 
                                           id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($_POST['hire_date'] ?? ''); ?>">
                                    <?php if (isset($errors['hire_date'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['hire_date']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        Add Employee
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>