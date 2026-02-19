-- Inventory Transactions System
-- Run this SQL to add inventory transaction tracking

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

-- Add inventory management permission
INSERT INTO permissions (permission_name, description) 
VALUES ('manage_inventory', 'Manage inventory transactions and adjustments')
ON DUPLICATE KEY UPDATE description = 'Manage inventory transactions and adjustments';

-- Assign permission to Super Admin, Admin, and Manager roles
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name IN ('Super Admin', 'Admin', 'Manager') 
AND p.permission_name = 'manage_inventory'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Update existing transactions to create inventory transaction records
-- This is a one-time migration for existing data
INSERT INTO inventory_transactions (product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, user_id, created_at)
SELECT 
    ti.product_id,
    'sale' as transaction_type,
    ti.quantity,
    (p.stock_quantity + ti.quantity) as previous_stock,
    p.stock_quantity as new_stock,
    'transaction' as reference_type,
    t.id as reference_id,
    t.user_id,
    t.created_at
FROM transaction_items ti
JOIN transactions t ON ti.transaction_id = t.id
JOIN products p ON ti.product_id = p.id
WHERE t.payment_status != 'voided'
ON DUPLICATE KEY UPDATE id = id;
