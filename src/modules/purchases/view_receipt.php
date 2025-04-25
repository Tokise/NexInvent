<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Purchase Order Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet">
    <style>
        /* Regular view styles */
        .screen-only { display: block; }
        .print-view { display: none; }
        
        /* Print styles */
        @media print {
            .screen-only { display: none !important; }
            .print-view { display: block !important; }
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                margin: 0;
                padding: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .company-name {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .system-name {
                font-size: 14px;
                color: #666;
                margin-bottom: 5px;
            }
            .document-title {
                font-size: 16px;
                color: #444;
            }
            .info-section {
                margin-bottom: 30px;
            }
            .info-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            .info-item {
                margin-bottom: 10px;
            }
            .info-label {
                font-weight: bold;
                margin-right: 10px;
                min-width: 120px;
                display: inline-block;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f8f9fa;
            }
            .text-end {
                text-align: right;
            }
            .total-row {
                font-weight: bold;
            }
            .notes {
                margin-bottom: 30px;
            }
            .signatures {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 30px;
                margin-top: 50px;
            }
            .signature-box {
                text-align: center;
            }
            .signature-line {
                border-top: 1px solid #000;
                margin-top: 40px;
                margin-bottom: 5px;
            }
            @page {
                margin: 20px;
                size: A4;
            }
        }
    </style>
</head>
<body>

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
requirePermission('manage_purchases');

// Get PO ID from URL
$po_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$po_id) {
    $_SESSION['error'] = 'Invalid purchase order ID';
    header("Location: index.php");
    exit();
}

// Fetch PO details
$sql = "SELECT po.*, 
        u1.username as created_by_name,
        u2.username as approved_by_name,
        DATE_FORMAT(po.created_at, '%M %d, %Y') as formatted_date
        FROM purchase_orders po 
        LEFT JOIN users u1 ON po.created_by = u1.user_id
        LEFT JOIN users u2 ON po.approved_by = u2.user_id
        WHERE po.po_id = ?";
$po = fetchRow($sql, [$po_id]);

if (!$po) {
    $_SESSION['error'] = 'Purchase order not found';
    header("Location: index.php");
    exit();
}

// Fetch PO items
$sql = "SELECT poi.*, p.name as product_name, p.sku, c.name as category_name
        FROM purchase_order_items poi 
        JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE poi.po_id = ?";
$items = fetchAll($sql, [$po_id]);
?>

