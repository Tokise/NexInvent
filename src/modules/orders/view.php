<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to view sales
requirePermission('view_sales');

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid order ID";
    header("Location: ../sales/index.php");
    exit();
}

$sale_id = (int)$_GET['id'];

// Get order details
$sql = "SELECT so.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
        u.username as created_by
        FROM sales_orders so 
        LEFT JOIN customers c ON so.customer_id = c.customer_id
        LEFT JOIN users u ON so.user_id = u.user_id
        WHERE so.sale_id = ?";
$order = fetchOne($sql, [$sale_id]);

if (!$order) {
    $_SESSION['error'] = "Order not found";
    header("Location: ../sales/index.php");
    exit();
}

// Get order items
$sql = "SELECT soi.*, p.name as product_name, p.sku
        FROM sales_order_items soi
        JOIN products p ON soi.product_id = p.product_id
        WHERE soi.sale_id = ?";
$orderItems = fetchAll($sql, [$sale_id]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Order Details</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
 
</head>
<body>

<?php include '../../includes/sidebar.php'; ?>
<?php include '../../includes/header.php'; ?>
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Order #<?php echo str_pad($order['sale_id'], 6, '0', STR_PAD_LEFT); ?></h2>
            <div>
                <?php if ($order['payment_status'] !== 'paid' && hasPermission('process_payments')): ?>
                <a href="payment.php?id=<?php echo $order['sale_id']; ?>" class="btn btn-success me-2">
                    <i class="bi bi-cash me-1"></i> Process Payment
                </a>
                <?php endif; ?>
                
                <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled' && (hasPermission('manage_sales') || hasPermission('process_payments'))): ?>
                <button type="button" class="btn btn-primary me-2" onclick="updateOrderStatus(<?php echo $order['sale_id']; ?>)">
                    <i class="bi bi-pencil me-1"></i> Update Status
                </button>
                <?php endif; ?>
                
                <a href="../sales/index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Sales
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="order-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Order Information</h5>
                                    <p class="mb-0"><strong>Order Date:</strong> <?php echo date('Y-m-d', strtotime($order['created_at'])); ?></p>
                                    <p class="mb-0"><strong>Created By:</strong> <?php echo htmlspecialchars($order['created_by']); ?></p>
                                    <p class="mb-0">
                                        <strong>Status:</strong> 
                                        <span class="badge bg-<?php echo getStatusBadgeClass($order['status']); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Payment Information</h5>
                                    <p class="mb-0">
                                        <strong>Payment Status:</strong> 
                                        <span class="badge bg-<?php echo getPaymentStatusBadgeClass($order['payment_status'] ?? 'unpaid'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['payment_status'] ?? 'unpaid')); ?>
                                        </span>
                                    </p>
                                    <p class="mb-0"><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method'] ?? 'Not specified'); ?></p>
                                    <p class="mb-0"><strong>Amount Paid:</strong> $<?php echo number_format($order['amount_paid'] ?? 0, 2); ?></p>
                                    <?php if (isset($order['payment_date']) && $order['payment_date']): ?>
                                    <p class="mb-0"><strong>Payment Date:</strong> <?php echo date('Y-m-d', strtotime($order['payment_date'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="status-timeline">
                            <?php
                            $statuses = ['draft', 'confirmed', 'processing', 'shipped', 'delivered', 'completed'];
                            $currentStatusIndex = array_search($order['status'], $statuses);
                            
                            foreach ($statuses as $index => $status):
                                $statusClass = '';
                                if ($index < $currentStatusIndex || $order['status'] === $status) {
                                    $statusClass = 'completed';
                                }
                                if ($order['status'] === $status) {
                                    $statusClass = 'active';
                                }
                            ?>
                            <div class="status-step <?php echo $statusClass; ?>">
                                <div class="status-step-icon">
                                    <?php if ($index < $currentStatusIndex): ?>
                                    <i class="bi bi-check"></i>
                                    <?php else: ?>
                                    <i class="bi bi-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="status-step-label"><?php echo ucfirst($status); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <h5 class="mt-4">Order Items</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Quantity</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                        <td class="text-end">$<?php echo number_format($item['price'], 2); ?></td>
                                        <td class="text-end"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end">$<?php echo number_format($order['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php if ($order['tax_amount'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Tax:</strong></td>
                                        <td class="text-end">$<?php echo number_format($order['tax_amount'], 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($order['discount_amount'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Discount:</strong></td>
                                        <td class="text-end">-$<?php echo number_format($order['discount_amount'], 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Grand Total:</strong></td>
                                        <td class="text-end"><strong>$<?php echo number_format($order['grand_total'], 2); ?></strong></td>
                                    </tr>
                                    <?php if (($order['payment_status'] ?? 'unpaid') !== 'paid'): ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Balance Due:</strong></td>
                                        <td class="text-end"><strong>$<?php echo number_format(($order['grand_total'] - ($order['amount_paid'] ?? 0)), 2); ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Customer Information</h5>
                        <p class="mb-0"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                        <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                        <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                    </div>
                </div>

                <?php if (!empty($order['notes'])): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Order Notes</h5>
                        <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function updateOrderStatus(saleId) {
    Swal.fire({
        title: 'Update Order Status',
        input: 'select',
        inputOptions: {
            'draft': 'Draft',
            'confirmed': 'Confirmed',
            'processing': 'Processing',
            'shipped': 'Shipped',
            'delivered': 'Delivered',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        },
        inputPlaceholder: 'Select a status',
        showCancelButton: true,
        confirmButtonText: 'Update',
        showLoaderOnConfirm: true,
        preConfirm: (status) => {
            return fetch('../sales/ajax/update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `sale_id=${saleId}&status=${status}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(response.statusText)
                }
                return response.json()
            })
            .catch(error => {
                Swal.showValidationMessage(
                    `Request failed: ${error}`
                )
            })
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            if (result.value.success) {
                Swal.fire({
                    title: 'Success!',
                    text: result.value.message,
                    icon: 'success'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: result.value.message,
                    icon: 'error'
                });
            }
        }
    });
}
</script>

<?php
function getStatusBadgeClass($status) {
    return match($status) {
        'draft' => 'secondary',
        'confirmed' => 'primary',
        'processing' => 'info',
        'shipped' => 'warning',
        'delivered' => 'success',
        'completed' => 'success',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}

function getPaymentStatusBadgeClass($status) {
    return match($status) {
        'paid' => 'success',
        'partially_paid' => 'warning',
        'unpaid' => 'danger',
        default => 'warning'
    };
}
?>

</body>
</html>