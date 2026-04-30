<?php
class POSController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    private function deductProductBatches($productId, $quantity) {
        $remaining = (int) $quantity;
        if ($remaining <= 0) {
            return;
        }

        $batchStmt = $this->db->prepare("
            SELECT id, quantity
            FROM product_batches
            WHERE product_id = ? AND quantity > 0
            ORDER BY
                CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END,
                expiry_date ASC,
                id ASC
            FOR UPDATE
        ");
        $batchStmt->execute([(int) $productId]);

        $updateBatchStmt = $this->db->prepare("UPDATE product_batches SET quantity = ?, updated_at = NOW() WHERE id = ?");
        foreach ($batchStmt->fetchAll(PDO::FETCH_ASSOC) as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min((int) $batch['quantity'], $remaining);
            $updateBatchStmt->execute([(int) $batch['quantity'] - $deduct, (int) $batch['id']]);
            $remaining -= $deduct;
        }
    }
    
    public function getProducts($search = '', $category = '', $productType = '') {
        try {
            $sql = "
                SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.status = 'active' AND p.stock_quantity > 0
            ";
            $params = [];
            
            if (!empty($search)) {
                $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ? OR p.id = ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
                $params[] = is_numeric($search) ? intval($search) : 0;
            }
            
            if (!empty($category) && ctype_digit((string) $category)) {
                $sql .= " AND p.category_id = ?";
                $params[] = (int) $category;
            }

            if (in_array($productType, ['retail', 'wholesale'], true)) {
                $sql .= " AND p.product_type = ?";
                $params[] = $productType;
            }
            
            $sql .= " ORDER BY p.name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getCategories() {
        try {
            $stmt = $this->db->query("
                SELECT DISTINCT c.id, c.name
                FROM categories c
                INNER JOIN products p ON p.category_id = c.id
                WHERE p.status = 'active' AND p.stock_quantity > 0
                ORDER BY c.name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function createOrder($items, $payment_method = 'cash', $amount_received = 0, $discount_amount = 0) {
        try {
            // Set timezone to Philippines (Manila)
            date_default_timezone_set('Asia/Manila');
            
            $this->db->beginTransaction();
            
            // Generate order number with proper timezone
            $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $created_at = date('Y-m-d H:i:s');
            
            // Calculate total
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            
            // Product prices are tax-inclusive. Discount is entered as a peso amount.
            $discount_amount = max(0, min((float) $discount_amount, (float) $subtotal));
            $gross_total = max(0, $subtotal - $discount_amount);
            $net_sales = $gross_total / 1.12;
            $tax_amount = $gross_total - $net_sales;
            $discount_percent = $subtotal > 0 ? ($discount_amount / $subtotal) * 100 : 0;
            
            // Create order with fallback for older database schema
            try {
                // Try new schema first
                $stmt = $this->db->prepare("
                    INSERT INTO orders (order_number, user_id, subtotal, discount_percent, discount_amount, tax_amount, total_amount, payment_method, amount_received, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
                ");
                $stmt->execute([
                    $order_number, 
                    $_SESSION['user_id'], 
                    $net_sales,
                    $discount_percent,
                    $discount_amount,
                    $tax_amount,
                    $gross_total,
                    $payment_method,
                    $amount_received,
                    $created_at
                ]);
            } catch(PDOException $e) {
                // Fallback to old schema if new columns don't exist
                $stmt = $this->db->prepare("INSERT INTO orders (order_number, user_id, total_amount, status, created_at) VALUES (?, ?, ?, 'completed', ?)");
                $stmt->execute([$order_number, $_SESSION['user_id'], $gross_total, $created_at]);
            }
            $order_id = $this->db->lastInsertId();
            
            // Add order items and update stock
            $costStmt = $this->db->prepare("SELECT cost_price FROM products WHERE id = ?");
            foreach ($items as $item) {
                $costStmt->execute([(int) $item['id']]);
                $costPriceAtSale = (float) ($costStmt->fetchColumn() ?: 0);

                // Insert order item
                $stmt = $this->db->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, cost_price_at_sale, total_price) VALUES (?, ?, ?, ?, ?, ?)");
                $item_total = $item['price'] * $item['quantity'];
                $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price'], $costPriceAtSale, $item_total]);
                
                // Update product stock
                $this->deductProductBatches($item['id'], $item['quantity']);
                $stmt = $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['id']]);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'order_number' => $order_number,
                'subtotal' => $subtotal,
                'net_sales' => $net_sales,
                'discount' => $discount_amount,
                'tax' => $tax_amount,
                'total' => $gross_total,
                'change' => $amount_received - $gross_total
            ];
            
        } catch(PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getProductById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.id = ? AND p.status = 'active'
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>
