<?php
require_once __DIR__ . '/../app/config.php';
requireLogin();

if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: ' . BASE_URL . 'public/dashboard.php');
    exit();
}

if (($_SESSION['role'] ?? '') === 'cashier') {
    header('Location: ' . BASE_URL . 'cashier/dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$title = 'Staff Dashboard';
$staffName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
$staffName = $staffName !== '' ? $staffName : ($_SESSION['username'] ?? 'Staff');

$stats = [
    'total_products' => (int) $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
    'in_stock' => (int) $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity > low_stock_threshold")->fetchColumn(),
    'low_stock' => (int) $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity <= low_stock_threshold AND stock_quantity > 0")->fetchColumn(),
    'out_of_stock' => (int) $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity = 0")->fetchColumn(),
    'retail' => (int) $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND product_type = 'retail'")->fetchColumn(),
    'wholesale' => (int) $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND product_type = 'wholesale'")->fetchColumn(),
    'stock_value' => (float) $db->query("SELECT COALESCE(SUM(COALESCE(cost_price, 0) * stock_quantity), 0) FROM products WHERE status = 'active'")->fetchColumn(),
];

$lowStockStmt = $db->query("
    SELECT id, name, sku, stock_quantity, low_stock_threshold, product_type
    FROM products
    WHERE status = 'active' AND stock_quantity <= low_stock_threshold AND stock_quantity > 0
    ORDER BY stock_quantity ASC, name ASC
    LIMIT 8
");
$lowStockProducts = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

$expiringStmt = $db->query("
    SELECT name, stock_quantity, expiry, product_type
    FROM products
    WHERE status = 'active'
      AND expiry IS NOT NULL
      AND expiry <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    ORDER BY expiry ASC, name ASC
    LIMIT 8
");
$expiringProducts = $expiringStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryStmt = $db->query("
    SELECT COALESCE(c.name, 'Uncategorized') AS category_name, COUNT(p.id) AS product_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active'
    GROUP BY COALESCE(c.name, 'Uncategorized')
    ORDER BY product_count DESC, category_name ASC
    LIMIT 6
");
$categoryMix = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

$movementStmt = $db->prepare("
    SELECT ir.change_type, ir.quantity_changed, ir.created_at, ir.remarks, p.name AS product_name
    FROM inventory_reports ir
    LEFT JOIN products p ON p.id = ir.product_id
    WHERE ir.user_id = ?
    ORDER BY ir.created_at DESC
    LIMIT 8
");
$movementStmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
$recentMovements = $movementStmt->fetchAll(PDO::FETCH_ASSOC);

$stockHealth = $stats['total_products'] > 0 ? round(($stats['in_stock'] / $stats['total_products']) * 100, 1) : 0;

ob_start();
?>

<style>
.panel-hero {
    background: linear-gradient(135deg, rgba(159, 122, 28, 0.96), rgba(199, 154, 43, 0.94) 52%, rgba(225, 190, 98, 0.92));
    border-radius: 28px;
    padding: 1.9rem;
    color: #172033;
    box-shadow: 0 24px 60px rgba(17, 24, 39, 0.12);
    position: relative;
    overflow: hidden;
}
.hero-kicker {
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-size: 0.76rem;
    color: rgba(23, 32, 51, 0.72);
    margin-bottom: 0.7rem;
    font-weight: 700;
}
.hero-title {
    font-family: 'Manrope', sans-serif;
    font-weight: 800;
    letter-spacing: -0.04em;
    margin-bottom: 0.55rem;
}
.hero-subtitle {
    max-width: 680px;
    color: rgba(23, 32, 51, 0.78);
    margin-bottom: 0;
}
.hero-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    padding: 0.7rem 1rem;
    background: rgba(255,255,255,0.22);
    border: 1px solid rgba(255,255,255,0.3);
    color: #172033;
    font-weight: 700;
    backdrop-filter: blur(10px);
}
.overview-card {
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(15, 23, 42, 0.06);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.07);
    height: 100%;
}
.overview-icon {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.35rem;
}
.overview-label {
    color: #667085;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.overview-value {
    font-family: 'Manrope', sans-serif;
    font-size: 1.65rem;
    font-weight: 800;
    color: #172033;
}
.work-panel {
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(15, 23, 42, 0.06);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.07);
}
.work-panel-header {
    padding: 1rem 1.15rem;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}
.stock-progress {
    height: 12px;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.08);
    overflow: hidden;
}
.stock-progress-bar {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(135deg, #198754, #34d399);
}
</style>

<div class="panel-hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <div class="hero-kicker">Staff Dashboard</div>
            <h2 class="hero-title">Good day, <?php echo htmlspecialchars($staffName); ?>.</h2>
            <p class="hero-subtitle">Monitor stock health, prioritize low inventory, and keep product records accurate from one inventory-focused dashboard.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="hero-chip"><i class="fas fa-warehouse"></i> Inventory Workspace</span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="overview-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="overview-label">Products</div>
                    <div class="overview-value"><?php echo number_format($stats['total_products']); ?></div>
                    <small class="text-muted">Retail <?php echo number_format($stats['retail']); ?> &middot; Wholesale <?php echo number_format($stats['wholesale']); ?></small>
                </div>
                <div class="overview-icon bg-primary"><i class="fas fa-boxes"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="overview-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="overview-label">Low Stock</div>
                    <div class="overview-value"><?php echo number_format($stats['low_stock']); ?></div>
                    <small class="text-muted">Needs replenishment soon</small>
                </div>
                <div class="overview-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="overview-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="overview-label">Out of Stock</div>
                    <div class="overview-value"><?php echo number_format($stats['out_of_stock']); ?></div>
                    <small class="text-muted">Unavailable items</small>
                </div>
                <div class="overview-icon bg-danger"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="overview-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="overview-label">Stock Cost Value</div>
                    <div class="overview-value"><?php echo formatCurrency($stats['stock_value']); ?></div>
                    <small class="text-muted">Based on current cost price</small>
                </div>
                <div class="overview-icon bg-success"><i class="fas fa-coins"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-5">
        <div class="work-panel h-100">
            <div class="work-panel-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Stock Health</h5>
                <span class="badge bg-success"><?php echo number_format($stockHealth, 1); ?>%</span>
            </div>
            <div class="p-3">
                <div class="stock-progress mb-3">
                    <div class="stock-progress-bar" style="width: <?php echo min(100, max(0, $stockHealth)); ?>%;"></div>
                </div>
                <div class="row text-center g-2">
                    <div class="col-4"><div class="fw-bold text-success"><?php echo number_format($stats['in_stock']); ?></div><small class="text-muted">Healthy</small></div>
                    <div class="col-4"><div class="fw-bold text-warning"><?php echo number_format($stats['low_stock']); ?></div><small class="text-muted">Low</small></div>
                    <div class="col-4"><div class="fw-bold text-danger"><?php echo number_format($stats['out_of_stock']); ?></div><small class="text-muted">Empty</small></div>
                </div>
                <div class="d-grid gap-2 mt-3">
                    <a href="manage_product.php" class="btn btn-danger"><i class="fas fa-boxes me-2"></i>Open Inventory</a>
                    <a href="inventory_reports.php" class="btn btn-outline-secondary"><i class="fas fa-file-alt me-2"></i>Record Stock Movement</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="work-panel h-100">
            <div class="work-panel-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Priority Stock</h5>
                <a href="low_stock_alerts.php" class="btn btn-outline-danger btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle mb-0">
                    <thead><tr><th>Product</th><th>Type</th><th>Stock</th><th>Threshold</th></tr></thead>
                    <tbody>
                        <?php if (empty($lowStockProducts)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No low-stock items right now.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><div class="fw-semibold"><?php echo htmlspecialchars($product['name']); ?></div><small class="text-muted"><?php echo htmlspecialchars($product['sku'] ?: ('SKU-' . str_pad((int) $product['id'], 4, '0', STR_PAD_LEFT))); ?></small></td>
                                    <td><span class="badge bg-<?php echo ($product['product_type'] ?? 'retail') === 'wholesale' ? 'dark' : 'secondary'; ?>"><?php echo ucfirst($product['product_type'] ?? 'retail'); ?></span></td>
                                    <td><span class="badge bg-<?php echo (int) $product['stock_quantity'] <= 0 ? 'danger' : 'warning text-dark'; ?>"><?php echo number_format((int) $product['stock_quantity']); ?></span></td>
                                    <td><?php echo number_format((int) $product['low_stock_threshold']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="work-panel h-100">
            <div class="work-panel-header"><h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Category Mix</h5></div>
            <div class="list-group list-group-flush">
                <?php foreach ($categoryMix as $category): ?>
                    <div class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                        <span><?php echo htmlspecialchars($category['category_name']); ?></span>
                        <span class="badge bg-light text-dark border"><?php echo number_format((int) $category['product_count']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="work-panel h-100">
            <div class="work-panel-header"><h5 class="mb-0"><i class="fas fa-calendar-times me-2"></i>Expiring Soon</h5></div>
            <div class="list-group list-group-flush">
                <?php if (empty($expiringProducts)): ?>
                    <div class="list-group-item bg-transparent text-muted py-4 text-center">No products expiring within 14 days.</div>
                <?php else: ?>
                    <?php foreach ($expiringProducts as $product): ?>
                        <div class="list-group-item bg-transparent d-flex justify-content-between gap-3">
                            <div><div class="fw-semibold"><?php echo htmlspecialchars($product['name']); ?></div><small class="text-muted"><?php echo ucfirst($product['product_type'] ?? 'retail'); ?> &middot; Stock <?php echo number_format((int) $product['stock_quantity']); ?></small></div>
                            <span class="badge bg-warning text-dark align-self-start"><?php echo date('M d', strtotime($product['expiry'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="work-panel h-100">
            <div class="work-panel-header"><h5 class="mb-0"><i class="fas fa-clock-rotate-left me-2"></i>Your Recent Updates</h5></div>
            <div class="list-group list-group-flush">
                <?php if (empty($recentMovements)): ?>
                    <div class="list-group-item bg-transparent text-muted py-4 text-center">No inventory updates recorded yet.</div>
                <?php else: ?>
                    <?php foreach ($recentMovements as $movement): ?>
                        <div class="list-group-item bg-transparent">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="fw-semibold"><?php echo htmlspecialchars($movement['product_name'] ?? 'Product'); ?></div>
                                <span class="badge bg-<?php echo $movement['change_type'] === 'Added' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($movement['change_type']); ?> <?php echo number_format((int) $movement['quantity_changed']); ?></span>
                            </div>
                            <small class="text-muted"><?php echo date('M d, g:i A', strtotime($movement['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>
