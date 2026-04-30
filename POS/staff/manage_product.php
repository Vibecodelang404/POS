<?php
require_once __DIR__ . '/../app/config.php';
User::requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
// Get DB connection
$db = Database::getInstance()->getConnection();
// Fetch categories for dropdown
$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

function generatedSku($productId) {
    return 'SKU-' . str_pad((int) $productId, 4, '0', STR_PAD_LEFT);
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$product_type_filter = $_GET['product_type'] ?? '';

// Pagination setup
$perPage = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR CONCAT('SKU-', LPAD(p.id, 4, '0')) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter > 0) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (in_array($product_type_filter, ['retail', 'wholesale'], true)) {
    $whereConditions[] = "p.product_type = ?";
    $params[] = $product_type_filter;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Count total products with filters
$countStmt = $db->prepare("SELECT COUNT(*) FROM products p $whereClause");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Add product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $expiry = !empty($_POST['expiry']) ? $_POST['expiry'] : null;
    $product_type = in_array($_POST['product_type'] ?? '', ['retail', 'wholesale'], true) ? $_POST['product_type'] : 'retail';
    $stmt = $db->prepare("INSERT INTO products (name, category_id, product_type, price, cost_price, stock_quantity, low_stock_threshold, barcode, expiry, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['name'], $_POST['category_id'], $product_type, $_POST['price'], $_POST['cost_price'] ?? 0, $_POST['stock_quantity'], $_POST['low_stock_threshold'],
        $_POST['barcode'], $expiry, $_POST['status']
    ]);
    $product_id = (int) $db->lastInsertId();
    $skuStmt = $db->prepare("UPDATE products SET sku = ? WHERE id = ?");
    $skuStmt->execute([generatedSku($product_id), $product_id]);
    echo '<script>document.addEventListener("DOMContentLoaded",function(){
        document.getElementById("productForm").reset();
        document.getElementById("product_id").value = "";
        document.getElementById("modalAddBtn").classList.remove("d-none");
        document.getElementById("modalUpdateBtn").classList.add("d-none");
    });</script>';
    header('Location: manage_product.php');
    exit();
}

// Update product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $product_id = intval($_POST['id']);
    $new_stock = intval($_POST['stock_quantity']);
    $user_id = $_SESSION['user_id'];
    
    // Get old stock quantity to track changes
    $oldStockStmt = $db->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
    $oldStockStmt->execute([$product_id]);
    $oldProduct = $oldStockStmt->fetch(PDO::FETCH_ASSOC);
    $old_stock = intval($oldProduct['stock_quantity']);
    $product_name = $oldProduct['name'];
    
    // Calculate stock change
    $stock_change = $new_stock - $old_stock;
    
    // Update product
    $expiry = !empty($_POST['expiry']) ? $_POST['expiry'] : null;
    $product_type = in_array($_POST['product_type'] ?? '', ['retail', 'wholesale'], true) ? $_POST['product_type'] : 'retail';
    $stmt = $db->prepare("UPDATE products SET name=?, sku=?, category_id=?, product_type=?, price=?, cost_price=?, stock_quantity=?, low_stock_threshold=?, barcode=?, expiry=?, status=?, last_updated_by=? WHERE id=?");
    $stmt->execute([
        $_POST['name'], generatedSku($product_id), $_POST['category_id'], $product_type, $_POST['price'], $_POST['cost_price'] ?? 0, $new_stock, $_POST['low_stock_threshold'],
        $_POST['barcode'], $expiry, $_POST['status'], $user_id, $product_id
    ]);
    
    // Record inventory change in inventory_reports if stock quantity changed
    if ($stock_change != 0) {
        $change_type = ($stock_change > 0) ? 'Added' : 'Removed';
        $quantity_changed = abs($stock_change);
        $remarks = ($stock_change > 0) 
            ? "Stock added by staff. Previous: $old_stock, New: $new_stock" 
            : "Stock removed by staff. Previous: $old_stock, New: $new_stock";
        
        $reportStmt = $db->prepare("INSERT INTO inventory_reports (product_id, change_type, quantity, quantity_changed, previous_quantity, new_quantity, date, remarks, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())");
        $reportStmt->execute([
            $product_id,
            $change_type,
            $quantity_changed,  // Use same value for 'quantity' (legacy column)
            $quantity_changed,
            $old_stock,
            $new_stock,
            $remarks,
            $user_id
        ]);
    }
    
    header('Location: manage_product.php');
    exit();
}

