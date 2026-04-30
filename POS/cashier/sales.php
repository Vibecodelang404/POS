<?php
require_once __DIR__ . '/../app/config.php';
User::requireLogin();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . BASE_URL . 'public/dashboard.php');
    } elseif ($_SESSION['role'] === 'staff') {
        header('Location: ' . BASE_URL . 'staff/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'public/login.php');
    }
    exit();
}

$page_title = 'Sales Summary';
$db = Database::getInstance()->getConnection();
$cashierId = (int) ($_SESSION['user_id'] ?? 0);
$cashierName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
$cashierName = $cashierName !== '' ? $cashierName : ($_SESSION['username'] ?? 'Cashier');

$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-d');
$startDate = $_GET['start_date'] ?? $defaultFrom;
$endDate = $_GET['end_date'] ?? $defaultTo;
$filterError = '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = $defaultFrom;
    $filterError = 'Invalid start date detected. The report used the default range instead.';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $defaultTo;
    $filterError = 'Invalid end date detected. The report used the default range instead.';
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$summaryStmt = $db->prepare("
    SELECT
        COUNT(*) AS total_transactions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_transactions,
        SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) AS total_sales,
        AVG(CASE WHEN status = 'completed' THEN total_amount END) AS average_sale,
        SUM(CASE WHEN status = 'completed' THEN COALESCE(subtotal, total_amount) ELSE 0 END) AS subtotal_sales,
        SUM(CASE WHEN status = 'completed' THEN COALESCE(discount_amount, 0) ELSE 0 END) AS total_discount,
        SUM(CASE WHEN status = 'completed' THEN COALESCE(tax_amount, 0) ELSE 0 END) AS total_tax,
        SUM(CASE WHEN status = 'completed' THEN COALESCE(amount_received, total_amount) ELSE 0 END) AS total_received,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) AS completion_ratio
    FROM orders
    WHERE user_id = ?
      AND DATE(created_at) BETWEEN ? AND ?
