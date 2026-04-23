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
        }
        .sidebar {
            background:
                linear-gradient(180deg, rgba(170, 140, 44, 0.96) 0%, rgba(212, 175, 55, 0.94) 52%, rgba(255, 255, 255, 0.97) 100%);
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
        }
        .sidebar-menu .nav-link {
            color: rgba(23, 32, 51, 0.8);
            padding: 0.88rem 1rem;
            border: none;
            border-radius: 16px;
            transition: all 0.3s;
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
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .page-header {
            background: linear-gradient(135deg, rgba(255,255,255,0.98), rgba(255,255,255,0.9));
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255,255,255,0.7);
        }
        .stats-card {
            background: var(--surface-strong);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255,255,255,0.65);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        .stats-card .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
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
        .btn-custom {
            border-radius: 999px;
            padding: 0.65rem 1.1rem;
            font-weight: 700;
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
        .container-fluid.p-4 {
            padding: 1.75rem !important;
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
        .panel-hero::after {
            content: '';
            position: absolute;
            inset: auto -40px -60px auto;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(255,255,255,0.18), transparent 65%);
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
            .container-fluid.p-4 {
                padding: 1rem !important;
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
            <h4>Kakai's POS</h4>
            <small>Admin Panel</small>
        </div>
        
        <nav class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Overview
                    </a>
                </li>
                
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
                        <i class="fas fa-boxes"></i>
                        Inventory
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                        <i class="fas fa-receipt"></i>
                        Transactions
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                        <i class="fas fa-chart-line"></i>
                        Inventory Reports
                    </a>
                </li>
                
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sales_trend_analysis.php' ? 'active' : ''; ?>" href="sales_trend_analysis.php">
                        <i class="fas fa-chart-area"></i>
                        Sales Trend Analysis
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active' : ''; ?>" href="user_management.php">
                        <i class="fas fa-users-cog"></i>
                        User Management
                    </a>
                </li>
                

                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'view_shifts.php' ? 'active' : ''; ?>" href="view_shifts.php">
                        <i class="fas fa-calendar-alt"></i>
                        View Shifts
                    </a>
                </li> -->
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
                    <div class="text-uppercase text-muted small fw-bold" style="letter-spacing: 0.14em;">Business Management Suite</div>
                    <h5 class="mb-0 section-heading"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h5>
                </div>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                    <span class="badge bg-primary ms-2"><?php echo ucfirst($_SESSION['role']); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="container-fluid p-4">
            <?php if (isset($content)) echo $content; ?>
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

