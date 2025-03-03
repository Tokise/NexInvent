<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to view suppliers
requirePermission('view_suppliers');

try {
    // Updated SQL query to properly show all suppliers without duplication
    $sql = "SELECT 
            s.supplier_id,
            s.company_name,
            s.contact_name,
            s.email,
            s.phone,
            s.address,
            s.created_at,
            COUNT(po.po_id) as total_orders,
            COALESCE(AVG(NULLIF(po.total_amount, 0)), 0) as avg_order_amount,
            MAX(po.created_at) as last_order_date,
            COUNT(CASE WHEN po.status = 'received' THEN 1 END) as completed_orders
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
        GROUP BY 
            s.supplier_id,
            s.company_name,
            s.contact_name,
            s.email,
            s.phone,
            s.address,
            s.created_at
        ORDER BY s.created_at DESC";

    $suppliers = fetchAll($sql);

    // Calculate rating and verified status for each supplier
    foreach ($suppliers as &$supplier) {
        $total_orders = $supplier['total_orders'] ?? 0;
        $completed_orders = $supplier['completed_orders'] ?? 0;
        
        // Calculate rating based on completed orders (0-5 scale)
        $supplier['rating'] = $total_orders > 0 
            ? number_format(($completed_orders / $total_orders) * 5, 1) 
            : 0;
        
        // Set verified status (true if has at least one completed order)
        $supplier['verified'] = $completed_orders > 0;
    }

} catch (Exception $e) {
    $error = "Error loading suppliers: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Suppliers</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include '../../includes/header.php'; ?>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Suppliers Management</h2>
                <div class="d-flex gap-2">
                    <?php if (hasPermission('manage_suppliers')): ?>
                    <button class="btn btn-danger" onclick="resetSuppliers()">
                        <i class="bi bi-trash me-1"></i> Reset Suppliers
                    </button>
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                        <i class="bi bi-plus-lg me-1"></i> Add New Supplier
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($suppliers as $supplier): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card supplier-card">
                        <div class="supplier-header position-relative">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1 text-white"><?php echo htmlspecialchars($supplier['company_name']); ?></h5>
                                    <small class="text-white opacity-75">
                                        <i class="bi bi-envelope me-1"></i>
                                        <?php echo htmlspecialchars($supplier['email']); ?>
                                    </small>
                                </div>
                                <span class="supplier-badge <?php echo $supplier['verified'] ? 'badge-verified' : 'badge-pending'; ?>">
                                    <?php echo $supplier['verified'] ? 'Verified' : 'Pending'; ?>
                                </span>
                            </div>
                            <div class="d-flex text-white opacity-75 small">
                                <div class="me-3">
                                    <i class="bi bi-person me-1"></i>
                                    <?php echo htmlspecialchars($supplier['contact_name']); ?>
                                </div>
                                <div>
                                    <i class="bi bi-telephone me-1"></i>
                                    <?php echo htmlspecialchars($supplier['phone']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="supplier-body">
                            <div class="supplier-stats mb-3">
                                <div class="stat-box">
                                    <div class="stat-value text-primary">
                                        <?php echo $supplier['total_orders']; ?>
                                    </div>
                                    <div class="stat-label">Orders</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-value text-success">
                                        <?php echo number_format($supplier['rating'], 1); ?>
                                        <small class="text-muted">/5</small>
                                    </div>
                                    <div class="stat-label">Rating</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                                <div>
                                    <small class="text-muted d-block">Average Order</small>
                                    <strong class="text-success">
                                        $<?php echo number_format($supplier['avg_order_amount'], 2); ?>
                                    </strong>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">Last Order</small>
                                    <strong class="text-primary">
                                        <?php echo $supplier['last_order_date'] ? date('M d, Y', strtotime($supplier['last_order_date'])) : 'No orders'; ?>
                                    </strong>
                                </div>
                            </div>

                            <div class="supplier-actions">
                                <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="viewSupplier(<?php echo $supplier['supplier_id']; ?>)">
                                    <i class="bi bi-eye me-1"></i> View Details
                                </button>
                                <button class="btn btn-sm btn-success flex-grow-1" onclick="createOrder(<?php echo $supplier['supplier_id']; ?>)">
                                    <i class="bi bi-cart-plus me-1"></i> New Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <?php if (hasPermission('manage_suppliers')): ?>
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addSupplierForm" onsubmit="return false;">
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name*</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="contact_name" class="form-label">Contact Person*</label>
                            <input type="text" class="form-control" id="contact_name" name="contact_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email*</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone*</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveSupplier()">Save Supplier</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // Initialize the modal properly
    let supplierModal;

    document.addEventListener('DOMContentLoaded', function() {
        supplierModal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
    });

    function viewSupplier(id) {
        window.location.href = `view.php?id=${id}`;
    }

    function createOrder(id) {
        Swal.fire({
            title: 'Creating New Order...',
            text: 'Please wait while we prepare the form',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Use setTimeout to ensure the loading shows before redirect
        setTimeout(() => {
            window.location.href = `../purchases/index.php?supplier_id=${id}&autoopen=true`;
        }, 500);
    }

    function saveSupplier() {
        const form = document.getElementById('addSupplierForm');
        
        if (!form) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Form not found'
            });
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);

        Swal.fire({
            title: 'Adding supplier...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('ajax/save_supplier.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => Promise.reject(data.error));
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('addSupplierModal'));
                if (modal) {
                    modal.hide();
                }
                form.reset();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                throw new Error(data.error || 'Failed to save supplier');
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.toString()
            });
        });
    }

    function resetSuppliers() {
        Swal.fire({
            title: 'Are you sure?',
            text: "This will delete all suppliers and their related data. This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, reset it!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Resetting suppliers...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('ajax/reset_suppliers.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(async response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Invalid server response format');
                    }
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.error || 'Reset failed');
                    }
                    return data;
                })
                .then(data => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                })
                .catch(error => {
                    console.error('Reset error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Failed to reset suppliers'
                    });
                });
            }
        });
    }
    </script>
</body>
</html>
