-- NexInvent Database Schema (Refactored)

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS nexinvent;
USE nexinvent;

-- Disable foreign key checks temporarily for safe table creation
SET FOREIGN_KEY_CHECKS=0;

-- System Settings Table (for currency and other global settings)
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
('currency_code', 'USD'),
('currency_symbol', '$'),
('currency_position', 'before') -- 'before' or 'after'
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'employee') NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by INT NULL,
    updated_by INT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Add foreign keys for users table after creation (self-referencing)
ALTER TABLE users
ADD CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(user_id),
ADD CONSTRAINT fk_users_updated_by FOREIGN KEY (updated_by) REFERENCES users(user_id);

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_created_by FOREIGN KEY (created_by) REFERENCES users(user_id),
    CONSTRAINT fk_categories_updated_by FOREIGN KEY (updated_by) REFERENCES users(user_id)
);

-- Products Table (Updated with in_threshold_amount)
CREATE TABLE IF NOT EXISTS products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    sku VARCHAR(50) UNIQUE NOT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    in_stock_quantity INT NOT NULL DEFAULT 0,
    out_stock_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL,
    out_threshold_amount INT NOT NULL,
    in_threshold_amount INT NOT NULL DEFAULT 10,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(category_id),
    CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(user_id),
    CONSTRAINT fk_products_updated_by FOREIGN KEY (updated_by) REFERENCES users(user_id)
);

-- Stock Movements Table
CREATE TABLE IF NOT EXISTS stock_movements (
    movement_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    quantity INT NOT NULL,
    type ENUM('in_initial', 'in_purchase', 'in_to_out', 'out_to_in', 'out_sale', 'out_adjustment') NOT NULL,
    reference_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Permissions Table
CREATE TABLE IF NOT EXISTS permissions (
    permission_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Role Permissions Table
CREATE TABLE IF NOT EXISTS role_permissions (
    role_permission_id INT PRIMARY KEY AUTO_INCREMENT,
    role ENUM('admin', 'manager', 'employee') NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id),
    UNIQUE KEY unique_role_permission (role, permission_id)
);

-- Employee Details Table
CREATE TABLE IF NOT EXISTS employee_details (
    employee_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    temp_name VARCHAR(100),
    department VARCHAR(50),
    position VARCHAR(50),
    hire_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Attendance Table
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIMESTAMP NULL DEFAULT NULL,
    time_out TIMESTAMP NULL DEFAULT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_details(employee_id)
);

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    UNIQUE KEY unique_user_setting (user_id, setting_key)
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    reference_id INT NULL,
    for_role ENUM('admin', 'manager', 'employee') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dashboard Statistics Table
CREATE TABLE IF NOT EXISTS dashboard_stats (
    stat_id INT PRIMARY KEY AUTO_INCREMENT,
    stat_date DATE NOT NULL,
    total_sales INT DEFAULT 0,
    monthly_revenue DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_stat_date (stat_date)
);

-- Sales Table (POS System)
CREATE TABLE IF NOT EXISTS sales (
    sale_id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash', 'card', 'transfer') NOT NULL,
    payment_status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT,
    cashier_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cashier_id) REFERENCES users(user_id)
);

-- Sale Items Table
CREATE TABLE IF NOT EXISTS sale_items (
    sale_item_id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(sale_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Purchase Orders Table (Auto-generated for low stock)
CREATE TABLE IF NOT EXISTS purchase_orders (
    po_id INT PRIMARY KEY AUTO_INCREMENT,
    po_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('pending', 'approved', 'ordered', 'received', 'cancelled') NOT NULL DEFAULT 'pending',
    supplier_name VARCHAR(100),
    supplier_contact VARCHAR(100),
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT,
    is_auto_generated BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    approved_by INT NULL,
    received_by INT NULL,
    received_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id),
    FOREIGN KEY (received_by) REFERENCES users(user_id)
);

-- Purchase Order Items Table
CREATE TABLE IF NOT EXISTS purchase_order_items (
    po_item_id INT PRIMARY KEY AUTO_INCREMENT,
    po_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    received_quantity INT DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Daily Sales Summary Table
CREATE TABLE IF NOT EXISTS daily_sales_summary (
    summary_id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    total_sales INT NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date (date)
);

-- Reports Table
CREATE TABLE IF NOT EXISTS reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('sales', 'inventory', 'employee', 'purchase') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    report_data JSON,
    date_range_start DATE,
    date_range_end DATE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Purchase Stock Movements Table
CREATE TABLE IF NOT EXISTS purchase_stock_movements (
    movement_id INT PRIMARY KEY AUTO_INCREMENT,
    po_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'received', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Pending Stock Additions Table
CREATE TABLE IF NOT EXISTS pending_stock_additions (
    addition_id INT PRIMARY KEY AUTO_INCREMENT,
    po_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- Insert default permissions
INSERT INTO permissions (name, description) VALUES
('view_dashboard', 'View dashboard and statistics'),
('manage_inventory', 'Add, edit, and delete inventory items'),
('view_inventory', 'View inventory items'),
('manage_products', 'Add, edit, and delete products'),
('view_products', 'View products'),
('manage_sales', 'Create and manage sales'),
('view_sales', 'View sales'),
('manage_purchases', 'Create and manage purchase orders'),
('view_purchases', 'View purchase orders'),
('manage_employees', 'Add, edit, and delete employees'),
('view_employees', 'View employees'),
('manage_reports', 'Generate and manage reports'),
('view_reports', 'View reports'),
('manage_settings', 'Manage system settings')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign permissions to roles
INSERT IGNORE INTO role_permissions (role, permission_id) 
SELECT 'admin', permission_id FROM permissions;

INSERT IGNORE INTO role_permissions (role, permission_id)
SELECT 'manager', permission_id FROM permissions 
WHERE name IN (
    'view_dashboard',
    'manage_inventory',
    'view_inventory',
    'manage_products',
    'view_products',
    'manage_sales',
    'view_sales',
    'manage_purchases',
    'view_purchases',
    'view_employees',
    'view_reports'
);

INSERT IGNORE INTO role_permissions (role, permission_id)
SELECT 'employee', permission_id FROM permissions 
WHERE name IN (
    'view_dashboard',
    'view_inventory',
    'view_products',
    'view_sales',
    'view_purchases'
);

-- Insert default admin user if not exists
INSERT INTO users (username, password, email, full_name, role, status)
SELECT 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@nexinvent.local', 'System Administrator', 'admin', 'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- Insert test category if none exists
INSERT INTO categories (name, description, created_by)
SELECT 'General', 'General category for products', 1
WHERE NOT EXISTS (SELECT 1 FROM categories LIMIT 1);