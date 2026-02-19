-- Accounts Receivable and Customer Rewards Module
-- Run this SQL to add AR and customer management functionality

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    zip_code VARCHAR(20) NULL,
    birth_date DATE NULL,
    customer_type ENUM('regular', 'member') DEFAULT 'regular',
    credit_limit DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Current outstanding balance',
    points_balance INT DEFAULT 0 COMMENT 'Reward points balance',
    points_earned_total INT DEFAULT 0 COMMENT 'Total points earned (lifetime)',
    standing ENUM('good', 'warning', 'bad') DEFAULT 'good',
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (customer_code),
    INDEX idx_name (customer_name),
    INDEX idx_type (customer_type),
    INDEX idx_standing (standing),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounts Receivable table
CREATE TABLE IF NOT EXISTS accounts_receivable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    customer_id INT NOT NULL,
    invoice_number VARCHAR(100) NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) DEFAULT 0.00,
    balance DECIMAL(15,2) NOT NULL,
    status ENUM('open', 'partial', 'paid', 'overdue') DEFAULT 'open',
    payment_date DATE NULL,
    points_earned INT DEFAULT 0 COMMENT 'Points earned from this transaction',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_transaction (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AR Payments table
CREATE TABLE IF NOT EXISTS ar_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    accounts_receivable_id INT NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'other') DEFAULT 'cash',
    check_number VARCHAR(100) NULL,
    reference_number VARCHAR(100) NULL,
    amount DECIMAL(15,2) NOT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (accounts_receivable_id) REFERENCES accounts_receivable(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_ar (accounts_receivable_id),
    INDEX idx_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Points Transactions table
CREATE TABLE IF NOT EXISTS customer_points_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    transaction_type ENUM('earned', 'redeemed', 'expired', 'adjusted') NOT NULL,
    points INT NOT NULL COMMENT 'Positive for earned/adjusted, negative for redeemed/expired',
    reference_type VARCHAR(50) NULL COMMENT 'transaction, ar_payment, manual, etc.',
    reference_id INT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    INDEX idx_customer (customer_id),
    INDEX idx_type (transaction_type),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rewards/Redeemables table
CREATE TABLE IF NOT EXISTS rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reward_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    points_required INT NOT NULL,
    discount_percentage DECIMAL(5,2) NULL COMMENT 'If applicable',
    discount_amount DECIMAL(10,2) NULL COMMENT 'If applicable',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add payment method 'credit' to transactions table
ALTER TABLE transactions MODIFY COLUMN payment_method ENUM('cash', 'credit_card', 'e_wallet', 'credit') DEFAULT 'cash';

-- Add customer_id to transactions table
ALTER TABLE transactions ADD COLUMN customer_id INT NULL AFTER user_id;
ALTER TABLE transactions ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
ALTER TABLE transactions ADD INDEX idx_customer (customer_id);

-- Add permissions
INSERT INTO permissions (permission_name, description) VALUES
('manage_customers', 'Manage customers and customer accounts'),
('manage_accounts_receivable', 'Manage accounts receivable and collections'),
('view_ar_reports', 'View accounts receivable reports'),
('process_credit_sales', 'Process credit sales transactions')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign permissions to roles
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name IN ('Super Admin', 'Admin', 'Manager') 
AND p.permission_name IN ('manage_customers', 'manage_accounts_receivable', 'view_ar_reports', 'process_credit_sales')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Assign process_credit_sales to Cashier role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name = 'Cashier' 
AND p.permission_name = 'process_credit_sales'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Insert default rewards
INSERT INTO rewards (reward_name, description, points_required, discount_percentage, is_active) VALUES
('5% Discount', 'Get 5% discount on your next purchase', 100, 5.00, 1),
('10% Discount', 'Get 10% discount on your next purchase', 200, 10.00, 1),
('15% Discount', 'Get 15% discount on your next purchase', 300, 15.00, 1),
('Free Item (Small)', 'Redeem a small free item', 500, NULL, 1),
('Free Item (Medium)', 'Redeem a medium free item', 1000, NULL, 1),
('Free Item (Large)', 'Redeem a large free item', 2000, NULL, 1)
ON DUPLICATE KEY UPDATE reward_name = reward_name;

-- Settings for points system
INSERT INTO settings (setting_key, setting_value, description) VALUES
('points_per_peso', '1', 'Points earned per peso spent'),
('points_expiry_days', '365', 'Points expiry in days'),
('credit_payment_terms', '30', 'Default credit payment terms in days'),
('bad_standing_threshold', '90', 'Days overdue to mark as bad standing'),
('warning_standing_threshold', '60', 'Days overdue to mark as warning')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
