<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../login/index.php");
    exit();
}

// Check if user has permission to create sales
requirePermission('create_sale');

// Fetch all customers
$customers = fetchAll("SELECT customer_id, name, email, phone FROM customers ORDER BY name");

// Fetch all products with their current stock
$products = fetchAll("SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.category_id 
                     WHERE p.quantity_in_stock > 0 
                     ORDER BY p.name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Create Sales Order</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Create Order</h2>
        </div>

        <form id="salesOrderForm" class="needs-validation" novalidate>
            <div class="row">
                <!-- Customer Information -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Customer Information</h5>
                            
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Customer</label>
                                <select class="form-select" id="customer_id" name="customer_id" required>
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['customer_id']; ?>">
                                            <?php echo htmlspecialchars($customer['name']); ?> 
                                            (<?php echo htmlspecialchars($customer['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a customer</div>
                            </div>

                            <div class="mb-3">
                                <label for="shipping_address" class="form-label">Shipping Address</label>
                                <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3" required></textarea>
                                <div class="invalid-feedback">Please enter shipping address</div>
                            </div>

                            <div class="mb-3">
                                <label for="billing_address" class="form-label">Billing Address</label>
                                <textarea class="form-control" id="billing_address" name="billing_address" rows="3" required></textarea>
                                <div class="invalid-feedback">Please enter billing address</div>
                            </div>

                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" required>
                                <div class="invalid-feedback">Please select a due date</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Order Items</h5>
                            
                            <div class="mb-3">
                                <button type="button" class="btn btn-success" onclick="addOrderItem()">
                                    <i class="bi bi-plus-lg"></i> Add Item
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered" id="orderItemsTable">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Tax Rate (%)</th>
                                            <th>Discount (%)</th>
                                            <th>Subtotal</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                            <td colspan="2">$<span id="subtotal">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-end"><strong>Tax:</strong></td>
                                            <td colspan="2">$<span id="total_tax">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-end"><strong>Discount:</strong></td>
                                            <td colspan="2">$<span id="total_discount">0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-end"><strong>Grand Total:</strong></td>
                                            <td colspan="2">$<span id="grand_total">0.00</span></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Additional Information</h5>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Payment Information</h5>
                            
                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select class="form-select" id="payment_method" name="payment_method">
                                    <option value="">Select Payment Method</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="amount_paid" class="form-label">Amount Paid</label>
                                <input type="number" class="form-control" id="amount_paid" name="amount_paid" step="0.01" min="0" value="0">
                                <div class="form-text">Leave as 0 for unpaid orders.</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="pay_full_amount">
                                    <label class="form-check-label" for="pay_full_amount">
                                        Pay Full Amount
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" onclick="saveDraft()">Save as Draft</button>
                        <button type="submit" class="btn btn-primary">Create Order</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Product Template -->
<template id="orderItemTemplate">
    <tr>
        <td>
            <select class="form-select product-select" required>
                <option value="">Select Product</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['product_id']; ?>" 
                            data-price="<?php echo $product['unit_price']; ?>"
                            data-stock="<?php echo $product['quantity_in_stock']; ?>">
                        <?php echo htmlspecialchars($product['name']); ?> 
                        (<?php echo htmlspecialchars($product['sku']); ?>) - 
                        Stock: <?php echo $product['quantity_in_stock']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" class="form-control quantity" min="1" required>
        </td>
        <td>
            <input type="number" class="form-control unit-price" step="0.01" min="0" required>
        </td>
        <td>
            <input type="number" class="form-control tax-rate" step="0.01" min="0" max="100" value="0">
        </td>
        <td>
            <input type="number" class="form-control discount-rate" step="0.01" min="0" max="100" value="0">
        </td>
        <td>$<span class="item-subtotal">0.00</span></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeOrderItem(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Initialize Select2 for customer selection
    $('#customer_id').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select Customer'
    });

    // Set minimum due date to today
    const today = new Date().toISOString().split('T')[0];
    $('#due_date').attr('min', today);
    $('#due_date').val(today);

    // Add first item row
    addOrderItem();

    // Form submission
    $('#salesOrderForm').on('submit', function(e) {
        e.preventDefault();
        if (this.checkValidity()) {
            createOrder(false);
        }
        $(this).addClass('was-validated');
    });
    
    // Handle "Pay Full Amount" checkbox
    $('#pay_full_amount').on('change', function() {
        if ($(this).is(':checked')) {
            const grandTotal = parseFloat($('#grand_total').text());
            $('#amount_paid').val(grandTotal.toFixed(2));
        } else {
            $('#amount_paid').val('0.00');
        }
    });
    
    // Update payment status when amount changes
    $('#amount_paid').on('input', function() {
        const amountPaid = parseFloat($(this).val()) || 0;
        const grandTotal = parseFloat($('#grand_total').text());
        
        if (amountPaid >= grandTotal) {
            $('#pay_full_amount').prop('checked', true);
        } else {
            $('#pay_full_amount').prop('checked', false);
        }
    });
});

function addOrderItem() {
    const template = document.getElementById('orderItemTemplate');
    const clone = template.content.cloneNode(true);
    
    // Initialize Select2 for product selection
    const select = clone.querySelector('.product-select');
    $('#orderItemsTable tbody').append(clone);
    
    $(select).select2({
        theme: 'bootstrap-5',
        placeholder: 'Select Product'
    }).on('change', function() {
        const option = $(this).find(':selected');
        const row = $(this).closest('tr');
        row.find('.unit-price').val(option.data('price'));
        row.find('.quantity').attr('max', option.data('stock'));
        updateTotals();
    });

    // Add event listeners for calculations
    const row = $('#orderItemsTable tbody tr:last');
    row.find('input').on('input', updateTotals);
}

function removeOrderItem(button) {
    $(button).closest('tr').remove();
    updateTotals();
}

function updateTotals() {
    let subtotal = 0;
    let totalTax = 0;
    let totalDiscount = 0;

    $('#orderItemsTable tbody tr').each(function() {
        const quantity = parseFloat($(this).find('.quantity').val()) || 0;
        const unitPrice = parseFloat($(this).find('.unit-price').val()) || 0;
        const taxRate = parseFloat($(this).find('.tax-rate').val()) || 0;
        const discountRate = parseFloat($(this).find('.discount-rate').val()) || 0;

        const rowSubtotal = quantity * unitPrice;
        const rowTax = rowSubtotal * (taxRate / 100);
        const rowDiscount = rowSubtotal * (discountRate / 100);

        subtotal += rowSubtotal;
        totalTax += rowTax;
        totalDiscount += rowDiscount;

        $(this).find('.item-subtotal').text(rowSubtotal.toFixed(2));
    });

    const grandTotal = subtotal + totalTax - totalDiscount;

    $('#subtotal').text(subtotal.toFixed(2));
    $('#total_tax').text(totalTax.toFixed(2));
    $('#total_discount').text(totalDiscount.toFixed(2));
    $('#grand_total').text(grandTotal.toFixed(2));
}

function createOrder(isDraft = false) {
    try {
        // Collect and validate order data
        const orderData = collectOrderData();
        orderData.status = isDraft ? 'draft' : 'confirmed';

        console.log('Sending order data:', orderData);

        // Show loading
        Swal.fire({
            title: 'Creating Order...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Send to server
        $.ajax({
            url: 'process_order.php',
            method: 'POST',
            data: JSON.stringify(orderData),
            contentType: 'application/json',
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: isDraft ? 'Order saved as draft' : 'Order created successfully',
                        confirmButtonText: 'View Order'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `view.php?id=${response.sale_id}`;
                        } else {
                            window.location.href = '../index.php';
                        }
                    });
                } else {
                    throw new Error(response.error || 'Failed to create order');
                }
            },
            error: function(xhr, status, error) {
                console.error('Order creation error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText
                });
                
                let errorMessage = 'Failed to create order. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || response.error || errorMessage;
                    console.error('Server error details:', response.details);
                } catch (e) {
                    console.error('Error parsing server response:', e);
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage
                });
            }
        });
    } catch (error) {
        console.error('Order creation error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

function collectOrderData() {
    const items = [];
    let isValid = true;

    $('#orderItemsTable tbody tr').each(function() {
        const product_id = $(this).find('.product-select').val();
        const quantity = $(this).find('.quantity').val();
        
        if (!product_id || !quantity) {
            isValid = false;
            return false;
        }

        items.push({
            product_id: parseInt(product_id),
            quantity: parseInt(quantity),
            unit_price: parseFloat($(this).find('.unit-price').val()),
            tax_rate: parseFloat($(this).find('.tax-rate').val() || 0),
            discount_rate: parseFloat($(this).find('.discount-rate').val() || 0)
        });
    });

    if (!isValid || items.length === 0) {
        throw new Error('Please fill in all required fields');
    }

    const grandTotal = parseFloat($('#grand_total').text());
    const amountPaid = parseFloat($('#amount_paid').val()) || 0;
    let paymentStatus = 'unpaid';
    
    if (amountPaid >= grandTotal) {
        paymentStatus = 'paid';
    } else if (amountPaid > 0) {
        paymentStatus = 'partially_paid';
    }

    const orderData = {
        customer_id: parseInt($('#customer_id').val()),
        shipping_address: $('#shipping_address').val().trim(),
        billing_address: $('#billing_address').val().trim(),
        due_date: $('#due_date').val(),
        notes: $('#notes').val().trim(),
        items: items,
        subtotal: parseFloat($('#subtotal').text()),
        tax_amount: parseFloat($('#total_tax').text()),
        discount_amount: parseFloat($('#total_discount').text()),
        grand_total: grandTotal,
        payment_method: $('#payment_method').val() || null,
        amount_paid: amountPaid,
        payment_status: paymentStatus
    };

    // Validate required fields
    if (!orderData.customer_id) {
        throw new Error('Please select a customer');
    }
    if (!orderData.shipping_address) {
        throw new Error('Please enter shipping address');
    }
    if (!orderData.billing_address) {
        throw new Error('Please enter billing address');
    }
    if (!orderData.due_date) {
        throw new Error('Please select due date');
    }
    
    // Validate payment method if amount is paid
    if (orderData.amount_paid > 0 && !orderData.payment_method) {
        throw new Error('Please select a payment method');
    }

    console.log('Collected order data:', orderData);
    return orderData;
}

function saveDraft() {
    if ($('#salesOrderForm')[0].checkValidity()) {
        createOrder(true);
    }
    $('#salesOrderForm').addClass('was-validated');
}
</script>

</body>
</html>