// Delete product
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
}


// Statistics queries
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
    'low_stock' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity <= low_stock_threshold AND stock_quantity > 0")->fetchColumn(),
    'out_of_stock' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity = 0")->fetchColumn(),
    'in_stock' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity > low_stock_threshold")->fetchColumn(),
    'retail' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND product_type = 'retail'")->fetchColumn(),
    'wholesale' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND product_type = 'wholesale'")->fetchColumn(),
    'total_value' => $db->query("SELECT SUM(COALESCE(cost_price, 0) * stock_quantity) FROM products WHERE status = 'active'")->fetchColumn(),
];

// Fetch products with filters
$productsStmt = $db->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause ORDER BY p.id DESC LIMIT $perPage OFFSET $offset");
$productsStmt->execute($params);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get expiring products (within 7 days) and expired products
$expiringProducts = [];
$expiredProducts = [];
foreach ($products as $product) {
    if (!empty($product['expiry'])) {
        $expiryDate = new DateTime($product['expiry']);
        $today = new DateTime();
        $interval = $today->diff($expiryDate);
        $daysUntilExpiry = (int)$interval->format('%R%a'); // +/- days
        
        if ($daysUntilExpiry < 0) {
            // Already expired
            $expiredProducts[] = $product;
        } elseif ($daysUntilExpiry <= 7) {
            // Expiring within 7 days
            $expiringProducts[] = $product;
        }
    }
}

// Set page title for layout
$title = 'Inventory';
$shownStart = $totalProducts > 0 ? $offset + 1 : 0;
$shownEnd = min($offset + $perPage, $totalProducts);
ob_start();
?>
<style>
.staff-inventory-page {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.inventory-hero {
    border-radius: 22px;
    padding: 1.25rem;
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.52), transparent 24%),
        linear-gradient(135deg, rgba(170, 140, 44, 0.96), rgba(212, 175, 55, 0.86));
    border: 1px solid rgba(255, 255, 255, 0.62);
    box-shadow: 0 18px 44px rgba(17, 24, 39, 0.12);
}
.inventory-hero h2 {
    font-family: 'Manrope', sans-serif;
    font-weight: 800;
    color: #172033;
}
.inventory-hero p {
    color: rgba(23, 32, 51, 0.72);
    max-width: 680px;
}
.inventory-stat-card {
    min-height: 118px;
    border: 1px solid rgba(15, 23, 42, 0.06);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 12px 30px rgba(17, 24, 39, 0.07);
}
.inventory-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}
.inventory-stat-label {
    color: #667085;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.inventory-stat-value {
    font-family: 'Manrope', sans-serif;
    font-size: 1.45rem;
    font-weight: 800;
    color: #172033;
}
.inventory-filter-card,
.inventory-table-card {
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.06);
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 12px 30px rgba(17, 24, 39, 0.07);
}
.inventory-table-card .table {
    margin-bottom: 0;
}
.inventory-table-card thead th {
    color: #667085;
    font-size: 0.74rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    white-space: nowrap;
}
.inventory-table-card tbody td {
    vertical-align: middle;
}
.product-name-cell {
    min-width: 210px;
}
.stock-pill {
    min-width: 64px;
    border-radius: 999px;
    display: inline-flex;
    justify-content: center;
    padding: 0.38rem 0.65rem;
    font-weight: 800;
}
.floating-add-product {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 1050;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    box-shadow: 0 18px 34px rgba(25, 135, 84, 0.32);
}
@media (max-width: 768px) {
    .inventory-hero {
        padding: 1rem;
    }
    .floating-add-product {
        bottom: 18px;
        right: 18px;
    }
}
</style>

