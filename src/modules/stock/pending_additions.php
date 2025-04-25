<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission
requirePermission('manage_inventory');

// Fetch pending stock additions
$sql = "SELECT psa.*, 
        p.name as product_name, 
        p.sku,
        c.name as category_name,
        po.po_number,
        u.username as created_by_name
        FROM pending_stock_additions psa
        JOIN products p ON psa.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        JOIN purchase_orders po ON psa.po_id = po.po_id
        JOIN users u ON psa.created_by = u.user_id
        WHERE psa.status = 'pending'
        ORDER BY psa.created_at DESC";
$pending_additions = fetchAll($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Pending Stock Additions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Pending Stock Additions</h2>
        </div>

        <!-- Pending Additions Table -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Products Pending Addition to Stock</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="pendingAdditionsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>PO Number</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Value</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_additions as $item): ?>
                                <tr data-addition-id="<?php echo $item['addition_id']; ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input item-select">
                                    </td>
                                    <td><?php echo htmlspecialchars($item['po_number']); ?></td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['created_by_name']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($item['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-success approve-btn" 
                                                onclick="approveAddition(<?php echo $item['addition_id']; ?>)">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger reject-btn"
                                                onclick="rejectAddition(<?php echo $item['addition_id']; ?>)">
                                            <i class="bi bi-x-lg"></i> Reject
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <button id="approveSelectedBtn" class="btn btn-success" disabled>
                    <i class="bi bi-check-lg"></i> Approve Selected
                </button>
                <button id="rejectSelectedBtn" class="btn btn-danger ms-2" disabled>
                    <i class="bi bi-x-lg"></i> Reject Selected
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#pendingAdditionsTable').DataTable({
        pageLength: 25,
        order: [[9, 'desc']] // Sort by Created At column by default
    });

    // Initialize toastr
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "timeOut": "3000"
    };

    // Handle select all checkbox
    $('#selectAll').change(function() {
        $('.item-select').prop('checked', $(this).prop('checked'));
        updateBulkActionButtons();
    });

    // Handle individual checkboxes
    $('.item-select').change(function() {
        updateBulkActionButtons();
    });

    // Handle bulk approve button
    $('#approveSelectedBtn').click(function() {
        const selectedIds = getSelectedIds();
        if (selectedIds.length === 0) return;

        Swal.fire({
            title: 'Approve Selected Items?',
            text: 'This will add the selected items to stock. Continue?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, approve',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                approveMultiple(selectedIds);
            }
        });
    });

    // Handle bulk reject button
    $('#rejectSelectedBtn').click(function() {
        const selectedIds = getSelectedIds();
        if (selectedIds.length === 0) return;

        Swal.fire({
            title: 'Reject Selected Items?',
            text: 'This will reject the selected items. Continue?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, reject',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                rejectMultiple(selectedIds);
            }
        });
    });
});

function updateBulkActionButtons() {
    const selectedCount = $('.item-select:checked').length;
    $('#approveSelectedBtn, #rejectSelectedBtn').prop('disabled', selectedCount === 0);
}

function getSelectedIds() {
    return $('.item-select:checked').map(function() {
        return $(this).closest('tr').data('addition-id');
    }).get();
}

function approveAddition(additionId) {
    Swal.fire({
        title: 'Approve Addition?',
        text: 'This will add the items to stock. Continue?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            processAddition(additionId, 'approve');
        }
    });
}

function rejectAddition(additionId) {
    Swal.fire({
        title: 'Reject Addition?',
        text: 'This will reject the addition. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reject',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            processAddition(additionId, 'reject');
        }
    });
}

function processAddition(additionId, action) {
    // Show loading state
    Swal.fire({
        title: 'Processing...',
        text: 'Please wait while we process your request.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'ajax/process_stock_addition.php',
        method: 'POST',
        data: {
            addition_id: additionId,
            action: action
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    showConfirmButton: true
                }).then(() => {
                    // Remove the row from the table
                    const table = $('#pendingAdditionsTable').DataTable();
                    const row = $(`tr[data-addition-id="${additionId}"]`);
                    table.row(row).remove().draw();
                    
                    // Update counts if needed
                    updateItemCounts();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.error || 'An error occurred while processing the request.'
                });
            }
        },
        error: function(xhr) {
            let errorMessage = 'An error occurred while processing the request.';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMessage = response.error || errorMessage;
            } catch (e) {
                console.error('Error parsing response:', e);
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
            });
        }
    });
}

function approveMultiple(additionIds) {
    let processed = 0;
    let errors = [];

    // Show loading state
    Swal.fire({
        title: 'Processing...',
        text: `Processing 0/${additionIds.length} items`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Process each addition sequentially
    function processNext(index) {
        if (index >= additionIds.length) {
            // All items processed
            if (errors.length === 0) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'All selected items have been processed successfully.',
                    showConfirmButton: true
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Completed with Errors',
                    html: `Successfully processed ${processed} items.<br>Failed to process ${errors.length} items.<br><br>Errors:<br>${errors.join('<br>')}`,
                    showConfirmButton: true
                }).then(() => {
                    location.reload();
                });
            }
            return;
        }

        const additionId = additionIds[index];
        
        $.ajax({
            url: 'ajax/process_stock_addition.php',
            method: 'POST',
            data: {
                addition_id: additionId,
                action: 'approve'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    processed++;
                } else {
                    errors.push(`Item ${additionId}: ${response.error || 'Unknown error'}`);
                }
            },
            error: function(xhr) {
                let errorMessage = 'Unknown error occurred';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.error || errorMessage;
                } catch (e) {}
                errors.push(`Item ${additionId}: ${errorMessage}`);
            },
            complete: function() {
                // Update progress
                Swal.update({
                    text: `Processing ${index + 1}/${additionIds.length} items`
                });
                // Process next item
                processNext(index + 1);
            }
        });
    }

    // Start processing
    processNext(0);
}

function rejectMultiple(additionIds) {
    let processed = 0;
    let errors = [];

    // Show loading state
    Swal.fire({
        title: 'Processing...',
        text: `Processing 0/${additionIds.length} items`,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Process each addition sequentially
    function processNext(index) {
        if (index >= additionIds.length) {
            // All items processed
            if (errors.length === 0) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'All selected items have been processed successfully.',
                    showConfirmButton: true
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Completed with Errors',
                    html: `Successfully processed ${processed} items.<br>Failed to process ${errors.length} items.<br><br>Errors:<br>${errors.join('<br>')}`,
                    showConfirmButton: true
                }).then(() => {
                    location.reload();
                });
            }
            return;
        }

        const additionId = additionIds[index];
        
        $.ajax({
            url: 'ajax/process_stock_addition.php',
            method: 'POST',
            data: {
                addition_id: additionId,
                action: 'reject'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    processed++;
                } else {
                    errors.push(`Item ${additionId}: ${response.error || 'Unknown error'}`);
                }
            },
            error: function(xhr) {
                let errorMessage = 'Unknown error occurred';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.error || errorMessage;
                } catch (e) {}
                errors.push(`Item ${additionId}: ${errorMessage}`);
            },
            complete: function() {
                // Update progress
                Swal.update({
                    text: `Processing ${index + 1}/${additionIds.length} items`
                });
                // Process next item
                processNext(index + 1);
            }
        });
    }

    // Start processing
    processNext(0);
}

function updateItemCounts() {
    // Refresh the page counts without reloading
    $.ajax({
        url: 'ajax/get_counts.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update any count displays on the page
                if (response.pending_count !== undefined) {
                    $('.pending-count').text(response.pending_count);
                }
            }
        }
    });
}
</script>

</body>
</html> 