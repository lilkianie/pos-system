-- Fix: Allow Cashier role to open shift and create cash count
-- Run this if cashiers get "Permission denied" or cannot access Cash Count / open new shift.
-- This grants "Manage cashier cash counts" to the role named "Cashier" (any role_id).

USE pos_system;

-- Ensure permission exists
INSERT IGNORE INTO permissions (permission_name, description)
VALUES ('manage_cash_count', 'Manage cashier cash counts');

-- Grant manage_cash_count to the role named "Cashier"
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_name = 'Cashier'
  AND p.permission_name = 'manage_cash_count'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Optional: also grant to Admin and Manager so they can open shifts if needed
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_name IN ('Admin', 'Manager', 'Super Admin')
  AND p.permission_name = 'manage_cash_count'
ON DUPLICATE KEY UPDATE role_id = role_id;