<div class="staff-inventory-page">
    <section class="inventory-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-uppercase fw-bold small mb-2" style="letter-spacing:0.08em;color:rgba(23,32,51,0.68);">Inventory Workbench</div>
                <h2 class="mb-2">Keep product stock clean and current.</h2>
                <p class="mb-0">Review low-stock items, update quantities, track wholesale and retail products, and keep expiry dates visible before they become a problem.</p>
            </div>
            <button type="button" class="btn btn-dark btn-lg" data-bs-toggle="modal" data-bs-target="#productModal">
                <i class="fas fa-plus me-2"></i>Add Product
            </button>
        </div>
    </section>

    <!-- Expiry Alerts -->
    <?php if (!empty($expiredProducts)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h5 class="alert-heading"><i class="fas fa-times-circle me-2"></i>Expired Products!</h5>
        <p class="mb-2">The following products have expired. Please remove from display immediately:</p>
        <ul class="mb-0">
            <?php foreach ($expiredProducts as $product): ?>
                <li>
                    <strong><?php echo htmlspecialchars($product['name']); ?></strong> 
                    expired on <strong><?php echo date('M d, Y', strtotime($product['expiry'])); ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($expiringProducts)): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Products Nearing Expiration</h5>
        <p class="mb-2">The following products will expire soon:</p>
        <ul class="mb-0">
            <?php foreach ($expiringProducts as $product): ?>
                <?php
                    $expiryDate = new DateTime($product['expiry']);
                    $today = new DateTime();
                    $daysLeft = (int)$today->diff($expiryDate)->format('%a');
                ?>
                <li>
                    <strong><?php echo htmlspecialchars($product['name']); ?></strong> 
                    will expire on <strong><?php echo date('M d, Y', strtotime($product['expiry'])); ?></strong>
                    (<?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?> remaining)
                </li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-3">
        <div class="col-xl-3 col-md-6">
            <div class="inventory-stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="inventory-stat-label">Total Products</div>
                        <div class="inventory-stat-value"><?=number_format((int) $stats['total'])?></div>
                        <small class="text-muted">Retail <?=number_format((int) $stats['retail'])?> · Wholesale <?=number_format((int) $stats['wholesale'])?></small>
                    </div>
                    <div class="inventory-stat-icon bg-primary"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="inventory-stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="inventory-stat-label">Needs Attention</div>
                        <div class="inventory-stat-value"><?=number_format((int) $stats['low_stock'])?></div>
                        <small class="text-muted">Low but still available</small>
                    </div>
                    <div class="inventory-stat-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="inventory-stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="inventory-stat-label">Out of Stock</div>
                        <div class="inventory-stat-value"><?=number_format((int) $stats['out_of_stock'])?></div>
                        <small class="text-muted">Unavailable products</small>
                    </div>
                    <div class="inventory-stat-icon bg-danger"><i class="fas fa-times-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="inventory-stat-card p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="inventory-stat-label">Cost Value</div>
                        <div class="inventory-stat-value"><?=formatCurrency((float) ($stats['total_value'] ?? 0))?></div>
                        <small class="text-muted"><?=number_format((int) $stats['in_stock'])?> healthy stock items</small>
                    </div>
                    <div class="inventory-stat-icon bg-success"><i class="fas fa-coins"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Add Button -->
    <button type="button" class="btn btn-success floating-add-product" data-bs-toggle="modal" data-bs-target="#productModal" title="Add Product">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="POST" id="productForm">
            <div class="modal-header">
              <h5 class="modal-title" id="productModalLabel">Add / Update Product</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="id" id="product_id">
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="name" class="form-label">Product Name</label>
                  <input type="text" name="name" id="name" required class="form-control" placeholder="Product Name">
                </div>
                <div class="col-md-4">
                  <label for="sku" class="form-label">SKU</label>
                  <input type="text" id="sku" class="form-control" value="Auto-generated" readonly>
                  <small class="text-muted">Generated after saving, using the same SKU-0001 format as admin.</small>
                </div>
                <div class="col-md-4">
                  <label for="categorySelect" class="form-label">Category</label>
                  <select name="category_id" required class="form-select" id="categorySelect">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?=$cat['id']?>"><?=htmlspecialchars($cat['name'])?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="product_type" class="form-label">Product Type</label>
                  <select name="product_type" id="product_type" required class="form-select">
                    <option value="retail">Retail</option>
                    <option value="wholesale">Wholesale</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="price" class="form-label">Price</label>
                  <input type="number" name="price" id="price" required class="form-control" step="0.01" placeholder="Price">
                </div>
                <div class="col-md-4">
                  <label for="cost_price" class="form-label">Cost Price</label>
                  <input type="number" name="cost_price" id="cost_price" required class="form-control" step="0.01" min="0" value="0.00" placeholder="Cost Price">
                </div>
                <div class="col-md-4">
                  <label for="stock_quantity" class="form-label">Quantity</label>
                  <input type="number" name="stock_quantity" id="stock_quantity" required class="form-control" placeholder="Quantity">
                </div>
                <div class="col-md-4">
                  <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                  <input type="number" name="low_stock_threshold" id="low_stock_threshold" required class="form-control" placeholder="Low Stock Threshold">
                </div>
                <div class="col-md-4 d-none">
                  <label for="barcode" class="form-label">Barcode</label>
                  <input type="text" name="barcode" id="barcode" class="form-control" placeholder="Barcode">
                </div>
                <div class="col-md-4">
                  <label for="expiry" class="form-label">Expiry</label>
                  <input type="date" name="expiry" id="expiry" class="form-control" placeholder="Expiry">
                </div>
                <div class="col-md-4">
                  <label for="status" class="form-label">Status</label>
                  <select name="status" id="status" class="form-select" required>
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="add" class="btn btn-success" id="modalAddBtn">Add Product</button>
              <button type="submit" name="update" class="btn btn-primary d-none" id="modalUpdateBtn">Update Product</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Search and Filter Form -->
    <div class="inventory-filter-card">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Find Products</h5>
                    <small class="text-muted">Showing <?=number_format($shownStart)?>-<?=number_format($shownEnd)?> of <?=number_format((int) $totalProducts)?> products</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="low_stock_alerts.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i>Low Stock
                    </a>
                    <a href="inventory_reports.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-file-alt me-1"></i>Reports
                    </a>
                </div>
            </div>
            <form method="GET" class="row g-3">
                <div class="col-lg-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by Name or SKU" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-lg-3">
                    <select name="category_id" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="product_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="retail" <?php echo $product_type_filter === 'retail' ? 'selected' : ''; ?>>Retail</option>
                        <option value="wholesale" <?php echo $product_type_filter === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-danger flex-fill">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="manage_product.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="inventory-table-card">
    <div class="table-responsive">
    <table class="table table-striped table-hover table-bordered align-middle mb-0">
        <thead>
            <tr>
                <th>Name</th><th>SKU</th><th>Category</th><th>Type</th><th>Cost</th><th>Price</th><th>Stock Qty</th><th>Low Stock Threshold</th><th class="d-none">Barcode</th><th>Expiry</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): 
                // Calculate expiry status
                $expiryClass = '';
                $expiryBadge = '';
                $expiryText = 'N/A';
                if (!empty($p['expiry'])) {
                    $expiryDate = new DateTime($p['expiry']);
                    $today = new DateTime();
                    $interval = $today->diff($expiryDate);
                    $daysUntilExpiry = (int)$interval->format('%R%a');
                    
                    $expiryText = date('M d, Y', strtotime($p['expiry']));
                    
                    if ($daysUntilExpiry < 0) {
                        // Expired
                        $expiryClass = 'table-danger';
                        $expiryBadge = '<span class="badge bg-danger ms-2">EXPIRED</span>';
                    } elseif ($daysUntilExpiry <= 7) {
                        // Expiring soon
                        $expiryClass = 'table-warning';
                        $expiryBadge = '<span class="badge bg-warning text-dark ms-2">' . $daysUntilExpiry . ' days left</span>';
                    }
                }
            ?>
            <tr class="<?php echo $expiryClass; ?>">
                <td class="product-name-cell">
                    <div class="fw-bold"><?=htmlspecialchars($p['name'] ?? '')?></div>
                    <small class="text-muted"><?=htmlspecialchars(generatedSku($p['id'] ?? 0))?></small>
                </td>
                <td><?=htmlspecialchars(generatedSku($p['id'] ?? 0))?></td>
                <td><?=htmlspecialchars($p['category_name'] ?? '')?></td>
                <td><span class="badge bg-<?=($p['product_type'] ?? 'retail') === 'wholesale' ? 'dark' : 'secondary'?>"><?=ucfirst($p['product_type'] ?? 'retail')?></span></td>
                <td><?=formatCurrency((float) ($p['cost_price'] ?? 0))?></td>
                <td>₱<?=number_format($p['price'],2)?></td>
                <td>
                    <?php
                        $stockClass = ((int) $p['stock_quantity'] <= 0) ? 'bg-danger' : (((int) $p['stock_quantity'] <= (int) $p['low_stock_threshold']) ? 'bg-warning text-dark' : 'bg-success');
                    ?>
                    <span class="stock-pill <?php echo $stockClass; ?>"><?=$p['stock_quantity']?></span>
                </td>
                <td><?=$p['low_stock_threshold']?></td>
                <td class="d-none">
                    <div class="d-flex align-items-center gap-2">
                        <span><?=htmlspecialchars($p['barcode'] ?? 'N/A')?></span>
                        <?php if (!empty($p['barcode'])): ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="showBarcode('<?=htmlspecialchars($p['barcode'])?>', '<?=htmlspecialchars($p['name'])?>')" title="Show Barcode">
                                <i class="fas fa-barcode"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php echo $expiryText; ?>
                    <?php echo $expiryBadge; ?>
                </td>
                <!-- <td><?=htmlspecialchars($p['status'] ?? '')?></td> -->
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="editProduct(<?=htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8')?>)" title="Edit product">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteProduct(<?=$p['id']?>, '<?=htmlspecialchars($p['name'] ?? 'this product', ENT_QUOTES, 'UTF-8')?>')" title="Delete product">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr>
                <td colspan="11" class="text-center text-muted py-5">
                    <i class="fas fa-box-open fa-2x d-block mb-2 opacity-50"></i>
                    No products match the current filters.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>
    <!-- Pagination -->
    <nav aria-label="Product pagination">
      <ul class="pagination justify-content-center mt-3">
        <?php 
        $queryParams = [];
        if (!empty($search)) $queryParams['search'] = $search;
        if ($category_filter > 0) $queryParams['category_id'] = $category_filter;
        if (!empty($product_type_filter)) $queryParams['product_type'] = $product_type_filter;
        
        for ($i = 1; $i <= $totalPages; $i++): 
            $queryParams['page'] = $i;
            $queryString = http_build_query($queryParams);
        ?>
          <li class="page-item<?=($i == $page ? ' active' : '')?>">
            <a class="page-link" href="?<?=$queryString?>"><?=$i?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>

    <script>
    // Make category dropdown searchable
    document.addEventListener('DOMContentLoaded', function() {
        var select = document.getElementById('categorySelect');
        if (select) {
            select.setAttribute('data-live-search', 'true');
            // For advanced search, use a JS library like select2 or bootstrap-select
            // Example: $('#categorySelect').select2();
        }
    });

    // Edit product handler
    function editProduct(product) {
        var modal = new bootstrap.Modal(document.getElementById('productModal'));
        setTimeout(function() {
            document.getElementById('product_id').value = product.id ?? '';
            document.getElementById('name').value = product.name ?? '';
            document.getElementById('sku').value = product.id ? `SKU-${String(product.id).padStart(4, '0')}` : 'Auto-generated';
            document.getElementById('categorySelect').value = product.category_id ?? '';
            document.getElementById('product_type').value = product.product_type ?? 'retail';
            document.getElementById('price').value = product.price ?? '';
            document.getElementById('cost_price').value = product.cost_price ?? '0.00';
            document.getElementById('stock_quantity').value = product.stock_quantity ?? '';
            document.getElementById('low_stock_threshold').value = product.low_stock_threshold ?? '';
            document.getElementById('barcode').value = product.barcode ?? '';
            document.getElementById('expiry').value = product.expiry ?? '';
            document.getElementById('status').value = product.status || 'active';
            document.getElementById('modalAddBtn').classList.add('d-none');
            document.getElementById('modalUpdateBtn').classList.remove('d-none');
        }, 200);
        modal.show();
    }

    // Reset modal for add
    if (document.getElementById('productModal')) {
        document.getElementById('productModal').addEventListener('show.bs.modal', function (event) {
            if (!event.relatedTarget || event.relatedTarget.classList.contains('btn-primary')) {
                document.getElementById('productForm').reset();
                document.getElementById('product_id').value = '';
                document.getElementById('modalAddBtn').classList.remove('d-none');
                document.getElementById('modalUpdateBtn').classList.add('d-none');
            }
        });
    }

    // Show barcode in modal
    function showBarcode(barcode, productName) {
        document.getElementById('barcodeValue').textContent = barcode;
        document.getElementById('productNameDisplay').textContent = productName;
        document.getElementById('barcodeImage').src = 'https://barcode.tec-it.com/barcode.ashx?data=' + encodeURIComponent(barcode) + '&code=Code128&translate-esc=on&unit=Fit&dpi=96&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0';
        
        const modal = new bootstrap.Modal(document.getElementById('barcodeModal'));
        modal.show();
    }

    // Print barcode
    function printBarcode() {
        const printWindow = window.open('', '', 'height=400,width=600');
        const barcodeImg = document.getElementById('barcodeImage').src;
        const productName = document.getElementById('productNameDisplay').textContent;
        const barcodeValue = document.getElementById('barcodeValue').textContent;
        
        printWindow.document.write('<html><head><title>Print Barcode</title>');
        printWindow.document.write('<style>body{text-align:center;padding:20px;font-family:Arial;}img{max-width:100%;}</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<h3>' + productName + '</h3>');
        printWindow.document.write('<img src="' + barcodeImg + '" />');
        printWindow.document.write('<p style="margin-top:10px;font-size:14px;">' + barcodeValue + '</p>');
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }

    // Delete Product with SweetAlert
    function deleteProduct(productId, productName) {
        Swal.fire({
            title: 'Delete Product?',
            text: `Are you sure you want to delete "${productName}"? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete=' + productId;
            }
        });
    }

    // Form submission handlers
    document.addEventListener('DOMContentLoaded', function() {
        const productForm = document.getElementById('productForm');
        if (productForm) {
            productForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const isAdd = document.getElementById('modalAddBtn').classList.contains('d-none') === false;
                const isUpdate = document.getElementById('modalUpdateBtn').classList.contains('d-none') === false;
                
                let title, text, confirmText, confirmColor;
                
                if (isAdd) {
                    title = 'Add Product?';
                    text = 'Are you sure you want to add this product to the inventory?';
                    confirmText = 'Yes, Add';
                    confirmColor = '#28a745';
                } else if (isUpdate) {
                    title = 'Update Product?';
                    text = 'Are you sure you want to update this product?';
                    confirmText = 'Yes, Update';
                    confirmColor = '#0d6efd';
                }
                
                Swal.fire({
                    title: title,
                    text: text,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: confirmColor,
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: confirmText,
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit();
                    }
                });
            });
        }
    });
    </script>

    <!-- Barcode Display Modal hidden for now; integration code kept in place. -->
    <div class="modal fade" id="barcodeModal" tabindex="-1" aria-labelledby="barcodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="barcodeModalLabel">
                        <i class="fas fa-barcode me-2"></i>Product Barcode
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <h6 class="mb-3" id="productNameDisplay"></h6>
                    <div class="p-3 bg-light rounded mb-3">
                        <img id="barcodeImage" src="" alt="Barcode" class="img-fluid" style="max-height: 150px;">
                    </div>
                    <p class="text-muted mb-0">Barcode: <strong id="barcodeValue"></strong></p>
                    <p class="text-info small mt-2">
                        <i class="fas fa-mobile-alt me-1"></i>Scan this barcode with your mobile app
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" onclick="printBarcode()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>
