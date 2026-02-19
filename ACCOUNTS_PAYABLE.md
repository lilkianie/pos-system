# Accounts Payable & Suppliers Module

## Overview
Complete Accounts Payable (AP) and Supplier management system with purchase orders, stock entry, payment tracking, and aging reports.

## Features

### Supplier Management
- **CRUD Operations**: Create, read, update, and delete suppliers
- **Contact Information**: Store contact person, email, phone, address
- **Payment Terms**: Set payment terms (days) per supplier
- **Credit Limits**: Track credit limits and outstanding balances
- **Status Tracking**: Active/inactive supplier status

### Purchase Orders
- **Create PO**: Generate purchase orders with multiple items
- **PO Numbering**: Unique PO numbers for tracking
- **Item Management**: Add products with quantities, unit costs, discounts
- **Status Tracking**: Pending, Ordered, Partial, Received, Cancelled
- **Delivery Dates**: Track expected and actual delivery dates
- **Automatic AP Creation**: Creates accounts payable entry automatically

### Stock Entry (Receiving)
- **Receive Orders**: Mark items as received with partial receiving support
- **Automatic Inventory Update**: Stock quantities updated automatically
- **Inventory Transactions**: Creates inventory transaction records
- **Status Updates**: PO status updated based on received quantities

### Accounts Payable
- **Automatic Creation**: Created when PO is saved
- **Payment Tracking**: Record multiple payments per invoice
- **Status Management**: Open, Partial, Paid, Overdue
- **Balance Tracking**: Track amount, paid amount, and balance
- **Payment Methods**: Cash, Check, Bank Transfer, Credit Card, Other

### AP Reports & Aging
- **Aging Report**: Categorize by days overdue (Current, 1-30, 31-60, 61-90, 90+)
- **Status Summary**: Summary by payment status
- **Supplier Summary**: Top suppliers by outstanding balance
- **Visual Charts**: Doughnut chart for status distribution
- **Filtering**: Filter by supplier and date range

## Setup

### 1. Database Setup
Run the SQL file to create necessary tables:
```sql
-- Import this file
database/add_suppliers_ap.sql
```

Or use the updated `database/schema.sql` which includes all tables.

### 2. Access
- **Suppliers**: Admin → Suppliers
- **Purchase Orders**: Admin → Purchase Orders
- **Accounts Payable**: Admin → Accounts Payable
- **AP Reports**: Admin → AP Reports

## Usage

### Managing Suppliers
1. Go to Admin → Suppliers
2. Click "Add Supplier"
3. Fill in supplier information:
   - Supplier name (required)
   - Contact person
   - Email, phone
   - Address details
   - Payment terms (default: 30 days)
   - Credit limit
4. Save supplier

### Creating Purchase Orders
1. Go to Admin → Purchase Orders
2. Click "New Purchase Order"
3. Enter PO details:
   - PO Number (unique, required)
   - Supplier (required)
   - Order date
   - Expected delivery date
4. Add items:
   - Select product
   - Enter quantity
   - Enter unit cost
   - Add discount if applicable
5. Set tax and discount amounts
6. Save PO

**Note**: Accounts Payable entry is created automatically with due date based on supplier payment terms.

### Receiving Purchase Orders
1. Go to Admin → Purchase Orders
2. Click "View" on a PO
3. Enter received quantities for each item
4. Click "Receive Order"
5. Stock is automatically updated
6. Inventory transactions are created

### Recording Payments
1. Go to Admin → Accounts Payable
2. Click "Pay" on an invoice
3. Enter payment details:
   - Payment date
   - Payment method
   - Reference number (check number, etc.)
   - Amount
   - Notes
4. Save payment

**Note**: 
- AP balance and status updated automatically
- PO payment status updated automatically
- Supplier balance updated automatically

### Viewing AP Reports
1. Go to Admin → AP Reports
2. Filter by supplier and date range
3. View:
   - Aging summary (by days overdue)
   - Status summary (chart)
   - Top suppliers by balance
   - Detailed aging report

## Database Tables

### suppliers
- Supplier information and contact details
- Payment terms and credit limits
- Outstanding balance tracking

### purchase_orders
- PO header information
- Status tracking
- Payment status

### purchase_order_items
- PO line items
- Received quantity tracking

### accounts_payable
- AP invoice information
- Balance and status tracking
- Due date for aging

### ap_payments
- Payment history
- Payment method and reference tracking

## Permissions

- `manage_suppliers` - Manage suppliers
- `manage_purchase_orders` - Create and manage POs
- `manage_accounts_payable` - Record payments
- `view_ap_reports` - View AP reports

Default access:
- **Admin**: Full access
- **Manager**: Full access
- **Cashier**: No access

## Integration

### Inventory Transactions
- When PO is received, inventory transactions are created automatically
- Transaction type: `stock_in`
- Reference type: `purchase_order`
- Links to PO ID

### Stock Management
- Receiving PO automatically updates product stock quantities
- Supports partial receiving
- Tracks received vs ordered quantities

## Aging Report Categories

| Bucket | Description |
|--------|-------------|
| Current | Not yet due |
| 1-30 | 1 to 30 days overdue |
| 31-60 | 31 to 60 days overdue |
| 61-90 | 61 to 90 days overdue |
| 90+ | More than 90 days overdue |

## Workflow

1. **Create Supplier** → Set payment terms and credit limit
2. **Create Purchase Order** → Add items, set delivery date
3. **Receive Order** → Enter received quantities, stock updated
4. **Accounts Payable Created** → Automatically when PO is saved
5. **Record Payments** → Track payments against AP
6. **View Reports** → Monitor aging and outstanding balances

## Notes

- PO numbers must be unique
- Partial receiving is supported
- AP is created automatically when PO is saved
- Due date calculated from order date + payment terms
- Status updates automatically based on payments and due dates
- Supplier balance tracks total outstanding across all AP entries
- Aging report only shows open, partial, and overdue invoices
