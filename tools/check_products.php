<?php
require_once 'src/config/db.php';

echo "Checking products in pending orders...\n";

try {
    // 1. Get products in low stock
    $sql = "SELECT p.product_id, p.name, p.in_stock_quantity, p.reorder_level 
            FROM products p 
            WHERE p.in_stock_quantity <= p.reorder_level
            ORDER BY p.name ASC";
    $low_stock = fetchAll($sql);
    
    echo "Found " . count($low_stock) . " products in low stock.\n";
    
    // 2. Get products in pending POs
    $sql = "SELECT DISTINCT p.product_id, p.name, p.in_stock_quantity, po.po_number, po.status
            FROM products p
            JOIN purchase_order_items poi ON p.product_id = poi.product_id
            JOIN purchase_orders po ON poi.po_id = po.po_id
            WHERE po.status IN ('pending', 'approved')
            ORDER BY p.name ASC";
    $pending_po_products = fetchAll($sql);
    
    echo "Found " . count($pending_po_products) . " products in pending purchase orders.\n";
    
    // 3. Find products that are in both lists
    $in_both = [];
    foreach ($low_stock as $low) {
        foreach ($pending_po_products as $pending) {
            if ($low['product_id'] == $pending['product_id']) {
                $in_both[] = [
                    'product_id' => $low['product_id'],
                    'name' => $low['name'],
                    'stock' => $low['in_stock_quantity'],
                    'threshold' => $low['reorder_level'],
                    'po_number' => $pending['po_number'],
                    'po_status' => $pending['status']
                ];
                break;
            }
        }
    }
    
    echo "Found " . count($in_both) . " products that are both in low stock and pending POs.\n";
    echo "These shouldn't appear in the low stock tab:\n";
    echo "-----------------------------------------------------------------\n";
    
    foreach ($in_both as $item) {
        echo "ID: {$item['product_id']}, Name: {$item['name']}, Stock: {$item['stock']}/{$item['threshold']}, PO: {$item['po_number']} ({$item['po_status']})\n";
    }
    
    echo "-----------------------------------------------------------------\n";
    
    // 4. Check the SQL query that should exclude these items
    $sql = "SELECT COUNT(*) FROM products p 
            WHERE p.in_stock_quantity <= p.reorder_level 
            AND p.product_id NOT IN (
                SELECT DISTINCT poi.product_id 
                FROM purchase_order_items poi
                JOIN purchase_orders po ON poi.po_id = po.po_id
                WHERE po.status IN ('pending', 'approved')
            )
            AND p.product_id NOT IN (
                SELECT DISTINCT product_id
                FROM pending_stock_additions
                WHERE status = 'pending'
            )";
    
    $count = fetchValue($sql);
    
    echo "The correct SQL query shows $count products should be in the low stock tab.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 