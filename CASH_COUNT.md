# Cash Count System

## Overview
The cash count system allows cashiers to record beginning and ending cash amounts for each shift, with automatic calculation of expected cash and differences. **Multiple shifts per day** are supported: a cashier can start and end several shifts in one day (e.g. Morning, Afternoon, Night, or Full day).

## Features

### Beginning Cash Count
- Cashiers input starting cash at the beginning of their shift
- Optional cash breakdown by denomination
- Notes field for additional information
- Prevents multiple open shifts per day

### Ending Cash Count
- Cashiers input ending cash at the end of their shift
- Automatic calculation of expected cash (beginning + cash sales)
- Automatic calculation of difference (ending - expected)
- Visual indicators for over/short/perfect
- Optional cash breakdown by denomination
- Notes field for additional information

### Daily Cash Count Report
- View all cash counts for a specific date
- Filter by cashier
- Summary statistics
- Detailed view with cash breakdowns
- Export capabilities

## Setup

### 1. Database Setup
Run the SQL file to create necessary tables:
```sql
-- Import this file
database/add_cash_count.sql
```

Or manually execute:
```sql
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
);

CREATE TABLE IF NOT EXISTS cash_count_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_count_id INT NOT NULL,
    denomination DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    count_type ENUM('beginning', 'ending') NOT NULL,
    FOREIGN KEY (cash_count_id) REFERENCES cash_counts(id) ON DELETE CASCADE
);
```

### 2. Access
- **Cashiers**: POS → Cash Count (or `http://possystem.com/pos/cash-count.php`)
- **Admin/Managers**: Admin → Cash Count Report

## Usage

### Starting a Shift
1. Go to POS → Cash Count
2. Choose **Shift type** (Morning, Afternoon, Night, or Full day). Types that already have an open shift show "(already open)" and cannot be selected again until that shift is ended.
3. Enter beginning cash amount
4. (Optional) Add cash breakdown by denomination
5. (Optional) Add notes
6. Click "Start Shift"
7. Shift is now open. You can start another shift of a different type at any time.

### Ending a Shift
1. Go to POS → Cash Count
2. View expected cash (calculated automatically)
3. Enter ending cash amount
4. (Optional) Add cash breakdown by denomination
5. (Optional) Add notes
6. Review difference (over/short/perfect)
7. Click "End Shift"
8. Shift is closed

### Viewing Reports
1. Go to Admin → Cash Count Report
2. Select date and/or cashier
3. View summary statistics
4. Click "View Details" for detailed breakdown

## Calculations

### Expected Cash
```
Expected Cash = Beginning Cash + Cash Sales (for the day)
```

### Difference
```
Difference = Ending Cash - Expected Cash
```

- **Difference = 0**: Perfect (green)
- **Difference > 0**: Over (yellow/warning)
- **Difference < 0**: Short (red/danger)

## Permissions

- `manage_cash_count` - Required to manage cash counts (open/close shift, create cash count)

**If cashiers cannot open a new shift or create a cash count:** The Cashier role must have the "Manage cashier cash counts" permission. Run `database/fix_cashier_cash_count_permission.sql` to grant it, or in Admin go to **Roles & Permissions**, edit the Cashier role, and enable "Manage cashier cash counts".
- `view_reports` - Required to view cash count reports

Default access:
- **Cashier**: Can start/end own shifts
- **Manager**: Can view all reports
- **Admin**: Full access

## API Endpoints

### Start Shift
```
POST /api/cash-count.php
{
    action: 'start_shift',
    shift_type: 'morning',   // 'morning' | 'afternoon' | 'night' | 'full_day'
    beginning_cash: 1000.00,
    beginning_notes: 'Starting cash',
    denominations: [
        {denomination: 100, quantity: 10, total_amount: 1000}
    ]
}
```

### End Shift
```
POST /api/cash-count.php
{
    action: 'end_shift',
    cash_count_id: 1,
    ending_cash: 1500.00,
    ending_notes: 'Ending cash',
    denominations: [...]
}
```

### Get Cash Count
```
GET /api/cash-count.php?action=get_cash_count&id={id}
```

## Features

### Cash Breakdown
- Optional denomination tracking
- Supports: ₱1000, ₱500, ₱200, ₱100, ₱50, ₱20, ₱10, ₱5, ₱1, ₱0.25, ₱0.10, ₱0.05
- Automatic total calculation
- Can add multiple denomination rows

### Validation
- Cannot start two **open** shifts of the same type on the same day (e.g. two "Morning" shifts). You can have one open shift per type (Morning, Afternoon, Night, Full day) per day.
- Cannot end shift that's already closed
- Beginning and ending cash must be > 0
- Automatic calculation of expected cash and difference

### Reporting
- Daily summary statistics
- Filter by date and cashier
- Detailed view with cash breakdowns
- Visual indicators for differences

## Notes

- Multiple shifts per cashier per day: one open shift **per shift type** (Morning, Afternoon, Night, Full day). After ending a shift, you can start a new one (same or different type).
- Cash sales are automatically calculated from transactions
- Only cash transactions are included in calculations
- Voided transactions are excluded
- Shift cannot be reopened once closed
