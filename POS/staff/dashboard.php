<?php
require_once __DIR__ . '/../app/config.php';
User::requireLogin();

// Redirect based on role
if (User::isAdmin()) {
    header('Location: ' . BASE_URL . 'public/dashboard.php');
    exit();
} elseif ($_SESSION['role'] === 'cashier') {
    header('Location: ' . BASE_URL . 'cashier/dashboard.php');
    exit();
}

$dashboardController = new DashboardController();
$stats = $dashboardController->getStats();
$recentOrders = $dashboardController->getRecentOrders(5);

// Additional statistics for inventory dashboard cards
$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

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

// Get sales by payment method for chart
$paymentMethodQuery = $db->query("
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(total_amount) as total
    FROM orders 
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'completed'
    GROUP BY payment_method
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

// Get category-wise inventory distribution
$categoryDistributionQuery = $db->query("
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category,
        COUNT(p.id) as product_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    GROUP BY COALESCE(c.name, 'Uncategorized')
    ORDER BY product_count DESC
    LIMIT 6
");
$categoryDistribution = $categoryDistributionQuery->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$salesDates = array_map(function($item) { return date('M d', strtotime($item['date'])); }, $dailySales);
$salesAmounts = array_map(function($item) { return $item['sales']; }, $dailySales);
$salesOrders = array_map(function($item) { return $item['orders']; }, $dailySales);
$paymentLabels = array_map(function($pm) { return ucfirst($pm['payment_method']); }, $paymentMethods);
$paymentCounts = array_map(function($pm) { return (int) $pm['count']; }, $paymentMethods);
$paymentTotals = array_map(function($pm) { return (float) $pm['total']; }, $paymentMethods);
$categoryLabels = array_map(function($item) { return $item['category']; }, $categoryDistribution);
$categoryCounts = array_map(function($item) { return (int) $item['product_count']; }, $categoryDistribution);

$title = 'Staff Dashboard';

ob_start();
?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
.dashboard-card {
    border-radius: 12px;
    padding: 1.5rem;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s;
    height: 100%;
}
.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.stat-icon-lg {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}
.chart-container {
    position: relative;
    height: 300px;
}
.chart-shell {
    position: relative;
    border-radius: 20px;
    padding: 1rem;
    background:
        radial-gradient(circle at top right, rgba(220, 53, 69, 0.12), transparent 24%),
        linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 0.98));
    border: 1px solid rgba(15, 23, 42, 0.06);
}
.chart-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.chart-summary-card {
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(15, 23, 42, 0.06);
    padding: 0.85rem 1rem;
}
.chart-summary-label {
    color: #667085;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.chart-summary-value {
    font-family: 'Manrope', sans-serif;
    font-size: 1.15rem;
    font-weight: 800;
    color: #172033;
}
.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #212529;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #dc3545;
}
@media (max-width: 991px) {
    .chart-summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<div style="max-height: 95vh; overflow-y: auto;">

<!-- Welcome Section -->
<div class="panel-hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <div class="hero-kicker">Inventory Overview</div>
            <h2 class="hero-title">Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Staff'); ?>.</h2>
            <p class="hero-subtitle">Track stock levels, sales activity, and inventory issues from one working dashboard.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="hero-chip"><i class="fas fa-boxes-stacked"></i> Live Inventory Status</span>
        </div>
    </div>
</div>
<div class="mb-4 d-none">
    <h4 class="mb-1">&nbsp;&nbsp;👋 Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Staff'); ?>!</h4>
    <p class="text-muted mb-0">&nbsp;&nbsp;&nbsp;Here's what's happening with your inventory today.</p>
</div>

<!-- Key Metrics Row -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-card" style="border-left: 4px solid #0d6efd;">
            <div class="d-flex align-items-center">
                <div class="stat-icon-lg bg-primary text-white me-3">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $inventoryStats['total_products'] ?></h3>
                    <p class="text-muted mb-0">Total Products</p>
                    <small class="text-primary"><i class="fas fa-database me-1"></i>Current catalog</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-card" style="border-left: 4px solid #28a745;">
            <div class="d-flex align-items-center">
                <div class="stat-icon-lg bg-success text-white me-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $inventoryStats['in_stock'] ?></h3>
                    <p class="text-muted mb-0">In Stock</p>
                    <small class="text-success"><i class="fas fa-arrow-up me-1"></i>Available for sale</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-card" style="border-left: 4px solid #ffc107;">
            <div class="d-flex align-items-center">
                <div class="stat-icon-lg bg-warning text-white me-3">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $inventoryStats['low_stock'] ?></h3>
                    <p class="text-muted mb-0">Low Stock</p>
                    <small class="text-warning"><i class="fas fa-exclamation me-1"></i>Needs restocking</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-card" style="border-left: 4px solid #dc3545;">
            <div class="d-flex align-items-center">
                <div class="stat-icon-lg bg-danger text-white me-3">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $inventoryStats['out_of_stock'] ?></h3>
                    <p class="text-muted mb-0">Out of Stock</p>
                    <small class="text-danger"><i class="fas fa-ban me-1"></i>Unavailable</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales Overview Row -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-card" style="border-left: 4px solid #667eea;">
            <div class="d-flex align-items-center">
                <div class="stat-icon-lg bg-primary text-white me-3" style="background-color: #667eea !important;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo formatCurrency($todayStats['total_sales']); ?></h3>
                    <p class="text-muted mb-0">Today's Sales</p>
                    <small class="text-primary"><?= $todayStats['order_count'] ?> transactions today</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-card" style="border-left: 4px solid #f093fb;">
            <div class="d-flex align-items-center">
                <div class="stat-icon-lg text-white me-3" style="background-color: #f093fb !important;">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo formatCurrency($weekStats['total_sales']); ?></h3>
                    <p class="text-muted mb-0">This Week</p>
                    <small class="text-info"><?= $weekStats['order_count'] ?> transactions this week</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-card" style="border-left: 4px solid #17a2b8;">
            <div class="d-flex align-items-center">
                <div class="stat-icon-lg bg-info text-white me-3">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $stats['total_orders'] ?></h3>
                    <p class="text-muted mb-0">Total Orders</p>
                    <small class="text-info">All time</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-card" style="border-left: 4px solid #28a745;">
            <div class="d-flex align-items-center">
                <div class="stat-icon-lg bg-success text-white me-3">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?php echo formatCurrency($stats['total_sales']); ?></h3>
                    <p class="text-muted mb-0">Total Revenue</p>
                    <small class="text-success">All time</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Sales Trend Chart -->
    <div class="col-xl-8 mb-3">
        <div class="dashboard-card">
            <h5 class="section-title"><i class="fas fa-chart-line me-2"></i>Sales for the Last 7 Days</h5>
            <div class="chart-summary-grid">
                <div class="chart-summary-card">
                    <div class="chart-summary-label">Peak Day</div>
                    <div class="chart-summary-value"><?php echo !empty($salesAmounts) ? formatCurrency(max($salesAmounts)) : formatCurrency(0); ?></div>
                </div>
                <div class="chart-summary-card">
                    <div class="chart-summary-label">Orders Tracked</div>
                    <div class="chart-summary-value"><?php echo number_format(array_sum($salesOrders)); ?></div>
                </div>
                <div class="chart-summary-card">
                    <div class="chart-summary-label">Daily Average</div>
                    <div class="chart-summary-value"><?php echo formatCurrency(!empty($salesAmounts) ? array_sum($salesAmounts) / count($salesAmounts) : 0); ?></div>
                </div>
            </div>
            <div class="chart-shell">
                <div class="chart-container">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Methods Chart -->
    <div class="col-xl-4 mb-3">
        <div class="dashboard-card">
            <h5 class="section-title"><i class="fas fa-credit-card me-2"></i>Payment Breakdown</h5>
            <div class="chart-shell">
                <div class="chart-container" style="height: 250px;">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="section-title mb-0"><i class="fas fa-layer-group me-2"></i>Category Stock Mix</h5>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?php echo count($categoryDistribution); ?> categories</span>
            </div>
            <div class="chart-shell">
                <div class="chart-container" style="height: 340px;">
                    <canvas id="categoryStockChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Content Row -->
<div class="row mb-4">
    <!-- Recent Transactions -->
    <div class="col-xl-7 mb-3">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="section-title mb-0"><i class="fas fa-receipt me-2"></i>Recent Transactions</h5>
                <a href="transactions.php" class="btn btn-outline-danger btn-sm">View Transactions</a>
            </div>
            <?php if (empty($recentOrders)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No recent transactions to show.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Staff</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><strong class="text-danger"><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                <td><strong><?php echo formatCurrency($order['total_amount']); ?></strong></td>
                                <td>
                                    <?php if (isset($order['payment_method']) && $order['payment_method'] === 'gcash'): ?>
                                        <span class="badge bg-info">GCash</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Cash</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?php echo Layout::getTimeAgo($order['created_at']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="col-xl-5 mb-3">
        <div class="dashboard-card">
            <h5 class="section-title"><i class="fas fa-fire me-2"></i>Top-Selling Products</h5>
            <?php if (empty($topProducts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No sales data available yet.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($topProducts as $index => $product): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">
                                    <?= $index + 1 ?>
                                </div>
                            </div>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                <small class="text-muted"><?= $product['total_sold'] ?> units sold in the last 30 days</small>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-success"><?php echo formatCurrency($product['revenue']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<!-- <div class="row">
    <div class="col-12">
        <div class="dashboard-card">
            <h5 class="section-title"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <a href="manage_product.php" class="btn btn-outline-danger w-100">
                        <i class="fas fa-boxes me-2"></i>Manage Inventory
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="inventory_reports.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-clipboard-list me-2"></i>Inventory Reports
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="transactions.php" class="btn btn-outline-success w-100">
                        <i class="fas fa-receipt me-2"></i>Transactions
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="sales.php" class="btn btn-outline-warning w-100">
                        <i class="fas fa-chart-line me-2"></i>Sales Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div> -->

</div>

<script>
// Sales Trend Chart
const salesCtx = document.getElementById('salesTrendChart');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($salesDates); ?>,
        datasets: [{
            label: 'Sales (₱)',
            data: <?php echo json_encode($salesAmounts); ?>,
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y'
        }, {
            label: 'Orders',
            data: <?php echo json_encode($salesOrders); ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4,
            fill: true,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Sales (₱)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Orders'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Payment Method Chart
const paymentCtx = document.getElementById('paymentMethodChart');
new Chart(paymentCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($pm) { return ucfirst($pm['payment_method']); }, $paymentMethods)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map(function($pm) { return $pm['count']; }, $paymentMethods)); ?>,
            backgroundColor: [
                '#28a745',
                '#17a2b8',
                '#ffc107',
                '#dc3545'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            }
        }
    }
});
</script>
<script>
const staffChartLabels = <?php echo json_encode($salesDates); ?>;
const staffSalesSeries = <?php echo json_encode($salesAmounts); ?>;
const staffOrderSeries = <?php echo json_encode($salesOrders); ?>;
const staffPaymentLabels = <?php echo json_encode($paymentLabels); ?>;
const staffPaymentSeries = <?php echo json_encode($paymentCounts); ?>;
const staffPaymentTotals = <?php echo json_encode($paymentTotals); ?>;
const staffCategoryLabels = <?php echo json_encode($categoryLabels); ?>;
const staffCategorySeries = <?php echo json_encode($categoryCounts); ?>;
const staffCurrency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function createStaffGradient(context, colorStops) {
    const gradient = context.createLinearGradient(0, 0, 0, 320);
    colorStops.forEach(stop => gradient.addColorStop(stop.position, stop.color));
    return gradient;
}

const enhancedSalesCanvas = document.getElementById('salesTrendChart');
if (enhancedSalesCanvas) {
    Chart.getChart(enhancedSalesCanvas)?.destroy();
    const salesGradient = createStaffGradient(enhancedSalesCanvas.getContext('2d'), [
        { position: 0, color: 'rgba(220, 53, 69, 0.28)' },
        { position: 1, color: 'rgba(220, 53, 69, 0.02)' }
    ]);

    new Chart(enhancedSalesCanvas, {
        type: 'line',
        data: {
            labels: staffChartLabels,
            datasets: [{
                label: 'Sales (PHP)',
                data: staffSalesSeries,
                borderColor: '#dc3545',
                backgroundColor: salesGradient,
                borderWidth: 3,
                pointBackgroundColor: '#fff5f5',
                pointBorderColor: '#dc3545',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.38,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Orders',
                data: staffOrderSeries,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.86)',
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
                                return `${context.dataset.label}: ${staffCurrency.format(context.parsed.y || 0)}`;
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
                            return staffCurrency.format(value);
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
                        text: 'Orders'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

const enhancedPaymentCanvas = document.getElementById('paymentMethodChart');
if (enhancedPaymentCanvas) {
    Chart.getChart(enhancedPaymentCanvas)?.destroy();
    new Chart(enhancedPaymentCanvas, {
        type: 'polarArea',
        data: {
            labels: staffPaymentLabels,
            datasets: [{
                data: staffPaymentSeries,
                backgroundColor: [
                    'rgba(40, 167, 69, 0.78)',
                    'rgba(23, 162, 184, 0.78)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(220, 53, 69, 0.76)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
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
                            const count = staffPaymentSeries[index] || 0;
                            const total = staffPaymentTotals[index] || 0;
                            return `${context.label}: ${count} txns, ${staffCurrency.format(total)}`;
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

const categoryStockCtx = document.getElementById('categoryStockChart');
if (categoryStockCtx) {
    new Chart(categoryStockCtx, {
        type: 'bar',
        data: {
            labels: staffCategoryLabels,
            datasets: [{
                label: 'Products',
                data: staffCategorySeries,
                backgroundColor: [
                    '#dc3545',
                    '#0d6efd',
                    '#20c997',
                    '#ffc107',
                    '#6f42c1',
                    '#fd7e14'
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
<?php
$content = ob_get_clean();

// Include staff layout
$title = 'Staff Dashboard';
include __DIR__ . '/views/layout.php';
?>


