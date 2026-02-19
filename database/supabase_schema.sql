-- ============================================================
-- POS Cashiering System - Supabase (PostgreSQL) Schema
-- Convert from MySQL to PostgreSQL for Supabase
-- Run this in Supabase: SQL Editor → New Query → Paste & Run
-- ============================================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL,
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id SERIAL PRIMARY KEY,
    permission_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Role permissions junction table
CREATE TABLE IF NOT EXISTS role_permissions (
    id SERIAL PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE (role_id, permission_id)
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
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
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id SERIAL PRIMARY KEY,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    final_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL CHECK (payment_method IN ('cash','credit_card','e_wallet')),
    payment_status VARCHAR(20) DEFAULT 'completed' CHECK (payment_status IN ('pending','completed','refunded','voided')),
    voided_at TIMESTAMP NULL,
    voided_by INT NULL,
    void_reason TEXT NULL,
    is_synced BOOLEAN DEFAULT FALSE,
    synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- Transaction items table
CREATE TABLE IF NOT EXISTS transaction_items (
    id SERIAL PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Offline transactions table
CREATE TABLE IF NOT EXISTS offline_transactions (
    id SERIAL PRIMARY KEY,
    local_transaction_id VARCHAR(100) UNIQUE NOT NULL,
    transaction_data TEXT NOT NULL,
    status VARCHAR(10) DEFAULT 'pending' CHECK (status IN ('pending','synced','failed')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cash counts table
CREATE TABLE IF NOT EXISTS cash_counts (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    shift_date DATE NOT NULL,
    shift_type VARCHAR(20) DEFAULT 'full_day' CHECK (shift_type IN ('morning','afternoon','night','full_day')),
    beginning_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ending_cash DECIMAL(10,2) DEFAULT NULL,
    expected_cash DECIMAL(10,2) DEFAULT NULL,
    actual_cash DECIMAL(10,2) DEFAULT NULL,
    difference DECIMAL(10,2) DEFAULT NULL,
    status VARCHAR(10) DEFAULT 'open' CHECK (status IN ('open','closed')),
    beginning_notes TEXT NULL,
    ending_notes TEXT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE (user_id, shift_date, shift_type)
);

-- Cash count items table
CREATE TABLE IF NOT EXISTS cash_count_items (
    id SERIAL PRIMARY KEY,
    cash_count_id INT NOT NULL,
    denomination DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    count_type VARCHAR(10) NOT NULL CHECK (count_type IN ('beginning','ending')),
    FOREIGN KEY (cash_count_id) REFERENCES cash_counts(id) ON DELETE CASCADE
);

-- Inventory transactions table
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    transaction_type VARCHAR(20) NOT NULL CHECK (transaction_type IN ('stock_in','stock_out','adjustment','sale','return','damaged','expired')),
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    notes TEXT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- Suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    id SERIAL PRIMARY KEY,
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
    payment_terms INT DEFAULT 30,
    credit_limit DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Purchase Orders table
CREATE TABLE IF NOT EXISTS purchase_orders (
    id SERIAL PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery_date DATE NULL,
    delivery_date DATE NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','ordered','partial','received','cancelled')),
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    discount_amount DECIMAL(15,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) DEFAULT 0.00,
    payment_status VARCHAR(10) DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid','partial','paid')),
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Purchase Order Items table
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id SERIAL PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(15,2) NOT NULL,
    received_quantity INT DEFAULT 0,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Accounts Payable table
CREATE TABLE IF NOT EXISTS accounts_payable (
    id SERIAL PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    supplier_id INT NOT NULL,
    invoice_number VARCHAR(100) NULL,
    invoice_date DATE NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) NOT NULL,
    status VARCHAR(10) DEFAULT 'open' CHECK (status IN ('open','partial','paid','overdue')),
    payment_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
);

-- AP Payments table
CREATE TABLE IF NOT EXISTS ap_payments (
    id SERIAL PRIMARY KEY,
    accounts_payable_id INT NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(20) DEFAULT 'bank_transfer' CHECK (payment_method IN ('cash','check','bank_transfer','credit_card','other')),
    check_number VARCHAR(100) NULL,
    reference_number VARCHAR(100) NULL,
    amount DECIMAL(15,2) NOT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (accounts_payable_id) REFERENCES accounts_payable(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Insert default roles
INSERT INTO roles (role_name, description) VALUES
('Super Admin', 'Full system access'),
('Admin', 'Administrative access'),
('Cashier', 'POS cashiering access'),
('Manager', 'Management and reporting access')
ON CONFLICT (role_name) DO NOTHING;

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
('view_ap_reports', 'View accounts payable reports')
ON CONFLICT (permission_name) DO NOTHING;

-- Assign ALL permissions to Super Admin (role id=1)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions
ON CONFLICT DO NOTHING;

-- Assign permissions to Admin (role id=2)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE permission_name IN ('manage_products','manage_categories','view_reports','process_sales','view_dashboard','manage_inventory','manage_suppliers','manage_purchase_orders','manage_accounts_payable','view_ap_reports')
ON CONFLICT DO NOTHING;

-- Assign permissions to Cashier (role id=3)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE permission_name IN ('process_sales','view_dashboard','manage_cash_count')
ON CONFLICT DO NOTHING;

-- Assign permissions to Manager (role id=4)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions
WHERE permission_name IN ('view_reports','view_dashboard','manage_products','manage_categories','manage_inventory','manage_suppliers','manage_purchase_orders','manage_accounts_payable','view_ap_reports')
ON CONFLICT DO NOTHING;

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, full_name, role_id) VALUES
('admin', 'admin@pos.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 1)
ON CONFLICT (username) DO NOTHING;

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
('Other', 'Other products')
ON CONFLICT DO NOTHING;

-- Insert sample products
INSERT INTO products (barcode, product_name, description, category_id, price, cost, stock_quantity, min_stock_level, unit) VALUES
('1234567890123', 'Coca Cola 1.5L', 'Carbonated soft drink', 1, 45.00, 35.00, 100, 20, 'bottle'),
('1234567890124', 'Pepsi 1.5L', 'Carbonated soft drink', 1, 45.00, 35.00, 100, 20, 'bottle'),
('1234567890125', 'Bread White', 'White bread loaf', 7, 35.00, 25.00, 50, 10, 'loaf'),
('1234567890126', 'Milk 1L', 'Fresh milk', 4, 65.00, 50.00, 80, 15, 'bottle'),
('1234567890127', 'Eggs Dozen', 'Fresh chicken eggs', 4, 120.00, 95.00, 60, 12, 'dozen')
ON CONFLICT (barcode) DO NOTHING;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('store_name', 'POS Store', 'Store name'),
('tax_rate', '12', 'Tax rate percentage'),
('currency', 'PHP', 'Currency code'),
('receipt_footer', 'Thank you for shopping with us!', 'Receipt footer text'),
('offline_mode_enabled', '1', 'Enable offline mode')
ON CONFLICT (setting_key) DO NOTHING;