<!-- Regular View (screen only) -->
<div class="screen-only">
    <?php include '../../includes/sidebar.php'; ?>
    <?php include '../../includes/header.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Purchase Order Receipt - <?php echo htmlspecialchars($po['po_number']); ?></h2>
                <div>
                    <button class="btn btn-primary me-2" id="saveChangesBtn">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                    <button class="btn btn-secondary me-2" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print Receipt
                    </button>
                    <button class="btn btn-primary" onclick="window.location.href='index.php'">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </button>
                </div>
            </div>

            <!-- Receipt Details -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Receipt Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Purchase Order Information</h6>
                            <p><strong>PO Number:</strong> <?php echo htmlspecialchars($po['po_number']); ?></p>
                            <p><strong>Date Created:</strong> <?php echo htmlspecialchars($po['formatted_date']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $po['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                    <?php echo ucfirst($po['status']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>Created By</h6>
                            <p><?php echo htmlspecialchars($po['created_by_name']); ?></p>
                            <?php if ($po['approved_by_name']): ?>
                            <h6>Approved By</h6>
                            <p><?php echo htmlspecialchars($po['approved_by_name']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form id="receiveForm">
                        <input type="hidden" name="po_id" value="<?php echo $po_id; ?>">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th>Ordered Qty</th>
                                        <th class="no-print">Received Qty</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                        <th class="no-print">Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>
                                            <input type="number" 
                                                   class="form-control received-qty" 
                                                   name="received_qty[<?php echo $item['po_item_id']; ?>]" 
                                                   value="<?php echo $item['quantity']; ?>"
                                                   min="0"
                                                   max="<?php echo $item['quantity']; ?>"
                                                   data-unit-price="<?php echo $item['unit_price']; ?>"
                                                   data-po-item-id="<?php echo $item['po_item_id']; ?>"
                                                   data-original-qty="<?php echo $item['quantity']; ?>"
                                                   onchange="updateTotals(this)">
                                        </td>
                                        <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="item-total">$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                        <td>
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="notes[<?php echo $item['po_item_id']; ?>]" 
                                                   placeholder="Enter any discrepancies">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" class="text-end"><strong>Total Amount:</strong></td>
                                        <td colspan="2"><strong id="totalAmount">$<?php echo number_format($po['total_amount'], 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mb-3 no-print">
                            <label class="form-label">Receipt Notes</label>
                            <textarea class="form-control" name="receipt_notes" rows="3" 
                                    placeholder="Enter any general notes about this receipt"></textarea>
                        </div>

                        <?php if ($po['status'] === 'ordered'): ?>
                        <div class="text-end no-print">
                            <button type="button" class="btn btn-success" id="submitReceiptBtn">
                                <i class="bi bi-check-lg"></i> Submit Receipt
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print View -->
<div class="print-view">
    <div class="header">
        <div class="company-name">NexInvent</div>
        <div class="system-name">Inventory Management System</div>
        <div class="document-title">Purchase Order Receipt - <?php echo htmlspecialchars($po['po_number']); ?></div>
    </div>

    <div class="info-section">
        <div class="info-grid">
            <div>
                <div class="info-item">
                    <span class="info-label">PO Number:</span>
                    <?php echo htmlspecialchars($po['po_number']); ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Date Created:</span>
                    <?php echo htmlspecialchars($po['formatted_date']); ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <?php echo ucfirst($po['status']); ?>
                </div>
            </div>
            <div>
                <div class="info-item">
                    <span class="info-label">Created By:</span>
                    <?php echo htmlspecialchars($po['created_by_name']); ?>
                </div>
                <?php if ($po['approved_by_name']): ?>
                <div class="info-item">
                    <span class="info-label">Approved By:</span>
                    <?php echo htmlspecialchars($po['approved_by_name']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Ordered Qty</th>
                <th>Received Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $index => $item): ?>
            <tr>
                <td><?php echo $index + 1; ?></td>
                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                <td class="text-end"><?php echo $item['quantity']; ?></td>
                <td class="text-end print-received-qty-<?php echo $item['po_item_id']; ?>"><?php echo $item['received_quantity'] ?? $item['quantity']; ?></td>
                <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="text-end print-total-<?php echo $item['po_item_id']; ?>">$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="7" class="text-end">Total Amount:</td>
                <td class="text-end print-grand-total">$<?php echo number_format($po['total_amount'], 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <?php if (!empty($po['notes'])): ?>
    <div class="notes">
        <h4>Notes:</h4>
        <p><?php echo nl2br(htmlspecialchars($po['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Prepared by</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Approved by</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Received by</div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Initialize toastr
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "timeOut": "3000",
        "preventDuplicates": true,
        "newestOnTop": true,
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut",
        "showDuration": "300",
        "hideDuration": "1000",
        "extendedTimeOut": "1000",
        "toastClass": "toastr",
        "containerId": "toast-container",
        "debug": false,
        "onclick": null,
        "tapToDismiss": true,
        "backgroundColor": "#ffffff",
        "titleColor": "#333333",
        "messageColor": "#333333"
    };

    // Add custom CSS for toastr
    const style = document.createElement('style');
    style.textContent = `
        .toast-success {
            background-color: #ffffff !important;
            color: #333333 !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
            border-left: 4px solid #51A351 !important;
        }
        .toast-info {
            background-color: #ffffff !important;
            color: #333333 !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
            border-left: 4px solid #2F96B4 !important;
        }
        .toast-warning {
            background-color: #ffffff !important;
            color: #333333 !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
            border-left: 4px solid #F89406 !important;
        }
        .toast-error {
            background-color: #ffffff !important;
            color: #333333 !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
            border-left: 4px solid #BD362F !important;
        }
        #toast-container > div {
            opacity: 1 !important;
            border-radius: 4px !important;
            padding: 15px 15px 15px 50px !important;
        }
    `;
    document.head.appendChild(style);

    // Handle Save Changes button
    $('#saveChangesBtn').click(function() {
        Swal.fire({
            title: 'Save Changes?',
            text: 'This will save the current received quantities. You can still modify them later before marking as received.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, save changes',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                saveChanges();
            }
        });
    });

    // Handle received quantity changes
    $('.received-qty').on('change', function() {
        const orderedQty = parseInt($(this).attr('max'));
        const receivedQty = parseInt($(this).val());
        const poItemId = $(this).data('po-item-id');
        const originalQty = parseInt($(this).data('original-qty'));
        
        if (receivedQty > orderedQty) {
            toastr.warning('Received quantity cannot be greater than ordered quantity');
            $(this).val(orderedQty);
        } else if (receivedQty < 0) {
            toastr.warning('Received quantity cannot be negative');
            $(this).val(0);
        }

        // Show notification if quantity changed
        if (receivedQty !== originalQty) {
            toastr.info('Quantity changed. Remember to save your changes!');
        }

        // Update print view received quantity
        $('.print-received-qty-' + poItemId).text($(this).val());

        updateTotals(this);
    });

    // Handle form submission
    $('#submitReceiptBtn').click(function() {
        Swal.fire({
            title: 'Submit Receipt?',
            text: 'This will update the stock levels based on the received quantities. Continue?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, submit',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                submitReceipt();
            }
        });
    });
});

function updateTotals(input) {
    const $row = $(input).closest('tr');
    const receivedQty = parseInt(input.value) || 0;
    const unitPrice = parseFloat($(input).data('unit-price'));
    const total = receivedQty * unitPrice;
    const poItemId = $(input).data('po-item-id');
    
    // Update row total in both views
    $row.find('.item-total').text('$' + total.toFixed(2));
    $('.print-total-' + poItemId).text('$' + total.toFixed(2));
    
    // Update grand total in both views
    let grandTotal = 0;
    $('.received-qty').each(function() {
        const qty = parseInt($(this).val()) || 0;
        const price = parseFloat($(this).data('unit-price'));
        grandTotal += qty * price;
    });
    
    $('#totalAmount').text('$' + grandTotal.toFixed(2));
    $('.print-grand-total').text('$' + grandTotal.toFixed(2));
}

function submitReceipt() {
    // Show loading state
    Swal.fire({
        title: 'Processing...',
        text: 'Please wait while we update the stock levels.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Collect form data
    const formData = {
        po_id: $('input[name="po_id"]').val(),
        items: [],
        receipt_notes: $('textarea[name="receipt_notes"]').val()
    };

    // Collect items data
    $('.received-qty').each(function() {
        const poItemId = $(this).data('po-item-id');
        formData.items.push({
            po_item_id: poItemId,
            received_quantity: parseInt($(this).val()) || 0,
            notes: $(`input[name="notes[${poItemId}]"]`).val()
        });
    });

    // Send AJAX request
    $.ajax({
        url: 'ajax/mark_po_received.php',
        method: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    showConfirmButton: true
                }).then(() => {
                    window.location.href = 'index.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.error || 'Failed to submit receipt'
                });
            }
        },
        error: function(xhr) {
            let errorMessage = 'Failed to submit receipt';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMessage = response.error || errorMessage;
            } catch (e) {}
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
            });
        }
    });
}

function saveChanges() {
    // Show loading state
    Swal.fire({
        title: 'Saving Changes...',
        text: 'Please wait while we save your changes.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const formData = {
        po_id: $('input[name="po_id"]').val(),
        items: []
    };

    $('.received-qty').each(function() {
        const poItemId = $(this).data('po-item-id');
        formData.items.push({
            po_item_id: poItemId,
            received_quantity: parseInt($(this).val()) || 0
        });
    });

    $.ajax({
        url: 'ajax/save_received_quantities.php',
        method: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                // Update the displayed total amount
                $('#totalAmount').text('$' + parseFloat(response.total_amount).toFixed(2));
                $('.print-grand-total').text('$' + parseFloat(response.total_amount).toFixed(2));
                
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Changes saved successfully',
                    showConfirmButton: true
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.error || 'Failed to save changes'
                });
            }
        },
        error: function(xhr) {
            let errorMessage = 'Failed to save changes';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMessage = response.error || errorMessage;
            } catch (e) {}
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
            });
        }
    });
}
</script>

</body>
</html> 