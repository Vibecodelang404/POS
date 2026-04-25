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
        COALESCE(SUM(o.total_amount), 0) as total_sales,
        COALESCE(SUM(o.subtotal), 0) as subtotal,
        COALESCE(SUM(o.discount_amount), 0) as total_discount,
        COALESCE(SUM(o.tax_amount), 0) as tax_amount
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
$category_names = array_column($category_sales, 'category_name');
$category_revenue = array_map('floatval', array_column($category_sales, 'total_revenue'));
$category_order_counts = array_map('intval', array_column($category_sales, 'order_count'));
$category_item_counts = array_map('intval', array_column($category_sales, 'total_items'));
$category_short_labels = array_map(function ($name) {
    $name = (string) $name;
    return mb_strlen($name) > 18 ? mb_substr($name, 0, 18) . '…' : $name;
}, $category_names);
$category_rich_labels = array_map(function ($sale) {
    return sprintf(
        '%s | %d orders | %d items',
        $sale['category_name'],
        (int) $sale['order_count'],
        (int) $sale['total_items']
    );
}, $category_sales);

ob_start();
?>

<style>
.analytics-chart-shell {
    border-radius: 24px;
    padding: 1rem;
    background:
        radial-gradient(circle at top right, rgba(212, 175, 55, 0.12), transparent 24%),
        linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 0.98));
    border: 1px solid rgba(15, 23, 42, 0.06);
}
.analytics-insights {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.analytics-insight {
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(15, 23, 42, 0.06);
    padding: 0.85rem 1rem;
}
.analytics-insight-label {
    color: var(--muted);
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.analytics-insight-value {
    font-family: 'Manrope', sans-serif;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--ink);
}
.category-chart-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(240px, 0.75fr);
    gap: 1rem;
    align-items: start;
}
.category-chart-legend {
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(15, 23, 42, 0.06);
    padding: 1rem;
}
.category-legend-title {
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--muted);
    margin-bottom: 0.85rem;
}
.category-legend-list {
    display: grid;
    gap: 0.7rem;
}
.category-legend-item {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.75rem;
    align-items: start;
}
.category-legend-swatch {
    width: 12px;
    height: 12px;
    border-radius: 999px;
    margin-top: 0.32rem;
}
.category-legend-name {
    font-weight: 700;
    color: var(--ink);
    line-height: 1.2;
}
.category-legend-meta {
    color: var(--muted);
    font-size: 0.84rem;
}
@media (max-width: 991px) {
    .analytics-insights {
        grid-template-columns: 1fr;
    }
    .category-chart-layout {
        grid-template-columns: 1fr;
    }
}
</style>

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
        <div class="analytics-insights">
            <div class="analytics-insight">
                <div class="analytics-insight-label">Peak Day</div>
                <div class="analytics-insight-value">₱<?php echo number_format(!empty($sales_amounts) ? max($sales_amounts) : 0, 2); ?></div>
            </div>
            <div class="analytics-insight">
                <div class="analytics-insight-label">Tracked Days</div>
                <div class="analytics-insight-value"><?php echo number_format(count($sales_data)); ?></div>
            </div>
            <div class="analytics-insight">
                <div class="analytics-insight-label">Revenue / Day</div>
                <div class="analytics-insight-value">₱<?php echo number_format(!empty($sales_data) ? $total_sales / count($sales_data) : 0, 2); ?></div>
            </div>
        </div>
        <div class="analytics-chart-shell">
            <div id="salesTrendChart"></div>
        </div>
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
                <div class="analytics-chart-shell">
                    <div class="category-chart-layout">
                        <div id="categorySalesChart"></div>
                        <div class="category-chart-legend">
                            <div class="category-legend-title">Category Detail</div>
                            <div class="category-legend-list">
                                <?php if (empty($category_sales)): ?>
                                    <div class="text-muted">No category sales data available.</div>
                                <?php else: ?>
                                    <?php
                                    $legendColors = ['#C79A2B', '#0d6efd', '#198754', '#ffc107', '#6c757d', '#6f42c1', '#fd7e14', '#20c997'];
                                    foreach ($category_sales as $index => $categorySale):
                                        $swatch = $legendColors[$index % count($legendColors)];
                                    ?>
                                        <div class="category-legend-item">
                                            <span class="category-legend-swatch" style="background-color: <?php echo $swatch; ?>"></span>
                                            <div>
                                                <div class="category-legend-name"><?php echo htmlspecialchars($categorySale['category_name']); ?></div>
                                                <div class="category-legend-meta">
                                                    <?php echo number_format((int) $categorySale['order_count']); ?> orders
                                                    • <?php echo number_format((int) $categorySale['total_items']); ?> items
                                                    • ₱<?php echo number_format((float) $categorySale['total_revenue'], 2); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
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
                                <td>₱<?php echo number_format((float) ($sale['subtotal'] ?? 0), 2); ?></td>
                                <td>₱<?php echo number_format((float) ($sale['total_discount'] ?? 0), 2); ?></td>
                                <td>₱<?php echo number_format((float) ($sale['tax_amount'] ?? 0), 2); ?></td>
                                <td><strong>₱<?php echo number_format((float) ($sale['total_sales'] ?? 0), 2); ?></strong></td>
                                <td>₱<?php echo number_format(((float) ($sale['total_sales'] ?? 0)) / max((int) ($sale['total_orders'] ?? 0), 1), 2); ?></td>
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
        type: 'bar',
        height: 360,
        toolbar: {
            show: false
        }
    },
    series: [{
        name: 'Revenue',
        data: <?php echo json_encode($category_revenue); ?>
    }],
    colors: ['#C79A2B'],
    plotOptions: {
        bar: {
            horizontal: true,
            borderRadius: 10,
            barHeight: '58%',
            dataLabels: {
                position: 'top'
            }
        }
    },
    xaxis: {
        categories: <?php echo json_encode($category_rich_labels); ?>,
        labels: {
            formatter: function (value) {
                return '₱' + Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });
            }
        }
    },
    yaxis: {
        labels: {
            maxWidth: 320,
            style: {
                fontSize: '12px',
                fontWeight: 600
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

categorySalesOptions.tooltip = {
    custom: function ({ dataPointIndex }) {
        const categories = <?php echo json_encode($category_names); ?>;
        const revenue = <?php echo json_encode($category_revenue); ?>;
        const orderCounts = <?php echo json_encode($category_order_counts); ?>;
        const itemCounts = <?php echo json_encode($category_item_counts); ?>;
        const category = categories[dataPointIndex] || 'Category';
        const amount = Number(revenue[dataPointIndex] || 0);
        const orders = Number(orderCounts[dataPointIndex] || 0);
        const items = Number(itemCounts[dataPointIndex] || 0);

        return `
            <div style="padding: 10px 12px;">
                <div style="font-weight: 700; margin-bottom: 4px;">${category}</div>
                <div>Revenue: ₱${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                <div>Orders: ${orders}</div>
                <div>Items Sold: ${items}</div>
            </div>
        `;
    }
};
categorySalesOptions.legend = {
    show: false
};
categorySalesOptions.dataLabels = {
    enabled: true,
    offsetX: 12,
    style: {
        fontSize: '12px',
        fontWeight: 700,
        colors: ['#172033']
    },
    formatter: function (val, opts) {
        const orderCounts = <?php echo json_encode($category_order_counts); ?>;
        const itemCounts = <?php echo json_encode($category_item_counts); ?>;
        const orders = orderCounts[opts.dataPointIndex] || 0;
        const items = itemCounts[opts.dataPointIndex] || 0;
        return '₱' + Number(val).toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }) + ` • ${orders} orders • ${items} items`;
    }
};
categorySalesOptions.grid = {
    borderColor: '#eef2f7',
    strokeDashArray: 4,
    padding: {
        left: 10,
        right: 24,
        top: 10,
        bottom: 10
    }
};
categorySalesOptions.noData = {
    text: 'No category sales data available'
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
<script>
const analyticsCurrency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const analyticsDates = <?php echo json_encode(array_map(function($date) { return date('M d', strtotime($date)); }, $dates)); ?>;
const analyticsSales = <?php echo json_encode($sales_amounts); ?>;
const analyticsOrders = <?php echo json_encode($order_counts); ?>;
const analyticsCategories = <?php echo json_encode(array_column($category_sales, 'category_name')); ?>;
const analyticsCategoryRevenue = <?php echo json_encode(array_column($category_sales, 'total_revenue')); ?>;

const salesTrendElement = document.querySelector('#salesTrendChart');
if (salesTrendElement) {
    salesTrendElement.innerHTML = '';
    new ApexCharts(salesTrendElement, {
        chart: {
            type: 'area',
            height: 380,
            foreColor: '#667085',
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
            dropShadow: {
                enabled: true,
                top: 12,
                left: 0,
                blur: 18,
                color: '#172033',
                opacity: 0.08
            }
        },
        series: [
            {
                name: 'Sales Amount (PHP)',
                type: 'area',
                data: analyticsSales,
                color: '#C79A2B'
            },
            {
                name: 'Number of Orders',
                type: 'line',
                data: analyticsOrders,
                color: '#0d6efd'
            }
        ],
        xaxis: {
            categories: analyticsDates,
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: [
            {
                title: { text: 'Sales Amount (PHP)' },
                labels: {
                    formatter(value) {
                        return analyticsCurrency.format(value).replace('.00', '');
                    }
                }
            },
            {
                opposite: true,
                title: { text: 'Number of Orders' }
            }
        ],
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter(value, { seriesIndex }) {
                    return seriesIndex === 0 ? analyticsCurrency.format(value) : value;
                }
            }
        },
        stroke: {
            curve: 'smooth',
            width: [3, 3]
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.35,
                opacityTo: 0.02,
                stops: [0, 90, 100]
            }
        },
        markers: {
            size: 4,
            hover: {
                sizeOffset: 3
            }
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
        },
        dataLabels: {
            enabled: false
        },
        noData: {
            text: 'No sales data available for this period'
        },
        responsive: [{
            breakpoint: 768,
            options: {
                chart: { height: 320 },
                legend: { position: 'bottom' }
            }
        }]
    }).render();
}

