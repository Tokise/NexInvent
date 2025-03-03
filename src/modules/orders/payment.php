<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to process payments
requirePermission('process_payments');

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid order ID";
    header("Location: ../sales/index.php");
    exit();
}

$sale_id = (int)$_GET['id'];

// Get order details
$sql = "SELECT so.*, c.name as customer_name 
        FROM sales_orders so 
        LEFT JOIN customers c ON so.customer_id = c.customer_id
        WHERE so.sale_id = ?";
$order = fetchOne($sql, [$sale_id]);

if (!$order) {
    $_SESSION['error'] = "Order not found";
    header("Location: ../sales/index.php");
    exit();
}

// Check if order is already fully paid
if ($order['payment_status'] === 'paid') {
    $_SESSION['error'] = "This order is already fully paid";
    header("Location: ../sales/index.php");
    exit();
}

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (!isset($_POST['payment_method']) || empty($_POST['payment_method'])) {
            throw new Exception('Payment method is required');
        }

        if (!isset($_POST['amount_paid']) || !is_numeric($_POST['amount_paid']) || $_POST['amount_paid'] <= 0) {
            throw new Exception('Amount paid must be a positive number');
        }

        $payment_method = $_POST['payment_method'];
        $amount_paid = (float)$_POST['amount_paid'];
        $current_amount_paid = (float)$order['amount_paid'];
        $grand_total = (float)$order['grand_total'];
        
        // Calculate new total paid
        $new_amount_paid = $current_amount_paid + $amount_paid;
        
        // Determine payment status
        $payment_status = 'partially_paid';
        if ($new_amount_paid >= $grand_total) {
            $payment_status = 'paid';
            $new_amount_paid = $grand_total; // Cap at grand total
        } else if ($new_amount_paid <= 0) {
            $payment_status = 'unpaid';
        }
        
        // Update order payment information
        $update_data = [
            'payment_method' => $payment_method,
            'amount_paid' => $new_amount_paid,
            'payment_status' => $payment_status,
            'payment_date' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // If fully paid, update status to completed
        if ($payment_status === 'paid') {
            $update_data['status'] = 'completed';
        }
        
        update('sales_orders', $update_data, 'sale_id = ?', [$sale_id]);
        
        $_SESSION['success'] = "Payment processed successfully";
        header("Location: ../sales/index.php");
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexInvent - Process Payment</title>
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
            <h2>Process Payment</h2>
            <a href="../sales/index.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Sales
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Order Information</h5>
                        <table class="table">
                            <tr>
                                <th>Order ID:</th>
                                <td>#<?php echo str_pad($order['sale_id'], 6, '0', STR_PAD_LEFT); ?></td>
                            </tr>
                            <tr>
                                <th>Customer:</th>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Order Date:</th>
                                <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge bg-<?php echo getStatusBadgeClass($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Payment Status:</th>
                                <td>
                                    <span class="badge bg-<?php echo getPaymentStatusBadgeClass($order['payment_status'] ?? 'unpaid'); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['payment_status'] ?? 'unpaid')); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Total Amount:</th>
                                <td>$<?php echo number_format($order['grand_total'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Amount Paid:</th>
                                <td>$<?php echo number_format($order['amount_paid'] ?? 0, 2); ?></td>
                            </tr>
                            <tr>
                                <th>Balance Due:</th>
                                <td>$<?php echo number_format(($order['grand_total'] - ($order['amount_paid'] ?? 0)), 2); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Process Payment</h5>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="amount_paid" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="amount_paid" name="amount_paid" 
                                       step="0.01" min="0.01" 
                                       max="<?php echo ($order['grand_total'] - ($order['amount_paid'] ?? 0)); ?>" 
                                       value="<?php echo ($order['grand_total'] - ($order['amount_paid'] ?? 0)); ?>" 
                                       required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Process Payment</button>
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