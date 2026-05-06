<?php
require_once __DIR__ . '/../app/config.php';
User::requireLogin();

// Redirect admin to admin panel
if (User::isAdmin()) {
    header('Location: ' . BASE_URL . 'public/dashboard.php');
    exit();
}

$posController = new POSController();

// Handle GET request for GCash QR Code
if (isset($_GET['action']) && $_GET['action'] === 'get_gcash_qr') {
    header('Content-Type: application/json');
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT qr_code_path FROM payment_qrcodes WHERE payment_method = ? AND is_active = 1 LIMIT 1");
    $stmt->execute(['gcash']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && !empty($row['qr_code_path'])) {
        echo json_encode([
            'success' => true,
            'qr_code_path' => $row['qr_code_path']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No QR code found'
        ]);
    }
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'complete_sale':
            $items = json_decode($_POST['items'], true);
            $amount_received = floatval($_POST['amount_received']);
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $discount_amount = floatval($_POST['discount_amount'] ?? 0);
            $result = $posController->createOrder($items, $payment_method, $amount_received, $discount_amount);
            echo json_encode($result);
            exit();
            
        case 'get_product':
            $product = $posController->getProductById($_POST['product_id']);
            echo json_encode($product);
            exit();
    }
}

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$productType = $_GET['product_type'] ?? '';
$products = $posController->getProducts($search, $category, $productType);
$categories = $posController->getCategories();

$title = 'Point of Sale';

ob_start();
?>

