<?php
require_once __DIR__ . '/../app/config.php';
requireLogin();

$page_title = 'Sales Analysis';

$db = Database::getInstance()->getConnection();
$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-d');

$startDate = $_GET['start_date'] ?? $defaultFrom;
$endDate = $_GET['end_date'] ?? $defaultTo;
$selectedCashier = trim((string) ($_GET['cashier'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = $defaultFrom;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $defaultTo;
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$dateSpanDays = max(1, (int) floor((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);

$cashierListStmt = $db->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.username
    FROM users u
    INNER JOIN orders o ON o.user_id = u.id
    ORDER BY u.first_name ASC, u.last_name ASC, u.username ASC
");
$cashierListStmt->execute();
$cashiers = $cashierListStmt->fetchAll(PDO::FETCH_ASSOC);

$cashierFilterSql = '';
$cashierParams = [];
if ($selectedCashier !== '' && ctype_digit($selectedCashier)) {
    $cashierFilterSql = ' AND o.user_id = ?';
    $cashierParams[] = (int) $selectedCashier;
} else {
    $selectedCashier = '';
}

$orderNetSalesExpr = "COALESCE(NULLIF(o.subtotal, 0), o.total_amount - COALESCE(o.tax_amount, 0), o.total_amount)";
$lineNetSalesExpr = "CASE WHEN ot.order_item_total > 0 THEN (oi.total_price / ot.order_item_total) * {$orderNetSalesExpr} ELSE 0 END";
$lineCostExpr = "oi.quantity * COALESCE(oi.cost_price_at_sale, p.cost_price, 0)";

$summarySql = "
    SELECT
        COUNT(*) AS total_transactions,
        COALESCE(SUM(o.total_amount), 0) AS total_sales,
        COALESCE(SUM({$orderNetSalesExpr}), 0) AS subtotal_sales,
        COALESCE(SUM(COALESCE(o.discount_amount, 0)), 0) AS total_discount,
        COALESCE(SUM(COALESCE(o.tax_amount, 0)), 0) AS total_tax,
        AVG(o.total_amount) AS average_sale
    FROM orders o
    WHERE o.status = 'completed'
      AND DATE(o.created_at) BETWEEN ? AND ?
      {$cashierFilterSql}
";
$summaryStmt = $db->prepare($summarySql);
$summaryStmt->execute(array_merge([$startDate, $endDate], $cashierParams));
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$revenueSql = "
    SELECT
        COALESCE(SUM(line_financials.net_sales), 0) AS product_sales,
        COALESCE(SUM(line_financials.product_cost), 0) AS product_cost,
        COALESCE(SUM(line_financials.net_sales - line_financials.product_cost), 0) AS net_revenue
    FROM (
        SELECT
            {$lineNetSalesExpr} AS net_sales,
            {$lineCostExpr} AS product_cost
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN products p ON p.id = oi.product_id
        INNER JOIN (
            SELECT order_id, SUM(total_price) AS order_item_total
            FROM order_items
            GROUP BY order_id
        ) ot ON ot.order_id = o.id
        WHERE o.status = 'completed'
          AND DATE(o.created_at) BETWEEN ? AND ?
          {$cashierFilterSql}
    ) line_financials
";
$revenueStmt = $db->prepare($revenueSql);
$revenueStmt->execute(array_merge([$startDate, $endDate], $cashierParams));
$revenueSummary = $revenueStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$paymentSql = "
    SELECT
        COALESCE(NULLIF(o.payment_method, ''), 'cash') AS payment_method,
        COUNT(*) AS transaction_count,
        SUM(o.total_amount) AS total_sales
    FROM orders o
    WHERE o.status = 'completed'
      AND DATE(o.created_at) BETWEEN ? AND ?
      {$cashierFilterSql}
    GROUP BY COALESCE(NULLIF(o.payment_method, ''), 'cash')
    ORDER BY total_sales DESC
";
$paymentStmt = $db->prepare($paymentSql);
$paymentStmt->execute(array_merge([$startDate, $endDate], $cashierParams));
$paymentBreakdown = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

$topProductsSql = "
    SELECT
        p.name,
        COALESCE(c.name, 'Uncategorized') AS category_name,
        SUM(oi.quantity) AS total_quantity,
        SUM({$lineNetSalesExpr}) AS total_sales,
        SUM({$lineCostExpr}) AS total_cost,
        SUM({$lineNetSalesExpr} - {$lineCostExpr}) AS net_revenue
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    INNER JOIN products p ON p.id = oi.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    INNER JOIN (
        SELECT order_id, SUM(total_price) AS order_item_total
        FROM order_items
        GROUP BY order_id
    ) ot ON ot.order_id = o.id
    WHERE o.status = 'completed'
      AND DATE(o.created_at) BETWEEN ? AND ?
      {$cashierFilterSql}
    GROUP BY p.id, p.name, c.name
    ORDER BY total_quantity DESC, total_sales DESC, p.name ASC
    LIMIT 8
";
$topProductsStmt = $db->prepare($topProductsSql);
$topProductsStmt->execute(array_merge([$startDate, $endDate], $cashierParams));
$topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

$dailySalesSql = "
    SELECT
        order_days.sale_date,
        order_days.total_transactions,
        order_days.total_sales,
        COALESCE(item_days.product_cost, 0) AS product_cost,
        COALESCE(item_days.net_revenue, 0) AS net_revenue
    FROM (
        SELECT
            DATE(o.created_at) AS sale_date,
            COUNT(*) AS total_transactions,
            SUM(o.total_amount) AS total_sales
        FROM orders o
        WHERE o.status = 'completed'
          AND DATE(o.created_at) BETWEEN ? AND ?
          {$cashierFilterSql}
        GROUP BY DATE(o.created_at)
    ) order_days
    LEFT JOIN (
        SELECT
            DATE(o.created_at) AS sale_date,
            SUM({$lineCostExpr}) AS product_cost,
            SUM({$lineNetSalesExpr} - {$lineCostExpr}) AS net_revenue
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN products p ON p.id = oi.product_id
        INNER JOIN (
            SELECT order_id, SUM(total_price) AS order_item_total
            FROM order_items
            GROUP BY order_id
        ) ot ON ot.order_id = o.id
        WHERE o.status = 'completed'
          AND DATE(o.created_at) BETWEEN ? AND ?
          {$cashierFilterSql}
        GROUP BY DATE(o.created_at)
    ) item_days ON item_days.sale_date = order_days.sale_date
    ORDER BY order_days.sale_date DESC
";
$dailySalesStmt = $db->prepare($dailySalesSql);
$dailySalesStmt->execute(array_merge([$startDate, $endDate], $cashierParams, [$startDate, $endDate], $cashierParams));
$dailySales = $dailySalesStmt->fetchAll(PDO::FETCH_ASSOC);

$recentTransactionsSql = "
    SELECT
        o.id,
        o.order_number,
        o.created_at,
        o.total_amount,
        o.payment_method,
        u.first_name,
        u.last_name,
        u.username
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.status = 'completed'
      AND DATE(o.created_at) BETWEEN ? AND ?
      {$cashierFilterSql}
    ORDER BY o.created_at DESC
    LIMIT 10
";
$recentTransactionsStmt = $db->prepare($recentTransactionsSql);
$recentTransactionsStmt->execute(array_merge([$startDate, $endDate], $cashierParams));
$recentTransactions = $recentTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalTransactions = (int) ($summary['total_transactions'] ?? 0);
$totalSales = (float) ($summary['total_sales'] ?? 0);
$subtotalSales = (float) ($summary['subtotal_sales'] ?? 0);
$totalDiscount = (float) ($summary['total_discount'] ?? 0);
$totalTax = (float) ($summary['total_tax'] ?? 0);
$productCost = (float) ($revenueSummary['product_cost'] ?? 0);
$netRevenue = (float) ($revenueSummary['net_revenue'] ?? 0);
$netRevenueMargin = $subtotalSales > 0 ? ($netRevenue / $subtotalSales) * 100 : 0;
$averageSale = (float) ($summary['average_sale'] ?? 0);
$averagePerDay = $dateSpanDays > 0 ? $totalSales / $dateSpanDays : 0;
$bestDay = null;

if (!empty($dailySales)) {
    $bestDay = $dailySales[0];
    foreach ($dailySales as $day) {
        if ((float) $day['total_sales'] > (float) $bestDay['total_sales']) {
            $bestDay = $day;
        }
    }
}

$selectedCashierLabel = 'All cashiers';
if ($selectedCashier !== '') {
    foreach ($cashiers as $cashier) {
        if ((string) $cashier['id'] === $selectedCashier) {
            $fullName = trim(($cashier['first_name'] ?? '') . ' ' . ($cashier['last_name'] ?? ''));
            $selectedCashierLabel = $fullName !== '' ? $fullName : ($cashier['username'] ?? 'Selected cashier');
            break;
        }
    }
}

$formatPaymentMethod = static function ($method) {
    $method = strtolower((string) $method);
    if ($method === 'gcash') {
        return 'GCash';
    }
    if ($method === 'maya') {
        return 'Maya';
    }
    return ucfirst($method ?: 'Cash');
};

ob_start();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
.sales-report-hero {
    background:
        radial-gradient(circle at top right, rgba(255, 255, 255, 0.28), transparent 24%),
        linear-gradient(135deg, #9f7a1c 0%, #c79a2b 45%, #ead28a 100%);
}
.report-chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1rem;
}
.report-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.72rem 0.95rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.42);
    border: 1px solid rgba(17, 24, 39, 0.08);
    color: #172033;
    font-size: 0.88rem;
    font-weight: 700;
}
.report-filter-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}
.report-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}
.report-summary-card {
    padding: 1rem 1.1rem;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(15, 23, 42, 0.06);
}
.report-summary-label {
    color: var(--muted);
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 0.3rem;
}
.report-summary-value {
    font-family: 'Manrope', sans-serif;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--ink);
}
.report-panel-note {
    color: var(--muted);
    font-size: 0.92rem;
}
.empty-state {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--muted);
}
.table td, .table th {
    vertical-align: middle;
}
.revenue-chart-wrap {
    min-height: 320px;
}
@media (max-width: 991px) {
    .report-filter-grid,
    .report-summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="panel-hero sales-report-hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <div class="hero-kicker">Business Insights</div>
            <h2 class="hero-title">Sales Analysis</h2>
            <p class="hero-subtitle">
                Review revenue, product demand, payment behavior, and sales pace to support pricing, stocking, and staffing decisions.
            </p>
            <div class="report-chip-group">
                <span class="report-chip"><i class="fas fa-calendar-range"></i><?php echo date('M d, Y', strtotime($startDate)); ?> to <?php echo date('M d, Y', strtotime($endDate)); ?></span>
                <span class="report-chip"><i class="fas fa-user"></i><?php echo htmlspecialchars($selectedCashierLabel); ?></span>
                <span class="report-chip"><i class="fas fa-clock"></i><?php echo number_format($dateSpanDays); ?> day range</span>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="hero-chip" style="background:rgba(255,255,255,0.52); border-color:rgba(17,24,39,0.12); color:#111827; font-weight:800;">
                <i class="fas fa-receipt"></i> Paid Transactions Only
            </span>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-xl col-md-4 mb-3">
        <div class="stats-card h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Sales</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($totalSales); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #9f7a1c, #c79a2b);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 mb-3">
        <div class="stats-card h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Net Revenue</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($netRevenue); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #198754, #34d399);">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 mb-3">
        <div class="stats-card h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Product Cost</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($productCost); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #0d6efd, #3b82f6);">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 mb-3">
        <div class="stats-card h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Tax</h6>
                    <h3 class="mb-0"><?php echo formatCurrency($totalTax); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #6f42c1, #a855f7);">
                    <i class="fas fa-percent"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 mb-3">
        <div class="stats-card h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Transactions</h6>
                    <h3 class="mb-0"><?php echo number_format($totalTransactions); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #dc3545, #f97316);">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Choose Dates</h5>
        <span class="report-panel-note">Filter the period and cashier scope to compare the performance you want to evaluate.</span>
    </div>
    <div class="card-body">
        <form method="GET" class="report-filter-grid">
            <div>
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
            </div>
            <div>
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
            </div>
            <div>
                <label class="form-label">Cashier</label>
                <select class="form-select" name="cashier">
                    <option value="">All cashiers</option>
                    <?php foreach ($cashiers as $cashier): ?>
                        <?php
                        $cashierName = trim(($cashier['first_name'] ?? '') . ' ' . ($cashier['last_name'] ?? ''));
                        $cashierName = $cashierName !== '' ? $cashierName : ($cashier['username'] ?? 'Cashier');
                        ?>
                        <option value="<?php echo (int) $cashier['id']; ?>" <?php echo $selectedCashier === (string) $cashier['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cashierName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex align-items-end">
                <button type="submit" class="btn btn-danger btn-custom w-100">
                    <i class="fas fa-search me-2"></i>Show Results
                </button>
            </div>
        </form>
        <div class="mt-4 report-summary-grid">
            <div class="report-summary-card">
                <div class="report-summary-label">Best Sales Day</div>
                <div class="report-summary-value">
                    <?php echo $bestDay ? htmlspecialchars(date('M d, Y', strtotime($bestDay['sale_date']))) : 'No sales yet'; ?>
                </div>
                <div class="text-muted small mt-1">
                    <?php echo $bestDay ? formatCurrency((float) $bestDay['total_sales']) . ' from ' . number_format((int) $bestDay['total_transactions']) . ' transactions' : 'Try a different date range.'; ?>
                </div>
            </div>
            <div class="report-summary-card">
                <div class="report-summary-label">Revenue Margin</div>
                <div class="report-summary-value"><?php echo number_format($netRevenueMargin, 1); ?>%</div>
                <div class="text-muted small mt-1">Average sale: <?php echo formatCurrency($averageSale); ?>.</div>
            </div>
            <div class="report-summary-card">
                <div class="report-summary-label">Discounts and Tax</div>
                <div class="report-summary-value"><?php echo formatCurrency($totalDiscount); ?></div>
                <div class="text-muted small mt-1">Discounts: <?php echo formatCurrency($totalDiscount); ?> • Tax: <?php echo formatCurrency($totalTax); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="content-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Sales vs Revenue</h5>
        <span class="report-panel-note">Sales are collected totals. Revenue is VAT-exclusive net sales after product cost.</span>
    </div>
    <div class="card-body">
        <?php if (empty($dailySales)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line fa-3x mb-3 d-block opacity-50"></i>
                <div class="fw-semibold mb-2">No sales data to compare</div>
                <div>Transactions in this range will appear here.</div>
            </div>
        <?php else: ?>
            <div class="revenue-chart-wrap">
                <canvas id="salesRevenueChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-xl-5 mb-4">
        <div class="content-card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Payment Breakdown</h5>
            </div>
            <div class="card-body">
                <?php if (empty($paymentBreakdown)): ?>
                    <div class="empty-state">
                        <i class="fas fa-wallet fa-3x mb-3 d-block opacity-50"></i>
                        <div class="fw-semibold mb-2">No payment results yet</div>
                        <div>Transactions in this date range will appear here.</div>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($paymentBreakdown as $payment): ?>
                            <?php $paymentSales = (float) ($payment['total_sales'] ?? 0); ?>
                            <div class="list-group-item px-0 py-3 bg-transparent d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($formatPaymentMethod($payment['payment_method'])); ?></div>
                                    <small class="text-muted"><?php echo number_format((int) $payment['transaction_count']); ?> transactions</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-success"><?php echo formatCurrency($paymentSales); ?></div>
                                    <small class="text-muted"><?php echo $totalSales > 0 ? number_format(($paymentSales / $totalSales) * 100, 1) : '0.0'; ?>% of sales</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-7 mb-4">
        <div class="content-card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Selling Products</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Qty Sold</th>
                                <th>Net Sales</th>
                                <th>Cost</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topProducts)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fas fa-box-open fa-2x mb-3 d-block opacity-50"></i>
                                        No product sales found for these dates.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($topProducts as $product): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo number_format((int) $product['total_quantity']); ?></span></td>
                                        <td class="fw-semibold"><?php echo formatCurrency((float) $product['total_sales']); ?></td>
                                        <td><?php echo formatCurrency((float) ($product['total_cost'] ?? 0)); ?></td>
                                        <td class="fw-semibold text-success"><?php echo formatCurrency((float) ($product['net_revenue'] ?? 0)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-6 mb-4">
        <div class="content-card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Daily Sales Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transactions</th>
                                <th>Total Sales</th>
                                <th>Cost</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dailySales)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="fas fa-chart-line fa-2x mb-3 d-block opacity-50"></i>
                                        No daily sales found for these dates.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dailySales as $day): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo date('M d, Y', strtotime($day['sale_date'])); ?></div>
                                            <small class="text-muted"><?php echo date('l', strtotime($day['sale_date'])); ?></small>
                                        </td>
                                        <td><span class="badge bg-info text-dark"><?php echo number_format((int) $day['total_transactions']); ?></span></td>
                                        <td class="fw-semibold text-success"><?php echo formatCurrency((float) $day['total_sales']); ?></td>
                                        <td><?php echo formatCurrency((float) ($day['product_cost'] ?? 0)); ?></td>
                                        <td class="fw-semibold text-success"><?php echo formatCurrency((float) ($day['net_revenue'] ?? 0)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 mb-4">
        <div class="content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                <a href="transactions.php" class="btn btn-outline-danger btn-sm">View Transactions</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Transaction #</th>
                                <th>Cashier</th>
                                <th>Payment</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTransactions)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-2x mb-3 d-block opacity-50"></i>
                                        No recent transactions found for these dates.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <?php $cashierName = trim(($transaction['first_name'] ?? '') . ' ' . ($transaction['last_name'] ?? '')); ?>
                                    <tr>
                                        <td class="fw-semibold text-brand"><?php echo htmlspecialchars($transaction['order_number'] ?: ('TXN-' . $transaction['id'])); ?></td>
                                        <td><?php echo htmlspecialchars($cashierName !== '' ? $cashierName : ($transaction['username'] ?? 'Cashier')); ?></td>
                                        <td><?php echo htmlspecialchars($formatPaymentMethod($transaction['payment_method'])); ?></td>
                                        <td class="fw-semibold text-success"><?php echo formatCurrency((float) $transaction['total_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$chartRows = array_reverse($dailySales);
$chartLabels = array_map(static function ($day) {
    return date('M d', strtotime($day['sale_date']));
}, $chartRows);
$chartSales = array_map(static function ($day) {
    return round((float) ($day['total_sales'] ?? 0), 2);
}, $chartRows);
$chartRevenue = array_map(static function ($day) {
    return round((float) ($day['net_revenue'] ?? 0), 2);
}, $chartRows);
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('salesRevenueChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [
                {
                    label: 'Sales',
                    data: <?php echo json_encode($chartSales); ?>,
                    borderColor: '#9f7a1c',
                    backgroundColor: 'rgba(159, 122, 28, 0.12)',
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Revenue',
                    data: <?php echo json_encode($chartRevenue); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.35,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return 'PHP ' + Number(value).toLocaleString();
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../app/views/layout.php';
?>
