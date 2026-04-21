<?php
require_once __DIR__ . '/../app/config.php';
requireLogin();
requireAdmin();

$page_title = "Sales Trend Analysis";

// Get date range from query parameters or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
$view_type = isset($_GET['view_type']) ? $_GET['view_type'] : 'daily'; // daily, weekly, monthly

// Fetch sales data by date
$sales_query = "
    SELECT 
        DATE(o.created_at) as sale_date,
        COUNT(DISTINCT o.id) as total_orders,
        SUM(o.total_amount) as total_sales,
        SUM(o.subtotal) as subtotal,
        SUM(o.discount_amount) as total_discount,
        SUM(o.tax_amount) as total_tax
    FROM orders o
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY DATE(o.created_at)
    ORDER BY sale_date ASC
";

$stmt = $conn->prepare($sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch top selling products
$top_products_query = "
    SELECT 
        p.name,
        p.sku,
        c.name as category,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.total_price) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total_quantity DESC
    LIMIT 10
";

$stmt = $conn->prepare($top_products_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch sales by category
$category_sales_query = "
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category_name,
        COUNT(DISTINCT o.id) as order_count,
        SUM(oi.quantity) as total_items,
        SUM(oi.total_price) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY total_revenue DESC
";

$stmt = $conn->prepare($category_sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$category_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate summary statistics
$total_sales = array_sum(array_column($sales_data, 'total_sales'));
$total_orders = array_sum(array_column($sales_data, 'total_orders'));
$avg_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;
$total_discount = array_sum(array_column($sales_data, 'total_discount'));

// Prepare data for charts
$dates = array_column($sales_data, 'sale_date');
$sales_amounts = array_column($sales_data, 'total_sales');
$order_counts = array_column($sales_data, 'total_orders');

ob_start();
?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Sales</h6>
                    <h3 class="mb-0">₱<?php echo number_format($total_sales, 2); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Orders</h6>
                    <h3 class="mb-0"><?php echo number_format($total_orders); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Avg Order Value</h6>
                    <h3 class="mb-0">₱<?php echo number_format($avg_order_value, 2); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Discounts</h6>
                    <h3 class="mb-0">₱<?php echo number_format($total_discount, 2); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="content-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Options</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-danger w-100 btn-custom">
                    <i class="fas fa-search me-2"></i>Apply Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Sales Trend Chart -->
<div class="content-card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Sales Trend Chart</h5>
    </div>
    <div class="card-body">
        <div id="salesTrendChart"></div>
    </div>
</div>

<div class="row">
    <!-- Top Selling Products -->
    <div class="col-md-6 mb-4">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top 10 Selling Products</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_products as $index => $product): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php 
                                                echo $index == 0 ? 'bg-warning' : ($index == 1 ? 'bg-secondary' : ($index == 2 ? 'bg-info' : 'bg-light text-dark')); 
                                            ?>">
                                                #<?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-primary"><?php echo number_format($product['total_quantity']); ?></span></td>
                                        <td><strong>₱<?php echo number_format($product['total_revenue'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sales by Category -->
    <div class="col-md-6 mb-4">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Sales by Category</h5>
            </div>
            <div class="card-body">
                <div id="categorySalesChart"></div>
            </div>
        </div>
    </div>
</div>

<!-- Daily Sales Table -->
<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Daily Sales Breakdown</h5>
        <button class="btn btn-sm btn-success" onclick="exportTableToCSV('sales_data.csv')">
            <i class="fas fa-file-excel me-2"></i>Export to CSV
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="salesTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Orders</th>
                        <th>Subtotal</th>
                        <th>Discount</th>
                        <th>Tax</th>
                        <th>Total Sales</th>
                        <th>Avg Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales_data)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No sales data found for the selected period</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales_data as $sale): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                                <td><span class="badge bg-info"><?php echo $sale['total_orders']; ?></span></td>
                                <td>₱<?php echo number_format($sale['subtotal'], 2); ?></td>
                                <td>₱<?php echo number_format($sale['total_discount'], 2); ?></td>
                                <td>₱<?php echo number_format($sale['tax_amount'], 2); ?></td>
                                <td><strong>₱<?php echo number_format($sale['total_sales'], 2); ?></strong></td>
                                <td>₱<?php echo number_format($sale['total_sales'] / $sale['total_orders'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"></script>
<script>
// Sales Trend Chart
const salesTrendOptions = {
    chart: {
        type: 'line',
        toolbar: {
            show: true,
            tools: {
                download: true,
                selection: true,
                zoom: true,
                zoomin: true,
                zoomout: true,
                pan: true,
                reset: true
            }
        },
        height: 350
    },
    series: [
        {
            name: 'Sales Amount (₱)',
            data: <?php echo json_encode($sales_amounts); ?>,
            color: '#dc3545'
        },
        {
            name: 'Number of Orders',
            data: <?php echo json_encode($order_counts); ?>,
            color: '#0d6efd'
        }
    ],
    xaxis: {
        categories: <?php echo json_encode(array_map(function($date) { return date('M d', strtotime($date)); }, $dates)); ?>,
        type: 'category'
    },
    yaxis: [
        {
            title: {
                text: 'Sales Amount (₱)'
            },
            labels: {
                formatter: function (value) {
                    return '₱' + value.toLocaleString('en-US', {maximumFractionDigits: 0});
                }
            }
        },
        {
            opposite: true,
            title: {
                text: 'Number of Orders'
            }
        }
    ],
    tooltip: {
        shared: true,
        intersect: false,
        x: {
            formatter: function (value) {
                return value;
            }
        },
        y: {
            formatter: function (value, {series, seriesIndex}) {
                if (seriesIndex === 0) {
                    return '₱' + value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                } else {
                    return value;
                }
            }
        }
    },
    stroke: {
        curve: 'smooth',
        width: 2
    },
    fill: {
        type: 'gradient',
        opacity: 0.1
    },
    markers: {
        size: 4
    },
    legend: {
        position: 'top',
        horizontalAlign: 'left'
    },
    grid: {
        borderColor: '#f1f5f7',
        padding: {
            top: 20,
            right: 20,
            bottom: 20,
            left: 60
        }
    }
};

new ApexCharts(document.querySelector('#salesTrendChart'), salesTrendOptions).render();

// Category Sales Chart
const categorySalesOptions = {
    chart: {
        type: 'donut',
        height: 320
    },
    series: <?php echo json_encode(array_column($category_sales, 'total_revenue')); ?>,
    labels: <?php echo json_encode(array_column($category_sales, 'category_name')); ?>,
    colors: [
        '#dc3545',
        '#0d6efd',
        '#198754',
        '#ffc107',
        '#6c757d',
        '#6f42c1'
    ],
    plotOptions: {
        pie: {
            donut: {
                size: '65%'
            }
        }
    },
    tooltip: {
        y: {
            formatter: function (value) {
                return '₱' + value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }
    },
    legend: {
        position: 'right',
        verticalAlign: 'middle'
    },
    dataLabels: {
        enabled: true,
        formatter: function (val) {
            return val.toFixed(1) + '%';
        }
    }
};

new ApexCharts(document.querySelector('#categorySalesChart'), categorySalesOptions).render();

// Export to CSV function
function exportTableToCSV(filename) {
    const table = document.getElementById('salesTable');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    const csvString = csv.join('\n');
    const link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString);
    link.download = filename;
    link.click();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../app/views/layout.php';
?>


