# Inventory Transactions & Reports System

## Overview
Complete inventory transaction tracking system that records all stock movements including sales, adjustments, stock in/out, returns, damaged items, and expired products.

## Features

### Inventory Transactions
- **Stock In**: Add inventory (purchases, returns)
- **Stock Out**: Remove inventory (transfers, etc.)
- **Adjustment**: Set stock to specific amount
- **Sale**: Automatic tracking from POS transactions
- **Return**: Automatic tracking from voided transactions
- **Damaged**: Mark items as damaged
- **Expired**: Mark items as expired

### Transaction Tracking
- Complete audit trail of all stock movements
- Previous stock and new stock recorded
- User who performed the transaction
- Reference to related transactions/documents
- Notes field for additional information
- Timestamp for each transaction

### Reports
- **Inventory Transactions**: View all transactions with filters
- **Inventory Report**: Summary statistics and analytics
- **Stock Movements Timeline**: Visual chart of stock in/out
- **Top Products**: Products with most transactions

## Setup

### 1. Database Setup
Run the SQL file to create necessary tables:
```sql
-- Import this file
database/add_inventory_transactions.sql
```

Or manually execute:
```sql
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    transaction_type ENUM('stock_in', 'stock_out', 'adjustment', 'sale', 'return', 'damaged', 'expired') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    notes TEXT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_product (product_id),
    INDEX idx_type (transaction_type),
    INDEX idx_date (created_at)
);
```

### 2. Access
- **Admin/Manager**: Admin → Inventory Transactions
- **Reports**: Admin → Inventory Report

## Usage

### Viewing Transactions
1. Go to Admin → Inventory Transactions
2. Filter by:
   - Product name/barcode
   - Specific product
   - Transaction type
   - Date range
3. View complete transaction history

### Making Stock Adjustments
1. Go to Admin → Inventory Transactions
2. Click "Stock Adjustment"
3. Select product
4. Choose adjustment type:
   - **Stock In**: Add to current stock
   - **Stock Out**: Remove from current stock
   - **Adjustment**: Set to specific amount
   - **Damaged**: Remove as damaged
   - **Expired**: Remove as expired
5. Enter quantity
6. Add notes (optional)
7. Save adjustment

### Viewing Reports
1. Go to Admin → Inventory Report
2. Filter by product and date range
3. View:
   - Summary by transaction type
   - Stock movements timeline chart
   - Top products by transactions

## Automatic Tracking

### Sales Transactions
- Automatically creates inventory transaction when sale is processed
- Records quantity as negative (stock out)
- Links to transaction ID

### Voided Transactions
- Automatically creates return transaction
- Restores stock quantity
- Records as "return" type
- Links to voided transaction

## Transaction Types

| Type | Description | Quantity Sign |
|------|-------------|---------------|
| stock_in | Stock received/purchased | Positive (+) |
| stock_out | Stock removed/transferred | Negative (-) |
| adjustment | Stock set to specific amount | Positive/Negative |
| sale | Sale from POS | Negative (-) |
| return | Return/void transaction | Positive (+) |
| damaged | Items marked as damaged | Negative (-) |
| expired | Items marked as expired | Negative (-) |

## Permissions

- `manage_inventory` - Required to make stock adjustments
- `view_reports` - Required to view inventory reports

Default access:
- **Admin**: Full access
- **Manager**: Full access
- **Cashier**: No access (sales tracked automatically)

## API Endpoints

### Adjust Stock
```
POST /api/inventory.php
{
    action: 'adjust_stock',
    product_id: 1,
    transaction_type: 'stock_in',
    quantity: 10,
    notes: 'Stock received'
}
```

### Get Product History
```
GET /api/inventory.php?action=get_product_history&product_id=1
```

## Features

### Complete Audit Trail
- Every stock movement is recorded
- Previous and new stock amounts tracked
- User who made the change
- Timestamp of change
- Reference to related documents

### Visual Reports
- Charts showing stock movements over time
- Summary statistics by type
- Top products analysis

### Flexible Filtering
- Filter by product
- Filter by transaction type
- Filter by date range
- Search by product name/barcode

## Notes

- All POS sales automatically create inventory transactions
- Voided transactions automatically create return transactions
- Stock adjustments require `manage_inventory` permission
- Transaction history cannot be deleted (audit trail)
- Reports show comprehensive inventory movement analysis
