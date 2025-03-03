<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user has a specific permission
function hasPermission($permission_name) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    try {
        require_once __DIR__ . '/../config/db.php';
        
        $sql = "SELECT COUNT(*) FROM role_permissions rp 
                JOIN permissions p ON rp.permission_id = p.permission_id 
                WHERE rp.role = ? AND p.name = ?";
                
        return fetchValue($sql, [$_SESSION['role'], $permission_name]) > 0;
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

// Function to check multiple permissions (returns true if user has ANY of the permissions)
function hasAnyPermission($permissions) {
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }
    return false;
}

// Function to check if user has ALL specified permissions
function hasAllPermissions($permissions) {
    foreach ($permissions as $permission) {
        if (!hasPermission($permission)) {
            return false;
        }
    }
    return true;
}

// Function to get all permissions for current user
function getUserPermissions() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return [];
    }
    
    try {
        require_once __DIR__ . '/../config/db.php';
        
        $sql = "SELECT p.name FROM role_permissions rp 
                JOIN permissions p ON rp.permission_id = p.permission_id 
                WHERE rp.role = ?";
                
        $permissions = fetchAll($sql, [$_SESSION['role']]);
        return array_column($permissions, 'name');
    } catch (Exception $e) {
        error_log("Get permissions error: " . $e->getMessage());
        return [];
    }
}

// Function to check permission and redirect if not authorized
function requirePermission($permission_name, $redirect_url = '../index.php') {
    if (!hasPermission($permission_name)) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header("Location: $redirect_url");
        exit();
    }
}

// Function to render content based on permission
function renderIfHasPermission($permission_name, $content) {
    if (hasPermission($permission_name)) {
        echo $content;
    }
}

// Function to disable buttons/forms based on permission
function getDisabledAttribute($permission_name) {
    return hasPermission($permission_name) ? '' : 'disabled';
}

// Function to hide elements based on permission
function getHiddenClass($permission_name) {
    return hasPermission($permission_name) ? '' : 'd-none';
} 