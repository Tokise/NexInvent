<?php
require_once '../../includes/require_auth.php';
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/index.php");
    exit();
}

// Check if user has permission to view sales
requirePermission('view_sales');

// Validate sale_id
if (!isset($_GET['sale_id']) || !is_numeric($_GET['sale_id'])) {
    die("Invalid sale ID");
}

$sale_id = (int)$_GET['sale_id'];

// Get sale details
$sql = "SELECT s.*, 
        u.username as cashier_name,
        DATE_FORMAT(s.created_at, '%Y-%m-%d %H:%i:%s') as formatted_date
        FROM sales s
        LEFT JOIN users u ON s.cashier_id = u.user_id
        WHERE s.sale_id = ?";
$sale = fetchRow($sql, [$sale_id]);

if (!$sale) {
    die("Sale not found");
}

// Get sale items
$sql = "SELECT si.*, p.name, p.sku, p.image_url
        FROM sale_items si
        JOIN products p ON si.product_id = p.product_id
        WHERE si.sale_id = ?";
$items = fetchAll($sql, [$sale_id]);

// Get store info from system settings
$sql = "SELECT * FROM system_settings WHERE setting_key IN ('store_name', 'store_address', 'store_phone', 'store_email')";
$settings_array = fetchAll($sql);

$settings = [];
foreach ($settings_array as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo htmlspecialchars($sale['transaction_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .receipt-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .receipt {
            width: 290px; /* Fixed width to match typical receipt size */
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 8px;
            border-radius: 4px;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.2;
        }
        .receipt-title {
            font-size: 14px;
            font-weight: bold;
            margin: 2px 0;
        }
        .receipt-info {
            margin: 8px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
            line-height: 1.3;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 10px;
        }
        .receipt-table th, .receipt-table td {
            padding: 2px;
            text-align: left;
        }
        .receipt-table th {
            font-size: 10px;
        }
        .receipt-table .amount {
            text-align: right;
        }
        .receipt-total {
            margin: 8px 0;
            border-top: 1px dashed #000;
            padding-top: 5px;
            font-size: 10px;
            line-height: 1.3;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 8px;
            border-top: 1px dashed #000;
            padding-top: 5px;
            font-size: 9px;
            line-height: 1.2;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .no-print {
            margin: 20px auto;
            text-align: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: rgba(255,255,255,0.9);
            padding: 10px;
        }
        @media print {
            body {
                background-color: #fff;
            }
            .receipt-container {
                padding: 0;
                display: block;
                min-height: auto;
            }
            .receipt {
                width: 100%;
                max-width: 290px;
                box-shadow: none;
                padding: 0;
                margin: 0 auto;
            }
            .no-print {
                display: none;
            }
            @page {
                margin: 0;
                size: 80mm auto;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print();">Print Receipt</button>
        <button class="btn btn-secondary" onclick="window.close();">Close</button>
    </div>

    <div class="receipt-container">
        <div class="receipt">
            <div class="receipt-header">
                <div class="receipt-title"><?php echo htmlspecialchars($settings['store_name'] ?? 'NexInvent Store'); ?></div>
                <div><?php echo htmlspecialchars($settings['store_address'] ?? '123 Main Street, City'); ?></div>
                <div>Phone: <?php echo htmlspecialchars($settings['store_phone'] ?? '555-1234'); ?></div>
                <div>Email: <?php echo htmlspecialchars($settings['store_email'] ?? 'info@example.com'); ?></div>
            </div>

            <div class="receipt-info">
                <div>Transaction #: <?php echo htmlspecialchars($sale['transaction_number']); ?></div>
                <div>Date: <?php echo htmlspecialchars($sale['formatted_date']); ?></div>
                <div>Cashier: <?php echo htmlspecialchars($sale['cashier_name']); ?></div>
                <div>Payment Method: <?php echo ucfirst(htmlspecialchars($sale['payment_method'])); ?></div>
            </div>

            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align:center">Qty</th>
                        <th class="amount">Price</th>
                        <th class="amount">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td style="text-align:center"><?php echo $item['quantity']; ?></td>
                        <td class="amount"><?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="amount"><?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="receipt-total">
                <div style="display:flex; justify-content:space-between">
                    <div>Subtotal:</div>
                    <div class="text-right"><?php echo number_format($sale['subtotal'], 2); ?></div>
                </div>
                <div style="display:flex; justify-content:space-between">
                    <div>Tax:</div>
                    <div class="text-right"><?php echo number_format($sale['tax_amount'], 2); ?></div>
                </div>
                <?php if ($sale['discount_amount'] > 0): ?>
                <div style="display:flex; justify-content:space-between">
                    <div>Discount:</div>
                    <div class="text-right"><?php echo number_format($sale['discount_amount'], 2); ?></div>
                </div>
                <?php endif; ?>
                <div style="display:flex; justify-content:space-between; font-weight:bold">
                    <div>TOTAL:</div>
                    <div class="text-right"><?php echo number_format($sale['total_amount'], 2); ?></div>
                </div>
            </div>

            <div class="receipt-footer">
                <p style="margin:1px">Thank you for your purchase!</p>
                <p style="margin:1px">Please keep this receipt for any returns or exchanges.</p>
                <p style="margin:1px"><?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html> 