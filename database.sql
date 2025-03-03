-- NexInvent Database Schema

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS nexinvent;
USE nexinvent;

-- Disable foreign key checks temporarily for safe table creation
SET FOREIGN_KEY_CHECKS=0;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'employee', 'customer') NOT NULL,
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

-- Products Table
CREATE TABLE IF NOT EXISTS products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    sku VARCHAR(50) UNIQUE NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity_in_stock INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(category_id),
    CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(user_id),
    CONSTRAINT fk_products_updated_by FOREIGN KEY (updated_by) REFERENCES users(user_id)
);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Sales Orders Table
CREATE TABLE IF NOT EXISTS sales_orders (
    sale_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    payment_status ENUM('unpaid', 'partially_paid', 'paid') NOT NULL DEFAULT 'unpaid',
    payment_method ENUM('cash', 'e_wallet') DEFAULT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_date TIMESTAMP NULL DEFAULT NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    shipping_address TEXT,
    billing_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_archived (archived)
);

-- Sales Order Items Table
CREATE TABLE IF NOT EXISTS sales_order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    discount_rate DECIMAL(5,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales_orders(sale_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Stock Movements Table (Updated with reference_id)
CREATE TABLE IF NOT EXISTS stock_movements (
    movement_id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    quantity INT NOT NULL,
    type ENUM('initial', 'adjustment', 'sale', 'purchase') NOT NULL,
    reference_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Suppliers Table
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Add supplier performance tracking columns
ALTER TABLE suppliers
ADD COLUMN performance_score DECIMAL(3,2) DEFAULT 0.00,
ADD COLUMN total_orders_completed INT DEFAULT 0,
ADD COLUMN last_order_date TIMESTAMP NULL,
ADD COLUMN is_verified BOOLEAN DEFAULT FALSE;

-- Add unique indexes to suppliers table
ALTER TABLE suppliers
ADD UNIQUE INDEX idx_supplier_email (email),
ADD UNIQUE INDEX idx_supplier_company (company_name);

-- Create supplier ratings table
CREATE TABLE IF NOT EXISTS supplier_ratings (
    rating_id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    po_id INT NOT NULL,
    rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Add trigger to update supplier performance when purchase order is completed
DELIMITER //
CREATE TRIGGER update_supplier_performance
AFTER UPDATE ON purchase_orders
FOR EACH ROW
BEGIN
    IF NEW.status = 'received' AND OLD.status != 'received' THEN
        UPDATE suppliers s
        SET 
            s.total_orders_completed = (
                SELECT COUNT(*) 
                FROM purchase_orders 
                WHERE supplier_id = NEW.supplier_id 
                AND status = 'received'
            ),
            s.last_order_date = NEW.created_at,
            s.is_verified = TRUE,
            s.performance_score = (
                SELECT AVG(rating)
                FROM supplier_ratings
                WHERE supplier_id = NEW.supplier_id
            )
        WHERE s.supplier_id = NEW.supplier_id;
    END IF;
END//
DELIMITER ;

-- Purchase Orders Table
CREATE TABLE IF NOT EXISTS purchase_orders (
    po_id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT NOT NULL,
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'received', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Purchase Order Items Table
CREATE TABLE IF NOT EXISTS po_items (
    po_item_id INT PRIMARY KEY AUTO_INCREMENT,
    po_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
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
    role ENUM('admin', 'manager', 'employee', 'customer') NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id),
    UNIQUE KEY unique_role_permission (role, permission_id)
);

-- Employee Details Table
CREATE TABLE IF NOT EXISTS employee_details (
    employee_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(50),
    position VARCHAR(50),
    hire_date DATE,
    salary DECIMAL(10,2),
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

-- Orders Table
CREATE TABLE IF NOT EXISTS orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Order Shipping Table
CREATE TABLE IF NOT EXISTS order_shipping (
    shipping_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(50) NOT NULL,
    shipping_method VARCHAR(50) NOT NULL,
    shipping_cost DECIMAL(10,2) NOT NULL,
    estimated_delivery DATE NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
);

-- Order History Table
CREATE TABLE IF NOT EXISTS order_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') NOT NULL,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    reference_id INT NULL,
    for_role ENUM('admin', 'manager', 'employee', 'customer') NOT NULL,
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

-- Insert default permissions
INSERT INTO permissions (name, description) VALUES
('view_dashboard', 'View dashboard and statistics'),
('manage_inventory', 'Add, edit, and delete inventory items'),
('view_inventory', 'View inventory items'),
('manage_products', 'Add, edit, and delete products'),
('view_products', 'View products'),
('manage_sales', 'Create and manage sales orders'),
('view_sales', 'View sales orders'),
('manage_purchases', 'Create and manage purchase orders'),
('view_purchases', 'View purchase orders'),
('manage_suppliers', 'Add, edit, and delete suppliers'),
('view_suppliers', 'View suppliers'),
('manage_employees', 'Add, edit, and delete employees'),
('view_employees', 'View employees'),
('manage_payroll', 'Manage payroll and salaries'),
('view_payroll', 'View payroll information'),
('view_reports', 'View reports and analytics'),
('manage_settings', 'Manage system settings'),
('manage_users', 'Add, edit, and delete users'),
('create_sale', 'Create new sales orders'),
('view_catalog', 'View product catalog'),
('place_order', 'Place new orders'),
('track_orders', 'Track order status'),
('view_order_history', 'View order history'),
('manage_profile', 'Manage own profile'),
('manage_wishlist', 'Manage wishlist items'),
('process_payments', 'Process order payments')
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
    'manage_suppliers',
    'view_suppliers',
    'view_employees',
    'view_payroll',
    'view_reports',
    'create_sale',
    'process_payments'
);

INSERT IGNORE INTO role_permissions (role, permission_id)
SELECT 'employee', permission_id FROM permissions 
WHERE name IN (
    'view_dashboard',
    'view_inventory',
    'view_products',
    'view_sales',
    'view_purchases',
    'view_suppliers',
    'create_sale',
    'process_payments'
);

INSERT IGNORE INTO role_permissions (role, permission_id)
SELECT 'customer', permission_id FROM permissions 
WHERE name IN (
    'view_catalog',
    'place_order',
    'track_orders',
    'view_order_history',
    'manage_profile',
    'manage_wishlist'
);

-- Insert default admin user if not exists
INSERT INTO users (username, password, email, full_name, role, status)
SELECT 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@nexinvent.local', 'System Administrator', 'admin', 'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- Insert test data if none exists
INSERT INTO categories (name, description)
SELECT 'General', 'General category for products'
WHERE NOT EXISTS (SELECT 1 FROM categories LIMIT 1);

INSERT INTO customers (name, email, phone, address)
SELECT 'Walk-in Customer', 'customer@example.com', '1234567890', '123 Test Street'
WHERE NOT EXISTS (SELECT 1 FROM customers LIMIT 1);

-- Insert test customer account if not exists
INSERT INTO users (username, password, email, full_name, role, status)
SELECT 'customer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer@example.com', 'Test Customer', 'customer', 'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'customer');

-- Wishlist Table
CREATE TABLE IF NOT EXISTS wishlist (
    wishlist_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    UNIQUE KEY unique_user_product (user_id, product_id)
);

-- Customer Profiles Table (extends user information for customers)
CREATE TABLE IF NOT EXISTS customer_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    default_shipping_address TEXT,
    default_billing_address TEXT,
    preferred_payment_method ENUM('credit_card', 'bank_transfer', 'cash', 'check') NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS shopping_cart (
    cart_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    UNIQUE KEY unique_cart_item (user_id, product_id)
);


-- Add created_by column to employee_details if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = "employee_details";
SET @columnname = "created_by";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD ", @columnname, " INT, ADD FOREIGN KEY (", @columnname, ") REFERENCES users(user_id)")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Insert sample order
INSERT INTO orders (customer_id, order_number, status, payment_method, payment_status, subtotal, tax, total, created_by, notes)
VALUES (1, 'ORD-20231001-0001', 'processing', 'Credit Card', 'paid', 100.00, 10.00, 110.00, 1, 'Sample order for testing');

INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
VALUES (1, 1, 2, 50.00, 100.00);

INSERT INTO order_shipping (order_id, address, city, state, postal_code, country, shipping_method, shipping_cost, estimated_delivery)
VALUES (1, '123 Main St', 'New York', 'NY', '10001', 'United States', 'Standard Shipping', 15.00, DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY));

INSERT INTO order_history (order_id, status, notes, created_by)
VALUES (1, 'processing', 'Order created and processing', 1);

-- Insert test ratings if none exist
INSERT INTO supplier_ratings (supplier_id, po_id, rating, created_by)
SELECT 
    po.supplier_id,
    po.po_id,
    ROUND(RAND() * 3 + 2, 2), -- Random rating between 2.00 and 5.00
    1 -- Admin user
FROM purchase_orders po
WHERE po.status = 'received'
AND NOT EXISTS (
    SELECT 1 FROM supplier_ratings 
    WHERE po_id = po.po_id
)
LIMIT 10;