");
$summaryStmt->execute([$cashierId, $startDate, $endDate]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$itemsStmt = $db->prepare("
    SELECT
        COALESCE(SUM(oi.quantity), 0) AS total_items_sold,
        COUNT(DISTINCT oi.product_id) AS unique_products
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE o.user_id = ?
      AND o.status = 'completed'
      AND DATE(o.created_at) BETWEEN ? AND ?
");
$itemsStmt->execute([$cashierId, $startDate, $endDate]);
$itemSummary = $itemsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$paymentStmt = $db->prepare("
    SELECT
        COALESCE(payment_method, 'cash') AS payment_method,
        COUNT(*) AS order_count,
        SUM(total_amount) AS total_amount
    FROM orders
    WHERE user_id = ?
      AND status = 'completed'
      AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total_amount DESC, payment_method ASC
");
$paymentStmt->execute([$cashierId, $startDate, $endDate]);
$paymentBreakdown = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

$dailyStmt = $db->prepare("
    SELECT
        DATE(created_at) AS sale_date,
        COUNT(*) AS total_transactions,
        SUM(total_amount) AS total_sales,
        SUM(COALESCE(subtotal, total_amount)) AS subtotal_sales,
        SUM(COALESCE(discount_amount, 0)) AS total_discount,
        SUM(COALESCE(tax_amount, 0)) AS total_tax
    FROM orders
    WHERE user_id = ?
      AND status = 'completed'
      AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY sale_date DESC
");
$dailyStmt->execute([$cashierId, $startDate, $endDate]);
$dailySales = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

$topProductsStmt = $db->prepare("
    SELECT
        p.name,
        SUM(oi.quantity) AS total_quantity,
        SUM(oi.total_price) AS total_revenue,
        COUNT(DISTINCT oi.order_id) AS transaction_count
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    INNER JOIN products p ON p.id = oi.product_id
    WHERE o.user_id = ?
      AND o.status = 'completed'
      AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id, p.name
    ORDER BY total_quantity DESC, total_revenue DESC, p.name ASC
    LIMIT 10
");
$topProductsStmt->execute([$cashierId, $startDate, $endDate]);
$topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

$transactionsStmt = $db->prepare("
    SELECT
        o.id,
        o.order_number,
        o.created_at,
        o.status,
        o.total_amount,
        o.payment_method,
        o.amount_received,
        COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = ?
      AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY o.id, o.order_number, o.created_at, o.status, o.total_amount, o.payment_method, o.amount_received
    ORDER BY o.created_at DESC
    LIMIT 25
");
$transactionsStmt->execute([$cashierId, $startDate, $endDate]);
$transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);

$bestDay = null;
if (!empty($dailySales)) {
    $bestDay = $dailySales[0];
    foreach ($dailySales as $day) {
        if ((float) $day['total_sales'] > (float) $bestDay['total_sales']) {
            $bestDay = $day;
        }
    }
}

$totalTransactions = (int) ($summary['total_transactions'] ?? 0);
$completedTransactions = (int) ($summary['completed_transactions'] ?? 0);
$totalSales = (float) ($summary['total_sales'] ?? 0);
$averageSale = (float) ($summary['average_sale'] ?? 0);
$subtotalSales = (float) ($summary['subtotal_sales'] ?? 0);
$totalDiscount = (float) ($summary['total_discount'] ?? 0);
$totalTax = (float) ($summary['total_tax'] ?? 0);
$totalReceived = (float) ($summary['total_received'] ?? 0);
$totalItemsSold = (int) ($itemSummary['total_items_sold'] ?? 0);
$uniqueProducts = (int) ($itemSummary['unique_products'] ?? 0);
$dateSpanDays = max(1, (int) floor((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
$averagePerDay = $dateSpanDays > 0 ? $totalSales / $dateSpanDays : 0;
$completionRate = $totalTransactions > 0 ? ($completedTransactions / $totalTransactions) * 100 : 0;
$changeGiven = $totalReceived - $totalSales;
$activeSalesDays = count($dailySales);

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

$csvNumber = static function ($value, $decimals = 2) {
    return number_format((float) $value, $decimals, '.', '');
};

$exportType = strtolower((string) ($_GET['export'] ?? ''));

if (in_array($exportType, ['csv', 'xls'], true)) {
    $filenameBase = sprintf(
        'cashier-sales-summary-%s-to-%s',
        preg_replace('/[^0-9-]/', '', $startDate),
        preg_replace('/[^0-9-]/', '', $endDate)
    );

    if ($exportType === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, ['Cashier Sales Summary']);
        fputcsv($output, ['Cashier', $cashierName]);
        fputcsv($output, ['Period', $startDate . ' to ' . $endDate]);
        fputcsv($output, ['Generated At', date('Y-m-d H:i:s')]);
        fputcsv($output, []);

        fputcsv($output, ['Summary']);
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Transactions', $totalTransactions]);
        fputcsv($output, ['Completed Transactions', $completedTransactions]);
        fputcsv($output, ['Total Sales', $csvNumber($totalSales)]);
        fputcsv($output, ['Average Sale', $csvNumber($averageSale)]);
        fputcsv($output, ['Subtotal Sales', $csvNumber($subtotalSales)]);
        fputcsv($output, ['Total Discount', $csvNumber($totalDiscount)]);
        fputcsv($output, ['Total Tax', $csvNumber($totalTax)]);
        fputcsv($output, ['Total Received', $csvNumber($totalReceived)]);
        fputcsv($output, ['Change Given', $csvNumber(max(0, $changeGiven))]);
        fputcsv($output, ['Items Sold', $totalItemsSold]);
        fputcsv($output, ['Unique Products', $uniqueProducts]);
        fputcsv($output, ['Average Per Day', $csvNumber($averagePerDay)]);
        fputcsv($output, ['Completion Rate %', $csvNumber($completionRate, 1)]);
        fputcsv($output, ['Active Sales Days', $activeSalesDays]);
        fputcsv($output, []);

        fputcsv($output, ['Daily Sales Breakdown']);
        fputcsv($output, ['Date', 'Transactions', 'Subtotal', 'Discount', 'Tax', 'Total Sales']);
        if (empty($dailySales)) {
            fputcsv($output, ['No completed sales found for the selected date range.']);
        } else {
            foreach ($dailySales as $day) {
                fputcsv($output, [
                    $day['sale_date'],
                    (int) $day['total_transactions'],
                    $csvNumber($day['subtotal_sales']),
                    $csvNumber($day['total_discount']),
                    $csvNumber($day['total_tax']),
                    $csvNumber($day['total_sales']),
                ]);
            }
        }
        fputcsv($output, []);

        fputcsv($output, ['Payment Breakdown']);
        fputcsv($output, ['Payment Method', 'Completed Transactions', 'Total Amount', 'Share %']);
        if (empty($paymentBreakdown)) {
            fputcsv($output, ['No completed payments found in this range.']);
        } else {
            foreach ($paymentBreakdown as $payment) {
                $paymentTotal = (float) ($payment['total_amount'] ?? 0);
                fputcsv($output, [
                    $formatPaymentMethod($payment['payment_method']),
                    (int) $payment['order_count'],
                    $csvNumber($paymentTotal),
                    $csvNumber($totalSales > 0 ? ($paymentTotal / $totalSales) * 100 : 0, 1),
                ]);
            }
        }
        fputcsv($output, []);

        fputcsv($output, ['Top Selling Products']);
        fputcsv($output, ['Rank', 'Product', 'Transactions', 'Qty Sold', 'Revenue']);
        if (empty($topProducts)) {
            fputcsv($output, ['No product sales recorded for this range.']);
        } else {
            foreach ($topProducts as $index => $product) {
                fputcsv($output, [
                    $index + 1,
                    $product['name'],
                    (int) $product['transaction_count'],
                    (int) $product['total_quantity'],
                    $csvNumber($product['total_revenue']),
                ]);
            }
        }
        fputcsv($output, []);

        fputcsv($output, ['Latest Transactions In Range']);
        fputcsv($output, ['Transaction #', 'Date & Time', 'Items', 'Payment', 'Status', 'Total']);
        if (empty($transactions)) {
            fputcsv($output, ['No transactions matched the selected date range.']);
        } else {
            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction['order_number'] ?: ('ORD-' . $transaction['id']),
                    date('Y-m-d H:i:s', strtotime($transaction['created_at'])),
                    (int) $transaction['item_count'],
                    $formatPaymentMethod($transaction['payment_method']),
                    ucfirst(strtolower((string) ($transaction['status'] ?? 'unknown'))),
                    $csvNumber($transaction['total_amount']),
                ]);
            }
        }

        fclose($output);
        exit();
    }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xls"');

    $excelEscape = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cashier Sales Summary</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
        th { background: #e2e8f0; font-weight: bold; }
        h2, h3 { margin-bottom: 8px; }
        .meta td { border: none; padding: 4px 0; }
    </style>
</head>
<body>
    <h2>Cashier Sales Summary</h2>
    <table class="meta">
        <tr><td><strong>Cashier</strong></td><td><?php echo $excelEscape($cashierName); ?></td></tr>
        <tr><td><strong>Period</strong></td><td><?php echo $excelEscape($startDate . ' to ' . $endDate); ?></td></tr>
        <tr><td><strong>Generated At</strong></td><td><?php echo $excelEscape(date('Y-m-d H:i:s')); ?></td></tr>
    </table>

    <h3>Summary</h3>
    <table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Total Transactions</td><td><?php echo $excelEscape($totalTransactions); ?></td></tr>
        <tr><td>Completed Transactions</td><td><?php echo $excelEscape($completedTransactions); ?></td></tr>
        <tr><td>Total Sales</td><td><?php echo $excelEscape($csvNumber($totalSales)); ?></td></tr>
        <tr><td>Average Sale</td><td><?php echo $excelEscape($csvNumber($averageSale)); ?></td></tr>
        <tr><td>Subtotal Sales</td><td><?php echo $excelEscape($csvNumber($subtotalSales)); ?></td></tr>
        <tr><td>Total Discount</td><td><?php echo $excelEscape($csvNumber($totalDiscount)); ?></td></tr>
        <tr><td>Total Tax</td><td><?php echo $excelEscape($csvNumber($totalTax)); ?></td></tr>
        <tr><td>Total Received</td><td><?php echo $excelEscape($csvNumber($totalReceived)); ?></td></tr>
        <tr><td>Change Given</td><td><?php echo $excelEscape($csvNumber(max(0, $changeGiven))); ?></td></tr>
        <tr><td>Items Sold</td><td><?php echo $excelEscape($totalItemsSold); ?></td></tr>
        <tr><td>Unique Products</td><td><?php echo $excelEscape($uniqueProducts); ?></td></tr>
        <tr><td>Average Per Day</td><td><?php echo $excelEscape($csvNumber($averagePerDay)); ?></td></tr>
        <tr><td>Completion Rate %</td><td><?php echo $excelEscape($csvNumber($completionRate, 1)); ?></td></tr>
        <tr><td>Active Sales Days</td><td><?php echo $excelEscape($activeSalesDays); ?></td></tr>
    </table>

    <h3>Daily Sales Breakdown</h3>
    <table>
        <tr><th>Date</th><th>Transactions</th><th>Subtotal</th><th>Discount</th><th>Tax</th><th>Total Sales</th></tr>
        <?php if (empty($dailySales)): ?>
            <tr><td colspan="6">No completed sales found for the selected date range.</td></tr>
        <?php else: ?>
            <?php foreach ($dailySales as $day): ?>
                <tr>
                    <td><?php echo $excelEscape($day['sale_date']); ?></td>
                    <td><?php echo $excelEscape((int) $day['total_transactions']); ?></td>
                    <td><?php echo $excelEscape($csvNumber($day['subtotal_sales'])); ?></td>
                    <td><?php echo $excelEscape($csvNumber($day['total_discount'])); ?></td>
                    <td><?php echo $excelEscape($csvNumber($day['total_tax'])); ?></td>
                    <td><?php echo $excelEscape($csvNumber($day['total_sales'])); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <h3>Payment Breakdown</h3>
    <table>
        <tr><th>Payment Method</th><th>Completed Transactions</th><th>Total Amount</th><th>Share %</th></tr>
        <?php if (empty($paymentBreakdown)): ?>
            <tr><td colspan="4">No completed payments found in this range.</td></tr>
        <?php else: ?>
            <?php foreach ($paymentBreakdown as $payment): ?>
                <?php $paymentTotal = (float) ($payment['total_amount'] ?? 0); ?>
                <tr>
                    <td><?php echo $excelEscape($formatPaymentMethod($payment['payment_method'])); ?></td>
                    <td><?php echo $excelEscape((int) $payment['order_count']); ?></td>
                    <td><?php echo $excelEscape($csvNumber($paymentTotal)); ?></td>
                    <td><?php echo $excelEscape($csvNumber($totalSales > 0 ? ($paymentTotal / $totalSales) * 100 : 0, 1)); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <h3>Top Selling Products</h3>
    <table>
        <tr><th>Rank</th><th>Product</th><th>Transactions</th><th>Qty Sold</th><th>Revenue</th></tr>
        <?php if (empty($topProducts)): ?>
            <tr><td colspan="5">No product sales recorded for this range.</td></tr>
        <?php else: ?>
            <?php foreach ($topProducts as $index => $product): ?>
                <tr>
                    <td><?php echo $excelEscape($index + 1); ?></td>
                    <td><?php echo $excelEscape($product['name']); ?></td>
                    <td><?php echo $excelEscape((int) $product['transaction_count']); ?></td>
                    <td><?php echo $excelEscape((int) $product['total_quantity']); ?></td>
                    <td><?php echo $excelEscape($csvNumber($product['total_revenue'])); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <h3>Latest Transactions In Range</h3>
    <table>
        <tr><th>Transaction #</th><th>Date & Time</th><th>Items</th><th>Payment</th><th>Status</th><th>Total</th></tr>
        <?php if (empty($transactions)): ?>
            <tr><td colspan="6">No transactions matched the selected date range.</td></tr>
        <?php else: ?>
            <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td><?php echo $excelEscape($transaction['order_number'] ?: ('ORD-' . $transaction['id'])); ?></td>
                    <td><?php echo $excelEscape(date('Y-m-d H:i:s', strtotime($transaction['created_at']))); ?></td>
                    <td><?php echo $excelEscape((int) $transaction['item_count']); ?></td>
                    <td><?php echo $excelEscape($formatPaymentMethod($transaction['payment_method'])); ?></td>
                    <td><?php echo $excelEscape(ucfirst(strtolower((string) ($transaction['status'] ?? 'unknown')))); ?></td>
                    <td><?php echo $excelEscape($csvNumber($transaction['total_amount'])); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</body>
</html>
<?php
    exit();
}

ob_start();
?>

<style>
@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    body {
        background: #fff !important;
        font-size: 12px;
    }
    .card,
    .content-card {
        box-shadow: none !important;
        border: 1px solid #d1d5db !important;
        background: #fff !important;
    }
    .panel-hero {
        color: #111827 !important;
        background: #fff !important;
        border: 1px solid #d1d5db !important;
    }
    .stats-icon,
    .hero-chip {
        display: none !important;
    }
    @page {
        margin: 1cm;
    }
}
.print-only { display: none; }
.report-filter .form-control,
.report-filter .btn {
    min-height: 48px;
}
.summary-note {
    color: var(--muted);
    font-size: 0.95rem;
}
</style>

<div class="container py-4">
    <div class="panel-hero mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="hero-kicker">Cashier Sales Summary</div>
                <h2 class="hero-title"><?php echo htmlspecialchars($cashierName); ?>'s Report</h2>
                <p class="hero-subtitle">
                    Review your sales performance from <?php echo date('F j, Y', strtotime($startDate)); ?> to <?php echo date('F j, Y', strtotime($endDate)); ?>,
                    including completed sales, items sold, payment mix, daily results, and the latest transactions in that range.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <span class="hero-chip"><i class="fas fa-calendar-range"></i><?php echo $dateSpanDays; ?> day range</span>
            </div>
        </div>
    </div>

    <?php if ($filterError): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <i class="fas fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($filterError); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4 no-print">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-filter me-2"></i>Select Date Range</span>
            <a href="sales.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 report-filter">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-dark flex-fill">
                            <i class="fas fa-search me-2"></i>Generate Summary
                        </button>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&export=csv">
                                        <i class="fas fa-file-csv me-2 text-success"></i>Export CSV
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>&export=xls">
                                        <i class="fas fa-file-excel me-2 text-success"></i>Export Excel
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="print-only mb-4">
        <div class="text-center">
            <h3 class="mb-1">KAKAI'S POS</h3>
            <h5 class="mb-2">Cashier Sales Summary</h5>
            <div>Cashier: <?php echo htmlspecialchars($cashierName); ?></div>
            <div>Period: <?php echo date('F j, Y', strtotime($startDate)); ?> to <?php echo date('F j, Y', strtotime($endDate)); ?></div>
            <div>Generated: <?php echo date('F j, Y g:i A'); ?></div>
        </div>
        <hr>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Completed Sales</div>
                            <h3 class="mt-2 mb-1 text-success"><?php echo formatCurrency($totalSales); ?></h3>
                            <div class="summary-note"><?php echo $completedTransactions; ?> completed transactions</div>
                        </div>
                        <div class="stats-icon" style="background:linear-gradient(135deg,#15803d,#16a34a);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="summary-note">Average per day: <?php echo formatCurrency($averagePerDay); ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Transactions</div>
                            <h3 class="mt-2 mb-1 text-primary"><?php echo number_format($totalTransactions); ?></h3>
                            <div class="summary-note"><?php echo number_format($completionRate, 1); ?>% completion rate</div>
                        </div>
                        <div class="stats-icon" style="background:linear-gradient(135deg,#1d4ed8,#2563eb);">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                    <div class="summary-note"><?php echo $activeSalesDays; ?> days with completed sales</div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Average Sale</div>
                            <h3 class="mt-2 mb-1 text-brand"><?php echo formatCurrency($averageSale); ?></h3>
                            <div class="summary-note"><?php echo number_format($totalItemsSold); ?> items sold</div>
                        </div>
                        <div class="stats-icon" style="background:linear-gradient(135deg,var(--accent),var(--accent-deep));">
                            <i class="fas fa-chart-column"></i>
                        </div>
                    </div>
                    <div class="summary-note"><?php echo number_format($uniqueProducts); ?> unique products sold</div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Tax And Discount</div>
                            <h3 class="mt-2 mb-1 text-dark"><?php echo formatCurrency($totalTax); ?></h3>
                            <div class="summary-note">Discounts: <?php echo formatCurrency($totalDiscount); ?></div>
                        </div>
                        <div class="stats-icon" style="background:linear-gradient(135deg,#7c3aed,#9333ea);">
                            <i class="fas fa-percent"></i>
                        </div>
                    </div>
                    <div class="summary-note">Subtotal before tax: <?php echo formatCurrency($subtotalSales); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-calendar-day me-2"></i>Daily Sales Breakdown</span>
                    <span class="badge text-bg-light border"><?php echo count($dailySales); ?> active days</span>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Transactions</th>
                                    <th>Subtotal</th>
                                    <th>Discount</th>
                                    <th>Tax</th>
                                    <th>Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dailySales)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fas fa-chart-line fa-2x mb-3 d-block opacity-50"></i>
                                            No completed sales found for the selected date range.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($dailySales as $day): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo date('M j, Y', strtotime($day['sale_date'])); ?></div>
                                                <small class="text-muted"><?php echo date('l', strtotime($day['sale_date'])); ?></small>
                                            </td>
                                            <td><span class="badge text-bg-info"><?php echo (int) $day['total_transactions']; ?></span></td>
                                            <td><?php echo formatCurrency((float) $day['subtotal_sales']); ?></td>
                                            <td><?php echo formatCurrency((float) $day['total_discount']); ?></td>
                                            <td><?php echo formatCurrency((float) $day['total_tax']); ?></td>
                                            <td class="fw-bold text-success"><?php echo formatCurrency((float) $day['total_sales']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-circle-info me-2"></i>Range Highlights
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <div class="text-uppercase text-muted small fw-bold mb-2" style="letter-spacing:.12em;">Best Sales Day</div>
                        <?php if ($bestDay): ?>
                            <div class="fw-semibold fs-5"><?php echo date('F j, Y', strtotime($bestDay['sale_date'])); ?></div>
                            <div class="text-success fw-bold"><?php echo formatCurrency((float) $bestDay['total_sales']); ?></div>
                            <div class="summary-note"><?php echo (int) $bestDay['total_transactions']; ?> completed transactions</div>
                        <?php else: ?>
                            <div class="text-muted">No completed sales in this range yet.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <div class="text-uppercase text-muted small fw-bold mb-2" style="letter-spacing:.12em;">Cash Flow</div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Amount received</span>
                            <strong><?php echo formatCurrency($totalReceived); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Change given</span>
                            <strong><?php echo formatCurrency(max(0, $changeGiven)); ?></strong>
                        </div>
                    </div>

                    <div>
                        <div class="text-uppercase text-muted small fw-bold mb-2" style="letter-spacing:.12em;">Coverage</div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Date span</span>
                            <strong><?php echo $dateSpanDays; ?> days</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Completed transaction share</span>
                            <strong><?php echo number_format($completionRate, 1); ?>%</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Average items per sale</span>
                            <strong><?php echo $completedTransactions > 0 ? number_format($totalItemsSold / $completedTransactions, 1) : '0.0'; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-wallet me-2"></i>Payment Breakdown</span>
                    <span class="badge text-bg-light border"><?php echo count($paymentBreakdown); ?> methods</span>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($paymentBreakdown)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-wallet fa-2x mb-3 d-block opacity-50"></i>
                            No completed payments found in this range.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($paymentBreakdown as $payment): ?>
                                <div class="list-group-item px-0 py-3 bg-transparent d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($formatPaymentMethod($payment['payment_method'])); ?></div>
                                        <small class="text-muted"><?php echo (int) $payment['order_count']; ?> completed transactions</small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success"><?php echo formatCurrency((float) $payment['total_amount']); ?></div>
                                        <small class="text-muted">
                                            <?php echo $totalSales > 0 ? number_format((((float) $payment['total_amount']) / $totalSales) * 100, 1) : '0.0'; ?>% of sales
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-trophy me-2"></i>Top Selling Products</span>
                    <span class="badge text-bg-light border">Top 10 in range</span>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Transactions</th>
                                    <th>Qty Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topProducts)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5">
                                            <i class="fas fa-box-open fa-2x mb-3 d-block opacity-50"></i>
                                            No product sales recorded for this range.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topProducts as $index => $product): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                <small class="text-muted">Rank #<?php echo $index + 1; ?></small>
                                            </td>
                                            <td><span class="badge text-bg-light border"><?php echo (int) $product['transaction_count']; ?></span></td>
                                            <td><span class="badge text-bg-info"><?php echo (int) $product['total_quantity']; ?></span></td>
                                            <td class="fw-bold text-success"><?php echo formatCurrency((float) $product['total_revenue']); ?></td>
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

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-receipt me-2"></i>Latest Transactions In Range</span>
            <span class="badge text-bg-light border"><?php echo count($transactions); ?> shown</span>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Transaction #</th>
                            <th>Date & Time</th>
                            <th>Items</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block opacity-50"></i>
                                    No transactions matched the selected date range.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <?php
                                $status = strtolower((string) $transaction['status']);
                                $badgeClass = $status === 'completed' ? 'success' : ($status === 'pending' ? 'warning text-dark' : 'secondary');
                                ?>
                                <tr>
                                    <td class="fw-semibold text-brand"><?php echo htmlspecialchars($transaction['order_number'] ?: ('ORD-' . $transaction['id'])); ?></td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></small>
                                    </td>
                                    <td><span class="badge text-bg-light border"><?php echo (int) $transaction['item_count']; ?> items</span></td>
                                    <td><?php echo htmlspecialchars($formatPaymentMethod($transaction['payment_method'])); ?></td>
                                    <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($status ?: 'unknown')); ?></span></td>
                                    <td class="fw-bold text-success"><?php echo formatCurrency((float) $transaction['total_amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>
