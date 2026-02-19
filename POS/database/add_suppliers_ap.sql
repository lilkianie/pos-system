-- Suppliers and Accounts Payable Module
-- Run this SQL to add supplier and AP functionality

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

-- Add permissions
INSERT INTO permissions (permission_name, description) VALUES
('manage_suppliers', 'Manage suppliers and vendor information'),
('manage_purchase_orders', 'Create and manage purchase orders'),
('manage_accounts_payable', 'Manage accounts payable and payments'),
('view_ap_reports', 'View accounts payable reports')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign permissions to roles
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name IN ('Super Admin', 'Admin', 'Manager') 
AND p.permission_name IN ('manage_suppliers', 'manage_purchase_orders', 'manage_accounts_payable', 'view_ap_reports')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Insert sample supplier
INSERT INTO suppliers (supplier_name, contact_person, email, phone, address, payment_terms, credit_limit) VALUES
('ABC Trading Company', 'Juan Dela Cruz', 'juan@abctrading.com', '+63 912 345 6789', '123 Main Street, Manila', 30, 100000.00),
('XYZ Distributors', 'Maria Santos', 'maria@xyzdist.com', '+63 923 456 7890', '456 Business Ave, Quezon City', 45, 50000.00)
ON DUPLICATE KEY UPDATE supplier_name = supplier_name;
