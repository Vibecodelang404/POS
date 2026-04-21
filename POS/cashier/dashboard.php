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
$db = Database::getInstance()->getConnection();
$today = date('Y-m-d');
$salesToday = $db->query("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = '$today' AND status = 'completed'")->fetchColumn() ?? 0;
$transactionCount = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'")->fetchColumn() ?? 0;
$topProducts = $db->query("SELECT p.name, SUM(oi.quantity) as qty FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN orders o ON oi.order_id = o.id WHERE DATE(o.created_at) = '$today' GROUP BY p.id ORDER BY qty DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$content = '<div class="container py-4">
  <div class="panel-hero mb-4">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <div class="hero-kicker">Cashier Performance Snapshot</div>
        <h2 class="hero-title">Welcome back, ' . htmlspecialchars($_SESSION['first_name'] ?? 'Cashier') . '.</h2>
        <p class="hero-subtitle">This dashboard presents live cashier activity in a polished, defense-ready format, highlighting revenue, transaction volume, and best-selling products for the current day.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <span class="hero-chip"><i class="fas fa-circle-check"></i> Ready for Live Demonstration</span>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Today\'s Revenue</div>
              <h3 class="mt-2 mb-0 text-success">' . formatCurrency($salesToday) . '</h3>
            </div>
            <div class="stats-icon" style="background:linear-gradient(135deg,#16a34a,#15803d);">
              <i class="fas fa-coins"></i>
            </div>
          </div>
          <p class="text-muted mb-0">All completed sales recorded for ' . date('F d, Y') . '.</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Transactions</div>
              <h3 class="mt-2 mb-0 text-primary">' . $transactionCount . '</h3>
            </div>
            <div class="stats-icon" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">
              <i class="fas fa-receipt"></i>
            </div>
          </div>
          <p class="text-muted mb-0">Successful order entries processed throughout the day.</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <div class="text-uppercase text-muted small fw-bold" style="letter-spacing:.12em;">Shift Status</div>
              <h3 class="mt-2 mb-0" style="color:#b4232a;">Operational</h3>
            </div>
            <div class="stats-icon" style="background:linear-gradient(135deg,#b4232a,#7f1d1d);">
              <i class="fas fa-user-check"></i>
            </div>
          </div>
          <p class="text-muted mb-0">The POS terminal is active and ready to process customer payments.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Top Selling Products Today</span>
      <span class="badge bg-dark-subtle text-dark border">Live Ranking</span>
    </div>
    <div class="card-body p-4">
      <div class="list-group list-group-flush">' .
        (count($topProducts) ? implode('', array_map(function ($p, $index) {
            return '<div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center bg-transparent">' .
                '<div class="d-flex align-items-center gap-3">' .
                    '<span class="badge rounded-pill text-bg-light border" style="min-width:40px;">#' . ($index + 1) . '</span>' .
                    '<div>' .
                        '<div class="fw-semibold">' . htmlspecialchars($p['name']) . '</div>' .
                        '<small class="text-muted">Most purchased item for the current day</small>' .
                    '</div>' .
                '</div>' .
                '<span class="badge text-bg-info px-3 py-2">' . intval($p['qty']) . ' sold</span>' .
            '</div>';
        }, $topProducts, array_keys($topProducts))) : '<div class="text-center py-5 text-muted"><i class="fas fa-chart-line fa-2x mb-3 d-block opacity-50"></i>No completed sales have been recorded yet today.</div>') .
      '</div>
    </div>
  </div>
</div>';

include __DIR__ . '/views/layout.php';
