<?php
class DashboardController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getStats() {
        try {
            $stats = [];
            
            // Total Sales
            $stmt = $this->db->query("SELECT SUM(total_amount) as total_sales FROM orders WHERE status = 'completed'");
            $stats['total_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;
            
            // Total Products
            $stmt = $this->db->query("SELECT COUNT(*) as total_products FROM products WHERE status = 'active'");
            $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;
            
            // Total Orders
            $stmt = $this->db->query("SELECT COUNT(*) as total_orders FROM orders");
            $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;
            
            return $stats;
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getRecentOrders($limit = 5) {
        try {
            $limit = max(1, (int) $limit);
            $stmt = $this->db->query("SELECT o.*, u.first_name, u.last_name FROM orders o 
                                      LEFT JOIN users u ON o.user_id = u.id 
                                      ORDER BY o.created_at DESC LIMIT {$limit}");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getLowStockProducts($limit = 5) {
        try {
            $limit = max(1, (int) $limit);
            $stmt = $this->db->query("SELECT * FROM products WHERE stock_quantity <= 10 AND status = 'active' 
                                      ORDER BY stock_quantity ASC LIMIT {$limit}");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return [];
        }
    }
}
?>
