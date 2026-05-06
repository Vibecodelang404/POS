<?php
require_once __DIR__ . '/../config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #D4AF37;
            --brand-deep: #AA8C2C;
            --brand-soft: #FFF8DC;
            --accent: #C79A2B;
            --accent-deep: #9F7A1C;
            --accent-soft: #F3E4A8;
            --accent-rgb: 170, 140, 44;
            --ink: #172033;
            --muted: #667085;
            --line: rgba(23, 32, 51, 0.08);
            --surface: rgba(255, 255, 255, 0.92);
            --surface-strong: #ffffff;
            --shadow-lg: 0 24px 60px rgba(17, 24, 39, 0.12);
            --shadow-md: 0 12px 30px rgba(17, 24, 39, 0.08);
            --radius-lg: 24px;
            --radius-md: 18px;
            --radius-sm: 14px;
        }
        body {
            min-height: 100vh;
            color: var(--ink);
            background:
                radial-gradient(circle at top right, rgba(212, 175, 55, 0.12), transparent 24%),
                radial-gradient(circle at bottom left, rgba(255, 255, 255, 0.15), transparent 22%),
                linear-gradient(180deg, #fefef8 0%, #f5f5f0 100%);
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            overflow-x: hidden;
        }
        .sidebar {
            background: linear-gradient(180deg, rgba(170, 140, 44, 0.98) 0%, rgba(212, 175, 55, 0.94) 52%, rgba(255, 255, 255, 0.97) 100%);
            backdrop-filter: blur(18px);
            min-height: 100vh;
            width: 274px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s;
            box-shadow: 20px 0 50px rgba(15, 23, 42, 0.18);
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 2rem 1.6rem 1.5rem;
            border-bottom: 1px solid rgba(170, 140, 44, 0.2);
            text-align: center;
        }
        .sidebar-header h4 {
            color: #172033;
            margin: 0;
            font-weight: 800;
            font-family: 'Manrope', sans-serif;
            letter-spacing: -0.03em;
        }
        .sidebar-header small {
            color: rgba(23, 32, 51, 0.7);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .sidebar-menu {
            padding: 1.25rem 0.9rem;
            flex: 1;
        }
        .sidebar-menu .nav-link {
            color: rgba(23, 32, 51, 0.8);
            padding: 0.88rem 1rem;
            border: none;
            border-radius: 16px;
            transition: all 0.3s;
            margin-bottom: 0.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            background: rgba(212, 175, 55, 0.2);
            color: #AA8C2C;
            transform: translateX(4px);
            box-shadow: inset 0 0 0 1px rgba(212, 175, 55, 0.3);
        }
        .sidebar-menu .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 0.95rem;
        }
        .nav-label {
            transition: opacity 0.2s ease;
        }
        .main-content {
            margin-left: 274px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        .top-navbar {
            background: rgba(255,255,255,0.74);
            backdrop-filter: blur(14px);
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
            padding: 1.1rem 1.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.55);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .content-card {
            background: var(--surface);
            backdrop-filter: blur(12px);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255,255,255,0.72);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .content-card .card-header {
            background: linear-gradient(180deg, rgba(255,255,255,0.84), rgba(248,250,252,0.72));
            border-bottom: 1px solid var(--line);
            padding: 1.2rem 1.5rem;
            font-weight: 700;
            font-family: 'Manrope', sans-serif;
        }
        .content-card .card-body {
            padding: 1.5rem;
        }
        .page-context {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }
        .page-overline {
            color: var(--accent-deep);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .page-subtitle {
            color: var(--muted);
            font-size: 0.92rem;
        }
        .topbar-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .topbar-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.68rem 0.9rem;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255,255,255,0.8);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
            color: #344054;
            font-size: 0.88rem;
            font-weight: 700;
        }
        .panel-hero {
            position: relative;
            overflow: hidden;
            padding: 1.85rem;
            border-radius: var(--radius-lg);
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.3), transparent 26%),
                linear-gradient(135deg, rgba(170, 140, 44, 0.98), rgba(212, 175, 55, 0.84));
            color: #172033;
            box-shadow: var(--shadow-lg);
        }
        .panel-hero::after {
            content: "";
            position: absolute;
            inset: auto -80px -120px auto;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(255,255,255,0.14);
            filter: blur(6px);
        }
        .hero-kicker {
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(23, 32, 51, 0.72);
            margin-bottom: 0.5rem;
        }
        .hero-title {
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin: 0;
            font-size: clamp(1.9rem, 4vw, 2.8rem);
        }
        .hero-subtitle {
            color: rgba(23, 32, 51, 0.78);
            max-width: 52rem;
            margin: 0.85rem 0 0;
            font-size: 1rem;
            line-height: 1.6;
        }
        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.8rem 1rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.22);
            border: 1px solid rgba(255,255,255,0.3);
            color: #172033;
            font-weight: 700;
            backdrop-filter: blur(10px);
        }
        .stats-card,
        .metric-card {
            border-radius: 20px;
            padding: 1.35rem;
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.07);
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .stats-card:hover,
        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.1);
        }
        .stats-icon,
        .metric-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            color: #fff;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        }
        .metric-value {
            font-family: 'Manrope', sans-serif;
            font-size: 1.9rem;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #172033;
        }
        .metric-label {
            color: #344054;
            font-weight: 700;
            margin-top: 0.2rem;
        }
        .metric-note {
            color: var(--muted);
            font-size: 0.86rem;
        }
        .action-link-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-radius: 18px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255,255,255,0.7);
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .action-link-card:hover {
            transform: translateY(-2px);
            border-color: rgba(var(--accent-rgb), 0.35);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            color: inherit;
        }
        .action-link-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(var(--accent-rgb), 0.12);
            color: var(--accent-deep);
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .filter-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            justify-content: space-between;
        }
        .filter-summary {
            color: var(--muted);
            font-size: 0.92rem;
        }
        .summary-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }
        .summary-pill {
            border-radius: 18px;
            padding: 1rem 1.1rem;
            background: rgba(255,255,255,0.68);
            border: 1px solid rgba(255,255,255,0.4);
        }
        .summary-pill-label {
            font-size: 0.78rem;
            color: rgba(23, 32, 51, 0.62);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 800;
            margin-bottom: 0.35rem;
        }
        .summary-pill-value {
            font-family: 'Manrope', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -0.03em;
        }
        .data-table {
            min-width: 880px;
        }
        .data-table th {
            white-space: nowrap;
        }
        .data-table td {
            white-space: nowrap;
        }
        .table-actions {
            display: inline-flex;
            gap: 0.45rem;
            flex-wrap: wrap;
        }
        .empty-state {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--muted);
        }
        .form-control,
        .form-select {
            border-radius: 14px;
            border-color: rgba(15, 23, 42, 0.12);
            padding: 0.8rem 0.95rem;
            box-shadow: none;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: rgba(var(--accent-rgb), 0.55);
            box-shadow: 0 0 0 0.25rem rgba(var(--accent-rgb), 0.12);
        }
        .btn-custom {
            border-radius: 14px;
            font-weight: 700;
            padding: 0.8rem 1rem;
        }
        .modal-content {
            border: none;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.2);
        }
        .modal-header,
        .modal-footer {
            border-color: rgba(15, 23, 42, 0.08);
            padding-inline: 1.5rem;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.42);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            z-index: 999;
        }
        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            border-top: none;
            background-color: #f5f7fb;
            font-weight: 700;
            color: #344054;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 0.74rem;
        }
        .table td {
            vertical-align: middle;
        }
        .container-fluid.py-4 {
            padding: 1.75rem !important;
        }
        .dropdown-menu {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
            padding: 0.6rem;
            z-index: 1050 !important;
        }
        .dropdown-item {
            border-radius: 12px;
            padding: 0.65rem 0.9rem;
            font-weight: 600;
        }
        .btn-light {
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
        }
        .btn-light:hover {
            background: #fff;
            border-color: rgba(15, 23, 42, 0.12);
        }
        .badge {
            border-radius: 999px;
            padding: 0.5em 0.8em;
            font-weight: 700;
        }
        .section-heading {
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            letter-spacing: -0.03em;
        }
        .text-muted {
            color: var(--muted) !important;
        }
        .text-brand {
            color: var(--accent-deep) !important;
        }
        .bg-brand,
        .badge-brand {
            background: linear-gradient(135deg, var(--accent), var(--accent-deep)) !important;
            color: #fff !important;
        }
        .btn-danger {
            background: linear-gradient(135deg, var(--accent), var(--accent-deep));
            border-color: var(--accent-deep);
            color: #fff;
        }
        .btn-danger:hover,
        .btn-danger:focus {
            background: linear-gradient(135deg, var(--accent-deep), #886617);
            border-color: #886617;
            color: #fff;
        }
        .btn-outline-danger {
            color: var(--accent-deep);
            border-color: rgba(var(--accent-rgb), 0.38);
            background: rgba(var(--accent-rgb), 0.06);
        }
        .btn-outline-danger:hover,
        .btn-outline-danger:focus {
            background: linear-gradient(135deg, var(--accent), var(--accent-deep));
            border-color: var(--accent-deep);
            color: #fff;
        }
        .bg-danger {
            background: linear-gradient(135deg, var(--accent), var(--accent-deep)) !important;
        }
        .border-danger {
            border-color: rgba(var(--accent-rgb), 0.35) !important;
        }
        .table-danger {
            --bs-table-bg: rgba(var(--accent-rgb), 0.14);
            --bs-table-border-color: rgba(var(--accent-rgb), 0.22);
        }
        .sidebar-footer {
            padding: 0 0.9rem 1rem;
        }
        .sidebar-profile {
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(170, 140, 44, 0.2);
            padding: 1rem;
            box-shadow: 0 18px 32px rgba(15, 23, 42, 0.08);
        }
        .sidebar-profile-name {
            font-weight: 700;
            color: #172033;
            line-height: 1.2;
        }
        .sidebar-profile-role {
            color: rgba(23, 32, 51, 0.62);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.72rem;
            margin-top: 0.2rem;
        }
        .sidebar-logout {
            border-radius: 16px;
            padding: 0.8rem 1rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
        }
        body.sidebar-collapsed .sidebar {
            width: 92px;
        }
        body.sidebar-collapsed .main-content {
            margin-left: 92px;
        }
        body.sidebar-collapsed .sidebar-header {
            padding-inline: 1rem;
        }
        body.sidebar-collapsed .sidebar-header small,
        body.sidebar-collapsed .nav-label,
        body.sidebar-collapsed .sidebar-profile-name,
        body.sidebar-collapsed .sidebar-profile-role,
        body.sidebar-collapsed .sidebar-logout span {
            display: none;
        }
        body.sidebar-collapsed .sidebar-header h4 {
            font-size: 1rem;
        }
        body.sidebar-collapsed .sidebar-menu {
            padding-inline: 0.75rem;
        }
        body.sidebar-collapsed .sidebar-menu .nav-link {
            justify-content: center;
            padding-inline: 0;
        }
        body.sidebar-collapsed .sidebar-menu .nav-link i {
            margin-right: 0;
            font-size: 1rem;
        }
        body.sidebar-collapsed .sidebar-profile {
            padding: 0.9rem 0.75rem;
        }
        body.sidebar-collapsed .sidebar-profile .d-flex {
            justify-content: center;
        }
        body.sidebar-collapsed .sidebar-profile .fa-user-circle {
            margin-right: 0 !important;
        }
        body.sidebar-collapsed .sidebar-logout {
            padding-inline: 0;
        }
        body.sidebar-collapsed .sidebar-logout i {
            margin-right: 0;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .container-fluid.py-4 {
                padding: 1rem !important;
            }
            .top-navbar {
                padding: 1rem;
            }
            .top-navbar {
                align-items: flex-start !important;
                gap: 1rem;
                flex-direction: column;
            }
            .topbar-meta {
                width: 100%;
                justify-content: space-between;
            }
            .panel-hero {
                padding: 1.4rem;
            }
            .summary-strip {
                grid-template-columns: 1fr;
            }
            .stats-icon,
            .metric-icon {
                width: 52px;
                height: 52px;
            }
        }
    </style>
</head>
<body>
<?php
if ((!isset($_SESSION['first_name']) || !isset($_SESSION['last_name'])) && isset($_SESSION['user_id'])) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
        }
    } catch (Exception $e) {
        // Fallback if database query fails
    }
}

