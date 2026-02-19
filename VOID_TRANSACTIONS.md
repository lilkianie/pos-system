# Void Transaction Module

## Overview
The void transaction module allows authorized users to cancel/void completed transactions. When a transaction is voided:
- The transaction status is changed to "voided"
- Inventory is automatically restored
- A reason must be provided
- The action is logged with timestamp and user

## Setup

### 1. Database Update
If you have an existing database, run:
```sql
-- Run this SQL file
database/add_void_support.sql
```

Or manually execute:
```sql
ALTER TABLE transactions MODIFY COLUMN payment_status ENUM('pending', 'completed', 'refunded', 'voided') DEFAULT 'completed';
ALTER TABLE transactions 
ADD COLUMN voided_at TIMESTAMP NULL AFTER payment_status,
ADD COLUMN voided_by INT NULL AFTER voided_at,
ADD COLUMN void_reason TEXT NULL AFTER voided_by,
ADD FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE SET NULL,
ADD INDEX idx_voided (voided_at);

INSERT INTO permissions (permission_name, description) 
VALUES ('void_transactions', 'Void/cancel transactions')
ON DUPLICATE KEY UPDATE description = 'Void/cancel transactions';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name IN ('Super Admin', 'Admin') AND p.permission_name = 'void_transactions'
ON DUPLICATE KEY UPDATE role_id = role_id;
```

### 2. Access
- Navigate to: **Admin → Transactions**
- Or directly: `http://possystem.com/admin/transactions.php`

## Features

### Transaction List
- View all transactions with filters
- Search by transaction number or cashier name
- Filter by status (Completed, Voided, Refunded)
- Filter by date range
- Pagination support

### View Transaction Details
- Click "View" button to see:
  - Transaction information
  - All items in the transaction
  - Payment details
  - Void information (if voided)

### Void Transaction
1. Click "Void" button on a completed transaction
2. Enter reason for voiding (required)
3. Confirm the action
4. Transaction is voided and inventory is restored

## Permissions

### Required Permission
- `void_transactions` - Required to void transactions
- `view_reports` - Required to view transactions list

### Default Access
- **Super Admin**: Full access
- **Admin**: Full access
- **Manager**: View only (can view but not void)
- **Cashier**: No access

## Important Notes

1. **Irreversible Action**: Voiding a transaction cannot be undone
2. **Inventory Restoration**: Stock quantities are automatically restored
3. **Reason Required**: A reason must be provided when voiding
4. **Only Completed Transactions**: Only transactions with "completed" status can be voided
5. **Audit Trail**: All voids are logged with:
   - Timestamp (`voided_at`)
   - User who voided (`voided_by`)
   - Reason (`void_reason`)

## API Endpoints

### Get Transaction Details
```
GET /api/transactions.php?action=get&id={transaction_id}
```

### Void Transaction
```
POST /api/transactions.php
{
    action: 'void',
    transaction_id: {id},
    reason: 'Reason text'
}
```

## Security

- Only users with `void_transactions` permission can void transactions
- All void actions are logged
- Transaction status is validated before voiding
- Database transactions ensure data consistency
