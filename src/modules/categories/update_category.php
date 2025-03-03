<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/permissions.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Check if user is logged in and has permission
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('You must be logged in to perform this action');
    }

    if (!hasPermission('manage_products')) {
        throw new Exception('You do not have permission to manage categories');
    }

    // Validate required fields
    if (!isset($_POST['category_id']) || !is_numeric($_POST['category_id'])) {
        throw new Exception('Invalid category ID');
    }

    if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
        throw new Exception('Category name is required');
    }

    // Check if category exists
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->execute([$_POST['category_id']]);
    $category = $stmt->fetch();
    
    if (!$category) {
        throw new Exception('Category not found');
    }

    // Check if name already exists for other categories
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND category_id != ?");
    $stmt->execute([trim($_POST['name']), $_POST['category_id']]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('A category with this name already exists');
    }

    // Update category
    $stmt = $pdo->prepare("
        UPDATE categories SET 
            name = ?,
            description = ?,
            updated_by = ?,
            updated_at = NOW()
        WHERE category_id = ?
    ");

    $stmt->execute([
        trim($_POST['name']),
        $_POST['description'] ?? '',
        $_SESSION['user_id'],
        $_POST['category_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Category updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 