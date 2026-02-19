-- Add void transaction support
-- Run this SQL to add void functionality to existing database

-- Update payment_status enum to include 'voided'
ALTER TABLE transactions MODIFY COLUMN payment_status ENUM('pending', 'completed', 'refunded', 'voided') DEFAULT 'completed';

-- Add void tracking fields
ALTER TABLE transactions 
ADD COLUMN voided_at TIMESTAMP NULL AFTER payment_status,
ADD COLUMN voided_by INT NULL AFTER voided_at,
ADD COLUMN void_reason TEXT NULL AFTER voided_by,
ADD FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE SET NULL,
ADD INDEX idx_voided (voided_at);

-- Add void transaction permission
INSERT INTO permissions (permission_name, description) 
VALUES ('void_transactions', 'Void/cancel transactions')
ON DUPLICATE KEY UPDATE description = 'Void/cancel transactions';

-- Assign void permission to Super Admin and Admin roles
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name IN ('Super Admin', 'Admin') AND p.permission_name = 'void_transactions'
ON DUPLICATE KEY UPDATE role_id = role_id;
