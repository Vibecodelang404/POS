
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
            --brand: #b4232a;
            --brand-deep: #7f1d1d;
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
                radial-gradient(circle at top right, rgba(180, 35, 42, 0.12), transparent 24%),
                radial-gradient(circle at bottom left, rgba(37, 99, 235, 0.09), transparent 22%),
                linear-gradient(180deg, #f6f8fc 0%, #eef2f8 100%);
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, rgba(127, 29, 29, 0.96) 0%, rgba(180, 35, 42, 0.92) 52%, rgba(23, 32, 51, 0.95) 100%);
            backdrop-filter: blur(18px);
            min-height: 100vh;
            width: 274px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s;
            box-shadow: 20px 0 50px rgba(15, 23, 42, 0.18);
        }
        .sidebar-header {
            padding: 2rem 1.6rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            text-align: center;
        }
        .sidebar-header h4 {
            color: white;
            margin: 0;
            font-weight: 800;
            font-family: 'Manrope', sans-serif;
            letter-spacing: -0.03em;
        }
        .sidebar-header small {
            color: rgba(255,255,255,0.72);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .sidebar-menu {
            padding: 1.25rem 0.9rem;
        }
        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.82);
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
            background: rgba(255,255,255,0.12);
            color: white;
            transform: translateX(4px);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);
        }
        .sidebar-menu .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 0.95rem;
        }
        .main-content {
            margin-left: 274px;
            min-height: 100vh;
        }
        .top-navbar {
            background: rgba(255,255,255,0.74);
            backdrop-filter: blur(14px);
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
            padding: 1.1rem 1.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.55);
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
            background: linear-gradient(135deg, rgba(180,35,42,0.94), rgba(127,29,29,0.92) 52%, rgba(30,64,175,0.84));
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
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-cash-register fa-2x text-white mb-2"></i>
            <h4>PointShift</h4>
            <small>Cashier Panel</small>
        </div>
        
        <nav class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active' : ''; ?>" href="pos.php">
                        <i class="fas fa-shopping-cart"></i>
                        Point of Sale
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                        <i class="fas fa-receipt"></i>
                        Transaction History
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
                        <i class="fas fa-eye"></i>
                        View Inventory
                    </a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'view_shifts.php' ? 'active' : ''; ?>" href="view_shifts.php">
                        <i class="fas fa-calendar-alt"></i>
                        View Shifts
                    </a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'account_settings.php' ? 'active' : ''; ?>" href="account_settings.php">
                        <i class="fas fa-user-cog"></i>
                        Account Settings
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-md-none me-3" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="text-uppercase text-muted small fw-bold" style="letter-spacing: 0.14em;">Retail Operations Console</div>
                    <h5 class="mb-0 section-heading"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h5>
                </div>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i>
                    <?php 
                    // Check if session variables are missing and refresh them from database
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
                        } catch(Exception $e) {
                            // Fallback if database query fails
                        }
                    }
                    
                    $firstName = $_SESSION['first_name'] ?? $_SESSION['username'] ?? 'User';
                    $lastName = $_SESSION['last_name'] ?? '';
                    echo htmlspecialchars(trim($firstName . ' ' . $lastName)); 
                    ?>
                    <span class="badge bg-success ms-2">Cashier</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
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
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
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
    </script>
</body>
</html>
