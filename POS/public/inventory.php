<?php
require_once __DIR__ . '/../app/config.php';
requireAdmin(); // Only admin can access inventory

$inventoryController = new InventoryController();
$message = $_GET['message'] ?? '';
$message_type = $_GET['message_type'] ?? 'info';
$csrf_token = csrfToken();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Please refresh the page and try again.';
        $message_type = 'danger';
    } elseif (isset($_POST['action'])) {
        $success = true;
        $redirectMessage = '';
        switch ($_POST['action']) {
            case 'add':
                $success = $inventoryController->addProduct($_POST);
                break;
            case 'update':
                $success = $inventoryController->updateProduct($_POST['id'], $_POST);
                break;
            case 'delete':
                $success = $inventoryController->deleteProduct($_POST['id']);
                break;
            case 'add_category':
                $success = $inventoryController->addCategory($_POST);
                $redirectMessage = $success ? 'Product category added.' : 'Unable to add category. It may already exist.';
                break;
            case 'update_category':
                $success = $inventoryController->updateCategory($_POST['id'] ?? 0, $_POST);
                $redirectMessage = $success ? 'Product category updated.' : 'Unable to update category. The name may already exist.';
                break;
            case 'delete_category':
                $success = $inventoryController->deleteCategory($_POST['id'] ?? 0);
                $redirectMessage = $success ? 'Product category deleted.' : 'Unable to delete category. Remove or reassign active products first.';
                break;
            case 'save_breakdown_link':
                $success = $inventoryController->saveBreakdownLink($_POST);
                $redirectMessage = $success ? 'Breakdown mapping saved.' : 'Unable to save breakdown mapping. Check the selected products and units.';
                break;
            case 'breakdown_stock':
                $success = $inventoryController->breakdownWholesaleStock($_POST);
                $redirectMessage = $success ? 'Wholesale stock was broken down into retail stock.' : 'Unable to breakdown stock. Check available wholesale quantity and mapping.';
                break;
        }
        $redirectUrl = 'inventory.php';
        if ($redirectMessage !== '') {
            $redirectUrl .= '?message=' . urlencode($redirectMessage) . '&message_type=' . ($success ? 'success' : 'danger');
        }
        header('Location: ' . $redirectUrl);
        exit();
    }
}

