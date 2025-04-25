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
requirePermission('view_purchases');

// Get purchase order ID from URL
$po_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$po_id) {
    die("Invalid purchase order ID.");
}

// Fetch purchase order details
$sql = "SELECT po.*, 
        u1.username as created_by_name,
        u1.full_name as created_by_full_name,
        u2.username as approved_by_name,
        u2.full_name as approved_by_full_name,
        DATE_FORMAT(po.created_at, '%M %d, %Y') as formatted_date
        FROM purchase_orders po 
        LEFT JOIN users u1 ON po.created_by = u1.user_id
        LEFT JOIN users u2 ON po.approved_by = u2.user_id
        WHERE po.po_id = ?";
$po = fetchRow($sql, [$po_id]);

if (!$po) {
    die("Purchase order not found.");
}

// Fetch purchase order items
$sql = "SELECT poi.*, p.name as product_name, p.sku, c.name as category_name
        FROM purchase_order_items poi
        JOIN products p ON poi.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE poi.po_id = ?
        ORDER BY poi.po_item_id ASC";
$items = fetchAll($sql, [$po_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order #<?php echo htmlspecialchars($po['po_number']); ?></title>
    <style>
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
        @media print {
            body {
                padding: 0;
                margin: 20px;
            }
            @page {
                margin: 20px;
                size: A4;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">NexInvent</div>
        <div class="system-name">Inventory Management System</div>
        <div class="document-title">Purchase Order</div>
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
                <th class="text-end">Unit Price</th>
                <th class="text-end">Quantity</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                    <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td class="text-end"><?php echo $item['quantity']; ?></td>
                    <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6" class="text-end">Total Amount:</td>
                <td class="text-end">$<?php echo number_format($po['total_amount'], 2); ?></td>
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

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html> 