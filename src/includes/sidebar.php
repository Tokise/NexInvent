<?php
// Get current page for active menu highlighting
$current_path = $_SERVER['PHP_SELF'];
$path_parts = explode('/', trim($current_path, '/'));
$current_section = isset($path_parts[count($path_parts)-2]) ? $path_parts[count($path_parts)-2] : '';
$current_subsection = isset($path_parts[count($path_parts)-1]) ? $path_parts[count($path_parts)-1] : '';

// Function to check if a path is active
if (!function_exists('isPathActive')) {
    function isPathActive($section, $subsection = '') {
        global $current_section, $current_subsection;
        if (empty($subsection)) {
            return $current_section === $section;
        }
        return $current_section === $section && $current_subsection === $subsection;
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    return;
}
?>


<div class="sidebar">
    <div class="sidebar-brand">
        <a href="/NexInvent/src/modules/dashboard/index.php">
            <img src="/NexInvent/assets/LOGO.png" alt="NexInvent Logo" class="img-fluid">
        </a>
    </div>
    
    <div class="sidebar-content">
        <div class="nav-section">
            <div class="nav-header">Main Navigation</div>
            <nav>
                <a href="/NexInvent/src/modules/dashboard/index.php" class="sidebar-link <?php echo isPathActive('dashboard') ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                
                <?php if ($_SESSION['role'] !== 'employee'): ?>
                <a href="/NexInvent/src/modules/stock/index.php" class="sidebar-link <?php echo isPathActive('stock') || isPathActive('movements') ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam me-2"></i> Stock
                </a>
                
                <a href="/NexInvent/src/modules/categories/index.php" class="sidebar-link <?php echo isPathActive('categories') ? 'active' : ''; ?>">
                    <i class="bi bi-tags me-2"></i> Categories
                </a>    
                
                <a href="/NexInvent/src/modules/products/index.php" class="sidebar-link <?php echo isPathActive('products') || (isPathActive('products')) ? 'active' : ''; ?>">
                    <i class="bi bi-cart3 me-2"></i> Products
                </a>
                <?php endif; ?>

                <?php if ($_SESSION['role'] !== 'manager'): ?>
                <a href="/NexInvent/src/modules/pos/index.php" class="sidebar-link <?php echo isPathActive('pos') ? 'active' : ''; ?>">
                    <i class="bi bi-receipt me-2"></i> Point of Sale
                </a>
                <?php endif; ?>

                <a href="/NexInvent/src/modules/sales/index.php" class="sidebar-link <?php echo isPathActive('sales') || (isPathActive('sales')) ? 'active' : ''; ?>">
                    <i class="bi bi-graph-up me-2"></i> Sales
                </a>

                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="/NexInvent/src/modules/purchases/index.php" class="sidebar-link <?php echo isPathActive('purchases') ? 'active' : ''; ?>">
                    <i class="bi bi-bag me-2"></i> Purchases
                </a>
                <?php endif; ?>
            </nav>
        </div>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-header">Management</div>
            <nav>
                <a href="/NexInvent/src/modules/employees/index.php" class="sidebar-link <?php echo isPathActive('employees') ? 'active' : ''; ?>">
                    <i class="bi bi-people me-2"></i> Employees
                </a>
            </nav>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-header">Reports & Settings</div>
            <nav>
                <?php if ($_SESSION['role'] !== 'employee'): ?>
                <a href="/NexInvent/src/modules/reports/index.php" class="sidebar-link <?php echo isPathActive('reports') ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text me-2"></i> Reports
                </a>
                <?php endif; ?>
                
                <a href="/NexInvent/src/modules/users/settings.php" class="sidebar-link <?php echo isPathActive('users', 'settings') ? 'active' : ''; ?>">
                    <i class="bi bi-gear me-2"></i> My Account
                </a>
            </nav>
        </div>
    </div>

    <div class="logout-section">
        <a href="/NexInvent/src/login/logout.php" class="btn btn-outline-dark w-100">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>