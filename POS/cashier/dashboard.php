<?php
$requireLogin = require_once __DIR__ . '/../app/config.php';
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

$title = 'Cashier Dashboard';
$page_title = 'Cashier Dashboard';
$db = Database::getInstance()->getConnection();
$cashierId = (int) ($_SESSION['user_id'] ?? 0);
$today = date('Y-m-d');
$cashierName = htmlspecialchars($_SESSION['first_name'] ?? 'Cashier');

$summaryStmt = $db->prepare("
    SELECT
        COUNT(*) AS total_transactions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_transactions,
        SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) AS total_sales,
        AVG(CASE WHEN status = 'completed' THEN total_amount END) AS average_sale
    FROM orders
    WHERE user_id = ? AND DATE(created_at) = ?
");
$summaryStmt->execute([$cashierId, $today]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$recentTransactionsStmt = $db->prepare("
    SELECT order_number, total_amount, payment_method, status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$recentTransactionsStmt->execute([$cashierId]);
$recentTransactions = $recentTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);

$lastTransactionTime = !empty($recentTransactions)
    ? date('M d, Y g:i A', strtotime($recentTransactions[0]['created_at']))
    : 'No transactions yet';

$topProductsStmt = $db->prepare("
    SELECT p.name, SUM(oi.quantity) AS qty
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE o.user_id = ? AND DATE(o.created_at) = ? AND o.status = 'completed'
    GROUP BY p.id, p.name
    ORDER BY qty DESC, p.name ASC
    LIMIT 5
");
$topProductsStmt->execute([$cashierId, $today]);
$topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalTransactions = (int) ($summary['total_transactions'] ?? 0);
$completedTransactions = (int) ($summary['completed_transactions'] ?? 0);
$salesToday = (float) ($summary['total_sales'] ?? 0);
$averageSale = (float) ($summary['average_sale'] ?? 0);

$content = '<div class="container py-4">
  <div class="panel-hero mb-4" style="background:linear-gradient(135deg, #9f7a1c 0%, #c79a2b 45%, #e1be62 100%); color:#111827;">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <div class="hero-kicker" style="color:#4b5563; font-weight:800;">Cashier Overview</div>
        <h2 class="hero-title" style="color:#111827; text-shadow:0 1px 0 rgba(255,255,255,0.18);">' . $cashierName . '\'s Transactions</h2>
        <p class="hero-subtitle" style="color:#1f2937; font-weight:600;">This dashboard shows only your transactions for ' . date('F d, Y') . ', including sales, completed transactions, and recent activity.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <span class="hero-chip" style="background:rgba(255,255,255,0.5); border-color:rgba(17,24,39,0.12); color:#111827; font-weight:800;"><i class="fas fa-receipt"></i> Personal Transaction Summary</span>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Your Sales Today</div>
              <h3 class="mt-2 mb-0 text-success">' . formatCurrency($salesToday) . '</h3>
            </div>
            <div class="stats-icon" style="background:linear-gradient(135deg,#16a34a,#15803d);">
              <i class="fas fa-coins"></i>
            </div>
          </div>
          <p class="text-muted mb-0">Total value of your completed transactions today.</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Transactions Today</div>
              <h3 class="mt-2 mb-0 text-primary">' . $totalTransactions . '</h3>
            </div>
            <div class="stats-icon" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">
              <i class="fas fa-receipt"></i>
            </div>
          </div>
          <p class="text-muted mb-0">Last transaction: ' . htmlspecialchars($lastTransactionTime) . '.</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Average Sale</div>
              <h3 class="mt-2 mb-0 text-brand">' . formatCurrency($averageSale) . '</h3>
            </div>
            <div class="stats-icon" style="background:linear-gradient(135deg,var(--accent),var(--accent-deep));">
              <i class="fas fa-chart-column"></i>
            </div>
          </div>
          <p class="text-muted mb-0">Average amount per completed transaction today.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Recent Transactions</span>
          <a href="transactions.php" class="btn btn-sm btn-outline-danger">View History</a>
        </div>
        <div class="card-body p-4">
          ' . (count($recentTransactions) ? '
          <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Transaction #</th>
                  <th>Amount</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody>' .
                implode('', array_map(function ($transaction) {
                    $status = strtolower((string) $transaction['status']);
                    $paymentMethod = strtolower((string) ($transaction['payment_method'] ?? 'cash'));
                    $statusClass = $status === 'completed' ? 'success' : ($status === 'pending' ? 'warning text-dark' : 'secondary');
                    $statusLabel = ucfirst($status);
                    $paymentLabel = $paymentMethod === 'gcash' ? 'GCash' : ucfirst($paymentMethod);

                    return '<tr>' .
                        '<td><strong class="text-brand">' . htmlspecialchars($transaction['order_number']) . '</strong></td>' .
                        '<td>' . formatCurrency((float) $transaction['total_amount']) . '</td>' .
                        '<td><span class="badge text-bg-light border">' . htmlspecialchars($paymentLabel) . '</span></td>' .
                        '<td><span class="badge bg-' . $statusClass . '">' . htmlspecialchars($statusLabel) . '</span></td>' .
                        '<td><small class="text-muted">' . date('M d, Y g:i A', strtotime($transaction['created_at'])) . '</small></td>' .
                    '</tr>';
                }, $recentTransactions)) .
              '</tbody>
            </table>
          </div>' : '
          <div class="text-center py-5 text-muted">
            <i class="fas fa-receipt fa-2x mb-3 d-block opacity-50"></i>
            No transactions recorded for this cashier yet.
          </div>') . '
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Top-Selling Items Today</span>
          <span class="badge bg-dark-subtle text-dark border">Your Sales</span>
        </div>
        <div class="card-body p-4">
          <div class="list-group list-group-flush">' .
            (count($topProducts) ? implode('', array_map(function ($product, $index) {
                return '<div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center bg-transparent">' .
                    '<div class="d-flex align-items-center gap-3">' .
                        '<span class="badge rounded-pill text-bg-light border" style="min-width:40px;">#' . ($index + 1) . '</span>' .
                        '<div>' .
                            '<div class="fw-semibold">' . htmlspecialchars($product['name']) . '</div>' .
                            '<small class="text-muted">Units sold in your completed transactions</small>' .
                        '</div>' .
                    '</div>' .
                    '<span class="badge text-bg-info px-3 py-2">' . (int) $product['qty'] . ' sold</span>' .
                '</div>';
            }, $topProducts, array_keys($topProducts))) : '<div class="text-center py-5 text-muted"><i class="fas fa-box-open fa-2x mb-3 d-block opacity-50"></i>No completed sales recorded yet today.</div>') .
          '</div>
        </div>
      </div>
    </div>
  </div>
</div>';

include __DIR__ . '/views/layout.php';