$firstName = $_SESSION['first_name'] ?? $_SESSION['username'] ?? 'User';
$lastName = $_SESSION['last_name'] ?? '';
$displayName = htmlspecialchars(trim($firstName . ' ' . $lastName));
$roleLabel = ucfirst($_SESSION['role'] ?? 'Admin');
$pageName = isset($page_title) ? $page_title : 'Dashboard';
$pageDescriptions = [
    'Admin Dashboard' => 'Monitor revenue, demand signals, and stock risk to guide business decisions.',
    'Sales Analysis' => 'Compare revenue, payment behavior, and product demand across the selected period.',
    'Users' => 'Align team access and account coverage with daily operations and business priorities.',
    'Inventory' => 'Review stock health, availability risk, and replenishment priorities in one place.',
    'Supplier Receiving' => 'Receive supplier deliveries, record invoices, and update stock batches with cost details.',
    'Inventory Stock Reports' => 'Audit stock movement patterns and spot inventory issues that need follow-up.',
    'Settings' => 'Maintain the system settings that support reporting, operations, and store continuity.'
];
$pageSubtitle = $pageDescriptions[$pageName] ?? 'Use this workspace to monitor operations and act on the insights that matter most.';
?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-cash-register fa-2x text-white mb-2"></i>
            <h4>Kakai's Kutkutin POS</h4>
            <small><?php echo $roleLabel; ?> Panel</small>
        </div>
        
        <nav class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-label">Overview</span>
                    </a>
                </li>
                
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
                        <i class="fas fa-boxes"></i>
                        <span class="nav-label">Inventory</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'receiving.php' ? 'active' : ''; ?>" href="receiving.php">
                        <i class="fas fa-truck-loading"></i>
                        <span class="nav-label">Receiving</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                        <i class="fas fa-receipt"></i>
                        <span class="nav-label">Transactions</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                        <i class="fas fa-chart-line"></i>
                        <span class="nav-label">Inventory Reports</span>
                    </a>
                </li>
                
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sales_analysis.php' ? 'active' : ''; ?>" href="sales_analysis.php">
                        <i class="fas fa-chart-line"></i>
                        <span class="nav-label">Sales Analysis</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active' : ''; ?>" href="user_management.php">
                        <i class="fas fa-users-cog"></i>
                        <span class="nav-label">Users</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="nav-label">Settings</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-profile">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <i class="fas fa-user-circle fa-2x" style="color:#172033;"></i>
                    <div>
                        <div class="sidebar-profile-name"><?php echo $displayName; ?></div>
                        <div class="sidebar-profile-role"><?php echo $roleLabel; ?></div>
                    </div>
                </div>
                <a class="btn btn-dark w-100 sidebar-logout" href="<?php echo BASE_URL; ?>public/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="btn btn-light me-3" id="sidebar-toggle" type="button" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-context">
                    <span class="page-overline"><?php echo isAdmin() ? 'Business Insights Workspace' : $roleLabel . ' Workspace'; ?></span>
                    <h5 class="mb-0 section-heading"><?php echo $pageName; ?></h5>
                    <div class="page-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></div>
                </div>
            </div>
            <div class="topbar-meta">
                <span class="topbar-badge"><i class="fas fa-calendar-alt"></i><?php echo date('M d, Y'); ?></span>
            </div>
        </nav>

        <div class="container-fluid py-4">
            <?php if (isset($content)) echo $content; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
        
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
    </script>
    <script>
        function setSidebarVisibility(isVisible) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar?.classList.toggle('show', isVisible);
            overlay?.classList.toggle('show', isVisible);
        }

        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                setSidebarVisibility(!sidebar?.classList.contains('show'));
                return;
            }

            document.body.classList.toggle('sidebar-collapsed');
        });

        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const toggle = document.getElementById('sidebar-toggle');
                
                if (sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    setSidebarVisibility(false);
                }
            }
        });

        document.getElementById('sidebar-overlay')?.addEventListener('click', function() {
            setSidebarVisibility(false);
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                setSidebarVisibility(false);
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>