const categorySalesElement = document.querySelector('#categorySalesChart');
if (categorySalesElement) {
    categorySalesElement.innerHTML = '';
    new ApexCharts(categorySalesElement, {
        chart: {
            type: 'bar',
            height: 360,
            toolbar: {
                show: false
            }
        },
        series: [{
            name: 'Revenue',
            data: analyticsCategoryRevenue
        }],
        colors: ['#C79A2B'],
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 10,
                barHeight: '58%',
                dataLabels: {
                    position: 'top'
                }
            }
        },
        xaxis: {
            categories: <?php echo json_encode($category_short_labels); ?>,
            labels: {
                formatter(value) {
                    return analyticsCurrency.format(value).replace('.00', '');
                }
            }
        },
        yaxis: {
            labels: {
                maxWidth: 160,
                style: {
                    fontSize: '12px',
                    fontWeight: 600
                }
            }
        },
        tooltip: {
            custom({ dataPointIndex }) {
                const category = analyticsCategories[dataPointIndex] || 'Category';
                const amount = Number(analyticsCategoryRevenue[dataPointIndex] || 0);
                const orders = <?php echo json_encode($category_order_counts); ?>[dataPointIndex] || 0;
                const items = <?php echo json_encode($category_item_counts); ?>[dataPointIndex] || 0;

                return `
                    <div style="padding: 10px 12px;">
                        <div style="font-weight: 700; margin-bottom: 4px;">${category}</div>
                        <div>Revenue: ${analyticsCurrency.format(amount)}</div>
                        <div>Orders: ${orders}</div>
                        <div>Items Sold: ${items}</div>
                    </div>
                `;
            }
        },
        legend: {
            show: false
        },
        dataLabels: {
            enabled: true,
            offsetX: 10,
            style: {
                fontSize: '11px',
                fontWeight: 700,
                colors: ['#172033']
            },
            formatter(val) {
                return analyticsCurrency.format(val).replace('.00', '');
            }
        },
        grid: {
            borderColor: '#eef2f7',
            strokeDashArray: 4,
            padding: {
                left: 10,
                right: 12,
                top: 10,
                bottom: 10
            }
        },
        noData: {
            text: 'No category revenue data available'
        },
        responsive: [{
            breakpoint: 992,
            options: {
                chart: { height: 320 }
            }
        }]
    }).render();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../app/views/layout.php';
?>


