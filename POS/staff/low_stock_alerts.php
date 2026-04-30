<?php
require_once __DIR__ . '/../app/config.php';
User::requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$title = 'Low Stock Alerts';
$db = Database::getInstance()->getConnection();
$products = $db->query("
    SELECT *
    FROM products
    WHERE status = 'active'
      AND stock_quantity <= low_stock_threshold
      AND stock_quantity > 0
    ORDER BY stock_quantity ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-1"><i class="fas fa-exclamation-triangle me-2 text-brand"></i>Low Stock Alerts</h5>
            <small class="text-muted">Active products at or below their reorder threshold.</small>
        </div>
        <span class="badge bg-brand"><?php echo number_format(count($products)); ?> Items</span>
    </div>

    <?php if (empty($products)): ?>
        <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-check-circle fa-3x d-block mb-3"></i>
            <div class="fw-semibold">No low-stock items right now.</div>
            <small>Products will appear here once stock reaches the configured threshold.</small>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Current Stock</th>
                        <th>Threshold</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?=htmlspecialchars($p['name'] ?? '')?></div>
                            <small class="text-muted"><?=htmlspecialchars(ucfirst($p['product_type'] ?? 'retail'))?></small>
                        </td>
                        <td><?=htmlspecialchars($p['sku'] ?? '')?></td>
                        <td><?=htmlspecialchars(($p['barcode'] ?? '') ?: 'N/A')?></td>
                        <td><span class="badge bg-warning text-dark"><?=number_format((int) $p['stock_quantity'])?></span></td>
                        <td><?=number_format((int) $p['low_stock_threshold'])?></td>
                        <td><span class="badge bg-warning text-dark">Low Stock</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>
