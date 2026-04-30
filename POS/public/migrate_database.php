<?php
require_once __DIR__ . '/../app/config.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "<h2>Kakai's POS Database Migration</h2>";
    echo "<p>Checking and updating database structure...</p>";

    $result = $db->query("DESCRIBE orders");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);

    $requiredColumns = ['tax_amount', 'subtotal', 'order_number'];
    $missingColumns = [];

    foreach ($requiredColumns as $column) {
        if (!in_array($column, $columns, true)) {
            $missingColumns[] = $column;
        }
    }

    if (!empty($missingColumns)) {
        echo "<p>Adding missing columns to orders table...</p>";

        if (in_array('order_number', $missingColumns, true)) {
            $db->exec("ALTER TABLE orders ADD COLUMN order_number VARCHAR(20) AFTER id");
            echo "✓ Added order_number column<br>";
        }

        if (in_array('tax_amount', $missingColumns, true)) {
            $db->exec("ALTER TABLE orders ADD COLUMN tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount");
            echo "✓ Added tax_amount column<br>";
        }

        if (in_array('subtotal', $missingColumns, true)) {
            $db->exec("ALTER TABLE orders ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount");
            echo "✓ Added subtotal column<br>";
        }
    }

    $db->exec("ALTER TABLE orders MODIFY status ENUM('pending','completed','cancelled','refunded') DEFAULT 'pending'");
    echo "Updated order status options<br>";

    $db->exec("UPDATE orders SET order_number = CONCAT('ORD-', LPAD(id, 4, '0')) WHERE order_number IS NULL OR order_number = ''");
    echo "✓ Updated order numbers<br>";

    $result = $db->query("DESCRIBE order_items");
    $orderItemColumns = $result->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('cost_price_at_sale', $orderItemColumns, true)) {
        echo "<p>Adding cost_price_at_sale to order_items table...</p>";
        $db->exec("ALTER TABLE order_items ADD COLUMN cost_price_at_sale DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER unit_price");
        echo "Added cost_price_at_sale column<br>";
    }

    $result = $db->query("DESCRIBE products");
    $productColumns = $result->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('category_id', $productColumns, true)) {
        echo "<p>Adding category_id to products table...</p>";
        $db->exec("ALTER TABLE products ADD COLUMN category_id INT AFTER name");
        echo "✓ Added category_id column<br>";
    }

    if (!in_array('cost_price', $productColumns, true)) {
        echo "<p>Adding cost_price to products table...</p>";
        $db->exec("ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price");
        echo "Added cost_price column<br>";
    }

    if (!in_array('product_type', $productColumns, true)) {
        echo "<p>Adding product_type to products table...</p>";
        $db->exec("ALTER TABLE products ADD COLUMN product_type ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER category_id");
        echo "Added product_type column<br>";
    }

    $db->exec("
        UPDATE products
        SET product_type = CASE
            WHEN LOWER(name) LIKE '%wholesale%'
              OR LOWER(name) LIKE '%tie/%'
              OR LOWER(name) LIKE '%pack of%'
              OR LOWER(name) LIKE '%jar%'
            THEN 'wholesale'
            ELSE 'retail'
        END
        WHERE product_type = 'retail'
    ");
    echo "Backfilled retail/wholesale product types<br>";

    $db->exec("
        UPDATE products
        SET cost_price = CASE
            WHEN sku = 'CHP-PTTS-CHS-85' THEN 24.50
            WHEN sku = 'CHP-VCUT-BBQ-60' THEN 19.25
            WHEN sku = 'CHP-PICA-3IN1-90' THEN 27.00
            WHEN sku = 'CRN-BYBWG-GRL-100' THEN 17.00
            WHEN sku = 'CRN-BYBWG-TIE-20' THEN 72.00
            WHEN sku = 'CRN-CHPY-BBQ-110' THEN 22.50
            WHEN sku = 'CRN-NOVA-CDB-78' THEN 24.00
            WHEN sku = 'NUT-NGRY-ORG-160' THEN 29.00
            WHEN sku = 'NUT-GRWS-GRL-80' THEN 13.50
            WHEN sku = 'NUT-DDNG-MIX-100' THEN 19.50
            WHEN sku = 'CRK-OISH-SPC-60' THEN 12.00
            WHEN sku = 'CRK-FSHD-80' THEN 15.00
            WHEN sku = 'CRK-MRTY-VGC-90' THEN 18.50
            WHEN sku = 'SWT-STKO-CHO-380' THEN 88.00
            WHEN sku = 'SWT-PLLW-CHO-150' THEN 31.00
            WHEN sku = 'SWT-FLTP-100' THEN 102.00
            ELSE cost_price
        END
        WHERE cost_price = 0.00
    ");
    echo "Backfilled product cost prices where available<br>";

    $db->exec("
        UPDATE order_items oi
        INNER JOIN products p ON p.id = oi.product_id
        SET oi.cost_price_at_sale = p.cost_price
        WHERE oi.cost_price_at_sale = 0.00
    ");
    echo "Backfilled order item costs where available<br>";

    $db->exec("UPDATE products SET sku = CONCAT('SKU-', LPAD(id, 4, '0'))");
    echo "Normalized product SKUs<br>";

    $tables = $db->query("SHOW TABLES LIKE 'categories'")->fetchAll();
    if (empty($tables)) {
        echo "<p>Creating categories table...</p>";
        $db->exec("
            CREATE TABLE categories (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $db->exec("
            INSERT INTO categories (name, description) VALUES
            ('Electronics', 'Electronic devices and accessories'),
            ('Clothing', 'Apparel and fashion items'),
            ('Food & Beverages', 'Food and drink products'),
            ('General', 'General merchandise')
        ");
        echo "✓ Created categories table and added default categories<br>";
    }

    $db->exec("UPDATE products SET category_id = 4 WHERE category_id IS NULL OR category_id = 0");
    echo "✓ Updated products with default category<br>";

    $batchTables = $db->query("SHOW TABLES LIKE 'product_batches'")->fetchAll();
    if (empty($batchTables)) {
        echo "<p>Creating product_batches table...</p>";
        $db->exec("
            CREATE TABLE product_batches (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                quantity INT NOT NULL DEFAULT 0,
                expiry_date DATE DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_product_batches_product (product_id),
                KEY idx_product_batches_expiry (expiry_date),
                CONSTRAINT product_batches_ibfk_1 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");
        echo "✓ Created product_batches table<br>";
    }

    $breakdownTables = $db->query("SHOW TABLES LIKE 'product_breakdown_links'")->fetchAll();
    if (empty($breakdownTables)) {
        echo "<p>Creating product_breakdown_links table...</p>";
        $db->exec("
            CREATE TABLE product_breakdown_links (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                wholesale_product_id INT NOT NULL,
                retail_product_id INT NOT NULL,
                retail_units_per_wholesale INT NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_breakdown_pair (wholesale_product_id, retail_product_id),
                KEY idx_breakdown_wholesale (wholesale_product_id),
                KEY idx_breakdown_retail (retail_product_id),
                CONSTRAINT product_breakdown_links_ibfk_1 FOREIGN KEY (wholesale_product_id) REFERENCES products(id) ON DELETE CASCADE,
                CONSTRAINT product_breakdown_links_ibfk_2 FOREIGN KEY (retail_product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Created product_breakdown_links table<br>";
    }

    $breakdownLinkCount = (int) $db->query("SELECT COUNT(*) FROM product_breakdown_links")->fetchColumn();
    if ($breakdownLinkCount === 0) {
        $db->exec("
            INSERT INTO product_breakdown_links (wholesale_product_id, retail_product_id, retail_units_per_wholesale, created_at, updated_at)
            SELECT 5, 4, 20, NOW(), NOW()
            WHERE EXISTS (SELECT 1 FROM products WHERE id = 5 AND product_type = 'wholesale')
              AND EXISTS (SELECT 1 FROM products WHERE id = 4 AND product_type = 'retail')
        ");
        echo "Seeded default wholesale breakdown mapping<br>";
    }

    $refundTables = $db->query("SHOW TABLES LIKE 'order_refunds'")->fetchAll();
    if (empty($refundTables)) {
        echo "<p>Creating order_refunds table...</p>";
        $db->exec("
            CREATE TABLE order_refunds (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                refund_number VARCHAR(30) NOT NULL,
                order_id INT NOT NULL,
                refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                reason VARCHAR(255) DEFAULT NULL,
                refunded_by INT DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY refund_number (refund_number),
                UNIQUE KEY unique_order_refund (order_id),
                KEY idx_order_refunds_order (order_id),
                KEY idx_order_refunds_user (refunded_by),
                CONSTRAINT order_refunds_ibfk_1 FOREIGN KEY (order_id) REFERENCES orders(id),
                CONSTRAINT order_refunds_ibfk_2 FOREIGN KEY (refunded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Created order_refunds table<br>";
    }

    $supplierTables = $db->query("SHOW TABLES LIKE 'suppliers'")->fetchAll();
    if (empty($supplierTables)) {
        echo "<p>Creating suppliers table...</p>";
        $db->exec("
            CREATE TABLE suppliers (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                contact_person VARCHAR(100) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                email VARCHAR(100) DEFAULT NULL,
                address TEXT,
                status ENUM('active','inactive') DEFAULT 'active',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_suppliers_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Created suppliers table<br>";
    }

    $receivingTables = $db->query("SHOW TABLES LIKE 'supplier_receivings'")->fetchAll();
    if (empty($receivingTables)) {
        echo "<p>Creating supplier_receivings table...</p>";
        $db->exec("
            CREATE TABLE supplier_receivings (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                receiving_number VARCHAR(30) NOT NULL,
                supplier_id INT NOT NULL,
                invoice_number VARCHAR(100) DEFAULT NULL,
                received_date DATE NOT NULL,
                total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                notes TEXT,
                status ENUM('received','cancelled') NOT NULL DEFAULT 'received',
                received_by INT DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY receiving_number (receiving_number),
                KEY idx_supplier_receivings_supplier (supplier_id),
                KEY idx_supplier_receivings_date (received_date),
                KEY idx_supplier_receivings_user (received_by),
                CONSTRAINT supplier_receivings_ibfk_1 FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
                CONSTRAINT supplier_receivings_ibfk_2 FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Created supplier_receivings table<br>";
    }

    $receivingColumns = $db->query("DESCRIBE supplier_receivings")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('purchase_order_number', $receivingColumns, true)) {
        $db->exec("ALTER TABLE supplier_receivings ADD COLUMN purchase_order_number VARCHAR(100) DEFAULT NULL AFTER supplier_id");
        echo "Added purchase_order_number column<br>";
    }

    $receivingItemTables = $db->query("SHOW TABLES LIKE 'supplier_receiving_items'")->fetchAll();
    if (empty($receivingItemTables)) {
        echo "<p>Creating supplier_receiving_items table...</p>";
        $db->exec("
            CREATE TABLE supplier_receiving_items (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                receiving_id INT NOT NULL,
                product_id INT NOT NULL,
                product_batch_id INT DEFAULT NULL,
                quantity INT NOT NULL,
                unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                expiry_date DATE DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_receiving_items_receiving (receiving_id),
                KEY idx_receiving_items_product (product_id),
                KEY idx_receiving_items_batch (product_batch_id),
                CONSTRAINT supplier_receiving_items_ibfk_1 FOREIGN KEY (receiving_id) REFERENCES supplier_receivings(id) ON DELETE CASCADE,
                CONSTRAINT supplier_receiving_items_ibfk_2 FOREIGN KEY (product_id) REFERENCES products(id),
                CONSTRAINT supplier_receiving_items_ibfk_3 FOREIGN KEY (product_batch_id) REFERENCES product_batches(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "Created supplier_receiving_items table<br>";
    }

    $supplierCount = (int) $db->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    if ($supplierCount === 0) {
        $db->exec("
            INSERT INTO suppliers (name, contact_person, phone, email, address, status) VALUES
            ('Metro Snacks Distributor', 'Ana Reyes', '0917-555-0141', 'orders@metrosnacks.example', 'Manila, Philippines', 'active'),
            ('Pinoy Wholesale Mart', 'Ramon Cruz', '0918-555-0222', 'sales@pinoywholesale.example', 'Quezon City, Philippines', 'active'),
            ('Sweet Treats Supply Co.', 'Liza Santos', '0919-555-0333', 'supply@sweettreats.example', 'Pasig City, Philippines', 'active')
        ");
        echo "Seeded default suppliers<br>";
    }

    $legacyProducts = $db->query("
        SELECT p.id, p.stock_quantity, p.expiry
        FROM products p
        LEFT JOIN product_batches pb ON pb.product_id = p.id
        WHERE pb.id IS NULL AND p.stock_quantity > 0
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($legacyProducts)) {
        echo "<p>Backfilling legacy product stock into product_batches...</p>";
        $batchInsert = $db->prepare("
            INSERT INTO product_batches (product_id, quantity, expiry_date, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");

        foreach ($legacyProducts as $product) {
            $batchInsert->execute([
                $product['id'],
                (int) $product['stock_quantity'],
                $product['expiry'] ?: null
            ]);
        }

        echo "✓ Backfilled legacy stock batches<br>";
    }

    $syncProducts = $db->query("
        SELECT
            p.id,
            COALESCE(SUM(pb.quantity), 0) as total_quantity,
            MIN(CASE WHEN pb.quantity > 0 AND pb.expiry_date IS NOT NULL THEN pb.expiry_date END) as nearest_expiry
        FROM products p
        LEFT JOIN product_batches pb ON pb.product_id = p.id
        GROUP BY p.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($syncProducts)) {
        $syncStmt = $db->prepare("UPDATE products SET stock_quantity = ?, expiry = ? WHERE id = ?");
        foreach ($syncProducts as $product) {
            $syncStmt->execute([
                (int) ($product['total_quantity'] ?? 0),
                $product['nearest_expiry'] ?: null,
                (int) $product['id']
            ]);
        }
        echo "✓ Synced product stock totals from batches<br>";
    }

    echo "<br><h3 style='color: green;'>✅ Migration completed successfully!</h3>";
    echo "<p>Your database is now ready for batch-based expiry tracking.</p>";
    echo "<p><a href='dashboard.php'>Go to Dashboard</a> | <a href='cashier/pos.php'>Go to POS</a> | <a href='reports.php'>View Reports</a></p>";
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Migration failed!</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>
