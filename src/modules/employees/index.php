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

// Get all employees
$sql = "SELECT e.*, u.email, u.status as user_status, u.role, u.user_id 
        FROM employee_details e 
        LEFT JOIN users u ON e.user_id = u.user_id 
        ORDER BY e.full_name";
$employees = fetchAll($sql);

// Get attendance records
$sql = "SELECT a.*, e.full_name as employee_name 
        FROM attendance a 
        JOIN employee_details e ON a.employee_id = e.employee_id 
        ORDER BY a.date DESC, e.full_name";
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
    
   
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
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
                                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
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
                                                    <span class="badge status-<?php echo $record['status']; ?>">
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
                // Add your employee delete logic here
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
        });
    </script>
</body>
</html>