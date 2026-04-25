<?php
require_once __DIR__ . '/../app/config.php';
requireLogin();

// Handle AJAX JSON request for transaction details (before role check)
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    
    // Verify cashier role
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    if (isset($_GET['id'])) {
        $db = Database::getInstance()->getConnection();
        $tid = intval($_GET['id']);
        $userId = $_SESSION['user_id']; // Get current cashier's ID
        
        $stmt = $db->prepare("SELECT o.*, u.first_name, u.last_name, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ? AND o.user_id = ?");
        $stmt->execute([$tid, $userId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction) {
            $stmt = $db->prepare("SELECT oi.*, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
            $stmt->execute([$tid]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'transaction' => $transaction,
                'items' => $items
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaction not found or unauthorized']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No transaction ID provided']);
    }
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . BASE_URL . 'public/dashboard.php');
    } elseif ($_SESSION['role'] === 'staff') {
        header('Location: ' . BASE_URL . 'staff/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'public/login.php');
    }
    exit();
}

$page_title = 'Transaction History';
$db = Database::getInstance()->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$limit = $_GET['limit'] ?? 50;

// Build query with filters
$sql = "SELECT o.*, u.first_name, u.last_name, u.username, o.created_at as date 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($date_from) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY o.created_at DESC LIMIT " . (int)$limit;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get transaction details if requested
$details = [];
$selectedTransaction = null;
if (isset($_GET['details'])) {
    $tid = intval($_GET['details']);
    
    // Get transaction info
    $stmt = $db->prepare("SELECT o.*, u.first_name, u.last_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$tid]);
    $selectedTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get transaction items
    $stmt = $db->prepare("SELECT oi.*, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt->execute([$tid]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

ob_start();
?>

<style>
@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    body { font-size: 12px; }
    .content-card { box-shadow: none !important; border: 1px solid #000 !important; }
    .table { font-size: 11px; }
    @page { margin: 1cm; }
}
.print-only { display: none; }
</style>

<!-- Filter Section -->
<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter Transactions
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Order number, cashier name...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Limit</label>
                        <select class="form-select" name="limit">
                            <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-custom me-2">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-custom" onclick="printTransactions()">
                            <i class="fas fa-print me-2"></i>Print List
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Transaction List -->
<div class="row">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-receipt me-2"></i>Transaction History
                </h5>
                <span class="badge bg-primary"><?php echo count($transactions); ?> transactions</span>
            </div>
            <div class="card-body">
                <div class="print-only">
                    <div class="text-center mb-4">
                        <h3>KAKAI'S POS</h3>
                        <h4>Transaction History Report</h4>
                        <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
                        <p>Cashier: <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                        <?php if ($date_from || $date_to): ?>
                            <p>Period: <?php echo $date_from ?: 'Beginning'; ?> to <?php echo $date_to ?: 'Now'; ?></p>
                        <?php endif; ?>
                        <hr>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Date & Time</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>No transactions found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($t['order_number'] ?? 'ORD-' . $t['id']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo date('M j, Y', strtotime($t['date'])); ?></div>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($t['date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-success"><?php echo formatCurrency($t['total_amount']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $t['status'] === 'completed' ? 'success' : ($t['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($t['status']); ?>
                                            </span>
                                        </td>
                                        <td class="no-print">
                                            <button type="button" class="btn btn-info btn-sm" onclick="viewTransactionDetails(<?php echo $t['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog" aria-labelledby="transactionDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="transactionDetailsModalLabel">
                    <i class="fas fa-file-invoice me-2"></i>Transaction Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="transactionDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="printTransactionModal()">
                    <i class="fas fa-print me-1"></i>Print Receipt
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function getTransactionItemUnitPrice(item) {
    return parseFloat(item.unit_price ?? item.price ?? 0);
}

function getTransactionItemTotal(item) {
    const storedTotal = parseFloat(item.total_price ?? item.total ?? NaN);
    if (!Number.isNaN(storedTotal)) {
        return storedTotal;
    }

    return getTransactionItemUnitPrice(item) * parseInt(item.quantity || 0, 10);
}

// View transaction details in modal
function viewTransactionDetails(transactionId) {
    const modal = new bootstrap.Modal(document.getElementById('transactionDetailsModal'));
    const contentDiv = document.getElementById('transactionDetailsContent');
    
    // Show loading spinner
    contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    modal.show();
    
    // Fetch transaction details via AJAX
    fetch('transactions.php?id=' + transactionId + '&format=json')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.transaction) {
                const t = data.transaction;
                const items = data.items || [];
                
                let html = '<div class="transaction-modal-content">';
                html += '<div class="row mb-3">';
                html += '<div class="col-md-6">';
                html += '<h6 class="fw-bold">Transaction Information</h6>';
                html += '<table class="table table-borderless table-sm">';
                html += '<tr><td><strong>Order Number:</strong></td><td>' + (t.order_number || 'ORD-' + t.id) + '</td></tr>';
                html += '<tr><td><strong>Date & Time:</strong></td><td>' + new Date(t.created_at).toLocaleString() + '</td></tr>';
                html += '<tr><td><strong>Cashier:</strong></td><td>' + ((t.first_name || '') + ' ' + (t.last_name || '') || t.username || 'Unknown') + '</td></tr>';
                const statusBadge = t.status === 'completed' ? 'success' : (t.status === 'pending' ? 'warning' : 'danger');
                html += '<tr><td><strong>Status:</strong></td><td><span class="badge bg-' + statusBadge + '">' + (t.status || '').charAt(0).toUpperCase() + (t.status || '').slice(1) + '</span></td></tr>';
                html += '</table></div>';
                
                html += '<div class="col-md-6">';
                html += '<h6 class="fw-bold">Payment Information</h6>';
                html += '<table class="table table-borderless table-sm">';
                html += '<tr><td><strong>Payment Method:</strong></td><td>' + (t.payment_method || 'Cash').charAt(0).toUpperCase() + (t.payment_method || 'Cash').slice(1) + '</td></tr>';
                html += '<tr><td><strong>Amount Received:</strong></td><td>₱' + parseFloat(t.amount_received || t.total_amount || 0).toFixed(2) + '</td></tr>';
                html += '<tr><td><strong>Change:</strong></td><td>₱' + ((parseFloat(t.amount_received || t.total_amount || 0) - parseFloat(t.total_amount || 0)).toFixed(2)) + '</td></tr>';
                html += '</table></div></div>';
                
                html += '<hr>';
                html += '<h6 class="fw-bold mb-3">Items</h6>';
                html += '<table class="table table-sm table-hover">';
                html += '<thead><tr><th>Product</th><th class="text-center">Quantity</th><th class="text-right">Price</th><th class="text-right">Total</th></tr></thead>';
                html += '<tbody>';
                
                if (items.length > 0) {
                    items.forEach(item => {
                        const itemPrice = getTransactionItemUnitPrice(item);
                        const itemTotal = getTransactionItemTotal(item);
                        item.price = itemPrice;
                        html += '<tr>';
                        html += '<td>' + item.product_name + '</td>';
                        html += '<td class="text-center">' + item.quantity + '</td>';
                        html += '<td class="text-end">₱' + parseFloat(item.price).toFixed(2) + '</td>';
                        html += '<td class="text-end">₱' + itemTotal.toFixed(2) + '</td>';
                        html += '</tr>';
                    });
                } else {
                    html += '<tr><td colspan="4" class="text-center text-muted">No items found</td></tr>';
                }
                
                html += '</tbody></table>';
                
                html += '<hr>';
                html += '<div class="row text-end">';
                html += '<div class="col-md-4 offset-md-8">';
                html += '<table class="table table-borderless table-sm">';
                const grossSubtotal = items.reduce((sum, item) => sum + getTransactionItemTotal(item), 0);
                const discount = parseFloat(t.discount_amount || 0);
                const total = parseFloat(t.total_amount || 0);
                const tax = parseFloat(t.tax_amount || 0);
                const vatableSales = parseFloat(t.subtotal || Math.max(total - tax, 0) || 0);
                html += '<tr><td><strong>Subtotal:</strong></td><td>₱' + grossSubtotal.toFixed(2) + '</td></tr>';
                if (discount > 0) html += '<tr><td><strong>Discount:</strong></td><td>-₱' + discount.toFixed(2) + '</td></tr>';
                html += '<tr><td><strong>VATable Sales:</strong></td><td>₱' + vatableSales.toFixed(2) + '</td></tr>';
                if (tax > 0) html += '<tr><td><strong>VAT Included (12%):</strong></td><td>₱' + tax.toFixed(2) + '</td></tr>';
                html += '<tr><td><strong class="fs-5">Total:</strong></td><td><strong class="fs-5">₱' + parseFloat(t.total_amount).toFixed(2) + '</strong></td></tr>';
                html += '</table>';
                html += '</div></div>';
                html += '</div>';
                
                contentDiv.innerHTML = html;
                // Store for printing
                window.currentTransactionForPrint = data;
            } else {
                contentDiv.innerHTML = '<div class="alert alert-danger">Failed to load transaction details</div>';
                toastr.error('Failed to load transaction details', 'Error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contentDiv.innerHTML = '<div class="alert alert-danger">Error loading transaction details</div>';
            toastr.error('Error loading transaction details', 'Error');
        });
}

// Print transaction from modal
function printTransactionModal() {
    if (!window.currentTransactionForPrint) {
        toastr.warning('No transaction data to print', 'Warning');
        return;
    }
    
    const t = window.currentTransactionForPrint.transaction;
    const items = window.currentTransactionForPrint.items || [];
    
    let printContent = '<div style="max-width: 400px; margin: 0 auto; padding: 20px; text-align: center; font-family: monospace;">';
    printContent += '<h3 style="margin: 10px 0;">KAKAI\'S POS</h3>';
    printContent += '<p style="margin: 5px 0;">Official Transaction Receipt</p>';
    printContent += '<hr style="border: 1px solid #000; margin: 15px 0;">';
    
    printContent += '<p style="text-align: left; margin: 10px 0;"><strong>Order:</strong> ' + (t.order_number || 'ORD-' + t.id) + '</p>';
    printContent += '<p style="text-align: left; margin: 10px 0;"><strong>Date:</strong> ' + new Date(t.created_at).toLocaleString() + '</p>';
    printContent += '<p style="text-align: left; margin: 10px 0;"><strong>Cashier:</strong> ' + ((t.first_name || '') + ' ' + (t.last_name || '') || t.username || 'Unknown') + '</p>';
    
    printContent += '<hr style="border: 1px solid #000; margin: 15px 0;">';
    printContent += '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
    printContent += '<tr><th style="text-align: left; border-bottom: 1px solid #000; padding: 5px;">Item</th><th style="text-align: center; border-bottom: 1px solid #000; padding: 5px;">Qty</th><th style="text-align: right; border-bottom: 1px solid #000; padding: 5px;">Total</th></tr>';
    
    items.forEach(item => {
        const itemTotal = getTransactionItemTotal(item);
        printContent += '<tr><td style="text-align: left; padding: 5px;">' + item.product_name + '</td>';
        printContent += '<td style="text-align: center; padding: 5px;">' + item.quantity + '</td>';
        printContent += '<td style="text-align: right; padding: 5px;">₱' + itemTotal.toFixed(2) + '</td></tr>';
    });
    
    printContent += '</table>';
    printContent += '<hr style="border: 1px solid #000; margin: 15px 0;">';
    
    const grossSubtotal = items.reduce((sum, item) => sum + getTransactionItemTotal(item), 0);
    const discount = parseFloat(t.discount_amount || 0);
    const total = parseFloat(t.total_amount || 0);
    const tax = parseFloat(t.tax_amount || 0);
    const vatableSales = parseFloat(t.subtotal || Math.max(total - tax, 0) || 0);
    printContent += '<p style="text-align: right; margin: 6px 0;"><strong>Subtotal: ₱' + grossSubtotal.toFixed(2) + '</strong></p>';
    if (discount > 0) printContent += '<p style="text-align: right; margin: 6px 0;"><strong>Discount: -₱' + discount.toFixed(2) + '</strong></p>';
    printContent += '<p style="text-align: right; margin: 6px 0;"><strong>VATable Sales: ₱' + vatableSales.toFixed(2) + '</strong></p>';
    if (tax > 0) printContent += '<p style="text-align: right; margin: 6px 0;"><strong>VAT Included (12%): ₱' + tax.toFixed(2) + '</strong></p>';
    printContent += '<p style="text-align: right; margin: 10px 0;"><strong>Total: ₱' + total.toFixed(2) + '</strong></p>';
    printContent += '<p style="text-align: right; margin: 10px 0;"><strong>Received: ₱' + parseFloat(t.amount_received || t.total_amount || 0).toFixed(2) + '</strong></p>';
    printContent += '<p style="text-align: right; margin: 10px 0;"><strong>Change: ₱' + (parseFloat(t.amount_received || t.total_amount || 0) - total).toFixed(2) + '</strong></p>';
    
    printContent += '<hr style="border: 1px solid #000; margin: 15px 0;">';
    printContent += '<p style="font-size: 12px; margin: 10px 0;">Thank you for your purchase!</p>';
    printContent += '<p style="font-size: 10px; color: #666;">Payment: ' + (t.payment_method || 'Cash') + '</p>';
    printContent += '</div>';
    
    const printWindow = window.open('', '', 'height=600,width=500');
    printWindow.document.write(`
        <html>
        <head>
            <title>Receipt</title>
        </head>
        <body onload="window.print(); window.close();">
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<!-- Transaction Details Modal/Section -->
<?php if ($details && $selectedTransaction): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice me-2"></i>Transaction Details - <?php echo htmlspecialchars($selectedTransaction['order_number'] ?? 'ORD-' . $selectedTransaction['id']); ?>
                </h5>
                <div class="no-print">
                    <button class="btn btn-outline-primary btn-sm me-2" onclick="printTransactionDetails()">
                        <i class="fas fa-print me-1"></i>Print Receipt
                    </button>
                    <a href="transactions.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Close
                    </a>
                </div>
            </div>
            <div class="card-body" id="transactionDetails">
                <div class="print-only">
                    <div class="text-center mb-4">
                        <h3>KAKAI'S POS</h3>
                        <p>Transaction Receipt</p>
                        <hr style="border-top: 2px solid #000;">
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Transaction Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Order Number:</strong></td>
                                <td><?php echo htmlspecialchars($selectedTransaction['order_number'] ?? 'ORD-' . $selectedTransaction['id']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Date:</strong></td>
                                <td><?php echo date('F j, Y g:i A', strtotime($selectedTransaction['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Cashier:</strong></td>
                                <td><?php echo htmlspecialchars(trim(($selectedTransaction['first_name'] ?? '') . ' ' . ($selectedTransaction['last_name'] ?? '')) ?: 'Unknown'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $selectedTransaction['status'] === 'completed' ? 'success' : ($selectedTransaction['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($selectedTransaction['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h6>Items Purchased</h6>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grossSubtotal = 0;
                            $discountAmount = (float) ($selectedTransaction['discount_amount'] ?? 0);
                            $taxAmount = (float) ($selectedTransaction['tax_amount'] ?? 0);
                            $vatableSales = (float) ($selectedTransaction['subtotal'] ?? max(((float) $selectedTransaction['total_amount']) - $taxAmount, 0));
                            foreach ($details as $d): 
                                $itemTotal = $d['total_price'];
                                $grossSubtotal += $itemTotal;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($d['name']); ?></td>
                                    <td class="text-center"><?php echo $d['quantity']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($d['unit_price']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($itemTotal); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Subtotal:</th>
                                <th class="text-end"><?php echo formatCurrency($grossSubtotal); ?></th>
                            </tr>
                            <?php if ($discountAmount > 0): ?>
                            <tr>
                                <th colspan="3" class="text-end">Discount:</th>
                                <th class="text-end">-<?php echo formatCurrency($discountAmount); ?></th>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th colspan="3" class="text-end">VATable Sales:</th>
                                <th class="text-end"><?php echo formatCurrency($vatableSales); ?></th>
                            </tr>
                            <?php if ($taxAmount > 0): ?>
                            <tr>
                                <th colspan="3" class="text-end">VAT Included (12%):</th>
                                <th class="text-end"><?php echo formatCurrency($taxAmount); ?></th>
                            </tr>
                            <?php endif; ?>
                            <tr class="table-success">
                                <th colspan="3" class="text-end">TOTAL:</th>
                                <th class="text-end"><?php echo formatCurrency($selectedTransaction['total_amount']); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="print-only text-center mt-4">
                    <hr style="border-top: 2px solid #000;">
                    <p>Thank you for your business!</p>
                    <p><small>This receipt was generated on <?php echo date('F j, Y g:i A'); ?></small></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function printTransactions() {
    window.print();
}

function printTransactionDetails() {
    // Create a new window for printing just the transaction details
    const printContent = document.getElementById('transactionDetails').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Transaction Receipt</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .text-center { text-align: center; }
                .text-end { text-align: right; }
                .table-borderless td { border: none; }
                .badge { padding: 3px 8px; border-radius: 3px; color: white; }
                .bg-success { background-color: #28a745; }
                .bg-warning { background-color: #ffc107; color: #000; }
                .bg-danger { background-color: #dc3545; }
                hr { border: 1px solid #000; margin: 15px 0; }
            </style>
        </head>
        <body>
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>


