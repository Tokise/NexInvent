<?php
require_once '../../includes/require_auth.php';

// Add aggressive history protection to prevent back button to login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Store session info in browser storage for history tracking
$session_id = session_id();
$user_id = $_SESSION['user_id'];
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

requirePermission('manage_employees');

// Handle attendance update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_id'])) {
    try {
        $attendance_id = $_POST['attendance_id'];
        $employee_id = $_POST['employee_id'];
        $date = $_POST['date'];
        $time_in = !empty($_POST['time_in']) ? date('Y-m-d H:i:s', strtotime("$date {$_POST['time_in']}")) : null;
        $time_out = !empty($_POST['time_out']) ? date('Y-m-d H:i:s', strtotime("$date {$_POST['time_out']}")) : null;
        $status = $_POST['status'];
        $notes = $_POST['notes'];

        // First check if the attendance record exists
        $check_sql = "SELECT attendance_id FROM attendance WHERE attendance_id = ?";
        $existing = fetchOne($check_sql, [$attendance_id]);
        
        if (!$existing) {
            throw new Exception("Attendance record not found");
        }

        // Update attendance record using direct SQL for better error handling
        $sql = "UPDATE attendance SET 
                employee_id = ?,
                date = ?,
                time_in = ?,
                time_out = ?,
                status = ?,
                notes = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE attendance_id = ?";
        
        $params = [
            $employee_id,
            $date,
            $time_in,
            $time_out,
            $status,
            $notes,
            $attendance_id
        ];

        // Log the update attempt
        error_log("Attempting to update attendance record: " . print_r($params, true));

        $pdo = getDBConnection();
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        if (!$result) {
            error_log("Database error: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Failed to update attendance record");
        }

        $_SESSION['success'] = "Attendance record updated successfully!";
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        error_log("Attendance update error: " . $e->getMessage());
        $_SESSION['error'] = "Error updating attendance: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

// Get all employees
$sql = "SELECT e.*, 
        COALESCE(u.full_name, e.temp_name) as full_name,
        u.email, 
        u.status as user_status, 
        u.role, 
        u.user_id 
        FROM employee_details e 
        LEFT JOIN users u ON e.user_id = u.user_id 
        ORDER BY COALESCE(u.full_name, e.temp_name)";
$employees = fetchAll($sql);

// Get attendance records
$sql = "SELECT a.*, 
        COALESCE(u.full_name, e.temp_name) as employee_name 
        FROM attendance a 
        JOIN employee_details e ON a.employee_id = e.employee_id 
        LEFT JOIN users u ON e.user_id = u.user_id 
        ORDER BY a.date DESC, COALESCE(u.full_name, e.temp_name)";
$attendance_records = fetchAll($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Employee Management</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Add Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        .status-present {
            background-color: #198754;
            color: white;
        }
        .status-absent {
            background-color: #dc3545;
            color: white;
        }
        .status-late {
            background-color: #ffc107;
            color: black;
        }
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
        }
    </style>
   
</head>
<body>

    <?php include '../../includes/header.php'; ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Employee Management</h2>
                <div class="btn-group">
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Add Employee
                    </a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recordAttendanceModal">
                        <i class="bi bi-calendar-check me-2"></i>Record Attendance
                    </button>
                </div>
            </div>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="employeeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button" role="tab">
                        <i class="bi bi-people me-2"></i>Employees
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                        <i class="bi bi-calendar-check me-2"></i>Attendance
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="employeeTabsContent">
                <!-- Employees Tab -->
                <div class="tab-pane fade show active" id="employees" role="tabpanel">
                    <div class="card table-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="employeesTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Department</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $employee): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                                <td><?php echo $employee['email'] ? htmlspecialchars($employee['email']) : '-'; ?></td>
                                                <td>
                                                    <?php if ($employee['user_id']): ?>
                                                        <span class="badge bg-<?php echo $employee['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($employee['user_status']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">No Account</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group action-buttons">
                                                        <?php if (!$employee['user_id']): ?>
                                                            <a href="/NexInvent/src/modules/users/create_staff.php?employee_id=<?php echo $employee['employee_id']; ?>" 
                                                               class="btn btn-sm btn-info" 
                                                               title="Create User Account">
                                                                <i class="bi bi-person-plus"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-danger delete-employee"
                                                                data-id="<?php echo $employee['employee_id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($employee['full_name']); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Tab -->
                <div class="tab-pane fade" id="attendance" role="tabpanel">
                    <div class="card table-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="attendanceTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Employee</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_records as $record): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['employee_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    echo $record['time_in'] 
                                                        ? date('h:i A', strtotime($record['time_in'])) 
                                                        : '-'; 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    echo $record['time_out'] 
                                                        ? date('h:i A', strtotime($record['time_out'])) 
                                                        : '-'; 
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge status-<?php echo strtolower($record['status']); ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-info edit-attendance" 
                                                                data-id="<?php echo $record['attendance_id']; ?>"
                                                                data-employee="<?php echo $record['employee_id']; ?>"
                                                                data-date="<?php echo $record['date']; ?>"
                                                                data-timein="<?php echo $record['time_in'] ? date('H:i', strtotime($record['time_in'])) : ''; ?>"
                                                                data-timeout="<?php echo $record['time_out'] ? date('H:i', strtotime($record['time_out'])) : ''; ?>"
                                                                data-status="<?php echo $record['status']; ?>"
                                                                data-notes="<?php echo htmlspecialchars($record['notes'] ?? ''); ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-attendance"
                                                                data-id="<?php echo $record['attendance_id']; ?>"
                                                                data-employee="<?php echo htmlspecialchars($record['employee_name']); ?>"
                                                                data-date="<?php echo date('M d, Y', strtotime($record['date'])); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include your existing modals here -->
    <?php include 'modals/attendance_modals.php'; ?>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#employeesTable').DataTable({
                order: [[0, 'asc']], // Sort by name ascending
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });
            
            $('#attendanceTable').DataTable({
                order: [[0, 'desc']], // Sort by date descending
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });
            
            // Handle tab changes
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                // Adjust DataTables columns when switching tabs
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            });
            
            // Status change handlers
            $('#status, #edit_status').change(function() {
                const status = $(this).val();
                const prefix = $(this).attr('id').startsWith('edit_') ? 'edit_' : '';
                if (status === 'absent') {
                    $(`#${prefix}time_in, #${prefix}time_out`).val('').prop('disabled', true);
                } else {
                    $(`#${prefix}time_in, #${prefix}time_out`).prop('disabled', false);
                }
            });
            
            // Employee edit button handler
            $('.edit-employee').click(function() {
                // Add your employee edit logic here
            });
            
            // Employee delete button handler
            $('.delete-employee').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                
                if (confirm(`Are you sure you want to delete employee "${name}"?`)) {
                    $.ajax({
                        url: 'ajax/delete_employee.php',
                        type: 'POST',
                        data: { employee_id: id },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.error || 'Failed to delete employee');
                            }
                        },
                        error: function() {
                            alert('An error occurred while deleting the employee');
                        }
                    });
                }
            });
            
            // Attendance edit button handler
            $('.edit-attendance').click(function() {
                const id = $(this).data('id');
                const employee = $(this).data('employee');
                const date = $(this).data('date');
                const timeIn = $(this).data('timein');
                const timeOut = $(this).data('timeout');
                const status = $(this).data('status');
                const notes = $(this).data('notes');
                
                $('#edit_attendance_id').val(id);
                $('#edit_employee_id').val(employee);
                $('#edit_date').val(date);
                $('#edit_time_in').val(timeIn);
                $('#edit_time_out').val(timeOut);
                $('#edit_status').val(status);
                $('#edit_notes').val(notes);
                
                $('#editAttendanceModal').modal('show');
            });
            
            // Attendance delete button handler
            $('.delete-attendance').click(function() {
                const id = $(this).data('id');
                const employee = $(this).data('employee');
                const date = $(this).data('date');
                
                $('#delete_attendance_id').val(id);
                $('#delete_employee_name').text(employee);
                $('#delete_attendance_date').text(date);
                
                $('#deleteAttendanceModal').modal('show');
            });

            // Handle delete attendance form submission
            $('#deleteAttendanceModal form').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const submitBtn = form.find('button[type="submit"]');
                
                submitBtn.prop('disabled', true);
                
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.error || 'Failed to delete attendance record');
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting the attendance record');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false);
                        $('#deleteAttendanceModal').modal('hide');
                    }
                });
            });
        });
    </script>
</body>
</html>