<div class="row g-3 pos-workspace">
    <!-- Products Section -->
    <div class="col-lg-8">
        <div class="card h-100 pos-products-panel">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Products</h6>
                    <small class="text-muted">Search, filter, then tap an item to add it.</small>
                </div>
                <span class="badge bg-light text-dark border"><?php echo number_format(count($products)); ?> shown</span>
            </div>
            <div class="card-body p-3">
                <!-- Search and Filters -->
                <form method="GET" class="row g-2 mb-2 pos-searchbar">
                    <div class="col-md-5">
                        <input type="text" id="product-search" name="search" class="form-control form-control-sm" placeholder="Search by name or code..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat['id']; ?>" <?php echo (string) $category === (string) $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="product_type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option value="retail" <?php echo $productType === 'retail' ? 'selected' : ''; ?>>Retail</option>
                            <option value="wholesale" <?php echo $productType === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-danger btn-sm flex-grow-1">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="pos.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>

                <div class="mb-3 d-flex flex-wrap gap-2">
                    <?php
                    $baseTypeUrl = 'pos.php?search=' . urlencode($search) . '&category=' . urlencode($category);
                    ?>
                    <a class="btn btn-sm <?php echo empty($productType) ? 'btn-danger' : 'btn-outline-danger'; ?>" href="<?php echo $baseTypeUrl; ?>">All</a>
                    <a class="btn btn-sm <?php echo $productType === 'retail' ? 'btn-secondary' : 'btn-outline-secondary'; ?>" href="<?php echo $baseTypeUrl; ?>&product_type=retail">Retail</a>
                    <a class="btn btn-sm <?php echo $productType === 'wholesale' ? 'btn-dark' : 'btn-outline-dark'; ?>" href="<?php echo $baseTypeUrl; ?>&product_type=wholesale">Wholesale</a>
                </div>
                
                <!-- Products Grid -->
                <div class="product-scroll">
                    <div class="row g-2">
                    <?php if (empty($products)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No products found</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="product-card pos-product-tile p-3 border rounded" onclick="addToCart(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-2" style="font-size: 0.92rem; line-height: 1.3;"><?php echo htmlspecialchars($product['name']); ?></h6>
                                    <div class="mb-1">
                                        <span class="badge bg-<?php echo ($product['product_type'] ?? 'retail') === 'wholesale' ? 'dark' : 'secondary'; ?>" style="font-size: 0.68rem;">
                                            <?php echo ucfirst($product['product_type'] ?? 'retail'); ?>
                                        </span>
                                        <?php if (!empty($product['category_name'])): ?>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.68rem;"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-success fw-bold" style="font-size: 1rem;"><?php echo formatCurrency($product['price']); ?></div>
                                    <small class="text-muted" style="font-size: 0.78rem;">Stock: <?php echo $product['stock_quantity']; ?></small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); addToCart(<?php echo htmlspecialchars(json_encode($product)); ?>)" style="padding: 5px 8px;">
                                    <i class="fas fa-plus" style="font-size: 0.82rem;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Current Order Section -->
    <div class="col-lg-4">
        <div class="card h-100 pos-order-panel">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Current Order</h6>
                    <small class="text-muted">Cart, quantities, payment</small>
                </div>
                <span class="badge bg-secondary" id="cart-count">0 Items</span>
            </div>
            <div class="card-body p-2 d-flex flex-column pos-order-body">
                <!-- Cart Items -->
                <div id="cart-items" class="pos-cart-list">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered align-middle mb-0" style="font-size: 0.75rem;">
                            <thead style="position: sticky; top: 0; background: #f8f9fa; z-index: 1;">
                                <tr style="border-bottom: 2px solid #dee2e6;">
                                    <th class="py-2" style="font-weight: 600; color: #495057;">ITEM</th>
                                    <th class="py-2 text-center" style="font-weight: 600; color: #495057; width: 120px;">QTY</th>
                                    <th class="py-2 text-end" style="font-weight: 600; color: #495057; width: 90px;">TOTAL</th>
                                    <th class="py-2 text-center" style="font-weight: 600; color: #495057; width: 44px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cart-tbody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4" style="font-size: 0.85rem;">
                                        <i class="fas fa-shopping-cart mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
                                        <div>Cart is empty</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <button type="button" id="clear-cart-btn" class="btn btn-outline-danger btn-sm w-100 mb-2" disabled>
                    <i class="fas fa-trash-alt me-1"></i>Clear All
                </button>
                
                <!-- Cart Summary (Compact) -->
                <div class="pos-total-panel">
                    <div class="row g-1 mb-1">
                        <div class="col-6"><small style="font-weight: 500;">Subtotal:</small></div>
                        <div class="col-6 text-end"><small id="subtotal" style="font-weight: 600;">₱0.00</small></div>
                    </div>
                    <div class="row g-1 mb-1 align-items-center">
                        <div class="col-6">
                            <small style="font-weight: 500;">Discount (<?php echo chr(8369); ?>):</small>
                            <input type="number" id="discount" class="form-control form-control-sm d-inline" style="width: 68px; height: 22px; font-size: 0.7rem; padding: 2px 4px;" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-6 text-end"><small id="discount-amount" style="font-weight: 600; color: #198754;">-₱0.00</small></div>
                    </div>
                    <div class="row g-1 pt-1" style="border-top: 1px solid #dee2e6;">
                        <div class="col-6"><strong style="font-size: 1.1rem; color: #212529;">TOTAL:</strong></div>
                        <div class="col-6 text-end"><strong id="total" style="font-size: 1.1rem; color: var(--accent-deep);">₱0.00</strong></div>
                    </div>
                </div>
                
                <!-- Payment Details -->
                <div class="pos-payment-panel">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label style="font-size: 0.75rem; font-weight: 600; color: #495057; margin-bottom: 4px; display: block;">Payment Method:</label>
                            <select id="payment-method" class="form-select form-select-sm" style="font-size: 0.8rem;">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label style="font-size: 0.75rem; font-weight: 600; color: #495057; margin-bottom: 4px; display: block;">Amount Received:</label>
                            <input type="number" id="amount-received" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00" style="font-size: 0.8rem;">
                        </div>
                    </div>
                    
                    <!-- Quantity is adjusted directly in each cart row, like a standard POS. -->
                    <div class="payment-buttons mb-2 d-none">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small style="font-size: 0.7rem; color: #6c757d;">Quick Quantity:</small>
                            <small class="text-muted" id="selected-cart-item-label" style="font-size: 0.68rem;">Select an item</small>
                        </div>
                        <div class="row g-1 mb-1">
                            <div class="col"><button class="btn btn-outline-primary btn-sm w-100 quick-quantity" data-quantity="1" style="font-size: 0.7rem; padding: 4px;">1</button></div>
                            <div class="col"><button class="btn btn-outline-primary btn-sm w-100 quick-quantity" data-quantity="2" style="font-size: 0.7rem; padding: 4px;">2</button></div>
                            <div class="col"><button class="btn btn-outline-primary btn-sm w-100 quick-quantity" data-quantity="3" style="font-size: 0.7rem; padding: 4px;">3</button></div>
                            <div class="col"><button class="btn btn-outline-primary btn-sm w-100 quick-quantity" data-quantity="5" style="font-size: 0.7rem; padding: 4px;">5</button></div>
                            <div class="col"><button class="btn btn-outline-primary btn-sm w-100 quick-quantity" data-quantity="10" style="font-size: 0.7rem; padding: 4px;">10</button></div>
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" style="font-size: 0.7rem;">Qty</span>
                            <input type="number" id="quick-quantity-input" class="form-control" min="1" value="1" style="font-size: 0.75rem;">
                            <button class="btn btn-outline-success" type="button" id="apply-quantity-btn" style="font-size: 0.7rem;">Apply</button>
                            <button class="btn btn-outline-danger" type="button" id="remove-selected-item-btn" style="font-size: 0.7rem;" title="Remove selected item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="payment-buttons mb-2 d-none">
                        <small class="d-block mb-1" style="font-size: 0.7rem; color: #6c757d;">Quick Amount:</small>
                        <div class="row g-1 mb-1">
                            <div class="col-4"><button class="btn btn-outline-primary btn-sm w-100 quick-amount" data-amount="20" style="font-size: 0.7rem; padding: 4px;">₱20</button></div>
                            <div class="col-4"><button class="btn btn-outline-primary btn-sm w-100 quick-amount" data-amount="50" style="font-size: 0.7rem; padding: 4px;">₱50</button></div>
                            <div class="col-4"><button class="btn btn-outline-primary btn-sm w-100 quick-amount" data-amount="100" style="font-size: 0.7rem; padding: 4px;">₱100</button></div>
                        </div>
                        <div class="row g-1 mb-1">
                            <div class="col-4"><button class="btn btn-outline-primary btn-sm w-100 quick-amount" data-amount="200" style="font-size: 0.7rem; padding: 4px;">₱200</button></div>
                            <div class="col-4"><button class="btn btn-outline-primary btn-sm w-100 quick-amount" data-amount="500" style="font-size: 0.7rem; padding: 4px;">₱500</button></div>
                            <div class="col-4"><button class="btn btn-outline-primary btn-sm w-100 quick-amount" data-amount="1000" style="font-size: 0.7rem; padding: 4px;">₱1000</button></div>
                        </div>
                        <div class="row g-1">
                            <div class="col-6"><button class="btn btn-outline-success btn-sm w-100" id="exact-amount-btn" style="font-size: 0.7rem; padding: 4px;">Exact Amount</button></div>
                            <div class="col-6"><button class="btn btn-outline-danger btn-sm w-100" id="clear-amount-btn" style="font-size: 0.7rem; padding: 4px;">Clear</button></div>
                        </div>
                    </div>
                    
                    <!-- Change Display -->
                    <div id="change-display" class="alert alert-success py-1 px-2 mb-2" style="display: none; font-size: 0.8rem;">
                        <div class="d-flex justify-content-between">
                            <strong>Change:</strong>
                            <strong id="change-amount">₱0.00</strong>
                        </div>
                    </div>
                </div>
                    
                <button id="complete-sale" class="btn btn-success w-100" style="font-weight: 600; padding: 10px; font-size: 0.95rem;" disabled title="Add items to cart and provide payment amount">
                    <i class="fas fa-check-circle me-1"></i> Complete Sale
                </button>

                <div class="border rounded p-2 mt-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <small class="fw-bold"><i class="fas fa-print me-1"></i>Thermal Printer</small>
                        <span class="badge bg-secondary" id="printer-bridge-status">Not checked</span>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="printer-bridge-url" value="http://127.0.0.1:9123" aria-label="Printer bridge URL">
                        <button class="btn btn-outline-secondary" type="button" id="check-printer-bridge" title="Check bridge">
                            <i class="fas fa-plug"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="receiptModalLabel">
                    <i class="fas fa-check-circle me-2"></i>Sale completed successfully
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="receiptContent" style="font-family: 'Courier New', monospace; background: #fff;">
                <!-- Receipt content will be dynamically generated here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="thermal-print-receipt">
                    <i class="fas fa-receipt me-1"></i>Thermal Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- GCash QR Code Modal -->
