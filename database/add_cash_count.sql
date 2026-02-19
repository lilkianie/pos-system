-- Cash Count System
-- Run this SQL to add cash count functionality

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

-- Cash count items (for detailed breakdown)
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

-- Add cash count permission
INSERT INTO permissions (permission_name, description) 
VALUES ('manage_cash_count', 'Manage cashier cash counts')
ON DUPLICATE KEY UPDATE description = 'Manage cashier cash counts';

-- Assign permission to Cashier, Manager, Admin, and Super Admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name IN ('Super Admin', 'Admin', 'Manager', 'Cashier') 
AND p.permission_name = 'manage_cash_count'
ON DUPLICATE KEY UPDATE role_id = role_id;
