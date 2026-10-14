<?php
// CategoryManager.php
class CategoryManager {
    private $db;
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    /**
     * Créer une nouvelle catégorie
     */
    public function createCategory($data) {
        $sql = "INSERT INTO categories (nom, slug, description, image_url, parent_id, ordre_affichage, est_actif) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $slug = $this->generateSlug($data['nom']);
        
        return $stmt->execute([
            $data['nom'],
            $slug,
            $data['description'] ?? null,
            $data['image_url'] ?? null,
            $data['parent_id'] ?? null,
            $data['ordre_affichage'] ?? 0,
            $data['est_actif'] ?? 1
        ]);
    }
    
    /**
     * Obtenir toutes les catégories
     */
    public function getAllCategories($activeOnly = false) {
        $sql = "SELECT * FROM categories";
        if ($activeOnly) {
            $sql .= " WHERE est_actif = 1";
        }
        $sql .= " ORDER BY parent_id, ordre_affichage, nom";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $this->buildCategoryTree($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    /**
     * Construire l'arborescence des catégories
     */
    private function buildCategoryTree($categories, $parentId = null) {
        $branch = [];
        
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $children = $this->buildCategoryTree($categories, $category['id']);
                if ($children) {
                    $category['children'] = $children;
                }
                $branch[] = $category;
            }
        }
        
        return $branch;
    }
    
    /**
     * Obtenir une catégorie par son ID
     */
    public function getCategoryById($id) {
        $sql = "SELECT c.*, 
                       parent.nom as parent_nom 
                FROM categories c 
                LEFT JOIN categories parent ON c.parent_id = parent.id 
                WHERE c.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les produits d'une catégorie
     */
    public function getProductsByCategory($categoryId, $limit = null, $offset = 0) {
        $sql = "SELECT p.* 
                FROM produits p 
                INNER JOIN produit_categories pc ON p.id = pc.produit_id 
                WHERE pc.categorie_id = ? AND p.est_en_stock = 1";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$categoryId, $limit, $offset]);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$categoryId]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mettre à jour une catégorie
     */
    public function updateCategory($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key === 'nom' && isset($data['nom'])) {
                $data['slug'] = $this->generateSlug($data['nom']);
                $fields[] = "slug = ?";
                $values[] = $data['slug'];
            }
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Supprimer une catégorie
     */
    public function deleteCategory($id) {
        // Vérifier s'il y a des sous-catégories
        $sqlCheck = "SELECT COUNT(*) as count FROM categories WHERE parent_id = ?";
        $stmt = $this->db->prepare($sqlCheck);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception("Impossible de supprimer : catégorie a des sous-catégories");
        }
        
        $sql = "DELETE FROM categories WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Générer un slug URL-friendly
     */
    private function generateSlug($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        
        if (empty($text)) {
            return 'n-a';
        }
        
        return $text;
    }
    
    /**
     * Obtenir le chemin de la catégorie (breadcrumb)
     */
    public function getCategoryPath($categoryId) {
        $path = [];
        $current = $this->getCategoryById($categoryId);
        
        while ($current) {
            array_unshift($path, $current);
            if ($current['parent_id']) {
                $current = $this->getCategoryById($current['parent_id']);
            } else {
                $current = null;
            }
        }
        
        return $path;

    }
    
}

