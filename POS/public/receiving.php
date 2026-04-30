<?php
require_once __DIR__ . '/../app/config.php';
requireAdmin();

$db = Database::getInstance()->getConnection();
$page_title = 'Supplier Receiving';
$csrf_token = csrfToken();
$message = '';
$message_type = '';

function generatedReceivingNumber($id) {
    return 'RCV-' . date('Ymd') . '-' . str_pad((int) $id, 4, '0', STR_PAD_LEFT);
}

function generatedPurchaseOrderNumber($id) {
    return 'PO-' . date('Ymd') . '-' . str_pad((int) $id, 4, '0', STR_PAD_LEFT);
}

function nextPurchaseOrderNumber($db) {
    try {
        $stmt = $db->query("SHOW TABLE STATUS LIKE 'supplier_receivings'");
        $status = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return generatedPurchaseOrderNumber((int) ($status['Auto_increment'] ?? 1));
    } catch (Throwable $e) {
        return generatedPurchaseOrderNumber(1);
    }
}

function fetchSuppliers($db) {
    return $db->query("SELECT * FROM suppliers ORDER BY status ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function fetchProductsForReceiving($db) {
    return $db->query("
        SELECT p.id, p.name, p.sku, p.product_type, p.cost_price, p.stock_quantity, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active'
        ORDER BY p.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function fetchRecentReceivings($db, $supplierId = '', $startDate = '', $endDate = '') {
    $sql = "
        SELECT
            sr.*,
            s.name AS supplier_name,
            u.first_name,
            u.last_name,
            u.username,
            COUNT(sri.id) AS item_count,
            SUM(sri.quantity) AS total_quantity
        FROM supplier_receivings sr
        INNER JOIN suppliers s ON s.id = sr.supplier_id
        LEFT JOIN users u ON u.id = sr.received_by
        LEFT JOIN supplier_receiving_items sri ON sri.receiving_id = sr.id
        WHERE 1 = 1
    ";
    $params = [];

    if ($supplierId !== '' && ctype_digit((string) $supplierId)) {
        $sql .= " AND sr.supplier_id = ?";
        $params[] = (int) $supplierId;
    }

    if ($startDate !== '') {
        $sql .= " AND sr.received_date >= ?";
        $params[] = $startDate;
    }

    if ($endDate !== '') {
        $sql .= " AND sr.received_date <= ?";
        $params[] = $endDate;
    }

    $sql .= " GROUP BY sr.id ORDER BY sr.received_date DESC, sr.id DESC LIMIT 50";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchReceivingItems($db, $receivingIds) {
    if (empty($receivingIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($receivingIds), '?'));
    $stmt = $db->prepare("
        SELECT
            sri.*,
            p.name AS product_name,
            p.sku,
            p.product_type
        FROM supplier_receiving_items sri
        INNER JOIN products p ON p.id = sri.product_id
        WHERE sri.receiving_id IN ($placeholders)
        ORDER BY sri.receiving_id DESC, p.name ASC
    ");
    $stmt->execute(array_values($receivingIds));

    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[$row['receiving_id']][] = $row;
    }

    return $grouped;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Please refresh the page and try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_supplier') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                $message = 'Supplier name is required.';
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO suppliers (name, contact_person, phone, email, address, status)
                    VALUES (?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $name,
                    trim($_POST['contact_person'] ?? '') ?: null,
                    trim($_POST['phone'] ?? '') ?: null,
                    trim($_POST['email'] ?? '') ?: null,
                    trim($_POST['address'] ?? '') ?: null,
                ]);
                header('Location: receiving.php?supplier_added=1');
                exit();
            }
        }

        if ($action === 'receive_stock') {
            $supplierId = (int) ($_POST['supplier_id'] ?? 0);
            $receivedDate = $_POST['received_date'] ?? date('Y-m-d');
            $invoiceNumber = trim($_POST['invoice_number'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $productIds = $_POST['product_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            $unitCosts = $_POST['unit_cost'] ?? [];
            $expiries = $_POST['expiry_date'] ?? [];

            $items = [];
            $totalCost = 0;
            $itemCount = max(count($productIds), count($quantities), count($unitCosts));

            for ($i = 0; $i < $itemCount; $i++) {
                $productId = (int) ($productIds[$i] ?? 0);
                $quantity = (int) ($quantities[$i] ?? 0);
                $unitCost = (float) ($unitCosts[$i] ?? 0);
                $expiry = trim((string) ($expiries[$i] ?? ''));

                if ($productId <= 0 || $quantity <= 0) {
                    continue;
                }

                $lineTotal = $quantity * max(0, $unitCost);
                $items[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_cost' => max(0, $unitCost),
                    'line_total' => $lineTotal,
                    'expiry_date' => $expiry !== '' ? $expiry : null,
                ];
                $totalCost += $lineTotal;
            }

            if ($supplierId <= 0 || empty($items) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate)) {
                $message = 'Choose a supplier, date, and at least one valid received item.';
                $message_type = 'danger';
            } else {
                try {
                    $db->beginTransaction();

                    $supplierStmt = $db->prepare("SELECT name FROM suppliers WHERE id = ? AND status = 'active'");
                    $supplierStmt->execute([$supplierId]);
                    $supplierName = $supplierStmt->fetchColumn();
                    if (!$supplierName) {
                        throw new RuntimeException('Selected supplier is not active.');
                    }

                    $receiveStmt = $db->prepare("
                        INSERT INTO supplier_receivings (receiving_number, supplier_id, purchase_order_number, invoice_number, received_date, total_cost, notes, status, received_by, created_at, updated_at)
                        VALUES ('PENDING', ?, 'PENDING', ?, ?, ?, ?, 'received', ?, NOW(), NOW())
                    ");
                    $receiveStmt->execute([
                        $supplierId,
                        $invoiceNumber !== '' ? $invoiceNumber : null,
                        $receivedDate,
                        $totalCost,
                        $notes !== '' ? $notes : null,
                        $_SESSION['user_id'] ?? null,
                    ]);
                    $receivingId = (int) $db->lastInsertId();
                    $receivingNumber = generatedReceivingNumber($receivingId);
                    $purchaseOrderNumber = generatedPurchaseOrderNumber($receivingId);

                    $numberStmt = $db->prepare("UPDATE supplier_receivings SET receiving_number = ?, purchase_order_number = ? WHERE id = ?");
                    $numberStmt->execute([$receivingNumber, $purchaseOrderNumber, $receivingId]);

                    $productStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
                    $batchStmt = $db->prepare("
                        INSERT INTO product_batches (product_id, quantity, expiry_date, created_at, updated_at)
                        VALUES (?, ?, ?, NOW(), NOW())
                    ");
                    $itemStmt = $db->prepare("
                        INSERT INTO supplier_receiving_items (receiving_id, product_id, product_batch_id, quantity, unit_cost, line_total, expiry_date, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $syncStmt = $db->prepare("
                        UPDATE products
                        SET stock_quantity = (
                            SELECT COALESCE(SUM(quantity), 0)
                            FROM product_batches
                            WHERE product_id = ?
                        ),
                        expiry = (
                            SELECT MIN(CASE WHEN quantity > 0 AND expiry_date IS NOT NULL THEN expiry_date END)
                            FROM product_batches
                            WHERE product_id = ?
                        ),
                        cost_price = ?,
                        last_updated_by = ?
                        WHERE id = ?
                    ");
                    $newStockStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                    $reportStmt = $db->prepare("
                        INSERT INTO inventory_reports (date, product_id, user_id, change_type, quantity, quantity_changed, previous_quantity, new_quantity, remarks, created_at)
                        VALUES (?, ?, ?, 'Added', ?, ?, ?, ?, ?, NOW())
                    ");

                    foreach ($items as $item) {
                        $productStmt->execute([$item['product_id']]);
                        $previousStock = (int) $productStmt->fetchColumn();

                        $batchStmt->execute([
                            $item['product_id'],
                            $item['quantity'],
                            $item['expiry_date'],
                        ]);
                        $batchId = (int) $db->lastInsertId();

                        $itemStmt->execute([
                            $receivingId,
                            $item['product_id'],
                            $batchId,
                            $item['quantity'],
                            $item['unit_cost'],
                            $item['line_total'],
                            $item['expiry_date'],
                        ]);

                        $syncStmt->execute([
                            $item['product_id'],
                            $item['product_id'],
                            $item['unit_cost'],
                            $_SESSION['user_id'] ?? null,
                            $item['product_id'],
                        ]);

                        $newStockStmt->execute([$item['product_id']]);
                        $newStock = (int) $newStockStmt->fetchColumn();
                        $remarks = 'Received from ' . $supplierName . ' via ' . $receivingNumber;
                        $remarks .= ' / PO ' . $purchaseOrderNumber;
                        if ($invoiceNumber !== '') {
                            $remarks .= ' / Invoice ' . $invoiceNumber;
                        }

                        $reportStmt->execute([
                            $receivedDate,
                            $item['product_id'],
                            $_SESSION['user_id'] ?? null,
                            $item['quantity'],
                            $item['quantity'],
                            $previousStock,
                            $newStock,
                            $remarks,
                        ]);
                    }

                    $db->commit();
                    header('Location: receiving.php?received=1');
                    exit();
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $message = 'Receiving failed: ' . $e->getMessage();
                    $message_type = 'danger';
                }
            }
        }
    }
}

if (isset($_GET['received'])) {
    $message = 'Supplier delivery received and stock updated.';
    $message_type = 'success';
} elseif (isset($_GET['supplier_added'])) {
    $message = 'Supplier added.';
    $message_type = 'success';
}

$filterSupplier = $_GET['supplier_id'] ?? '';
$filterStart = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$filterEnd = $_GET['end_date'] ?? date('Y-m-d');

$suppliers = fetchSuppliers($db);
$products = fetchProductsForReceiving($db);
$nextPurchaseOrderNumber = nextPurchaseOrderNumber($db);
$receivings = fetchRecentReceivings($db, $filterSupplier, $filterStart, $filterEnd);
$receivingItems = fetchReceivingItems($db, array_map(static function ($row) {
    return (int) $row['id'];
}, $receivings));

ob_start();
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="fas fa-truck-loading me-2"></i>Receive Stock</h5>
                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#supplierModal">
                    <i class="fas fa-plus me-1"></i>Supplier
                </button>
            </div>
            <div class="card-body">
                <form method="POST" id="receiveForm">
                    <input type="hidden" name="action" value="receive_stock">
                    <?php echo csrfInput(); ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Choose supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <?php if (($supplier['status'] ?? 'active') === 'active'): ?>
                                        <option value="<?php echo (int) $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Received Date</label>
                            <input type="date" name="received_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">PO Number</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($nextPurchaseOrderNumber); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-control" placeholder="Supplier invoice">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Total Cost</label>
                            <input type="text" id="receivingTotal" class="form-control" value="<?php echo formatCurrency(0); ?>" readonly>
                        </div>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-striped table-hover table-bordered align-middle mb-0">
                            <thead class="table-danger">
                                <tr>
                                    <th style="min-width: 240px;">Product</th>
                                    <th style="width: 110px;">Qty</th>
                                    <th style="width: 130px;">Unit Cost</th>
                                    <th style="width: 150px;">Expiry</th>
                                    <th style="width: 120px;">Line Total</th>
                                    <th style="width: 52px;"></th>
                                </tr>
                            </thead>
                            <tbody id="receivingRows"></tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addReceivingRow()">
                        <i class="fas fa-plus me-1"></i>Add Item
                    </button>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Delivery condition, receiving notes, or other supplier details"></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-check me-1"></i>Receive Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="content-card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-address-book me-2"></i>Suppliers</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($suppliers as $supplier): ?>
                        <div class="list-group-item px-0 bg-transparent">
                            <div class="fw-semibold"><?php echo htmlspecialchars($supplier['name']); ?></div>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($supplier['contact_person'] ?: 'No contact person'); ?>
                                <?php if (!empty($supplier['phone'])): ?>
                                    &middot; <?php echo htmlspecialchars($supplier['phone']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Receiving History</h5>
        <form method="GET" class="d-flex flex-wrap gap-2">
            <select name="supplier_id" class="form-select form-select-sm" style="width: 190px;">
                <option value="">All suppliers</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo (int) $supplier['id']; ?>" <?php echo (string) $filterSupplier === (string) $supplier['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($supplier['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterStart); ?>">
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterEnd); ?>">
            <button class="btn btn-danger btn-sm" type="submit">Filter</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (empty($receivings)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-clipboard-list fa-3x mb-3 opacity-50"></i>
                <div>No receiving records found.</div>
            </div>
        <?php else: ?>
            <div class="accordion" id="receivingAccordion">
                <?php foreach ($receivings as $index => $receiving): ?>
                    <?php
                    $receiverName = trim(($receiving['first_name'] ?? '') . ' ' . ($receiving['last_name'] ?? ''));
                    $receiverName = $receiverName !== '' ? $receiverName : ($receiving['username'] ?? 'User');
                    $collapseId = 'receiving-' . (int) $receiving['id'];
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>">
                                <div class="d-flex flex-wrap gap-3 align-items-center w-100">
                                    <strong><?php echo htmlspecialchars($receiving['receiving_number']); ?></strong>
                                    <span><?php echo htmlspecialchars($receiving['supplier_name']); ?></span>
                                    <span class="badge bg-secondary"><?php echo number_format((int) $receiving['total_quantity']); ?> units</span>
                                    <span class="text-success fw-semibold"><?php echo formatCurrency((float) $receiving['total_cost']); ?></span>
                                    <small class="text-muted ms-auto"><?php echo date('M d, Y', strtotime($receiving['received_date'])); ?></small>
                                </div>
                            </button>
                        </h2>
                        <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#receivingAccordion">
                            <div class="accordion-body">
                                <div class="row g-2 mb-3 text-muted small">
                                    <div class="col-md-3">PO: <strong><?php echo htmlspecialchars($receiving['purchase_order_number'] ?: 'N/A'); ?></strong></div>
                                    <div class="col-md-3">Invoice: <strong><?php echo htmlspecialchars($receiving['invoice_number'] ?: 'N/A'); ?></strong></div>
                                    <div class="col-md-4">Received by: <strong><?php echo htmlspecialchars($receiverName); ?></strong></div>
                                    <div class="col-md-2">Items: <strong><?php echo number_format((int) $receiving['item_count']); ?></strong></div>
                                </div>
                                <?php if (!empty($receiving['notes'])): ?>
                                    <div class="alert alert-light border py-2"><?php echo htmlspecialchars($receiving['notes']); ?></div>
                                <?php endif; ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover table-bordered align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Type</th>
                                                <th>Qty</th>
                                                <th>Unit Cost</th>
                                                <th>Line Total</th>
                                                <th>Expiry</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($receivingItems[$receiving['id']] ?? [] as $item): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($item['sku'] ?? ''); ?></small>
                                                    </td>
                                                    <td><span class="badge bg-<?php echo ($item['product_type'] ?? 'retail') === 'wholesale' ? 'dark' : 'secondary'; ?>"><?php echo ucfirst($item['product_type'] ?? 'retail'); ?></span></td>
                                                    <td><?php echo number_format((int) $item['quantity']); ?></td>
                                                    <td><?php echo formatCurrency((float) $item['unit_cost']); ?></td>
                                                    <td><?php echo formatCurrency((float) $item['line_total']); ?></td>
                                                    <td><?php echo $item['expiry_date'] ? date('M d, Y', strtotime($item['expiry_date'])) : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_supplier">
                    <?php echo csrfInput(); ?>
                    <div class="mb-3">
                        <label class="form-label">Supplier Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const products = <?php echo json_encode($products); ?>;

function productOptions() {
    return products.map(product => {
        const type = product.product_type === 'wholesale' ? 'Wholesale' : 'Retail';
        const sku = product.sku || `SKU-${String(product.id).padStart(4, '0')}`;
        return `<option value="${product.id}" data-cost="${product.cost_price || 0}">${product.name} (${type}, ${sku})</option>`;
    }).join('');
}

function addReceivingRow(product = {}) {
    const tbody = document.getElementById('receivingRows');
    const row = document.createElement('tr');
    row.className = 'receiving-row';
    row.innerHTML = `
        <td>
            <select name="product_id[]" class="form-select form-select-sm product-select" required onchange="applyProductCost(this)">
                <option value="">Choose product</option>
                ${productOptions()}
            </select>
        </td>
        <td><input type="number" name="quantity[]" class="form-control form-control-sm quantity-input" min="1" value="${product.quantity || ''}" required oninput="updateReceivingTotals()"></td>
        <td><input type="number" name="unit_cost[]" class="form-control form-control-sm cost-input" min="0" step="0.01" value="${product.unit_cost || ''}" required oninput="updateReceivingTotals()"></td>
        <td><input type="date" name="expiry_date[]" class="form-control form-control-sm"></td>
        <td class="line-total fw-semibold">${formatMoney(0)}</td>
        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeReceivingRow(this)"><i class="fas fa-trash"></i></button></td>
    `;
    tbody.appendChild(row);
}

function applyProductCost(select) {
    const option = select.options[select.selectedIndex];
    const row = select.closest('tr');
    const costInput = row.querySelector('.cost-input');
    if (costInput && option?.dataset.cost && !costInput.value) {
        costInput.value = Number(option.dataset.cost || 0).toFixed(2);
    }
    updateReceivingTotals();
}

function removeReceivingRow(button) {
    button.closest('tr')?.remove();
    updateReceivingTotals();
}

function formatMoney(value) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(Number(value || 0));
}

function updateReceivingTotals() {
    let total = 0;
    document.querySelectorAll('.receiving-row').forEach(row => {
        const qty = Number(row.querySelector('.quantity-input')?.value || 0);
        const cost = Number(row.querySelector('.cost-input')?.value || 0);
        const lineTotal = qty * cost;
        total += lineTotal;
        const lineCell = row.querySelector('.line-total');
        if (lineCell) {
            lineCell.textContent = formatMoney(lineTotal);
        }
    });
    document.getElementById('receivingTotal').value = formatMoney(total);
}

document.addEventListener('DOMContentLoaded', function () {
    addReceivingRow();

    document.getElementById('receiveForm')?.addEventListener('submit', function (event) {
        if (!document.querySelector('.receiving-row')) {
            event.preventDefault();
            Swal.fire('Missing items', 'Add at least one received product.', 'warning');
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../app/views/layout.php';
?>
