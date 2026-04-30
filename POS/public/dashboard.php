<?php
require_once __DIR__ . '/../app/config.php';
requireLogin();

// Redirect based on role
if ($_SESSION['role'] !== 'admin') {
    if ($_SESSION['role'] === 'cashier') {
        header('Location: ' . BASE_URL . 'cashier/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'staff/dashboard.php');
    }
    exit();
}

$dashboardController = new DashboardController();
$stats = $dashboardController->getStats();
$recentOrders = $dashboardController->getRecentOrders(5);
$lowStockProducts = $dashboardController->getLowStockProducts();

$role = $_SESSION['role'];
$page_title = $role === 'admin' ? 'Admin Dashboard' : 'Staff Dashboard';

// Additional statistics for admin dashboard
$db = Database::getInstance()->getConnection();

// Get comprehensive statistics
$inventoryStats = [
    'total_products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'low_stock' => $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= low_stock_threshold AND stock_quantity > 0")->fetchColumn(),
    'out_of_stock' => $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0")->fetchColumn(),
    'in_stock' => $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity > low_stock_threshold")->fetchColumn(),
];

// Get today's sales
$todayQuery = $db->query("
    SELECT 
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_sales
    FROM orders 
    WHERE DATE(created_at) = CURDATE() AND status = 'completed'
");
$todayStats = $todayQuery->fetch(PDO::FETCH_ASSOC);

// Get this week's sales
$weekQuery = $db->query("
    SELECT 
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_sales
    FROM orders 
    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) AND status = 'completed'
");
$weekStats = $weekQuery->fetch(PDO::FETCH_ASSOC);

$previousWeekQuery = $db->query("
    SELECT 
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_sales
    FROM orders
    WHERE YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1) AND status = 'completed'
");
$previousWeekStats = $previousWeekQuery->fetch(PDO::FETCH_ASSOC);

// Get this month's sales
$monthQuery = $db->query("
    SELECT 
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_sales
    FROM orders 
    WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'completed'
");
$monthStats = $monthQuery->fetch(PDO::FETCH_ASSOC);

$previousMonthQuery = $db->query("
    SELECT 
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_sales
    FROM orders
    WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
      AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND status = 'completed'
");
$previousMonthStats = $previousMonthQuery->fetch(PDO::FETCH_ASSOC);

// Get sales by payment method for chart
$paymentMethodQuery = $db->query("
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(total_amount) as total
    FROM orders 
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'completed'
    GROUP BY payment_method
    ORDER BY total DESC
");
$paymentMethods = $paymentMethodQuery->fetchAll(PDO::FETCH_ASSOC);

// Get top selling products (last 30 days)
$topProductsQuery = $db->query("
    SELECT 
        p.name,
        SUM(oi.quantity) as total_sold,
        SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND o.status = 'completed'
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 5
");
$topProducts = $topProductsQuery->fetchAll(PDO::FETCH_ASSOC);

// Get daily sales for last 7 days
$dailySalesQuery = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as orders,
        SUM(total_amount) as sales
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$dailySales = $dailySalesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get category-wise product distribution
$categoryQuery = $db->query("
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category,
        COUNT(p.id) as product_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    GROUP BY COALESCE(c.name, 'Uncategorized')
    ORDER BY product_count DESC
    LIMIT 6
");
$categoryData = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);

$restockRiskQuery = $db->query("
    SELECT
        p.id,
        p.name,
        p.stock_quantity,
        p.low_stock_threshold,
        COALESCE(SUM(CASE WHEN o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND o.status = 'completed' THEN oi.quantity ELSE 0 END), 0) as units_sold_30d,
        COALESCE(SUM(CASE WHEN o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND o.status = 'completed' THEN oi.total_price ELSE 0 END), 0) as revenue_30d
    FROM products p
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id
    WHERE p.status = 'active' AND p.stock_quantity <= p.low_stock_threshold
    GROUP BY p.id, p.name, p.stock_quantity, p.low_stock_threshold
    ORDER BY revenue_30d DESC, units_sold_30d DESC, p.stock_quantity ASC
    LIMIT 5
");
$restockRisks = $restockRiskQuery->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$salesDates = array_map(function($item) { return date('M d', strtotime($item['date'])); }, $dailySales);
$salesAmounts = array_map(function($item) { return $item['sales']; }, $dailySales);
$salesOrders = array_map(function($item) { return $item['orders']; }, $dailySales);
$paymentLabels = array_map(function($pm) { return ucfirst($pm['payment_method']); }, $paymentMethods);
$paymentCounts = array_map(function($pm) { return (int) $pm['count']; }, $paymentMethods);
$paymentTotals = array_map(function($pm) { return (float) $pm['total']; }, $paymentMethods);
$categoryLabels = array_map(function($item) { return $item['category']; }, $categoryData);
$categoryCounts = array_map(function($item) { return (int) $item['product_count']; }, $categoryData);

$safeDivide = function ($numerator, $denominator) {
    return $denominator > 0 ? $numerator / $denominator : 0;
};

$percentChange = function ($current, $previous) use ($safeDivide) {
    if ((float) $previous === 0.0) {
        return (float) $current > 0 ? 100 : 0;
    }
    return (($current - $previous) / $previous) * 100;
};

$formatPercent = function ($value) {
    $prefix = $value > 0 ? '+' : '';
    return $prefix . number_format($value, 1) . '%';
};

$weekGrowth = $percentChange((float) $weekStats['total_sales'], (float) $previousWeekStats['total_sales']);
$monthGrowth = $percentChange((float) $monthStats['total_sales'], (float) $previousMonthStats['total_sales']);
$todayAverageTicket = $safeDivide((float) $todayStats['total_sales'], (int) $todayStats['order_count']);

// Start content
ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
.admin-dashboard-card {
    border-radius: 22px;
    padding: 1.5rem;
    background: rgba(255,255,255,0.94);
    box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
    border: 1px solid rgba(15, 23, 42, 0.05);
}
.admin-dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
}
.admin-stat-icon {
    width: 68px;
    height: 68px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.7rem;
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
}
.admin-chart-container {
    position: relative;
    height: 300px;
}
.admin-chart-canvas {
    width: 100% !important;
    height: 300px !important;
}
.admin-chart-canvas-tall {
    width: 100% !important;
    height: 340px !important;
}
.admin-section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--accent-deep);
}
.dashboard-action-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}
.metric-stack {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.metric-kicker {
    color: rgba(23, 32, 51, 0.58);
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 800;
}
.compact-list-item {
    border-radius: 16px;
    background: #f8fafc;
    border: 1px solid rgba(15, 23, 42, 0.05);
}
.inventory-health-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
    margin-top: 1rem;
}
.inventory-health-item {
    padding: 1rem;
    border-radius: 18px;
    background: #f8fafc;
    border: 1px solid rgba(15, 23, 42, 0.05);
}
.inventory-health-item strong {
    display: block;
    font-family: 'Manrope', sans-serif;
    font-size: 1.5rem;
    line-height: 1;
    margin-bottom: 0.35rem;
}
.inventory-health-label {
    color: #344054;
    font-weight: 700;
}
.inventory-health-note {
    color: var(--muted);
    font-size: 0.84rem;
    margin-top: 0.2rem;
}
.chart-shell {
    position: relative;
    border-radius: 20px;
    padding: 1rem;
    background:
        radial-gradient(circle at top right, rgba(212, 175, 55, 0.14), transparent 24%),
        linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 0.96));
    border: 1px solid rgba(15, 23, 42, 0.06);
}
.chart-kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.chart-kpi {
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(15, 23, 42, 0.06);
    padding: 0.85rem 1rem;
}
.chart-kpi-label {
    color: var(--muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.chart-kpi-value {
    font-family: 'Manrope', sans-serif;
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--ink);
}
@media (max-width: 991px) {
    .dashboard-action-grid {
        grid-template-columns: 1fr;
    }
    .inventory-health-grid {
        grid-template-columns: 1fr;
    }
    .chart-kpis {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Welcome Section -->
<div class="panel-hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <h2 class="hero-title">Admin Dashboard</h2>
            <p class="hero-subtitle">Monitor sales and stock signals that support better business decisions.</p>
        </div>
    </div>
</div>
<div class="admin-dashboard-card mb-4">
    <div class="mb-3">
        <h5 class="admin-section-title mb-2 border-0 pb-0">Action Center</h5>
        <p class="text-muted mb-0">Go straight to the areas that usually need immediate action.</p>
    </div>
    <div class="dashboard-action-grid">
        <a href="inventory.php?stock_filter=low_stock" class="action-link-card">
            <div class="d-flex align-items-center gap-3">
                <span class="action-link-icon"><i class="fas fa-box-open"></i></span>
                <div>
                    <div class="fw-bold">Review Replenishment</div>
                    <div class="metric-note">Review low-stock items before sales are affected</div>
                </div>
            </div>
            <i class="fas fa-arrow-right text-muted"></i>
        </a>
        <a href="transactions.php" class="action-link-card">
            <div class="d-flex align-items-center gap-3">
                <span class="action-link-icon"><i class="fas fa-receipt"></i></span>
                <div>
                    <div class="fw-bold">Review Sales Activity</div>
                    <div class="metric-note">Review recent transactions and checkout activity</div>
                </div>
            </div>
            <i class="fas fa-arrow-right text-muted"></i>
        </a>
        <a href="user_management.php" class="action-link-card">
            <div class="d-flex align-items-center gap-3">
                <span class="action-link-icon"><i class="fas fa-users-cog"></i></span>
                <div>
                    <div class="fw-bold">Review Team Coverage</div>
                    <div class="metric-note">Adjust access and support around store demand</div>
                </div>
            </div>
            <i class="fas fa-arrow-right text-muted"></i>
        </a>
    </div>
</div>

<!-- Top Metrics Row -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="admin-dashboard-card" style="border-left: 4px solid #667eea;">
            <div class="d-flex align-items-center">
                <div class="admin-stat-icon text-white me-3" style="background-color: #667eea !important;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="metric-stack">
                    <span class="metric-kicker">Today</span>
                    <h3 class="mb-0 text-dark"><?php echo formatCurrency($todayStats['total_sales']); ?></h3>
                    <p class="text-muted mb-0">Revenue Today</p>
                    <small class="text-primary"><?= $todayStats['order_count'] ?> transactions | Avg ticket <?php echo formatCurrency($todayAverageTicket); ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="admin-dashboard-card" style="border-left: 4px solid #f093fb;">
            <div class="d-flex align-items-center">
                <div class="admin-stat-icon text-white me-3" style="background-color: #f093fb !important;">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="metric-stack">
                    <span class="metric-kicker">7 Days</span>
                    <h3 class="mb-0 text-dark"><?php echo formatCurrency($weekStats['total_sales']); ?></h3>
                    <p class="text-muted mb-0">Revenue This Week</p>
                    <small class="<?php echo $weekGrowth >= 0 ? 'text-success' : 'text-danger'; ?>"><?= $weekStats['order_count'] ?> transactions | <?php echo $formatPercent($weekGrowth); ?> vs previous week</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="admin-dashboard-card" style="border-left: 4px solid #17a2b8;">
            <div class="d-flex align-items-center">
                <div class="admin-stat-icon bg-info text-white me-3">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="metric-stack">
                    <span class="metric-kicker">Month To Date</span>
                    <h3 class="mb-0 text-dark"><?php echo formatCurrency($monthStats['total_sales']); ?></h3>
                    <p class="text-muted mb-0">Revenue This Month</p>
                    <small class="<?php echo $monthGrowth >= 0 ? 'text-success' : 'text-danger'; ?>"><?= $monthStats['order_count'] ?> transactions | <?php echo $formatPercent($monthGrowth); ?> vs last month</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="admin-dashboard-card" style="border-left: 4px solid #28a745;">
            <div class="d-flex align-items-center">
                <div class="admin-stat-icon bg-success text-white me-3">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="metric-stack">
                    <span class="metric-kicker">Lifetime</span>
                    <h3 class="mb-0 text-dark"><?php echo formatCurrency($stats['total_sales']); ?></h3>
                    <p class="text-muted mb-0">Overall Revenue</p>
                    <small class="text-success">Historical context for trend decisions</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inventory & Transaction Stats -->
<div class="row mb-4">
    <div class="col-xl-6 col-md-12 mb-3">
        <div class="admin-dashboard-card" style="border-left: 4px solid #0d6efd;">
            <div class="d-flex align-items-center">
                <div class="admin-stat-icon bg-primary text-white me-3">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="metric-stack">
                    <span class="metric-kicker">Inventory Snapshot</span>
                    <h3 class="mb-0 text-dark"><?= $inventoryStats['total_products'] ?></h3>
                    <p class="text-muted mb-0">Products Monitored</p>
                    <small class="text-primary">Use this to spot assortment and availability pressure</small>
                </div>
            </div>
            <div class="inventory-health-grid">
                <div class="inventory-health-item">
                    <strong class="text-success"><?= $inventoryStats['in_stock'] ?></strong>
                    <div class="inventory-health-label">In Stock</div>
                    <div class="inventory-health-note">Stable availability for near-term demand</div>
                </div>
                <div class="inventory-health-item">
                    <strong class="text-warning"><?= $inventoryStats['low_stock'] ?></strong>
                    <div class="inventory-health-label">Low Stock</div>
                    <div class="inventory-health-note">Replenish soon to avoid missed sales</div>
                </div>
                <div class="inventory-health-item">
                    <strong class="text-brand"><?= $inventoryStats['out_of_stock'] ?></strong>
                    <div class="inventory-health-label">Out of Stock</div>
                    <div class="inventory-health-note">Immediate revenue risk from unavailable items</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 col-sm-6 mb-3">
        <div class="admin-dashboard-card" style="border-left: 4px solid #6f42c1;">
            <div class="text-center">
                <div class="admin-stat-icon bg-purple text-white mx-auto mb-2" style="background-color: #6f42c1 !important;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3 class="mb-0"><?= $stats['total_orders'] ?></h3>
                <small class="text-muted">Total Transactions</small>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 col-sm-6 mb-3">
        <div class="admin-dashboard-card" style="border-left: 4px solid var(--accent-deep);">
            <div class="text-center">
                <div class="admin-stat-icon bg-brand mx-auto mb-2">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <h3 class="mb-0"><?= $inventoryStats['low_stock'] + $inventoryStats['out_of_stock'] ?></h3>
                <small class="text-muted">Items for Review</small>
            </div>
        </div>
    </div>
    
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Sales Analysis Chart -->
    <div class="col-xl-8 mb-3">
        <div class="admin-dashboard-card">
            <h5 class="admin-section-title"><i class="fas fa-chart-line me-2"></i>7-Day Sales Trend</h5>
            <div class="chart-kpis">
                <div class="chart-kpi">
                    <div class="chart-kpi-label">Peak Day</div>
                    <div class="chart-kpi-value"><?php echo !empty($dailySales) ? formatCurrency(max($salesAmounts)) : formatCurrency(0); ?></div>
                </div>
                <div class="chart-kpi">
                    <div class="chart-kpi-label">Transactions Tracked</div>
                    <div class="chart-kpi-value"><?php echo number_format(array_sum($salesOrders)); ?></div>
                </div>
                <div class="chart-kpi">
                    <div class="chart-kpi-label">Daily Average</div>
                    <div class="chart-kpi-value"><?php echo formatCurrency(!empty($salesAmounts) ? array_sum($salesAmounts) / count($salesAmounts) : 0); ?></div>
                </div>
            </div>
            <div class="chart-shell">
                <canvas id="adminSalesAnalysisChart" class="admin-chart-canvas"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Payment Methods Chart -->
    <div class="col-xl-4 mb-3">
        <div class="admin-dashboard-card">
            <h5 class="admin-section-title"><i class="fas fa-credit-card me-2"></i>Payment Mix</h5>
            <div class="chart-shell">
                <canvas id="adminPaymentMethodChart" class="admin-chart-canvas"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="admin-dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="admin-section-title mb-0"><i class="fas fa-layer-group me-2"></i>Category Mix</h5>
                <span class="badge badge-brand"><?php echo count($categoryData); ?> categories monitored</span>
            </div>
            <div class="chart-shell">
                <?php if (empty($categoryData)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No category insights available yet.</p>
                    </div>
                <?php else: ?>
                    <canvas id="adminCategoryChart" class="admin-chart-canvas-tall"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Content Row -->
<div class="row mb-4">
    <!-- Recent Transactions -->
    <div class="col-xl-5 mb-3">
        <div class="admin-dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="admin-section-title mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                <a href="transactions.php" class="btn btn-outline-danger btn-sm">Open Transactions</a>
            </div>
            <?php if (empty($recentOrders)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No recent transactions available.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-striped table-hover table-bordered align-middle mb-0">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                            <tr>
                                <th>Transaction #</th>
                                <th>Staff</th>
                                <th>Amount</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><strong class="text-brand" style="font-size: 0.85rem;"><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                <td><small><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></small></td>
                                <td><strong style="font-size: 0.85rem;"><?php echo formatCurrency($order['total_amount']); ?></strong></td>
                                <td><small class="text-muted"><?php echo Layout::getTimeAgo($order['created_at']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Low Stock Alert -->
    <div class="col-xl-4 mb-3">
        <div class="admin-dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="admin-section-title mb-0"><i class="fas fa-exclamation-circle me-2"></i>Low Stock Priorities</h5>
                <span class="badge badge-brand"><?php echo count($lowStockProducts); ?> Items</span>
            </div>
            <?php if (empty($lowStockProducts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p class="text-muted">All monitored items are sufficiently stocked.</p>
                </div>
            <?php else: ?>
                <div style="max-height: 350px; overflow-y: auto;">
                    <?php foreach (array_slice($restockRisks, 0, 5) as $product): ?>
                    <div class="mb-2 p-2 compact-list-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($product['name']); ?></div>
                                <small class="text-muted">Stock: <?php echo (int) $product['stock_quantity']; ?></small>
                            </div>
                            <span class="badge badge-brand">Only <?php echo (int) $product['stock_quantity']; ?> left</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="inventory.php?stock_filter=low_stock" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-boxes me-1"></i>Review Inventory
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="col-xl-3 mb-3">
        <div class="admin-dashboard-card">
            <h5 class="admin-section-title"><i class="fas fa-fire me-2"></i>Top-Selling Products</h5>
            <?php if (empty($topProducts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                    <p class="text-muted mb-0">No product sales insights available yet.</p>
                </div>
            <?php else: ?>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach (array_slice($topProducts, 0, 3) as $index => $product): ?>
                    <div class="mb-3 p-3 compact-list-item">
                        <div>
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-brand rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 1rem; font-weight: bold;">
                                    <?= $index + 1 ?>
                                </div>
                                <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted"><i class="fas fa-box me-1"></i><?= $product['total_sold'] ?> sold</small>
                                <div class="fw-bold text-success"><?php echo formatCurrency($product['revenue']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
const adminChartLabels = <?php echo json_encode($salesDates); ?>;
const adminSalesSeries = <?php echo json_encode($salesAmounts); ?>;
const adminOrderSeries = <?php echo json_encode($salesOrders); ?>;
const adminPaymentLabels = <?php echo json_encode($paymentLabels); ?>;
const adminPaymentSeries = <?php echo json_encode($paymentCounts); ?>;
const adminPaymentTotals = <?php echo json_encode($paymentTotals); ?>;
const adminCategoryLabels = <?php echo json_encode($categoryLabels); ?>;
const adminCategorySeries = <?php echo json_encode($categoryCounts); ?>;
const adminCurrency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function createAdminGradient(context, colorStops) {
    const gradient = context.createLinearGradient(0, 0, 0, 320);
    colorStops.forEach(stop => gradient.addColorStop(stop.position, stop.color));
    return gradient;
}

// Sales Analysis Chart
const adminSalesCtx = document.getElementById('adminSalesAnalysisChart');
if (adminSalesCtx) {
const salesGradient = createAdminGradient(adminSalesCtx.getContext('2d'), [
    { position: 0, color: 'rgba(159, 122, 28, 0.34)' },
    { position: 1, color: 'rgba(159, 122, 28, 0.02)' }
]);

new Chart(adminSalesCtx, {
    type: 'line',
    data: {
        labels: adminChartLabels,
        datasets: [{
            label: 'Sales (PHP)',
            data: adminSalesSeries,
            borderColor: '#9F7A1C',
            backgroundColor: salesGradient,
            pointBackgroundColor: '#fff7db',
            pointBorderColor: '#9F7A1C',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 3,
            tension: 0.38,
            fill: true,
            yAxisID: 'y'
        }, {
            label: 'Transactions',
            data: adminOrderSeries,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.9)',
            borderWidth: 2,
            borderDash: [6, 6],
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.3,
            fill: false,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                backgroundColor: 'rgba(23, 32, 51, 0.94)',
                padding: 12,
                callbacks: {
                    label(context) {
                        if (context.dataset.yAxisID === 'y') {
                            return `${context.dataset.label}: ${adminCurrency.format(context.parsed.y || 0)}`;
                        }
                        return `${context.dataset.label}: ${context.parsed.y || 0}`;
                    }
                }
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true,
                grid: {
                    color: 'rgba(23, 32, 51, 0.06)'
                },
                ticks: {
                    callback(value) {
                        return adminCurrency.format(value);
                    }
                },
                title: {
                    display: true,
                    text: 'Sales (PHP)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                title: {
                    display: true,
                    text: 'Transactions'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});
}

// Payment Method Chart
const adminPaymentCtx = document.getElementById('adminPaymentMethodChart');
if (adminPaymentCtx) {
new Chart(adminPaymentCtx, {
    type: 'polarArea',
    data: {
        labels: adminPaymentLabels,
        datasets: [{
            data: adminPaymentSeries,
            backgroundColor: [
                'rgba(40, 167, 69, 0.78)',
                'rgba(23, 162, 184, 0.78)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(199, 154, 43, 0.82)'
            ],
            borderWidth: 2,
            borderColor: '#fff',
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label(context) {
                        const index = context.dataIndex;
                        const count = adminPaymentSeries[index] || 0;
                        const total = adminPaymentTotals[index] || 0;
                        return `${context.label}: ${count} txns, ${adminCurrency.format(total)}`;
                    }
                }
            }
        },
        scales: {
            r: {
                grid: {
                    color: 'rgba(23, 32, 51, 0.08)'
                },
                ticks: {
                    backdropColor: 'transparent',
                    precision: 0
                }
            }
        }
    }
});
}

const adminCategoryCtx = document.getElementById('adminCategoryChart');
if (adminCategoryCtx) {
new Chart(adminCategoryCtx, {
    type: 'bar',
    data: {
        labels: adminCategoryLabels,
        datasets: [{
            label: 'Products',
            data: adminCategorySeries,
            backgroundColor: [
                '#D4AF37',
                '#0d6efd',
                '#17a2b8',
                '#28a745',
                '#f39c12',
                '#9b59b6'
            ],
            borderRadius: 12,
            borderSkipped: false,
            maxBarThickness: 42
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label(context) {
                        return `Products: ${context.parsed.x || 0}`;
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(23, 32, 51, 0.06)'
                },
                ticks: {
                    precision: 0
                }
            },
            y: {
                grid: {
                    display: false
                }
            }
        }
    }
});
}
</script>





<!-- Quick Actions Row -->


<?php
$content = ob_get_clean();
include __DIR__ . '/../app/views/layout.php';
?>