$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? '';
$product_type = $_GET['product_type'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$stats = $inventoryController->getStats();
$allProducts = $inventoryController->getAllProducts($search, $category_id, $stock_filter, $product_type);
$totalProducts = count($allProducts);
$totalPages = ceil($totalProducts / $perPage);
$products = array_slice($allProducts, $offset, $perPage);
$categories = $inventoryController->getAllCategories();
$wholesaleProducts = $inventoryController->getProductsByType('wholesale');
$retailProducts = $inventoryController->getProductsByType('retail');
$breakdownLinks = $inventoryController->getBreakdownLinks();
$productBatches = $inventoryController->getBatchesByProductIds(array_map(function ($product) {
    return (int) $product['id'];
}, $products));

$batchAlerts = $inventoryController->getBatchExpiryAlerts(7);
$expiringProducts = $batchAlerts['expiring'];
$expiredProducts = $batchAlerts['expired'];

$page_title = 'Inventory';

ob_start();
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Expiry Alerts -->
<?php if (!empty($expiredProducts)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <h5 class="alert-heading"><i class="fas fa-times-circle me-2"></i>Expired Products!</h5>
    <p class="mb-2">The following products have expired. Please remove from display immediately:</p>
    <ul class="mb-0">
        <?php foreach ($expiredProducts as $product): ?>
            <li>
                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                batch of <strong><?php echo (int) $product['quantity']; ?></strong>
                expired on <strong><?php echo date('M d, Y', strtotime($product['expiry_date'])); ?></strong>
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
                $daysLeft = (int) $product['days_until_expiry'];
            ?>
            <li>
                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                batch of <strong><?php echo (int) $product['quantity']; ?></strong>
                will expire on <strong><?php echo date('M d, Y', strtotime($product['expiry_date'])); ?></strong>
                (<?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?> remaining)
            </li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="inventory.php" class="text-decoration-none">
            <div class="stats-card <?php echo empty($stock_filter) ? 'border-primary' : ''; ?>" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-primary me-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?php echo $stats['total_products']; ?></h3>
                        <p class="text-muted mb-0">Total Products</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="inventory.php?stock_filter=low_stock" class="text-decoration-none">
            <div class="stats-card <?php echo $stock_filter === 'low_stock' ? 'border-warning' : ''; ?>" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-warning me-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?php echo $stats['low_stock']; ?></h3>
                        <p class="text-muted mb-0">Low Stock</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="inventory.php?stock_filter=out_of_stock" class="text-decoration-none">
            <div class="stats-card <?php echo $stock_filter === 'out_of_stock' ? 'border-danger' : ''; ?>" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-danger me-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?php echo $stats['out_of_stock']; ?></h3>
                        <p class="text-muted mb-0">Out of Stock</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="inventory.php?stock_filter=in_stock" class="text-decoration-none">
            <div class="stats-card <?php echo $stock_filter === 'in_stock' ? 'border-success' : ''; ?>" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-success me-3" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?php echo $stats['in_stock']; ?></h3>
                        <p class="text-muted mb-0">In Stock</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Inventory -->
<div class="row">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Inventory</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-custom" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-tags me-2"></i>Category
                    </button>
                    <button class="btn btn-outline-dark btn-custom" data-bs-toggle="modal" data-bs-target="#breakdownModal">
                        <i class="fas fa-box-open me-2"></i>Breakdown
                    </button>
                    <button class="btn btn-danger btn-custom" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fas fa-plus me-2"></i>Add
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Search and Filter Bar -->
                <form method="GET" class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <input type="text" name="search" class="form-control" placeholder="Search by Name or SKU" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3 mb-2">
                        <select name="category_id" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <select name="product_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="retail" <?php echo $product_type === 'retail' ? 'selected' : ''; ?>>Retail</option>
                            <option value="wholesale" <?php echo $product_type === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button type="submit" class="btn btn-outline-danger me-2">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                        <a href="inventory.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo me-1"></i> Reset
                        </a>
                    </div>
                </form>
                
                <!-- Products Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered align-middle mb-0">
                        <thead class="table-danger">
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>SKU</th>
                                <th>Cost</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No products found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($products as $product): 
                                // Calculate expiry status
                                $expiryClass = '';
                                $expiryBadge = '';
                                $expiryText = 'N/A';
                                if (!empty($product['expiry'])) {
                                    $expiryDate = new DateTime($product['expiry']);
                                    $today = new DateTime();
                                    $interval = $today->diff($expiryDate);
                                    $daysUntilExpiry = (int)$interval->format('%R%a');
                                    
                                    $expiryText = date('M d, Y', strtotime($product['expiry']));
                                    
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
                                <td>
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                </td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></span></td>
                                <td>
                                    <span class="badge bg-<?php echo ($product['product_type'] ?? 'retail') === 'wholesale' ? 'dark' : 'secondary'; ?>">
                                        <?php echo ucfirst($product['product_type'] ?? 'retail'); ?>
                                    </span>
                                </td>
                                <td>SKU-<?php echo str_pad($product['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo formatCurrency((float) ($product['cost_price'] ?? 0)); ?></td>
                                <td><?php echo formatCurrency($product['price']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        if ($product['stock_quantity'] == 0) echo 'danger';
                                        elseif ($product['stock_quantity'] <= (int) ($product['low_stock_threshold'] ?? 10)) echo 'warning';
                                        else echo 'success';
                                    ?>">
                                        <?php echo $product['stock_quantity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $expiryText; ?>
                                    <?php echo $expiryBadge; ?>
                                    <?php if (!empty($product['batch_count'])): ?>
                                        <div><small class="text-muted"><?php echo (int) $product['batch_count']; ?> batch<?php echo (int) $product['batch_count'] !== 1 ? 'es' : ''; ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['stock_quantity'] == 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($product['stock_quantity'] <= (int) ($product['low_stock_threshold'] ?? 10)): ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button
                                            class="btn btn-sm btn-outline-info"
                                            data-product="<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-batches="<?php echo htmlspecialchars(json_encode($productBatches[$product['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="viewProduct(this)"
                                            title="View Product">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button
                                            class="btn btn-sm btn-outline-primary"
                                            data-product="<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-batches="<?php echo htmlspecialchars(json_encode($productBatches[$product['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="editProduct(this)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>&stock_filter=<?php echo $stock_filter; ?>&product_type=<?php echo urlencode($product_type); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || abs($i - $page) <= 2): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>&stock_filter=<?php echo $stock_filter; ?>&product_type=<?php echo urlencode($product_type); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php elseif (abs($i - $page) == 3): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $category_id; ?>&stock_filter=<?php echo $stock_filter; ?>&product_type=<?php echo urlencode($product_type); ?>">Next</a>
                        </li>
                    </ul>
                    <p class="text-center text-muted">Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalProducts); ?> of <?php echo $totalProducts; ?> products</p>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Category Management Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-tags me-2"></i>Category List</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" class="border rounded p-3 mb-3 bg-light">
                    <input type="hidden" name="action" value="add_category">
                    <?php echo csrfInput(); ?>

                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Category Name</label>
                            <input type="text" name="name" class="form-control" maxlength="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" maxlength="255" placeholder="Optional">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-1"></i>Add
                            </button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th class="text-center">Products</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No categories yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                    <?php $categoryProductCount = (int) ($category['product_count'] ?? 0); ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo $categoryProductCount; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                onclick="openEditCategory(this)"
                                                data-id="<?php echo (int) $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-description="<?php echo htmlspecialchars($category['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="deleteCategory(<?php echo (int) $category['id']; ?>, <?php echo $categoryProductCount; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_category">
                    <input type="hidden" name="id" id="editCategoryId">
                    <?php echo csrfInput(); ?>

                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" id="editCategoryName" class="form-control" maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editCategoryDescription" class="form-control" rows="3" placeholder="Optional"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Product Modal -->
<div class="modal fade" id="viewProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Product Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">Product</div>
                            <div class="fw-bold fs-5" id="viewName"></div>
                            <div class="text-muted" id="viewCategory"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">SKU</div>
                            <div class="fw-semibold" id="viewSku"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">Status</div>
                            <div id="viewStatus"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">Type</div>
                            <div class="fw-semibold" id="viewProductType"></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">Cost Price</div>
                            <div class="fw-bold" id="viewCostPrice"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">Selling Price</div>
                            <div class="fw-bold text-success" id="viewPrice"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">Stock</div>
                            <div class="fw-bold" id="viewStock"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small text-uppercase fw-bold mb-1">Nearest Expiry</div>
                            <div class="fw-bold" id="viewExpiry"></div>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mb-3 d-none">
                    <div class="text-muted small text-uppercase fw-bold mb-1">Barcode</div>
                    <div id="viewBarcode"></div>
                </div>

                <div class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-muted small text-uppercase fw-bold">Inventory Batches</div>
                        <span class="badge bg-secondary" id="viewBatchCount"></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Quantity</th>
                                    <th>Expiry Date</th>
                                </tr>
                            </thead>
                            <tbody id="viewBatchRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Breakdown Modal -->
<div class="modal fade" id="breakdownModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-box-open me-2"></i>Wholesale Breakdown</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="border rounded p-3 h-100">
                            <h6 class="fw-bold mb-3">Set Product Mapping</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="save_breakdown_link">
                                <?php echo csrfInput(); ?>

                                <div class="mb-3">
                                    <label class="form-label">Wholesale Product</label>
                                    <select name="wholesale_product_id" class="form-select" required>
                                        <option value="">Select wholesale product</option>
                                        <?php foreach ($wholesaleProducts as $product): ?>
                                            <option value="<?php echo (int) $product['id']; ?>">
                                                <?php echo htmlspecialchars($product['name']); ?> (<?php echo (int) $product['stock_quantity']; ?> in stock)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Retail Product</label>
                                    <select name="retail_product_id" class="form-select" required>
                                        <option value="">Select retail product</option>
                                        <?php foreach ($retailProducts as $product): ?>
                                            <option value="<?php echo (int) $product['id']; ?>">
                                                <?php echo htmlspecialchars($product['name']); ?> (<?php echo (int) $product['stock_quantity']; ?> in stock)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Retail Units Per Wholesale</label>
                                    <input type="number" name="retail_units_per_wholesale" class="form-control" min="1" required>
                                </div>

                                <button type="submit" class="btn btn-dark w-100">
                                    <i class="fas fa-link me-1"></i>Save Mapping
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="border rounded p-3 mb-3">
                            <h6 class="fw-bold mb-3">Breakdown Stock</h6>
                            <form method="POST" id="breakdownForm">
                                <input type="hidden" name="action" value="breakdown_stock">
                                <?php echo csrfInput(); ?>

                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label">Mapping</label>
                                        <select name="breakdown_link_id" id="breakdownLinkSelect" class="form-select" required>
                                            <option value="">Select mapping</option>
                                            <?php foreach ($breakdownLinks as $link): ?>
                                                <option
                                                    value="<?php echo (int) $link['id']; ?>"
                                                    data-units="<?php echo (int) $link['retail_units_per_wholesale']; ?>">
                                                    <?php echo htmlspecialchars($link['wholesale_name']); ?>
                                                    -> <?php echo htmlspecialchars($link['retail_name']); ?>
                                                    (1 = <?php echo (int) $link['retail_units_per_wholesale']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Wholesale Qty</label>
                                        <input type="number" name="wholesale_quantity" id="breakdownWholesaleQty" class="form-control" min="1" value="1" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Retail Adds</label>
                                        <input type="text" id="breakdownRetailAdds" class="form-control" value="0" readonly>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label">Retail Batch Expiry</label>
                                        <input type="date" name="retail_expiry" class="form-control">
                                        <small class="text-muted">Leave blank to use the nearest expiry from the wholesale stock used.</small>
                                    </div>
                                    <div class="col-md-5 d-flex align-items-end">
                                        <button type="submit" class="btn btn-danger w-100" <?php echo empty($breakdownLinks) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-exchange-alt me-1"></i>Breakdown Stock
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Wholesale</th>
                                        <th>Retail</th>
                                        <th>Conversion</th>
                                        <th>Current Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($breakdownLinks)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">No breakdown mappings yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($breakdownLinks as $link): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($link['wholesale_name']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($link['retail_name']); ?>
                                                    <?php if ((int) $link['retail_stock'] <= (int) $link['retail_low_stock_threshold']): ?>
                                                        <span class="badge bg-warning text-dark ms-1">Low retail</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>1 -> <?php echo (int) $link['retail_units_per_wholesale']; ?></td>
                                                <td>
                                                    <span class="badge bg-dark"><?php echo (int) $link['wholesale_stock']; ?> wholesale</span>
                                                    <span class="badge bg-secondary"><?php echo (int) $link['retail_stock']; ?> retail</span>
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <?php echo csrfInput(); ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Product Type</label>
                        <select name="product_type" class="form-select" required>
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Price</label>
                        <input type="number" name="price" class="form-control" step="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cost Price</label>
                        <input type="number" name="cost_price" class="form-control" step="0.01" min="0" value="0.00" required>
                    </div>
                    
                    <div class="mb-3 d-none">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Inventory Batches</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addBatchRow('addBatchRows')">
                                <i class="fas fa-plus me-1"></i>Add Batch
                            </button>
                        </div>
                        <div id="addBatchRows"></div>
                        <small class="text-muted">Create one row per expiry date. Leave expiry empty for non-expiring stock.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="editId">
                    <?php echo csrfInput(); ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" id="editCategory" class="form-select">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Product Type</label>
                        <select name="product_type" id="editProductType" class="form-select" required>
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Price</label>
                        <input type="number" name="price" id="editPrice" class="form-control" step="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cost Price</label>
                        <input type="number" name="cost_price" id="editCostPrice" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3 d-none">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" id="editBarcode" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Inventory Batches</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addBatchRow('editBatchRows')">
                                <i class="fas fa-plus me-1"></i>Add Batch
                            </button>
                        </div>
                        <div id="editBatchRows"></div>
                        <small class="text-muted">Update quantities per expiry batch. Removing a row removes that batch from tracked stock.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = <?php echo json_encode($csrf_token); ?>;

function createBatchRowHtml(batch = {}) {
    const quantity = Number(batch.quantity || 0);
    const expiry = batch.expiry_date || '';

    return `
        <div class="row g-2 align-items-end batch-row mb-2">
            <div class="col-5">
                <label class="form-label">Quantity</label>
                <input type="number" name="batch_quantity[]" class="form-control" min="1" value="${quantity > 0 ? quantity : ''}" required>
            </div>
            <div class="col-5">
                <label class="form-label">Expiry Date</label>
                <input type="date" name="batch_expiry[]" class="form-control" value="${expiry}">
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-outline-danger w-100" onclick="removeBatchRow(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
}

function addBatchRow(containerId, batch = {}) {
    const container = document.getElementById(containerId);
    if (!container) {
        return;
    }

    container.insertAdjacentHTML('beforeend', createBatchRowHtml(batch));
}

function removeBatchRow(button) {
    const row = button.closest('.batch-row');
    if (row) {
        row.remove();
    }
}

function ensureAtLeastOneBatch(containerId) {
    const container = document.getElementById(containerId);
    if (!container) {
        return;
    }

    if (!container.querySelector('.batch-row')) {
        addBatchRow(containerId);
    }
}

function formatMoney(value) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(Number(value || 0));
}

function formatDate(value) {
    if (!value) {
        return 'N/A';
    }

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit'
    });
}

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
    }
}

function openEditCategory(button) {
    document.getElementById('editCategoryId').value = button.dataset.id || '';
    document.getElementById('editCategoryName').value = button.dataset.name || '';
    document.getElementById('editCategoryDescription').value = button.dataset.description || '';

    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

function deleteCategory(id, productCount) {
    if (Number(productCount || 0) > 0) {
        Swal.fire({
            title: 'Category In Use',
            text: 'Reassign or delete products in this category before deleting it.',
            icon: 'info',
            confirmButtonColor: '#0d6efd'
        });
        return;
    }

    Swal.fire({
        title: 'Delete Category?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function viewProduct(button) {
    const product = JSON.parse(button.dataset.product || '{}');
    const batches = JSON.parse(button.dataset.batches || '[]');
    const stock = Number(product.stock_quantity || 0);
    const threshold = Number(product.low_stock_threshold || 10);
    const statusText = stock <= 0 ? 'Out of Stock' : (stock <= threshold ? 'Low Stock' : 'In Stock');
    const statusClass = stock <= 0 ? 'bg-danger' : (stock <= threshold ? 'bg-warning text-dark' : 'bg-success');

    setText('viewName', product.name || 'N/A');
    setText('viewCategory', product.category_name || 'Uncategorized');
    setText('viewProductType', product.product_type === 'wholesale' ? 'Wholesale' : 'Retail');
    setText('viewSku', `SKU-${String(product.id || '').padStart(4, '0')}`);
    setText('viewCostPrice', formatMoney(product.cost_price));
    setText('viewPrice', formatMoney(product.price));
    setText('viewStock', `${stock.toLocaleString()} item${stock === 1 ? '' : 's'}`);
    setText('viewExpiry', formatDate(product.expiry));
    setText('viewBarcode', product.barcode || 'N/A');
    setText('viewBatchCount', `${batches.length} batch${batches.length === 1 ? '' : 'es'}`);

    const statusElement = document.getElementById('viewStatus');
    if (statusElement) {
        statusElement.innerHTML = '';
        const badge = document.createElement('span');
        badge.className = `badge ${statusClass}`;
        badge.textContent = statusText;
        statusElement.appendChild(badge);
    }

    const batchRows = document.getElementById('viewBatchRows');
    if (batchRows) {
        batchRows.innerHTML = '';
        if (Array.isArray(batches) && batches.length > 0) {
            batches.forEach(batch => {
                const row = document.createElement('tr');
                const quantityCell = document.createElement('td');
                const expiryCell = document.createElement('td');

                quantityCell.textContent = Number(batch.quantity || 0).toLocaleString();
                expiryCell.textContent = formatDate(batch.expiry_date);

                row.appendChild(quantityCell);
                row.appendChild(expiryCell);
                batchRows.appendChild(row);
            });
        } else {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 2;
            cell.className = 'text-muted text-center py-3';
            cell.textContent = 'No batch records found.';
            row.appendChild(cell);
            batchRows.appendChild(row);
        }
    }

    new bootstrap.Modal(document.getElementById('viewProductModal')).show();
}

function editProduct(button) {
    const product = JSON.parse(button.dataset.product || '{}');
    const batches = JSON.parse(button.dataset.batches || '[]');

    document.getElementById('editId').value = product.id;
    document.getElementById('editName').value = product.name;
    document.getElementById('editCategory').value = product.category_id || '';
    document.getElementById('editProductType').value = product.product_type || 'retail';
    document.getElementById('editPrice').value = product.price;
    document.getElementById('editCostPrice').value = product.cost_price || '0.00';
    document.getElementById('editBarcode').value = product.barcode || '';

    const batchContainer = document.getElementById('editBatchRows');
    batchContainer.innerHTML = '';

    if (Array.isArray(batches) && batches.length > 0) {
        batches.forEach(batch => addBatchRow('editBatchRows', batch));
    } else {
        addBatchRow('editBatchRows', {
            quantity: product.stock_quantity || '',
            expiry_date: product.expiry || ''
        });
    }
    
    new bootstrap.Modal(document.getElementById('editProductModal')).show();
}

function deleteProduct(id) {
    Swal.fire({
        title: 'Delete Product?',
        text: 'This action cannot be undone. The product will be permanently removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Add Product Form Confirmation
document.addEventListener('DOMContentLoaded', function() {
    const breakdownLinkSelect = document.getElementById('breakdownLinkSelect');
    const breakdownWholesaleQty = document.getElementById('breakdownWholesaleQty');
    const breakdownRetailAdds = document.getElementById('breakdownRetailAdds');

    function updateBreakdownRetailAdds() {
        if (!breakdownLinkSelect || !breakdownWholesaleQty || !breakdownRetailAdds) {
            return;
        }

        const selectedOption = breakdownLinkSelect.options[breakdownLinkSelect.selectedIndex];
        const units = Number(selectedOption ? selectedOption.dataset.units || 0 : 0);
        const quantity = Number(breakdownWholesaleQty.value || 0);
        breakdownRetailAdds.value = (units * quantity).toLocaleString();
    }

    if (breakdownLinkSelect && breakdownWholesaleQty) {
        breakdownLinkSelect.addEventListener('change', updateBreakdownRetailAdds);
        breakdownWholesaleQty.addEventListener('input', updateBreakdownRetailAdds);
        updateBreakdownRetailAdds();
    }

    const breakdownForm = document.getElementById('breakdownForm');
    if (breakdownForm) {
        breakdownForm.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Breakdown Stock?',
                text: 'This will reduce wholesale stock and add retail stock.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Breakdown',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    const addBatchContainer = document.getElementById('addBatchRows');
    if (addBatchContainer) {
        addBatchContainer.innerHTML = '';
        addBatchRow('addBatchRows');
    }

    const addModal = document.getElementById('addProductModal');
    if (addModal) {
        addModal.addEventListener('shown.bs.modal', function() {
            ensureAtLeastOneBatch('addBatchRows');
        });

        addModal.addEventListener('hidden.bs.modal', function() {
            const addForm = addModal.querySelector('form');
            if (addForm) {
                addForm.reset();
            }
            addBatchContainer.innerHTML = '';
            addBatchRow('addBatchRows');
        });

        const addForm = addModal.querySelector('form');
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Add Product?',
                text: 'Are you sure you want to add this product to the inventory?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Add',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    // Edit Product Form Confirmation
    const editForm = document.getElementById('editForm');
    if (editForm) {
        document.getElementById('editProductModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('editBatchRows').innerHTML = '';
        });

        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Update Product?',
                text: 'Are you sure you want to update this product?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Update',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }
});

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

<?php
$content = ob_get_clean();
include __DIR__ . '/../app/views/layout.php';
?>


