
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
            --accent: #C79A2B;
            --accent-deep: #9F7A1C;
            --accent-soft: #F3E4A8;
            --accent-rgb: 170, 140, 44;
            --ink: #172033;
            --muted: #667085;
            --line: rgba(23, 32, 51, 0.08);
            --surface: rgba(255, 255, 255, 0.94);
            --surface-strong: #ffffff;
            --shadow-lg: 0 24px 60px rgba(17, 24, 39, 0.12);
            --shadow-md: 0 12px 30px rgba(17, 24, 39, 0.08);
            --radius-lg: 24px;
            --radius-md: 18px;
        }
        body {
            min-height: 100vh;
            color: var(--ink);
            background:
                radial-gradient(circle at top right, rgba(212, 175, 55, 0.12), transparent 24%),
                radial-gradient(circle at bottom left, rgba(255, 255, 255, 0.15), transparent 22%),
                linear-gradient(180deg, #fefef8 0%, #f5f5f0 100%);
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
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
        .pos-container {
            padding: 0;
            height: calc(100vh - 70px);
            overflow: hidden;
        }
        .cart-section,
        .products-section,
        .card {
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255,255,255,0.72);
            background: var(--surface);
            backdrop-filter: blur(12px);
            box-shadow: var(--shadow-md);
        }
        .cart-section {
            border-right: 1px solid rgba(23, 32, 51, 0.08);
            height: 100%;
            padding: 1.5rem;
        }
        .products-section {
            height: 100%;
            padding: 1.5rem;
        }
        .cart-header {
            background: #f8fafc;
            padding: 1rem;
            margin: -1.5rem -1.5rem 1rem -1.5rem;
            border-bottom: 1px solid rgba(23, 32, 51, 0.08);
        }
        .cart-item {
            border-bottom: 1px solid rgba(23, 32, 51, 0.08);
            padding: 0.75rem 0;
        }
        .cart-summary {
            border-top: 2px solid rgba(23, 32, 51, 0.08);
            padding-top: 1rem;
            margin-top: 1rem;
        }
        .product-card {
            background: var(--surface-strong);
            border: 1px solid rgba(23, 32, 51, 0.08);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
            cursor: pointer;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.09);
        }
        .category-tabs .nav-link {
            background: rgba(255,255,255,0.82);
            color: var(--muted);
            border: none;
            margin-right: 0.5rem;
            border-radius: 999px;
            padding: 0.5rem 1.5rem;
            font-weight: 700;
        }
        .category-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--brand), var(--brand-deep));
            color: white;
            box-shadow: 0 10px 20px rgba(180, 35, 42, 0.24);
        }
        .btn-add-cart,
        .btn-complete-sale {
            border: none;
            color: white;
        }
        .btn-add-cart {
            background: linear-gradient(135deg, #16a34a, #15803d);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 20px rgba(21, 128, 61, 0.22);
        }
        .btn-complete-sale {
            background: linear-gradient(135deg, #16a34a, #15803d);
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 18px;
            box-shadow: 0 16px 28px rgba(21, 128, 61, 0.22);
        }
        .payment-buttons button {
            margin: 0.25rem;
            min-width: 80px;
        }
        .card-header {
            background: linear-gradient(180deg, rgba(255,255,255,0.84), rgba(248,250,252,0.72)) !important;
            border-bottom: 1px solid var(--line) !important;
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.35rem;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
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
        .panel-hero {
            background: linear-gradient(135deg, rgba(159, 122, 28, 0.96), rgba(199, 154, 43, 0.94) 52%, rgba(225, 190, 98, 0.92));
            border-radius: 28px;
            padding: 1.9rem;
            color: #fff;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        .hero-kicker {
            text-transform: uppercase;
            letter-spacing: 0.14em;
            font-size: 0.76rem;
            opacity: 0.82;
            margin-bottom: 0.7rem;
            font-weight: 700;
        }
        .hero-title {
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin-bottom: 0.55rem;
        }
        .hero-subtitle {
            max-width: 680px;
            opacity: 0.88;
            margin-bottom: 0;
        }
        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            padding: 0.7rem 1rem;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.18);
            font-weight: 700;
            backdrop-filter: blur(10px);
        }
        .section-heading {
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            letter-spacing: -0.03em;
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
            padding: 1rem 0.9rem 1.25rem;
            border-top: 1px solid rgba(170, 140, 44, 0.22);
        }
        .sidebar-profile {
            background: rgba(255, 255, 255, 0.55);
            border: 1px solid rgba(23, 32, 51, 0.08);
            border-radius: 18px;
            padding: 1rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }
        .sidebar-profile-name {
            color: #172033;
            font-weight: 800;
            font-family: 'Manrope', sans-serif;
            margin-bottom: 0.15rem;
        }
        .sidebar-profile-role {
            color: rgba(23, 32, 51, 0.68);
            font-size: 0.84rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.85rem;
        }
        .sidebar-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            border-radius: 14px;
            font-weight: 700;
        }
        body.sidebar-collapsed .sidebar {
            width: 92px;
        }
        body.sidebar-collapsed .main-content {
            margin-left: 92px;
        }
        body.sidebar-collapsed .sidebar-header {
            padding: 1.5rem 0.75rem 1.25rem;
        }
        body.sidebar-collapsed .sidebar-header small,
        body.sidebar-collapsed .nav-label,
        body.sidebar-collapsed .sidebar-profile-name,
        body.sidebar-collapsed .sidebar-profile-role,
        body.sidebar-collapsed .sidebar-logout span {
            display: none;
        }
        body.sidebar-collapsed .sidebar-header h4 {
            font-size: 0;
        }
        body.sidebar-collapsed .sidebar-menu {
            padding: 1rem 0.6rem;
        }
        body.sidebar-collapsed .sidebar-menu .nav-link {
            justify-content: center;
            padding: 0.88rem 0.75rem;
        }
        body.sidebar-collapsed .sidebar-menu .nav-link i {
            margin-right: 0;
            width: auto;
            font-size: 1.05rem;
        }
        body.sidebar-collapsed .sidebar-profile {
            padding: 0.85rem 0.6rem;
            text-align: center;
        }
        body.sidebar-collapsed .sidebar-profile .d-flex {
            justify-content: center;
            margin-bottom: 0.75rem !important;
        }
        body.sidebar-collapsed .sidebar-profile .fa-user-circle {
            margin-right: 0 !important;
        }
        body.sidebar-collapsed .sidebar-logout {
            padding-left: 0;
            padding-right: 0;
        }
        body.sidebar-collapsed .sidebar-logout i {
            margin-right: 0;
        }
        .text-muted {
            color: var(--muted) !important;
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
            .top-navbar {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php
    if (!isset($_SESSION['first_name']) || !isset($_SESSION['last_name'])) {
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
    $pageName = isset($page_title) ? $page_title : ($title ?? 'Dashboard');
    $pageDescriptions = [
        'Cashier Dashboard' => 'Track today\'s cashier activity, quick actions, and current selling priorities.',
        'Dashboard' => 'Track today\'s cashier activity, quick actions, and current selling priorities.',
        'Point of Sale' => 'Build carts, apply retail or wholesale pricing, and complete customer checkout.',
        'Transaction History' => 'Review completed orders, payment details, and receipt records.',
        'Sales Summary' => 'Check cashier sales totals, item movement, and payment method breakdowns.',
        'View Inventory' => 'Look up available products, stock levels, and pricing while assisting customers.',
        'Account Settings' => 'Maintain your cashier account details and access credentials.'
    ];
    $pageSubtitle = $pageDescriptions[$pageName] ?? 'Use this workspace to serve customers and keep transactions accurate.';
    ?>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-cash-register fa-2x text-white mb-2"></i>
            <h4>Kakai's Kutkutin POS</h4>
            <small>Cashier Panel</small>
        </div>
        
        <nav class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-label">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active' : ''; ?>" href="pos.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="nav-label">Point of Sale</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                        <i class="fas fa-receipt"></i>
                        <span class="nav-label">Transaction History</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : ''; ?>" href="sales.php">
                        <i class="fas fa-chart-line"></i>
                        <span class="nav-label">Sales Summary</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
                        <i class="fas fa-eye"></i>
                        <span class="nav-label">View Inventory</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'account_settings.php' ? 'active' : ''; ?>" href="account_settings.php">
                        <i class="fas fa-user-cog"></i>
                        <span class="nav-label">Account Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-profile">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <i class="fas fa-user-circle fa-2x" style="color:#172033;"></i>
                    <div>
                        <div class="sidebar-profile-name"><?php echo $displayName; ?></div>
                        <div class="sidebar-profile-role">Cashier</div>
                    </div>
                </div>
                <a class="btn btn-dark w-100 sidebar-logout" href="<?php echo BASE_URL; ?>public/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="btn btn-light me-3" id="sidebar-toggle" type="button" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-context">
                    <span class="page-overline">Cashier Workspace</span>
                    <h5 class="mb-0 section-heading"><?php echo htmlspecialchars($pageName); ?></h5>
                    <div class="page-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></div>
                </div>
            </div>
            <div class="topbar-meta">
                <span class="topbar-badge"><i class="fas fa-calendar-alt"></i><?php echo date('M d, Y'); ?></span>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="<?php echo basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'container-fluid pos-container' : 'container-fluid py-4'; ?>">
            <?php echo $content; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script>
        // Expose BASE_URL to JavaScript
        const BASE_URL = '<?php echo BASE_URL; ?>';
        
        // Configure Toastr
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
        // Sidebar toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.toggle('show');
                return;
            }

            document.body.classList.toggle('sidebar-collapsed');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const toggle = document.getElementById('sidebar-toggle');
                
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar')?.classList.remove('show');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>
