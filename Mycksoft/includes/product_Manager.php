<?php
// ProductManager.php
class ProductManager {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    public function getTotalProductsCount() {
        $sql = "SELECT COUNT(*) as count FROM produits";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function getLowStockProductsCount() {
        $sql = "SELECT COUNT(*) as count FROM produits WHERE quantite_stock <= 5 AND quantite_stock > 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
?>