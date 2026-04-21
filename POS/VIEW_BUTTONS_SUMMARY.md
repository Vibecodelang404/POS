# View Buttons & Links - Complete Summary

## PUBLIC SECTION (public/*.php)

### [public/dashboard.php](public/dashboard.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [335](public/dashboard.php#L335) | "View All" | - | View all transactions | transactions.php | All recent orders/transactions |
| [394](public/dashboard.php#L394) | "Manage Inventory" | - | Manage inventory | inventory.php | Product inventory data |

---

### [public/transactions.php](public/transactions.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [336-337](public/transactions.php#L336) | "View" | `fa-eye` | View transaction details | ?details=<transaction_id> | Full transaction details (order #, items, customer info, payment method) |

---

### [public/inventory.php](public/inventory.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [271](public/inventory.php#L271) | "Show Barcode" | - | Show product barcode | Modal popup | Product barcode (QR code) |
| [302](public/inventory.php#L302) | "Edit" | - | Edit product details | Modal (editProduct) | Product form with all product data |

---

### [public/reports.php](public/reports.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [243](public/reports.php#L243) | "Print" | - | Print inventory reports | Print view | Filtered inventory reports |

---

### [public/sales_analysis.php](public/sales_analysis.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [27](public/sales_analysis.php#L27) | "View daily sales reports" | - | Description text | - | Daily sales reports |

---

### [public/user_management.php](public/user_management.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [298](public/user_management.php#L298) | "Edit" | - | Edit user details | Modal (editUser) | User form with username, role, status |

---

### [public/migrate_database.php](public/migrate_database.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [89](public/migrate_database.php#L89) | "View Reports" | - | View reports | reports.php | Inventory reports |

---

## CASHIER SECTION (cashier/*.php)

### [cashier/views/layout.php](cashier/views/layout.php) - Navigation

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [195-197](cashier/views/layout.php#L195) | "View Inventory" | `fa-eye` | Navigate to read-only inventory view | inventory.php | Products with stock info (read-only) |
| [201-203](cashier/views/layout.php#L201) | "View Shifts" | - | Navigate to view shifts | view_shifts.php | Shift history and schedule |

---

### [cashier/inventory.php](cashier/inventory.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [178](cashier/inventory.php#L178) | "Read-Only Access" | `fa-eye` | Label for read-only inventory | - | Inventory viewing mode (no edit) |
| [293](cashier/inventory.php#L293) | "View Inventory" | `fa-eye` | Title for inventory section | - | Product list, stock levels, categories |
| [403](cashier/inventory.php#L403) | "Show Barcode" | - | Show product barcode | Modal popup | Product barcode (QR code) |

---

### [cashier/transactions.php](cashier/transactions.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [201-202](cashier/transactions.php#L201) | "View" | `fa-eye` | View transaction details | ?details=<transaction_id> | Complete transaction details (items, amounts, payment) |

---

## STAFF SECTION (staff/*.php)

### [staff/layout.php](staff/layout.php) - Navigation

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [173-175](staff/layout.php#L173) | "View Shifts" | - | Navigate to view shifts | ../view_shifts.php | Shift records and schedule |
| [167](staff/layout.php#L167) | "Inventory Reports" | - | Navigate to reports | inventory_reports.php | Inventory report data |

---

### [staff/dashboard.php](staff/dashboard.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [301](staff/dashboard.php#L301) | "View All" | - | View all transactions | transactions.php | Recent transactions/orders |
| [385](staff/dashboard.php#L385) | "Manage Product" | - | Navigate to product mgmt | manage_product.php | Product list and details |
| [390](staff/dashboard.php#L390) | "Inventory Reports" | - | Navigate to reports | inventory_reports.php | Inventory analysis and reports |
| [395](staff/dashboard.php#L395) | "Transactions" | - | View transactions | transactions.php | Transaction list |
| [400](staff/dashboard.php#L400) | "Sales" | - | View sales data | sales.php | Sales performance data |

---

### [staff/manage_product.php](staff/manage_product.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [376](staff/manage_product.php#L376) | "Show Barcode" | - | Show product barcode | Modal popup | Product barcode (QR code) |
| [388](staff/manage_product.php#L388) | "Edit" | - | Edit product | Modal (editProduct) | Product form with details |

---

### [staff/inventory_reports.php](staff/inventory_reports.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [241](staff/inventory_reports.php#L241) | "Last 7 Days" | - | Filter reports | ?start_date=... | Last 7 days of inventory data |
| [244](staff/inventory_reports.php#L244) | "Create Report" | - | Create new report | Modal (createReportModal) | Report creation form |
| [336](staff/inventory_reports.php#L336) | "Print" | - | Print reports | Print view | Inventory report data |

---

### [staff/dashboard_backup.php](staff/dashboard_backup.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [234-236](staff/dashboard_backup.php#L234) | "View Transaction History" | - | View transactions | transactions.php | Transaction records |
| [238](staff/dashboard_backup.php#L238) | "View Sales" | - | View sales data | sales.php | Sales information |

---

### [staff/sales.php](staff/sales.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [63](staff/sales.php#L63) | "View today's sales performance" | - | Displays sales data | - | Daily sales metrics and charts |

---

## SHARED LAYOUT NAVIGATION

### [app/views/layout.php](app/views/layout.php)

| Line | Button/Link Text | Icon | What It Does | Navigates To | Data Displayed |
|------|-----------------|------|-------------|--------------|-----------------|
| [210-212](app/views/layout.php#L210) | "View Shifts" | - | Navigate to shifts | view_shifts.php | Staff shift records |

---

## SUMMARY BY FUNCTIONALITY

### Transaction Viewing
- **public/transactions.php:336** - View transaction details (eye icon)
- **cashier/transactions.php:201** - View transaction details (eye icon)  
- **staff/dashboard.php:301** - View all transactions
- **staff/dashboard_backup.php:234** - View transaction history

### Inventory Viewing
- **cashier/views/layout.php:195** - View Inventory (eye icon)
- **cashier/inventory.php:178** - Read-Only Access (eye icon)
- **cashier/inventory.php:293** - View Inventory section (eye icon)
- **public/inventory.php:271** - Show Barcode button

### Shift Viewing
- **app/views/layout.php:210** - View Shifts link
- **cashier/views/layout.php:201** - View Shifts link
- **staff/layout.php:173** - View Shifts link

### Report Viewing
- **public/reports.php:243** - Print reports
- **public/dashboard.php:335** - View All (transactions)
- **staff/inventory_reports.php:241** - Last 7 Days filter
- **staff/dashboard.php:390** - Inventory Reports link
- **public/migrate_database.php:89** - View Reports link

### Product Management
- **public/inventory.php:302** - Edit Product
- **public/user_management.php:298** - Edit User
- **staff/manage_product.php:388** - Edit Product
- **staff/manage_product.php:376** - Show Barcode

### Barcode Viewing
- **public/inventory.php:271** - Show Barcode
- **cashier/inventory.php:403** - Show Barcode
- **staff/manage_product.php:376** - Show Barcode

---

## NOTES

- **Eye Icon** (`fa-eye`) used for: View Inventory, View Transactions, Read-Only Access
- **Modal Popups** used for: Product editing, Barcode display, Report creation
- **Parameter-based Details**: `?details=<id>` used to display specific transaction details
- **Navigation Links**: Most view functions navigate to different pages or load modals
- **Data Access Control**: Cashier has read-only inventory access; staff/admin have full access