<div class="modal fade" id="gcashQRModal" tabindex="-1" aria-labelledby="gcashQRModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="gcashQRModalLabel">
                    <i class="fas fa-qrcode me-2"></i>Scan GCash QR Code
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" id="gcashQRContent">
                <p class="text-muted mb-3">Scan the QR code below to pay with GCash</p>
                <div id="qrCodeImage">
                    <!-- QR Code will be loaded here -->
                </div>
                <div id="gcashConfirmNote" class="small text-muted mt-3" style="display: none;">
                    Confirm the payment after the customer completes the GCash transaction.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-gcash-payment" style="display: none;">
                    <i class="fas fa-check me-1"></i>Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>public/assets/js/printer-bridge-client.js"></script>
<script src="<?php echo BASE_URL; ?>public/assets/js/thermal-printer.js"></script>
<script>
let cart = [];
let discountAmount = 0;
let gcashPaymentConfirmed = false;
const pesoSign = String.fromCharCode(8369);
const printerBridge = new PrinterBridgeClient();
let lastThermalReceiptData = null;
let selectedCartItemId = null;
let thermalPrintInProgress = false;
let thermalPrinterToast = null;

function getTotalAmount() {
    return parseFloat(document.getElementById('total').textContent.replace(/[^\d.-]/g, '')) || 0;
}

function normalizeCurrencySigns(value) {
    const mojibakePeso = String.fromCharCode(0x00E2, 0x201A, 0x00B1);
    const doubleMojibakePeso = String.fromCharCode(0x00C3, 0x00A2, 0x00E2, 0x20AC, 0x0161, 0x00C2, 0x00B1);

    return String(value)
        .replaceAll(doubleMojibakePeso, pesoSign)
        .replaceAll(mojibakePeso, pesoSign);
}

function isGcashSelected() {
    return document.getElementById('payment-method').value === 'gcash';
}

function resetGcashConfirmation(clearAmount = true) {
    gcashPaymentConfirmed = false;

    if (clearAmount) {
        document.getElementById('amount-received').value = '';
    }

    document.getElementById('change-display').style.display = 'none';
}

function updatePaymentControls() {
    const gcashSelected = isGcashSelected();
    const amountReceivedInput = document.getElementById('amount-received');

    amountReceivedInput.disabled = gcashSelected;
    amountReceivedInput.placeholder = gcashSelected ? 'Handled by GCash confirmation' : '0.00';

    document.querySelectorAll('.quick-amount').forEach(button => {
        button.disabled = gcashSelected;
    });

    document.getElementById('exact-amount-btn').disabled = gcashSelected;
    document.getElementById('clear-amount-btn').disabled = gcashSelected;

    if (gcashSelected) {
        document.getElementById('change-display').style.display = 'none';
    }
}

function showGcashQRModal() {
    const confirmButton = document.getElementById('confirm-gcash-payment');
    const confirmNote = document.getElementById('gcashConfirmNote');

    fetch('pos.php?action=get_gcash_qr')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.qr_code_path) {
                const qrImageUrl = BASE_URL + data.qr_code_path;
                document.getElementById('qrCodeImage').innerHTML = `
                    <div class="alert alert-danger mt-3" id="qrCodeError" style="display: none;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Unable to load the GCash QR code.
                    </div>
                    <img src="${qrImageUrl}" alt="GCash QR Code" class="img-fluid" style="max-width: 300px; border: 2px solid #0d6efd; border-radius: 8px;" onerror="this.style.display='none'; document.getElementById('qrCodeError')?.style.setProperty('display', 'block'); document.getElementById('confirm-gcash-payment')?.style.setProperty('display', 'none'); document.getElementById('gcashConfirmNote')?.style.setProperty('display', 'none');">
                `;
                confirmButton.style.display = 'inline-block';
                confirmNote.style.display = 'block';
            } else {
                document.getElementById('qrCodeImage').innerHTML = `
                    <div class="alert alert-warning" id="qrCodeError">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No GCash QR code available. Please contact administrator.
                    </div>
                `;
                confirmButton.style.display = 'none';
                confirmNote.style.display = 'none';
            }

            const gcashModal = new bootstrap.Modal(document.getElementById('gcashQRModal'));
            gcashModal.show();
        })
        .catch(error => {
            console.error('Error fetching GCash QR:', error);
            confirmButton.style.display = 'none';
            confirmNote.style.display = 'none';
            toastr.error('Error loading GCash QR code', 'Error');
        });
}

function addToCart(product) {
    const quantityInput = event.target.closest('.product-card')?.querySelector('input[type="number"]');
    const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
    
    // Check if product already in cart
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        existingItem.quantity += quantity;
        selectedCartItemId = existingItem.id;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            product_type: product.product_type || 'retail',
            price: parseFloat(product.price),
            quantity: quantity,
            stock: product.stock_quantity
        });
        selectedCartItemId = product.id;
    }
    
    updateCartDisplay();
}

function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    if (selectedCartItemId === productId) {
        selectedCartItemId = cart.length ? cart[cart.length - 1].id : null;
    }
    updateCartDisplay();
}

function clearShoppingCart(showNotice = true) {
    cart = [];
    discountAmount = 0;
    gcashPaymentConfirmed = false;
    selectedCartItemId = null;

    document.getElementById('discount').value = '0';
    document.getElementById('amount-received').value = '';
    document.getElementById('payment-method').value = 'cash';
    document.getElementById('change-display').style.display = 'none';

    updatePaymentControls();
    updateCartDisplay();

    if (showNotice) {
        toastr.info('Shopping cart cleared.', 'Cart Cleared');
    }
}

