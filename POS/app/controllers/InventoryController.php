<?php
class InventoryController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    private function generatedSku($productId) {
        return 'SKU-' . str_pad((int) $productId, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeBatches($data) {
        $batches = [];
        $quantities = $data['batch_quantity'] ?? [];
        $expiries = $data['batch_expiry'] ?? [];

        if (is_array($quantities)) {
            $count = max(count($quantities), count($expiries));
            for ($index = 0; $index < $count; $index++) {
                $quantity = (int) ($quantities[$index] ?? 0);
                $expiry = trim((string) ($expiries[$index] ?? ''));

                if ($quantity <= 0) {
                    continue;
                }

                $batches[] = [
                    'quantity' => $quantity,
                    'expiry_date' => $expiry !== '' ? $expiry : null,
                ];
            }
        }

        if (empty($batches)) {
            $legacyQuantity = (int) ($data['stock_quantity'] ?? 0);
            $legacyExpiry = trim((string) ($data['expiry'] ?? ''));

            if ($legacyQuantity > 0) {
                $batches[] = [
                    'quantity' => $legacyQuantity,
                    'expiry_date' => $legacyExpiry !== '' ? $legacyExpiry : null,
                ];
            }
        }

        return $batches;
    }

    private function replaceProductBatches($productId, $batches) {
        $deleteStmt = $this->db->prepare("DELETE FROM product_batches WHERE product_id = ?");
        $deleteStmt->execute([$productId]);

        if (empty($batches)) {
            return;
        }

        $insertStmt = $this->db->prepare("
            INSERT INTO product_batches (product_id, quantity, expiry_date, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");

        foreach ($batches as $batch) {
            $insertStmt->execute([
                $productId,
                (int) $batch['quantity'],
                $batch['expiry_date'] ?: null,
            ]);
        }
    }

    private function syncProductInventorySummary($productId) {
        $summaryStmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(quantity), 0) as total_quantity,
                MIN(CASE WHEN quantity > 0 AND expiry_date IS NOT NULL THEN expiry_date END) as nearest_expiry
            FROM product_batches
            WHERE product_id = ?
        ");
        $summaryStmt->execute([$productId]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $updateStmt = $this->db->prepare("
            UPDATE products
            SET stock_quantity = ?, expiry = ?, last_updated_by = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            (int) ($summary['total_quantity'] ?? 0),
            $summary['nearest_expiry'] ?? null,
            $_SESSION['user_id'] ?? null,
            $productId
        ]);
    }

    public function getProductBatches($productId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, product_id, quantity, expiry_date
                FROM product_batches
                WHERE product_id = ?
                ORDER BY
                    CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END,
                    expiry_date ASC,
                    id ASC
            ");
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getBatchesByProductIds($productIds) {
        if (empty($productIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $this->db->prepare("
                SELECT id, product_id, quantity, expiry_date
                FROM product_batches
                WHERE product_id IN ($placeholders)
                ORDER BY
                    product_id ASC,
                    CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END,
                    expiry_date ASC,
                    id ASC
            ");
            $stmt->execute(array_values($productIds));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row['product_id']][] = $row;
            }

            return $grouped;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getBatchExpiryAlerts($daysAhead = 7) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    pb.id,
                    pb.product_id,
                    pb.quantity,
                    pb.expiry_date,
                    p.name as product_name,
                    p.barcode,
                    c.name as category_name
                FROM product_batches pb
                INNER JOIN products p ON p.id = pb.product_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.status = 'active'
                  AND pb.quantity > 0
                  AND pb.expiry_date IS NOT NULL
                  AND pb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY pb.expiry_date ASC, p.name ASC
            ");
            $stmt->execute([(int) $daysAhead]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $expired = [];
            $expiring = [];
            $today = new DateTime();

            foreach ($rows as $row) {
                $expiryDate = new DateTime($row['expiry_date']);
                $daysUntilExpiry = (int) $today->diff($expiryDate)->format('%R%a');
                $row['days_until_expiry'] = $daysUntilExpiry;

                if ($daysUntilExpiry < 0) {
                    $expired[] = $row;
                } else {
                    $expiring[] = $row;
                }
            }

            return [
                'expired' => $expired,
                'expiring' => $expiring,
            ];
        } catch (PDOException $e) {
            return ['expired' => [], 'expiring' => []];
        }
    }
    
    public function getStats() {
        try {
            $stats = [];
            
            // Total Products
            $stmt = $this->db->query("SELECT COUNT(*) as total_products FROM products WHERE status = 'active'");
            $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;
            
            // Low Stock (quantity <= low_stock_threshold AND > 0)
            $stmt = $this->db->query("SELECT COUNT(*) as low_stock FROM products WHERE stock_quantity <= low_stock_threshold AND stock_quantity > 0 AND status = 'active'");
            $stats['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'] ?? 0;
            
            // Out of Stock
            $stmt = $this->db->query("SELECT COUNT(*) as out_of_stock FROM products WHERE stock_quantity = 0 AND status = 'active'");
            $stats['out_of_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['out_of_stock'] ?? 0;
            
            // In Stock (quantity > low_stock_threshold)
            $stmt = $this->db->query("SELECT COUNT(*) as in_stock FROM products WHERE stock_quantity > low_stock_threshold AND status = 'active'");
            $stats['in_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['in_stock'] ?? 0;
            
            return $stats;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getAllProducts($search = '', $category_id = '', $stock_filter = '', $product_type = '') {
        try {
            $sql = "SELECT p.*,
                           c.name as category_name,
                           COALESCE(pb.total_quantity, p.stock_quantity) as stock_quantity,
                           COALESCE(pb.nearest_expiry, p.expiry) as expiry,
                           COALESCE(pb.batch_count, 0) as batch_count
                    FROM products p 
                    LEFT JOIN (
                        SELECT
                            product_id,
                            COALESCE(SUM(quantity), 0) as total_quantity,
                            MIN(CASE WHEN quantity > 0 AND expiry_date IS NOT NULL THEN expiry_date END) as nearest_expiry,
                            COUNT(*) as batch_count
                        FROM product_batches
                        GROUP BY product_id
                    ) pb ON pb.product_id = p.id
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.status = 'active'";
            $params = [];
            
            if (!empty($search)) {
                // Handle generated SKU format (SKU-XXXX) and numeric ID
                $search_id = null;
                
                // Check if search matches SKU format (case-insensitive)
                if (preg_match('/^sku-(\d+)$/i', $search, $matches)) {
                    $search_id = intval($matches[1]);
                }
                // Check if search is just a number (could be product ID)
                elseif (is_numeric($search) && (int)$search > 0) {
                    $search_id = intval($search);
                }
                
                if ($search_id !== null) {
                    // If search matches SKU format or is numeric, search by product ID as well
                    $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ? OR p.id = ?)";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                    $params[] = $search_id;
                } else {
                    // Regular search by name, barcode, and SKU column
                    $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                }
            }
            
            if (!empty($category_id)) {
                $sql .= " AND p.category_id = ?";
                $params[] = $category_id;
            }

            if (in_array($product_type, ['retail', 'wholesale'], true)) {
                $sql .= " AND p.product_type = ?";
                $params[] = $product_type;
            }
            
            // Stock filter
            if (!empty($stock_filter)) {
                switch ($stock_filter) {
                    case 'low_stock':
                        $sql .= " AND COALESCE(pb.total_quantity, p.stock_quantity) <= p.low_stock_threshold AND COALESCE(pb.total_quantity, p.stock_quantity) > 0";
                        break;
                    case 'out_of_stock':
                        $sql .= " AND COALESCE(pb.total_quantity, p.stock_quantity) = 0";
                        break;
                    case 'in_stock':
                        $sql .= " AND COALESCE(pb.total_quantity, p.stock_quantity) > p.low_stock_threshold";
                        break;
                }
            }
            
            $sql .= " ORDER BY p.name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getAllCategories() {
        try {
            $stmt = $this->db->query("SELECT * FROM categories ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }

    public function addCategory($data) {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        if ($name === '') {
            return false;
        }

        try {
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?)");
            $checkStmt->execute([$name]);
            if ((int) $checkStmt->fetchColumn() > 0) {
                return false;
            }

            $stmt = $this->db->prepare("
                INSERT INTO categories (name, description, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$name, $description !== '' ? $description : null]);
            return true;
        } catch (PDOException $e) {
            error_log("Add category error: " . $e->getMessage());
            return false;
        }
    }

    public function getProductsByType($productType) {
        if (!in_array($productType, ['retail', 'wholesale'], true)) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, name, stock_quantity, cost_price
                FROM products
                WHERE status = 'active' AND product_type = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$productType]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getBreakdownLinks() {
        try {
            $stmt = $this->db->query("
                SELECT
                    pbl.*,
                    wp.name as wholesale_name,
                    wp.stock_quantity as wholesale_stock,
                    rp.name as retail_name,
                    rp.stock_quantity as retail_stock,
                    rp.low_stock_threshold as retail_low_stock_threshold
                FROM product_breakdown_links pbl
                INNER JOIN products wp ON wp.id = pbl.wholesale_product_id
                INNER JOIN products rp ON rp.id = pbl.retail_product_id
                WHERE wp.status = 'active'
                  AND rp.status = 'active'
                ORDER BY wp.name ASC, rp.name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function saveBreakdownLink($data) {
        $wholesaleProductId = (int) ($data['wholesale_product_id'] ?? 0);
        $retailProductId = (int) ($data['retail_product_id'] ?? 0);
        $retailUnitsPerWholesale = (int) ($data['retail_units_per_wholesale'] ?? 0);

        if ($wholesaleProductId <= 0 || $retailProductId <= 0 || $retailUnitsPerWholesale <= 0 || $wholesaleProductId === $retailProductId) {
            return false;
        }

        try {
            $typeStmt = $this->db->prepare("
                SELECT id, product_type
                FROM products
                WHERE id IN (?, ?) AND status = 'active'
            ");
            $typeStmt->execute([$wholesaleProductId, $retailProductId]);
            $types = [];
            foreach ($typeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $types[(int) $row['id']] = $row['product_type'];
            }

            if (($types[$wholesaleProductId] ?? '') !== 'wholesale' || ($types[$retailProductId] ?? '') !== 'retail') {
                return false;
            }

            $stmt = $this->db->prepare("
                INSERT INTO product_breakdown_links (wholesale_product_id, retail_product_id, retail_units_per_wholesale, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    retail_units_per_wholesale = VALUES(retail_units_per_wholesale),
                    updated_at = NOW()
            ");
            $stmt->execute([$wholesaleProductId, $retailProductId, $retailUnitsPerWholesale]);
            return true;
        } catch (PDOException $e) {
            error_log("Save breakdown link error: " . $e->getMessage());
            return false;
        }
    }

    public function breakdownWholesaleStock($data) {
        $linkId = (int) ($data['breakdown_link_id'] ?? 0);
        $wholesaleQuantity = (int) ($data['wholesale_quantity'] ?? 0);
        $retailExpiry = trim((string) ($data['retail_expiry'] ?? ''));
        $retailExpiry = $retailExpiry !== '' ? $retailExpiry : null;
        $userId = $_SESSION['user_id'] ?? null;

        if ($linkId <= 0 || $wholesaleQuantity <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $linkStmt = $this->db->prepare("
                SELECT
                    pbl.*,
                    wp.stock_quantity as wholesale_stock,
                    wp.cost_price as wholesale_cost,
                    rp.cost_price as retail_cost
                FROM product_breakdown_links pbl
                INNER JOIN products wp ON wp.id = pbl.wholesale_product_id AND wp.product_type = 'wholesale' AND wp.status = 'active'
                INNER JOIN products rp ON rp.id = pbl.retail_product_id AND rp.product_type = 'retail' AND rp.status = 'active'
                WHERE pbl.id = ?
                FOR UPDATE
            ");
            $linkStmt->execute([$linkId]);
            $link = $linkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$link || (int) $link['wholesale_stock'] < $wholesaleQuantity) {
                $this->db->rollBack();
                return false;
            }

            $wholesaleProductId = (int) $link['wholesale_product_id'];
            $retailProductId = (int) $link['retail_product_id'];
            $retailUnits = $wholesaleQuantity * (int) $link['retail_units_per_wholesale'];

            $beforeStmt = $this->db->prepare("SELECT id, stock_quantity FROM products WHERE id IN (?, ?) FOR UPDATE");
            $beforeStmt->execute([$wholesaleProductId, $retailProductId]);
            $before = [];
            foreach ($beforeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $before[(int) $row['id']] = (int) $row['stock_quantity'];
            }

            $batchStmt = $this->db->prepare("
                SELECT id, quantity, expiry_date
                FROM product_batches
                WHERE product_id = ? AND quantity > 0
                ORDER BY
                    CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END,
                    expiry_date ASC,
                    id ASC
                FOR UPDATE
            ");
            $batchStmt->execute([$wholesaleProductId]);
            $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
            $remaining = $wholesaleQuantity;
            $nearestConsumedExpiry = null;

            $updateBatchStmt = $this->db->prepare("UPDATE product_batches SET quantity = ?, updated_at = NOW() WHERE id = ?");
            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $deduct = min((int) $batch['quantity'], $remaining);
                $newQuantity = (int) $batch['quantity'] - $deduct;
                $updateBatchStmt->execute([$newQuantity, (int) $batch['id']]);
                $remaining -= $deduct;

                if (!empty($batch['expiry_date']) && ($nearestConsumedExpiry === null || $batch['expiry_date'] < $nearestConsumedExpiry)) {
                    $nearestConsumedExpiry = $batch['expiry_date'];
                }
            }

            if ($remaining > 0) {
                $this->db->rollBack();
                return false;
            }

            if ($retailExpiry === null) {
                $retailExpiry = $nearestConsumedExpiry;
            }

            $retailUnitCost = 0;
            if ((float) $link['wholesale_cost'] > 0 && (int) $link['retail_units_per_wholesale'] > 0) {
                $retailUnitCost = round((float) $link['wholesale_cost'] / (int) $link['retail_units_per_wholesale'], 2);
            }

            $insertRetailBatchStmt = $this->db->prepare("
                INSERT INTO product_batches (product_id, quantity, expiry_date, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $insertRetailBatchStmt->execute([$retailProductId, $retailUnits, $retailExpiry]);

            if ($retailUnitCost > 0) {
                $costStmt = $this->db->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                $costStmt->execute([$retailUnitCost, $retailProductId]);
            }

            $this->syncProductInventorySummary($wholesaleProductId);
            $this->syncProductInventorySummary($retailProductId);

            $afterStmt = $this->db->prepare("SELECT id, stock_quantity FROM products WHERE id IN (?, ?)");
            $afterStmt->execute([$wholesaleProductId, $retailProductId]);
            $after = [];
            foreach ($afterStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $after[(int) $row['id']] = (int) $row['stock_quantity'];
            }

            if ($userId) {
                $reportStmt = $this->db->prepare("
                    INSERT INTO inventory_reports
                        (product_id, change_type, quantity, quantity_changed, previous_quantity, new_quantity, date, remarks, user_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, NOW())
                ");
                $reportStmt->execute([
                    $wholesaleProductId,
                    'Removed',
                    $wholesaleQuantity,
                    $wholesaleQuantity,
                    $before[$wholesaleProductId] ?? null,
                    $after[$wholesaleProductId] ?? null,
                    'Breakdown to retail',
                    $userId
                ]);
                $reportStmt->execute([
                    $retailProductId,
                    'Added',
                    $retailUnits,
                    $retailUnits,
                    $before[$retailProductId] ?? null,
                    $after[$retailProductId] ?? null,
                    'Breakdown from wholesale',
                    $userId
                ]);
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Breakdown stock error: " . $e->getMessage());
            return false;
        }
    }
    
    public function addProduct($data) {
        try {
            $batches = $this->normalizeBatches($data);
            $initialQuantity = array_sum(array_map(function ($batch) {
                return (int) $batch['quantity'];
            }, $batches));
            $nearestExpiry = null;
            foreach ($batches as $batch) {
                if (!empty($batch['expiry_date']) && ($nearestExpiry === null || $batch['expiry_date'] < $nearestExpiry)) {
                    $nearestExpiry = $batch['expiry_date'];
                }
            }

            $stmt = $this->db->prepare("
                INSERT INTO products (name, category_id, product_type, price, cost_price, stock_quantity, barcode, expiry, last_updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                !empty($data['category_id']) ? (int) $data['category_id'] : null,
                in_array($data['product_type'] ?? '', ['retail', 'wholesale'], true) ? $data['product_type'] : 'retail',
                $data['price'],
                $data['cost_price'] ?? 0,
                $initialQuantity,
                $data['barcode'] ?: null,
                $nearestExpiry,
                $_SESSION['user_id'] ?? null
            ]);
            $productId = (int) $this->db->lastInsertId();
            $skuStmt = $this->db->prepare("UPDATE products SET sku = ? WHERE id = ?");
            $skuStmt->execute([$this->generatedSku($productId), $productId]);
            $this->replaceProductBatches($productId, $batches);
            $this->syncProductInventorySummary($productId);
            return true;
        } catch(PDOException $e) {
            error_log("Add product error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateProduct($id, $data) {
        try {
            $batches = $this->normalizeBatches($data);

            // Get old stock quantity to track changes
            $oldStockStmt = $this->db->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
            $oldStockStmt->execute([$id]);
            $oldProduct = $oldStockStmt->fetch(PDO::FETCH_ASSOC);
            $old_stock = intval($oldProduct['stock_quantity']);
            
            // Update product with user tracking
            $user_id = $_SESSION['user_id'] ?? null;
            $stmt = $this->db->prepare("
                UPDATE products
                SET name = ?, sku = ?, category_id = ?, product_type = ?, price = ?, cost_price = ?, barcode = ?, last_updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $this->generatedSku($id),
                !empty($data['category_id']) ? (int) $data['category_id'] : null,
                in_array($data['product_type'] ?? '', ['retail', 'wholesale'], true) ? $data['product_type'] : 'retail',
                $data['price'],
                $data['cost_price'] ?? 0,
                $data['barcode'] ?: null,
                $user_id,
                $id
            ]);

            $this->replaceProductBatches($id, $batches);
            $this->syncProductInventorySummary($id);

            $newStockStmt = $this->db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $newStockStmt->execute([$id]);
            $new_stock = (int) $newStockStmt->fetchColumn();
            $stock_change = $new_stock - $old_stock;
            
            // Record inventory change in inventory_reports if stock quantity changed
            if ($stock_change != 0 && $user_id) {
                $change_type = ($stock_change > 0) ? 'Added' : 'Removed';
                $quantity_changed = abs($stock_change);
                $user_role = $_SESSION['role'] ?? 'admin';
                $remarks = ($stock_change > 0) 
                    ? "Stock added by $user_role. Previous: $old_stock, New: $new_stock" 
                    : "Stock removed by $user_role. Previous: $old_stock, New: $new_stock";
                
                $reportStmt = $this->db->prepare("INSERT INTO inventory_reports (product_id, change_type, quantity, quantity_changed, previous_quantity, new_quantity, date, remarks, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())");
                $reportStmt->execute([
                    $id,
                    $change_type,
                    $quantity_changed,  // Use same value for 'quantity' (legacy column)
                    $quantity_changed,
                    $old_stock,
                    $new_stock,
                    $remarks,
                    $user_id
                ]);
            }
            
            return true;
        } catch(PDOException $e) {
            error_log("Update product error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteProduct($id) {
        try {
            $stmt = $this->db->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>
