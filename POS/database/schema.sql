-- POS Cashiering System Database Schema
-- Created: 2026-02-18

CREATE DATABASE IF NOT EXISTS pos_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pos_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    avatar_url VARCHAR(255) NULL DEFAULT NULL,
    role_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role permissions junction table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(100) UNIQUE,
    product_name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    cost DECIMAL(10,2) DEFAULT 0.00,
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 0,
    unit VARCHAR(50) DEFAULT 'pcs',
    image_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_barcode (barcode),
    INDEX idx_category (category_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    final_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'e_wallet') NOT NULL,
    payment_status ENUM('pending', 'completed', 'refunded', 'voided') DEFAULT 'completed',
    voided_at TIMESTAMP NULL,
    voided_by INT NULL,
    void_reason TEXT NULL,
    is_synced TINYINT(1) DEFAULT 0,
    synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_transaction_number (transaction_number),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    INDEX idx_synced (is_synced)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction items table
CREATE TABLE IF NOT EXISTS transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_transaction (transaction_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Offline transactions table (for tracking unsynced transactions)
CREATE TABLE IF NOT EXISTS offline_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    local_transaction_id VARCHAR(100) UNIQUE NOT NULL,
    transaction_data TEXT NOT NULL,
    status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_local_id (local_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cash counts table
CREATE TABLE IF NOT EXISTS cash_counts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    shift_date DATE NOT NULL,
    shift_type ENUM('morning', 'afternoon', 'night', 'full_day') DEFAULT 'full_day',
    beginning_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ending_cash DECIMAL(10,2) DEFAULT NULL,
    expected_cash DECIMAL(10,2) DEFAULT NULL,
    actual_cash DECIMAL(10,2) DEFAULT NULL,
    difference DECIMAL(10,2) DEFAULT NULL,
    status ENUM('open', 'closed') DEFAULT 'open',
    beginning_notes TEXT NULL,
    ending_notes TEXT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_user_date (user_id, shift_date),
    INDEX idx_date (shift_date),
    INDEX idx_status (status),
    UNIQUE KEY unique_user_shift (user_id, shift_date, shift_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cash count items table
CREATE TABLE IF NOT EXISTS cash_count_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_count_id INT NOT NULL,
    denomination DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    count_type ENUM('beginning', 'ending') NOT NULL,
    FOREIGN KEY (cash_count_id) REFERENCES cash_counts(id) ON DELETE CASCADE,
    INDEX idx_cash_count (cash_count_id),
    INDEX idx_count_type (count_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory transactions table
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    transaction_type ENUM('stock_in', 'stock_out', 'adjustment', 'sale', 'return', 'damaged', 'expired') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    reference_type VARCHAR(50) NULL COMMENT 'transaction, purchase_order, adjustment, etc.',
    reference_id INT NULL COMMENT 'ID of related transaction or document',
    notes TEXT NULL,
    user_id INT NOT NULL COMMENT 'User who performed the transaction',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_product (product_id),
    INDEX idx_type (transaction_type),
    INDEX idx_date (created_at),
    INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    zip_code VARCHAR(20) NULL,
    country VARCHAR(100) DEFAULT 'Philippines',
    tax_id VARCHAR(100) NULL,
    payment_terms INT DEFAULT 30 COMMENT 'Payment terms in days',
    credit_limit DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Current outstanding balance',
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (supplier_name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Orders table
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery_date DATE NULL,
    delivery_date DATE NULL,
    status ENUM('pending', 'ordered', 'partial', 'received', 'cancelled') DEFAULT 'pending',
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    discount_amount DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_supplier (supplier_id),
    INDEX idx_status (status),
    INDEX idx_date (order_date),
    INDEX idx_po_number (po_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Order Items table
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(15,2) NOT NULL,
    received_quantity INT DEFAULT 0,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_po (purchase_order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounts Payable table
CREATE TABLE IF NOT EXISTS accounts_payable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    supplier_id INT NOT NULL,
    invoice_number VARCHAR(100) NULL,
    invoice_date DATE NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) NOT NULL,
    status ENUM('open', 'partial', 'paid', 'overdue') DEFAULT 'open',
    payment_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    INDEX idx_supplier (supplier_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_po (purchase_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AP Payments table
CREATE TABLE IF NOT EXISTS ap_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    accounts_payable_id INT NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'other') DEFAULT 'bank_transfer',
    check_number VARCHAR(100) NULL,
    reference_number VARCHAR(100) NULL,
    amount DECIMAL(15,2) NOT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (accounts_payable_id) REFERENCES accounts_payable(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_ap (accounts_payable_id),
    INDEX idx_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO roles (role_name, description) VALUES
('Super Admin', 'Full system access'),
('Admin', 'Administrative access'),
('Cashier', 'POS cashiering access'),
('Manager', 'Management and reporting access');

-- Insert default permissions
INSERT INTO permissions (permission_name, description) VALUES
('manage_users', 'Create, edit, and delete users'),
('manage_products', 'Create, edit, and delete products'),
('manage_categories', 'Create, edit, and delete categories'),
('manage_roles', 'Create, edit, and delete roles and permissions'),
('view_reports', 'View sales and inventory reports'),
('process_sales', 'Process sales transactions'),
('manage_settings', 'Manage system settings'),
('view_dashboard', 'View dashboard and analytics'),
('void_transactions', 'Void/cancel transactions'),
('manage_cash_count', 'Manage cashier cash counts'),
('manage_inventory', 'Manage inventory transactions and adjustments'),
('manage_suppliers', 'Manage suppliers and vendor information'),
('manage_purchase_orders', 'Create and manage purchase orders'),
('manage_accounts_payable', 'Manage accounts payable and payments'),
('view_ap_reports', 'View accounts payable reports');

-- Assign permissions to Super Admin role
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Assign permissions to Admin role
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE permission_name IN ('manage_products', 'manage_categories', 'view_reports', 'process_sales', 'view_dashboard', 'manage_inventory', 'manage_suppliers', 'manage_purchase_orders', 'manage_accounts_payable', 'view_ap_reports');

-- Assign permissions to Cashier role
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE permission_name IN ('process_sales', 'view_dashboard', 'manage_cash_count');

-- Assign permissions to Manager role
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE permission_name IN ('view_reports', 'view_dashboard', 'manage_products', 'manage_categories', 'manage_inventory', 'manage_suppliers', 'manage_purchase_orders', 'manage_accounts_payable', 'view_ap_reports');

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, full_name, role_id) VALUES
('admin', 'admin@pos.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 1);

-- Insert default categories
INSERT INTO categories (category_name, description) VALUES
('Beverages', 'Drinks and beverages'),
('Food', 'Food items'),
('Snacks', 'Snacks and chips'),
('Dairy', 'Dairy products'),
('Fruits & Vegetables', 'Fresh fruits and vegetables'),
('Meat & Seafood', 'Meat and seafood products'),
('Bakery', 'Bread and bakery items'),
('Household', 'Household items'),
('Personal Care', 'Personal care products'),
('Other', 'Other products');

-- Insert sample products
INSERT INTO products (barcode, product_name, description, category_id, price, cost, stock_quantity, min_stock_level, unit) VALUES
('1234567890123', 'Coca Cola 1.5L', 'Carbonated soft drink', 1, 45.00, 35.00, 100, 20, 'bottle'),
('1234567890124', 'Pepsi 1.5L', 'Carbonated soft drink', 1, 45.00, 35.00, 100, 20, 'bottle'),
('1234567890125', 'Bread White', 'White bread loaf', 7, 35.00, 25.00, 50, 10, 'loaf'),
('1234567890126', 'Milk 1L', 'Fresh milk', 4, 65.00, 50.00, 80, 15, 'bottle'),
('1234567890127', 'Eggs Dozen', 'Fresh chicken eggs', 4, 120.00, 95.00, 60, 12, 'dozen');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('store_name', 'POS Store', 'Store name'),
('tax_rate', '12', 'Tax rate percentage'),
('currency', 'PHP', 'Currency code'),
('receipt_footer', 'Thank you for shopping with us!', 'Receipt footer text'),
('offline_mode_enabled', '1', 'Enable offline mode');