function confirmClearShoppingCart() {
    if (cart.length === 0) {
        return;
    }

    if (window.Swal) {
        Swal.fire({
            title: 'Clear All Items?',
            text: 'This will remove every item from the shopping cart.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Clear All',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                clearShoppingCart();
            }
        });
        return;
    }

    if (confirm('Clear all items from the shopping cart?')) {
        clearShoppingCart();
    }
}

function updateCartQuantity(productId, newQuantity) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        if (newQuantity <= 0) {
            removeFromCart(productId);
        } else {
            selectedCartItemId = productId;
            item.quantity = Math.min(parseInt(newQuantity, 10) || 1, parseInt(item.stock, 10) || 999999);
            updateCartDisplay();
        }
    }
}

function selectCartItem(productId) {
    selectedCartItemId = productId;
    const item = cart.find(item => item.id === productId);
    if (item) {
        document.getElementById('quick-quantity-input').value = item.quantity;
    }
    updateCartDisplay();
}

function getSelectedCartItem() {
    if (!selectedCartItemId && cart.length) {
        selectedCartItemId = cart[cart.length - 1].id;
    }

    return cart.find(item => item.id === selectedCartItemId) || null;
}

function applyQuickQuantity(quantity) {
    const item = getSelectedCartItem();
    if (!item) {
        toastr.warning('Select an item in the cart first.', 'No Item Selected');
        return;
    }

    updateCartQuantity(item.id, quantity);
}

function updateCartDisplay() {
    const cartTbody = document.getElementById('cart-tbody');
    
    if (cart.length === 0) {
        cartTbody.innerHTML = `<tr>
            <td colspan="3" class="text-center text-muted py-4" style="font-size: 0.85rem;">
                <i class="fas fa-shopping-cart mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
                <div>Cart is empty</div>
            </td>
        </tr>`;
        document.getElementById('clear-cart-btn').disabled = true;
        document.getElementById('complete-sale').disabled = true;
    } else {
        let html = '';
        cart.forEach(item => {
            const total = item.price * item.quantity;
            html += `
                <tr style="border-bottom: 1px solid #e9ecef;">
                    <td class="py-2">
                        <div style="font-weight: 600; font-size: 0.8rem; color: #212529; margin-bottom: 2px;">${item.name}</div>
                        <div style="font-size: 0.7rem; color: #6c757d;"><span style="text-transform: capitalize;">${item.product_type || 'retail'}</span> • ₱${item.price.toFixed(2)} each</div>
                    </td>
                    <td class="py-2 text-center">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateCartQuantity(${item.id}, ${item.quantity - 1})" style="padding: 2px 6px; font-size: 0.7rem; line-height: 1;">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span style="font-weight: 600; min-width: 25px; text-align: center; font-size: 0.85rem;">${item.quantity}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateCartQuantity(${item.id}, ${item.quantity + 1})" style="padding: 2px 6px; font-size: 0.7rem; line-height: 1;">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${item.id})" style="padding: 2px 6px; font-size: 0.7rem; line-height: 1;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                    <td class="py-2 text-end">
                        <div style="font-weight: 700; font-size: 0.9rem; color: #0d6efd;">₱${total.toFixed(2)}</div>
                    </td>
                </tr>
            `;
        });
        cartTbody.innerHTML = html;
        document.getElementById('clear-cart-btn').disabled = false;
        document.getElementById('complete-sale').disabled = false;
    }
    
    updateTotals();
}

