<?php
require_once __DIR__ . '/../app/config.php';
User::requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
$db = Database::getInstance()->getConnection();
$perPage = 15;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalProducts = (int) $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$productsStmt = $db->prepare("
    SELECT *
    FROM products
    WHERE status = 'active'
    ORDER BY name ASC
    LIMIT :limit OFFSET :offset
");
$productsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$productsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$productsStmt->execute();
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
$shownStart = $totalProducts > 0 ? $offset + 1 : 0;
$shownEnd = min($offset + $perPage, $totalProducts);
$title = 'Stock Monitoring';
ob_start();
?>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-1"><i class="fas fa-warehouse me-2 text-brand"></i>Stock Monitoring</h5>
            <small class="text-muted">
                Showing <?php echo number_format($shownStart); ?>-<?php echo number_format($shownEnd); ?>
                of <?php echo number_format($totalProducts); ?> active products.
            </small>
        </div>
        <span class="badge bg-brand"><?php echo number_format($totalProducts); ?> Products</span>
    </div>

    <?php if (empty($products)): ?>
        <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-box-open fa-3x d-block mb-3"></i>
            <div class="fw-semibold">No active products found.</div>
            <small>Add active products to start monitoring stock levels.</small>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Category ID</th>
                        <th>Stock</th>
                        <th>Threshold</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <?php
                        $stockQuantity = (int) ($p['stock_quantity'] ?? 0);
                        $threshold = (int) ($p['low_stock_threshold'] ?? 0);
                        $statusClass = $stockQuantity <= 0 ? 'bg-danger' : ($stockQuantity <= $threshold ? 'bg-warning text-dark' : 'bg-success');
                        $statusText = $stockQuantity <= 0 ? 'Out of Stock' : ($stockQuantity <= $threshold ? 'Low Stock' : 'In Stock');
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?=htmlspecialchars($p['name'] ?? '')?></div>
                            <small class="text-muted"><?=htmlspecialchars(ucfirst($p['product_type'] ?? 'retail'))?></small>
                        </td>
                        <td><?=htmlspecialchars($p['sku'] ?? '')?></td>
                        <td><?=htmlspecialchars(($p['barcode'] ?? '') ?: 'N/A')?></td>
                        <td><?=number_format((int) ($p['category_id'] ?? 0))?></td>
                        <td><span class="badge <?=$statusClass?>"><?=number_format($stockQuantity)?></span></td>
                        <td><?=number_format($threshold)?></td>
                        <td><span class="badge <?=$statusClass?>"><?=$statusText?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-body border-top">
                <nav aria-label="Stock monitoring pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>">Previous</a>
                        </li>

                        <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                        ?>

                        <?php if ($startPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a></li>
                        <?php endif; ?>

                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo min($totalPages, $page + 1); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>
