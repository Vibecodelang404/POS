<?php
require_once __DIR__ . '/../app/config.php';
requireAdmin(); // Only admin can view inventory reports

$page_title = 'Inventory Stock Reports';

// Get date range from request or default to last 7 days
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$filter_type = $_GET['filter_type'] ?? 'all'; // all, recent, admin, added, removed
$category_filter = trim($_GET['category_filter'] ?? '');
$search = trim($_GET['search'] ?? '');

// Database connection
$db = Database::getInstance()->getConnection();
$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get inventory change reports with user information
function getInventoryReports($db, $start_date, $end_date, $filter_type, $category_filter, $search) {
    $sql = "
        SELECT 
            ir.id,
            ir.date,
            ir.product_id,
            ir.change_type,
            COALESCE(NULLIF(ir.quantity_changed, 0), ir.quantity) as changed_quantity,
            ir.previous_quantity,
            ir.new_quantity,
            ir.remarks,
            ir.user_id,
            ir.created_at,
            p.name as product_name,
            p.sku,
            p.stock_quantity as current_stock,
            p.category_id,
            c.name as category_name,
            u.username,
            u.first_name,
            u.last_name,
            u.role,
            CASE 
                WHEN ir.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR) THEN 1
                ELSE 0
            END as is_recent
        FROM inventory_reports ir
        LEFT JOIN products p ON ir.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON ir.user_id = u.id
        WHERE ir.date BETWEEN ? AND ?
    ";
    
    $params = [$start_date, $end_date];
    
    // Filter by type
    if ($filter_type === 'recent') {
        $sql .= " AND ir.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)";
    } elseif ($filter_type === 'admin') {
        $sql .= " AND u.role = 'admin'";
    } elseif ($filter_type === 'added') {
        $sql .= " AND ir.change_type = 'Added'";
    } elseif ($filter_type === 'removed') {
        $sql .= " AND ir.change_type = 'Removed'";
    }

    if ($category_filter !== '') {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_filter;
    }

    if ($search !== '') {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR ir.remarks LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY ir.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get summary statistics
function getInventoryStats($db, $start_date, $end_date, $filter_type, $category_filter, $search) {
    $sql = "
        SELECT 
            COUNT(*) as total_changes,
            SUM(CASE WHEN ir.change_type = 'Added' THEN COALESCE(NULLIF(ir.quantity_changed, 0), ir.quantity) ELSE 0 END) as total_added,
            SUM(CASE WHEN ir.change_type = 'Removed' THEN COALESCE(NULLIF(ir.quantity_changed, 0), ir.quantity) ELSE 0 END) as total_removed,
            COUNT(DISTINCT ir.product_id) as products_affected,
            COUNT(CASE WHEN ir.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR) THEN 1 END) as recent_changes,
            COUNT(DISTINCT ir.user_id) as users_involved
        FROM inventory_reports ir
        LEFT JOIN products p ON ir.product_id = p.id
        LEFT JOIN users u ON ir.user_id = u.id
        WHERE ir.date BETWEEN ? AND ?
    ";

    $params = [$start_date, $end_date];

    if ($filter_type === 'recent') {
        $sql .= " AND ir.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)";
    } elseif ($filter_type === 'admin') {
        $sql .= " AND u.role = 'admin'";
    } elseif ($filter_type === 'added') {
        $sql .= " AND ir.change_type = 'Added'";
    } elseif ($filter_type === 'removed') {
        $sql .= " AND ir.change_type = 'Removed'";
    }

    if ($category_filter !== '') {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_filter;
    }

    if ($search !== '') {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR ir.remarks LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get data
$reports = getInventoryReports($db, $start_date, $end_date, $filter_type, $category_filter, $search);
$stats = getInventoryStats($db, $start_date, $end_date, $filter_type, $category_filter, $search);

ob_start();
?>

<style>
.stat-card {
    border-radius: 12px;
    padding: 1.1rem 1.25rem;
    background: white;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.06);
}
.stat-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.badge-new {
    animation: pulse 2s infinite;
    font-weight: bold;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.report-table th {
    background: #f8f9fa;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    color: #495057;
}
.report-table td {
    vertical-align: middle;
}
.change-added {
    color: #28a745;
    font-weight: 600;
}
.change-removed {
    color: #dc3545;
    font-weight: 600;
}
</style>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-primary text-white me-3">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo number_format($stats['total_changes'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">Total Changes</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-success text-white me-3">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo number_format($stats['total_added'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">Stock Added</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-danger text-white me-3">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo number_format($stats['total_removed'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">Stock Removed</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-warning text-white me-3">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo number_format($stats['recent_changes'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">Recent Changes</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Report Filters</h5>
                <a href="?start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>&filter_type=all" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-redo me-1"></i>Reset
                </a>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Filter By</label>
                        <select name="filter_type" class="form-select">
                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Changes</option>
                            <option value="recent" <?php echo $filter_type === 'recent' ? 'selected' : ''; ?>>Recent Only (48hrs)</option>
                            <option value="admin" <?php echo $filter_type === 'admin' ? 'selected' : ''; ?>>Admin Changes</option>
                            <option value="added" <?php echo $filter_type === 'added' ? 'selected' : ''; ?>>Added Only</option>
                            <option value="removed" <?php echo $filter_type === 'removed' ? 'selected' : ''; ?>>Removed Only</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Category</label>
                        <select name="category_filter" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo (int) $category['id']; ?>" <?php echo (string) $category_filter === (string) $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Search</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Product, SKU, remarks">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Inventory Reports Table -->
<div class="row">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Inventory Stock Changes</h5>
                <button class="btn btn-success btn-sm" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print Report
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($reports)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <p class="text-muted">No inventory changes found for the selected period</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered align-middle report-table mb-0">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Change Type</th>
                                <th>Quantity Changed</th>
                                <th>Stock Before</th>
                                <th>Stock After</th>
                                <th>Updated By</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo date('M d, Y', strtotime($report['date'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($report['created_at'] ?? $report['date'])); ?></small>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($report['product_name']); ?></div>
                                    <small class="text-muted">SKU: <?php echo htmlspecialchars($report['sku'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($report['category_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <?php if ($report['change_type'] === 'Added'): ?>
                                        <span class="badge bg-success"><i class="fas fa-plus me-1"></i>Added</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-minus me-1"></i>Removed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $report['change_type'] === 'Added' ? 'change-added' : 'change-removed'; ?>">
                                    <?php echo $report['change_type'] === 'Added' ? '+' : '-'; ?><?php echo number_format($report['changed_quantity']); ?>
                                </td>
                                <td>
                                    <strong><?php echo number_format((int) ($report['previous_quantity'] ?? 0)); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo number_format((int) (($report['new_quantity'] ?? $report['current_stock']) ?? 0)); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <?php 
                                        $full_name = trim($report['first_name'] . ' ' . $report['last_name']);
                                        echo htmlspecialchars($full_name ?: $report['username'] ?: 'Unknown'); 
                                        ?>
                                    </div>
                                    <?php if ($report['role'] === 'admin'): ?>
                                        <span class="badge bg-danger" style="font-size: 0.7rem;">ADMIN</span>
                                    <?php elseif ($report['role'] === 'staff'): ?>
                                        <span class="badge bg-primary" style="font-size: 0.7rem;">STAFF</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" style="font-size: 0.7rem;">CASHIER</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($report['is_recent']): ?>
                                        <span class="badge bg-warning badge-new">
                                            <i class="fas fa-star me-1"></i>NEW
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Past</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($report['remarks'] ?: '-'); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="row mt-3">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> The "NEW" badge indicates stock changes made within the last 48 hours.
            Reports show stock movement history, the before-and-after stock position, and who made each update.
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/layout.php';
?>