updateCartDisplay = function() {
    const cartTbody = document.getElementById('cart-tbody');

    if (cart.length === 0) {
        cartTbody.innerHTML = `<tr>
            <td colspan="4" class="text-center text-muted py-4" style="font-size: 0.85rem;">
                <i class="fas fa-shopping-cart mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
                <div>Cart is empty</div>
            </td>
        </tr>`;
        document.getElementById('cart-count').textContent = '0 Items';
        document.getElementById('selected-cart-item-label').textContent = 'Select an item';
        document.getElementById('clear-cart-btn').disabled = true;
        document.getElementById('complete-sale').disabled = true;
        updateTotals();
        return;
    }

    const cartCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    let html = '';

    cart.forEach(item => {
        const total = item.price * item.quantity;
        const selectedClass = selectedCartItemId === item.id ? 'table-warning' : '';

        html += `
            <tr class="${selectedClass}" onclick="selectCartItem(${item.id})" style="border-bottom: 1px solid #e9ecef; cursor: pointer;">
                <td class="py-2">
                    <div style="font-weight: 700; font-size: 0.86rem; color: #212529; margin-bottom: 2px;">${item.name}</div>
                    <div style="font-size: 0.72rem; color: #6c757d;"><span style="text-transform: capitalize;">${item.product_type || 'retail'}</span> • ${pesoSign}${item.price.toFixed(2)} each</div>
                </td>
                <td class="py-2 text-center">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); updateCartQuantity(${item.id}, ${item.quantity - 1})" style="padding: 2px 6px; font-size: 0.7rem; line-height: 1;">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="form-control form-control-sm text-center cart-qty-input" min="1" max="${item.stock || 999999}" value="${item.quantity}" onclick="event.stopPropagation();" onchange="updateCartQuantity(${item.id}, this.value)" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.blur(); }" aria-label="Quantity for ${item.name}">
                        <button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); updateCartQuantity(${item.id}, ${item.quantity + 1})" style="padding: 2px 6px; font-size: 0.7rem; line-height: 1;">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </td>
                <td class="py-2 text-end">
                    <div style="font-weight: 800; font-size: 0.92rem; color: #0d6efd;">${pesoSign}${total.toFixed(2)}</div>
                </td>
                <td class="py-2 text-center">
                    <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); removeFromCart(${item.id})" style="padding: 2px 6px; font-size: 0.7rem; line-height: 1;">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    cartTbody.innerHTML = html;
    document.getElementById('cart-count').textContent = `${cartCount} Item${cartCount === 1 ? '' : 's'}`;

    const selectedItem = getSelectedCartItem();
    document.getElementById('selected-cart-item-label').textContent = selectedItem ? selectedItem.name : 'Select an item';
    if (selectedItem) {
        document.getElementById('quick-quantity-input').value = selectedItem.quantity;
    }

    document.getElementById('clear-cart-btn').disabled = false;
    document.getElementById('complete-sale').disabled = false;
    updateTotals();
};

function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = Math.min(Math.max(discountAmount, 0), subtotal);
    const total = subtotal - discount;
    
    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('discount-amount').textContent = '-₱' + discount.toFixed(2);
    document.getElementById('total').textContent = '₱' + total.toFixed(2);
    
    if (isGcashSelected()) {
        resetGcashConfirmation();
    }

    // Validate payment after totals update
    validatePayment();
}

// Validate if payment received is sufficient
function validatePayment() {
    const total = parseFloat(document.getElementById('total').textContent.replace('₱', '')) || 0;
    const amountReceived = parseFloat(document.getElementById('amount-received').value) || 0;
    const completeBtn = document.getElementById('complete-sale');
    
    // Disable button if cart is empty or payment is insufficient
    if (cart.length === 0) {
        completeBtn.disabled = true;
        completeBtn.title = 'Add items to cart to complete sale';
    } else if (amountReceived < total) {
        completeBtn.disabled = true;
        const shortage = total - amountReceived;
        completeBtn.title = `Payment insufficient! Short by ₱${shortage.toFixed(2)}`;
    } else {
        completeBtn.disabled = false;
        completeBtn.title = 'Click to complete the sale';
    }
}

// Discount input handler
document.getElementById('discount').addEventListener('input', function() {
    discountAmount = parseFloat(this.value) || 0;
    updateTotals();
    calculateChange();
});

// Amount received input handler
document.getElementById('amount-received').addEventListener('input', function() {
    calculateChange();
    validatePayment();
});

// Quick amount buttons
document.querySelectorAll('.quick-amount').forEach(button => {
    button.addEventListener('click', function() {
        const amount = parseFloat(this.dataset.amount);
        document.getElementById('amount-received').value = amount.toFixed(2);
        calculateChange();
        validatePayment();
    });
});

// Exact amount button
document.getElementById('exact-amount-btn').addEventListener('click', function() {
    const total = parseFloat(document.getElementById('total').textContent.replace('₱', '')) || 0;
    document.getElementById('amount-received').value = total.toFixed(2);
    calculateChange();
    validatePayment();
});

// Clear amount button
document.getElementById('clear-amount-btn').addEventListener('click', function() {
    document.getElementById('amount-received').value = '';
    document.getElementById('change-display').style.display = 'none';
    validatePayment();
});

document.querySelectorAll('.quick-quantity').forEach(button => {
    button.addEventListener('click', function() {
        const quantity = parseInt(this.dataset.quantity, 10) || 1;
        document.getElementById('quick-quantity-input').value = quantity;
        applyQuickQuantity(quantity);
    });
});

document.getElementById('apply-quantity-btn').addEventListener('click', function() {
    const quantity = parseInt(document.getElementById('quick-quantity-input').value, 10) || 1;
    applyQuickQuantity(quantity);
});

document.getElementById('remove-selected-item-btn').addEventListener('click', function() {
    const item = getSelectedCartItem();
    if (!item) {
        toastr.warning('Select an item in the cart first.', 'No Item Selected');
        return;
    }

    removeFromCart(item.id);
});

document.getElementById('clear-cart-btn').addEventListener('click', confirmClearShoppingCart);
document.getElementById('check-printer-bridge').addEventListener('click', () => checkPrinterBridge(true));
document.getElementById('thermal-print-receipt').addEventListener('click', printThermalReceipt);

if (localStorage.getItem('posPrinterBridgeUrl')) {
    document.getElementById('printer-bridge-url').value = localStorage.getItem('posPrinterBridgeUrl');
}

checkPrinterBridge(false);

// Calculate change
function calculateChange() {
    const total = parseFloat(document.getElementById('total').textContent.replace('₱', '')) || 0;
    const amountReceived = parseFloat(document.getElementById('amount-received').value) || 0;
    const change = amountReceived - total;
    
    const changeDisplay = document.getElementById('change-display');
    const changeAmount = document.getElementById('change-amount');
    
    if (amountReceived > 0) {
        if (change >= 0) {
            changeDisplay.className = 'alert alert-success py-1 px-2 mb-2';
            changeAmount.textContent = '₱' + change.toFixed(2);
            changeDisplay.style.display = 'block';
        } else {
            changeDisplay.className = 'alert alert-danger py-1 px-2 mb-2';
            changeAmount.textContent = 'Short: ₱' + Math.abs(change).toFixed(2);
            changeDisplay.style.display = 'block';
        }
    } else {
        changeDisplay.style.display = 'none';
    }
}

// Complete sale
document.getElementById('complete-sale').addEventListener('click', function() {
    const amountReceived = parseFloat(document.getElementById('amount-received').value);
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = Math.min(Math.max(discountAmount, 0), subtotal);
    const total = subtotal - discount;
    
    if (!amountReceived || amountReceived < total) {
        toastr.error(`Amount received (₱${amountReceived ? amountReceived.toFixed(2) : '0.00'}) is less than total amount (₱${total.toFixed(2)})!`, 'Insufficient Payment');
        return;
    }
    
    const paymentMethod = document.getElementById('payment-method').value;
    
    // Send order to server
    fetch('pos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=complete_sale&items=${encodeURIComponent(JSON.stringify(cart))}&amount_received=${amountReceived}&payment_method=${paymentMethod}&discount_amount=${discount}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Generate and show receipt
            generateReceipt(data);
            
            // Clear cart
            clearShoppingCart(false);
            
            // Show receipt modal
            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            receiptModal.show();
        } else {
            toastr.error(data.message || 'Error completing sale', 'Sale Failed');
        }
    });
});

// Generate Receipt HTML
function generateReceipt(orderData) {
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    
    const subtotal = orderData.subtotal || 0;
    const netSales = orderData.net_sales || 0;
    const discount = orderData.discount || 0;
    const tax = orderData.tax || 0;
    const total = orderData.total || 0;
    const amountPaid = parseFloat(document.getElementById('amount-received').value) || 0;
    const change = orderData.change || (amountPaid - total);
    const paymentMethod = document.getElementById('payment-method').value.toUpperCase();
    const receiptItems = cart.map(item => ({ ...item }));

    lastThermalReceiptData = {
        storeName: "Kakai's Kutkutin POS",
        orderNumber: orderData.order_number || 'N/A',
        date: `${dateStr} ${timeStr}`,
        cashier: "<?php echo htmlspecialchars(trim(($_SESSION['first_name'] ?? 'Staff') . ' ' . ($_SESSION['last_name'] ?? 'Member')), ENT_QUOTES, 'UTF-8'); ?>",
        paymentMethod,
        items: receiptItems,
        subtotal,
        netSales,
        discount,
        tax,
        total,
        amountPaid,
        change
    };
    
    let itemsHTML = '';
    receiptItems.forEach(item => {
        const itemTotal = item.price * item.quantity;
        itemsHTML += `
            <tr>
                <td style="padding: 4px 0;">${item.name}<br><span style="font-size: 10px; text-transform: capitalize;">${item.product_type || 'retail'}</span></td>
                <td style="padding: 4px 0; text-align: center;">${item.quantity} x ₱${item.price.toFixed(2)}</td>
                <td style="padding: 4px 0; text-align: right;">₱${itemTotal.toFixed(2)}</td>
            </tr>
        `;
    });
    
    const receiptHTML = `
        <div style="max-width: 400px; margin: 0 auto; padding: 20px; text-align: center;">
            <h4 style="margin: 0 0 5px 0;">Kakai's Kutkutin POS</h4>
            <p style="margin: 0; font-size: 11px; line-height: 1.4;"><br>
            
            </p>
            
            <hr style="border-top: 1px solid #000; margin: 15px 0;">
            
            <div style="text-align: left; font-size: 12px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                    <span>Sale #: <strong>${orderData.order_number || 'N/A'}</strong></span>
                    <span>${dateStr} ${timeStr}</span>
                </div>
                <div style="margin-bottom: 3px;">
                    <span>Cashier: <strong><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Staff') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? 'Member'); ?></strong></span>
                </div>
                <div>
                    <span>Customer: <strong>Walk-in</strong></span>
                </div>
            </div>
            
            <table style="width: 100%; font-size: 12px; margin-bottom: 15px;">
                <tbody>
                    ${itemsHTML}
                </tbody>
            </table>
            
            <hr style="border-top: 1px dashed #000; margin: 15px 0;">
            
            <div style="text-align: left; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Subtotal:</span>
                    <span>₱${subtotal.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Discount:</span>
                    <span>-â‚±${discount.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>VATable Sales:</span>
                    <span>â‚±${netSales.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>VAT Included (12%):</span>
                    <span>â‚±${tax.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #000;">
                    <strong style="font-size: 15px;">TOTAL:</strong>
                    <strong style="font-size: 15px;">₱${total.toFixed(2)}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Payment (${paymentMethod}):</span>
                    <span>₱${amountPaid.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Change:</span>
                    <span>₱${change.toFixed(2)}</span>
                </div>
            </div>
            
            <hr style="border-top: 1px solid #000; margin: 20px 0 15px 0;">
            
            <p style="margin: 5px 0; font-size: 11px;">Thank you, come again.</p>
        </div>
    `;
    
    document.getElementById('receiptContent').innerHTML = normalizeCurrencySigns(receiptHTML);
}

function setPrinterBridgeStatus(status, label) {
    const badge = document.getElementById('printer-bridge-status');
    badge.className = `badge ${status}`;
    badge.textContent = label;
}

async function checkPrinterBridge(showToast = true) {
    const bridgeUrl = document.getElementById('printer-bridge-url').value.trim() || 'http://127.0.0.1:9123';
    printerBridge.setBaseUrl(bridgeUrl);

    try {
        const status = await printerBridge.status();
        setPrinterBridgeStatus('bg-success', 'Connected');
        if (showToast) {
            toastr.success(`Printer bridge connected: ${status.target || status.transport}`, 'Printer Ready');
        }
        return true;
    } catch (error) {
        setPrinterBridgeStatus('bg-danger', 'Offline');
        if (showToast) {
            toastr.error('Printer bridge is offline. Start tools/printer-bridge/start-printer-bridge.bat first.', 'Printer Bridge');
        }
        return false;
    }
}

async function printThermalReceipt() {
    if (thermalPrintInProgress) {
        toastr.info('Thermal print is already in progress.', 'Thermal Printer');
        return;
    }

    if (!lastThermalReceiptData) {
        toastr.warning('No receipt is ready for thermal printing.', 'Thermal Printer');
        return;
    }

    const printButton = document.getElementById('thermal-print-receipt');
    thermalPrintInProgress = true;
    printButton.disabled = true;
    printButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Printing...';

    const connected = await checkPrinterBridge(false);
    if (!connected) {
        if (thermalPrinterToast) {
            toastr.clear(thermalPrinterToast);
        }
        thermalPrinterToast = toastr.error('Printer bridge is offline. Start the local bridge and try again.', 'Thermal Printer', { timeOut: 6000, extendedTimeOut: 2000 });
        thermalPrintInProgress = false;
        printButton.disabled = false;
        printButton.innerHTML = '<i class="fas fa-receipt me-1"></i>Thermal Print';
        return;
    }

    try {
        const bytes = buildThermalReceipt(lastThermalReceiptData);
        await printerBridge.printBytes(bytes);
        if (thermalPrinterToast) {
            toastr.clear(thermalPrinterToast);
            thermalPrinterToast = null;
        }
        toastr.success('Receipt sent to thermal printer.', 'Printed');
    } catch (error) {
        if (thermalPrinterToast) {
            toastr.clear(thermalPrinterToast);
        }
        thermalPrinterToast = toastr.error(error.message || 'Unable to print receipt.', 'Thermal Printer', { timeOut: 8000, extendedTimeOut: 2500 });
    } finally {
        thermalPrintInProgress = false;
        printButton.disabled = false;
        printButton.innerHTML = '<i class="fas fa-receipt me-1"></i>Thermal Print';
    }
}

// Payment Method Change - Show GCash QR if selected
document.getElementById('payment-method').addEventListener('change', function() {
    if (this.value === 'gcash') {
        // Fetch and show GCash QR code
        fetch('pos.php?action=get_gcash_qr')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.qr_code_path) {
                    // Construct absolute URL for QR code image
                    const qrImageUrl = BASE_URL + data.qr_code_path;
                    document.getElementById('qrCodeImage').innerHTML = `
                        <div class="alert alert-danger mt-3" id="qrCodeError" style="display: none;">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Unable to load the GCash QR code.
                        </div>
                        <img src="${qrImageUrl}" alt="GCash QR Code" class="img-fluid" style="max-width: 300px; border: 2px solid #0d6efd; border-radius: 8px;" onerror="this.style.display='none'; document.getElementById('qrCodeError')?.style.setProperty('display', 'block');">
                    `;
                    const gcashModal = new bootstrap.Modal(document.getElementById('gcashQRModal'));
                    gcashModal.show();
                } else {
                    document.getElementById('qrCodeImage').innerHTML = `
                        <div class="alert alert-warning" id="qrCodeError">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No GCash QR code available. Please contact administrator.
                        </div>
                    `;
                    const gcashModal = new bootstrap.Modal(document.getElementById('gcashQRModal'));
                    gcashModal.show();
                }
            })
            .catch(error => {
                console.error('Error fetching GCash QR:', error);
                toastr.error('Error loading GCash QR code', 'Error');
            });
    }
});

// Search functionality
document.getElementById('product-search').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const params = new URLSearchParams(window.location.search);
        params.set('search', this.value);
        window.location.href = `pos.php?${params.toString()}`;
    }
});

const gcashQRModalElement = document.getElementById('gcashQRModal');

updateTotals = function() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = Math.min(Math.max(discountAmount, 0), subtotal);
    const total = subtotal - discount;

    document.getElementById('subtotal').textContent = 'â‚±' + subtotal.toFixed(2);
    document.getElementById('discount-amount').textContent = '-â‚±' + discount.toFixed(2);
    document.getElementById('total').textContent = 'â‚±' + total.toFixed(2);

    if (isGcashSelected()) {
        resetGcashConfirmation();
    }

    validatePayment();
};

validatePayment = function() {
    const total = getTotalAmount();
    const amountReceived = parseFloat(document.getElementById('amount-received').value) || 0;
    const completeBtn = document.getElementById('complete-sale');

    if (cart.length === 0) {
        completeBtn.disabled = true;
        completeBtn.title = 'Add items to cart to complete sale';
    } else if (isGcashSelected() && !gcashPaymentConfirmed) {
        completeBtn.disabled = true;
        completeBtn.title = 'Confirm the GCash payment in the QR modal first';
    } else if (amountReceived < total) {
        completeBtn.disabled = true;
        const shortage = total - amountReceived;
        completeBtn.title = `Payment insufficient! Short by â‚±${shortage.toFixed(2)}`;
    } else {
        completeBtn.disabled = false;
        completeBtn.title = 'Click to complete the sale';
    }
};

calculateChange = function() {
    if (isGcashSelected()) {
        document.getElementById('change-display').style.display = 'none';
        return;
    }

    const total = getTotalAmount();
    const amountReceived = parseFloat(document.getElementById('amount-received').value) || 0;
    const change = amountReceived - total;
    const changeDisplay = document.getElementById('change-display');
    const changeAmount = document.getElementById('change-amount');

    if (amountReceived > 0) {
        if (change >= 0) {
            changeDisplay.className = 'alert alert-success py-1 px-2 mb-2';
            changeAmount.textContent = 'â‚±' + change.toFixed(2);
            changeDisplay.style.display = 'block';
        } else {
            changeDisplay.className = 'alert alert-danger py-1 px-2 mb-2';
            changeAmount.textContent = 'Short: â‚±' + Math.abs(change).toFixed(2);
            changeDisplay.style.display = 'block';
        }
    } else {
        changeDisplay.style.display = 'none';
    }
};

document.getElementById('payment-method').addEventListener('change', function() {
    if (this.value === 'gcash') {
        resetGcashConfirmation();
    } else {
        gcashPaymentConfirmed = false;
    }

    updatePaymentControls();
    validatePayment();
});

gcashQRModalElement.addEventListener('shown.bs.modal', function() {
    const hasQrImage = !!document.querySelector('#qrCodeImage img');
    document.getElementById('confirm-gcash-payment').style.display = hasQrImage ? 'inline-block' : 'none';
    document.getElementById('gcashConfirmNote').style.display = hasQrImage ? 'block' : 'none';
});

document.getElementById('confirm-gcash-payment').addEventListener('click', function() {
    const total = getTotalAmount();

    if (cart.length === 0 || total <= 0) {
        toastr.warning('Add item(s) to the cart before confirming GCash payment.', 'Cart Empty');
        return;
    }

    gcashPaymentConfirmed = true;
    document.getElementById('amount-received').value = total.toFixed(2);
    validatePayment();

    const modalInstance = bootstrap.Modal.getInstance(gcashQRModalElement);
    if (modalInstance) {
        modalInstance.hide();
    }

    toastr.success('GCash payment confirmed.', 'Payment Confirmed');

    setTimeout(() => {
        if (!document.getElementById('complete-sale').disabled) {
            document.getElementById('complete-sale').click();
        }
    }, 150);
});

document.getElementById('complete-sale').addEventListener('click', function(e) {
    if (!isGcashSelected()) {
        return;
    }

    if (!gcashPaymentConfirmed) {
        e.preventDefault();
        e.stopImmediatePropagation();
        toastr.error('Confirm the GCash payment in the QR modal first.', 'GCash Payment Required');
        const gcashModal = new bootstrap.Modal(gcashQRModalElement);
        gcashModal.show();
        return;
    }

    document.getElementById('amount-received').value = getTotalAmount().toFixed(2);
}, true);

updateTotals = function() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = Math.min(Math.max(discountAmount, 0), subtotal);
    const total = subtotal - discount;

    document.getElementById('subtotal').textContent = pesoSign + subtotal.toFixed(2);
    document.getElementById('discount-amount').textContent = '-' + pesoSign + discount.toFixed(2);
    document.getElementById('total').textContent = pesoSign + total.toFixed(2);

    if (isGcashSelected()) {
        resetGcashConfirmation();
    }

    validatePayment();
};

validatePayment = function() {
    const total = getTotalAmount();
    const amountReceived = parseFloat(document.getElementById('amount-received').value) || 0;
    const completeBtn = document.getElementById('complete-sale');

    if (cart.length === 0) {
        completeBtn.disabled = true;
        completeBtn.title = 'Add items to cart to complete sale';
    } else if (isGcashSelected() && !gcashPaymentConfirmed) {
        completeBtn.disabled = true;
        completeBtn.title = 'Confirm the GCash payment in the QR modal first';
    } else if (amountReceived < total) {
        completeBtn.disabled = true;
        const shortage = total - amountReceived;
        completeBtn.title = `Payment insufficient! Short by ${pesoSign}${shortage.toFixed(2)}`;
    } else {
        completeBtn.disabled = false;
        completeBtn.title = 'Click to complete the sale';
    }
};

calculateChange = function() {
    if (isGcashSelected()) {
        document.getElementById('change-display').style.display = 'none';
        return;
    }

    const total = getTotalAmount();
    const amountReceived = parseFloat(document.getElementById('amount-received').value) || 0;
    const change = amountReceived - total;
    const changeDisplay = document.getElementById('change-display');
    const changeAmount = document.getElementById('change-amount');

    if (amountReceived > 0) {
        if (change >= 0) {
            changeDisplay.className = 'alert alert-success py-1 px-2 mb-2';
            changeAmount.textContent = pesoSign + change.toFixed(2);
            changeDisplay.style.display = 'block';
        } else {
            changeDisplay.className = 'alert alert-danger py-1 px-2 mb-2';
            changeAmount.textContent = `Short: ${pesoSign}${Math.abs(change).toFixed(2)}`;
            changeDisplay.style.display = 'block';
        }
    } else {
        changeDisplay.style.display = 'none';
    }
};

updatePaymentControls();
validatePayment();
</script>

<style>
.pos-workspace {
    align-items: stretch;
}

.pos-products-panel,
.pos-order-panel {
    border-radius: 10px;
    overflow: hidden;
}

.pos-products-panel .card-header,
.pos-order-panel .card-header {
    min-height: 58px;
}

.pos-searchbar .form-control,
.pos-searchbar .form-select,
.pos-searchbar .btn {
    min-height: 38px;
}

.product-scroll {
    max-height: calc(100vh - 250px);
    overflow-y: auto;
    padding-right: 2px;
}

.pos-product-tile {
    cursor: pointer;
    transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
    min-height: 122px;
    background: #fff;
}

.pos-product-tile:hover {
    transform: translateY(-2px);
    border-color: #0d6efd !important;
    box-shadow: 0 8px 18px rgba(13, 110, 253, 0.12);
}

.pos-order-panel {
    position: sticky;
    top: 86px;
}

.pos-order-body {
    max-height: calc(100vh - 120px);
    overflow-y: auto;
}

.pos-cart-list {
    max-height: 360px;
    overflow-y: auto;
    margin-bottom: 8px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 8px;
}

.pos-total-panel,
.pos-payment-panel {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 8px;
}

.pos-total-panel {
    background: #f8f9fa;
}

.pos-total-panel #total {
    font-size: 1.35rem !important;
}

.quick-quantity {
    font-weight: 800;
}

.cart-qty-input {
    width: 58px;
    min-width: 58px;
    height: 28px;
    padding: 2px 4px;
    font-size: 0.82rem;
    font-weight: 800;
}

.product-card:hover {
    background-color: #f8f9fa !important;
    border-color: #007bff !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
}

.btn-complete-sale {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
    font-weight: 600;
    padding: 8px 16px;
}

.btn-complete-sale:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

.btn-complete-sale:disabled {
    background-color: #6c757d;
    border-color: #6c757d;
    opacity: 0.5;
    cursor: not-allowed !important;
}

#complete-sale:disabled {
    background: linear-gradient(135deg, var(--accent), var(--accent-deep)) !important;
    border-color: var(--accent-deep) !important;
    opacity: 0.7 !important;
    cursor: not-allowed !important;
}

#complete-sale:not(:disabled) {
    background-color: #28a745;
    border-color: #28a745;
}

#complete-sale:not(:disabled):hover {
    background-color: #218838;
    border-color: #1e7e34;
}

.nav-pills .nav-link.active {
    background-color: #007bff;
    color: white;
}

.nav-pills .nav-link {
    background-color: #f8f9fa;
    color: #6c757d;
    margin: 0 2px;
    border-radius: 4px;
}

.nav-pills .nav-link:hover {
    background-color: #e9ecef;
    color: #495057;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.payment-buttons .btn {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.payment-buttons .btn:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
    color: #212529;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.payment-buttons .btn:active {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
}

@media (max-width: 991px) {
    .col-lg-3, .col-lg-9 {
        margin-bottom: 15px;
    }

    .pos-order-panel {
        position: static;
    }

    .product-scroll,
    .pos-order-body {
        max-height: none;
    }
    
    .card-body {
        max-height: none !important;
    }
    
    #cart-items {
        max-height: 150px !important;
    }
}

@media (max-width: 576px) {
    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }

    .product-card {
        min-height: auto !important;
        padding: 0.75rem !important;
    }
    
    .product-card h6 {
        font-size: 0.7rem !important;
        line-height: 1.1 !important;
    }
    
    .product-card .text-success {
        font-size: 0.75rem !important;
    }
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>

