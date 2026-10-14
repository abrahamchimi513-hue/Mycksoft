<?php
// classes/Panier.php

// Vérifier que la session est démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Panier {
    private $pdo;
    private $user_id;
    private $panier_id;
    private $items = [];
    
    /**
     * Constructeur
     */
    public function __construct($pdo, $user_id = null) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        
        if ($this->user_id) {
            $this->loadUserCart();
        } else {
            $this->loadSessionCart();
        }
    }
    
    /**
     * Charger le panier de l'utilisateur depuis la BD
     */
    private function loadUserCart() {
        try {
            if (!$this->pdo) return;
            
            $stmt = $this->pdo->prepare("
                SELECT id_panier FROM panier 
                WHERE id_utilisateur = ? 
                ORDER BY date_creation DESC LIMIT 1
            ");
            $stmt->execute([$this->user_id]);
            $cart = $stmt->fetch();
            
            if ($cart) {
                $this->panier_id = $cart['id_panier'];
                $this->loadUserCartItems();
            } else {
                $this->createUserCart();
            }
        } catch (PDOException $e) {
            error_log("Erreur chargement panier: " . $e->getMessage());
        }
    }
    
    /**
     * Créer un nouveau panier pour l'utilisateur
     */
    private function createUserCart() {
        try {
            if (!$this->pdo) return;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO panier (id_utilisateur, date_creation) 
                VALUES (?, NOW())
            ");
            $stmt->execute([$this->user_id]);
            $this->panier_id = $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur création panier: " . $e->getMessage());
        }
    }
    
    /**
     * Charger les articles du panier utilisateur
     */
    private function loadUserCartItems() {
        try {
            if (!$this->pdo) return;
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    lp.id_ligne_panier as item_id,
                    lp.id_produit as product_id,
                    lp.quantite as quantity,
                    lp.prix_unitaire as price,
                    p.nom_produit as name,
                    p.description_courte as description,
                    p.stock,
                    p.prix_promotion,
                    p.est_actif,
                    ip.url_image as image
                FROM ligne_panier lp
                JOIN produit p ON lp.id_produit = p.id_produit
                LEFT JOIN image_produit ip ON p.id_produit = ip.id_produit AND ip.est_principale = 1
                WHERE lp.id_panier = ?
                ORDER BY lp.date_ajout DESC
            ");
            $stmt->execute([$this->panier_id]);
            $this->items = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur chargement articles: " . $e->getMessage());
            $this->items = [];
        }
    }
    
    /**
     * Charger le panier depuis la session
     */
    private function loadSessionCart() {
        $this->items = $_SESSION['panier'] ?? [];
    }
    
    /**
     * Ajouter un produit au panier
     */
    public function add($product_id, $quantity = 1) {
        $quantity = max(1, min(99, intval($quantity)));
        
        $product = $this->getProduct($product_id);
        if (!$product || !$product['est_actif']) {
            return ['success' => false, 'message' => 'Produit non disponible'];
        }
        
        if ($product['stock'] < $quantity) {
            return ['success' => false, 'message' => 'Stock insuffisant ('.$product['stock'].' disponible)'];
        }
        
        $price = $product['prix_promotion'] ?? $product['prix'];
        
        if ($this->user_id) {
            return $this->addToDatabase($product_id, $quantity, $price, $product);
        } else {
            return $this->addToSession($product_id, $quantity, $price, $product);
        }
    }
    
    /**
     * Ajouter à la base de données
     */
    private function addToDatabase($product_id, $quantity, $price, $product) {
        try {
            if (!$this->pdo) {
                return ['success' => false, 'message' => 'Erreur de connexion'];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM ligne_panier 
                WHERE id_panier = ? AND id_produit = ?
            ");
            $stmt->execute([$this->panier_id, $product_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $new_quantity = $existing['quantite'] + $quantity;
                $stmt = $this->pdo->prepare("
                    UPDATE ligne_panier 
                    SET quantite = ? 
                    WHERE id_ligne_panier = ?
                ");
                $stmt->execute([$new_quantity, $existing['id_ligne_panier']]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO ligne_panier 
                    (id_panier, id_produit, quantite, prix_unitaire, date_ajout) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$this->panier_id, $product_id, $quantity, $price]);
            }
            
            $this->updateCartTimestamp();
            
            return [
                'success' => true, 
                'message' => 'Produit ajouté au panier',
                'cart_count' => $this->getTotalItems()
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur ajout panier: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de l\'ajout au panier'];
        }
    }
    
    /**
     * Ajouter à la session
     */
    private function addToSession($product_id, $quantity, $price, $product) {
        $found = false;
        
        foreach ($this->items as &$item) {
            if ($item['product_id'] == $product_id) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $this->items[] = [
                'product_id' => $product_id,
                'name' => $product['nom_produit'],
                'quantity' => $quantity,
                'price' => $price,
                'original_price' => $product['prix'],
                'stock' => $product['stock'],
                'image' => $this->getProductImage($product_id)
            ];
        }
        
        $_SESSION['panier'] = $this->items;
        
        return [
            'success' => true,
            'message' => 'Produit ajouté au panier',
            'cart_count' => $this->getTotalItems()
        ];
    }
    
    /**
     * Mettre à jour la quantité
     */
    public function update($item_id, $quantity) {
        $quantity = max(1, min(99, intval($quantity)));
        
        if ($this->user_id) {
            return $this->updateDatabase($item_id, $quantity);
        } else {
            return $this->updateSession($item_id, $quantity);
        }
    }
    
    /**
     * Mettre à jour en base de données
     */
    private function updateDatabase($item_id, $quantity) {
        try {
            if (!$this->pdo) {
                return ['success' => false, 'message' => 'Erreur de connexion'];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT p.stock FROM ligne_panier lp
                JOIN produit p ON lp.id_produit = p.id_produit
                WHERE lp.id_ligne_panier = ? AND lp.id_panier = ?
            ");
            $stmt->execute([$item_id, $this->panier_id]);
            $stock = $stmt->fetchColumn();
            
            if ($stock < $quantity) {
                return ['success' => false, 'message' => 'Stock insuffisant'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE ligne_panier 
                SET quantite = ? 
                WHERE id_ligne_panier = ? AND id_panier = ?
            ");
            $stmt->execute([$quantity, $item_id, $this->panier_id]);
            
            $this->updateCartTimestamp();
            
            return ['success' => true, 'message' => 'Quantité mise à jour'];
            
        } catch (PDOException $e) {
            error_log("Erreur mise à jour: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur de mise à jour'];
        }
    }
    
    /**
     * Mettre à jour en session
     */
    private function updateSession($item_key, $quantity) {
        if (isset($this->items[$item_key])) {
            $this->items[$item_key]['quantity'] = $quantity;
            $_SESSION['panier'] = $this->items;
            return ['success' => true, 'message' => 'Quantité mise à jour'];
        }
        return ['success' => false, 'message' => 'Article non trouvé'];
    }
    
    /**
     * Supprimer un article
     */
    public function remove($item_id) {
        if ($this->user_id) {
            return $this->removeFromDatabase($item_id);
        } else {
            return $this->removeFromSession($item_id);
        }
    }
    
    /**
     * Supprimer de la base de données
     */
    private function removeFromDatabase($item_id) {
        try {
            if (!$this->pdo) {
                return ['success' => false, 'message' => 'Erreur de connexion'];
            }
            
            $stmt = $this->pdo->prepare("
                DELETE FROM ligne_panier 
                WHERE id_ligne_panier = ? AND id_panier = ?
            ");
            $stmt->execute([$item_id, $this->panier_id]);
            
            $this->updateCartTimestamp();
            
            return ['success' => true, 'message' => 'Article supprimé'];
            
        } catch (PDOException $e) {
            error_log("Erreur suppression: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur de suppression'];
        }
    }
    
    /**
     * Supprimer de la session
     */
    private function removeFromSession($item_key) {
        if (isset($this->items[$item_key])) {
            unset($this->items[$item_key]);
            $this->items = array_values($this->items);
            $_SESSION['panier'] = $this->items;
            return ['success' => true, 'message' => 'Article supprimé'];
        }
        return ['success' => false, 'message' => 'Article non trouvé'];
    }
    
    /**
     * Vider le panier
     */
    public function clear() {
        if ($this->user_id) {
            return $this->clearDatabase();
        } else {
            return $this->clearSession();
        }
    }
    
    /**
     * Vider la base de données
     */
    private function clearDatabase() {
        try {
            if (!$this->pdo) {
                return ['success' => false, 'message' => 'Erreur de connexion'];
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM ligne_panier WHERE id_panier = ?");
            $stmt->execute([$this->panier_id]);
            
            $this->updateCartTimestamp();
            
            return ['success' => true, 'message' => 'Panier vidé'];
            
        } catch (PDOException $e) {
            error_log("Erreur vidage: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur de vidage'];
        }
    }
    
    /**
     * Vider la session
     */
    private function clearSession() {
        $this->items = [];
        $_SESSION['panier'] = [];
        return ['success' => true, 'message' => 'Panier vidé'];
    }
    
    /**
     * Obtenir le contenu du panier
     */
    public function getContent() {
        if ($this->user_id && empty($this->items)) {
            $this->loadUserCartItems();
        }
        
        $result = [];
        foreach ($this->items as $item) {
            $item['final_price'] = $item['prix_promotion'] ?? $item['price'] ?? $item['original_price'] ?? 0;
            $result[] = $item;
        }
        
        return $result;
    }
    
    /**
     * Calculer les totaux
     */
    public function getTotals() {
        $content = $this->getContent();
        $subtotal = 0;
        $items_count = 0;
        
        foreach ($content as $item) {
            $price = $item['final_price'] ?? $item['price'] ?? $item['original_price'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $subtotal += $price * $quantity;
            $items_count += $quantity;
        }
        
        $free_shipping_threshold = defined('FREE_SHIPPING_THRESHOLD') ? FREE_SHIPPING_THRESHOLD : 50000;
        $shipping_fee = defined('SHIPPING_FEE') ? SHIPPING_FEE : 5000;
        
        $shipping = ($subtotal >= $free_shipping_threshold) ? 0 : $shipping_fee;
        $total = $subtotal + $shipping;
        
        return [
            'subtotal' => $subtotal,
            'subtotal_formatted' => $this->formatPrice($subtotal),
            'shipping' => $shipping,
            'shipping_formatted' => $this->formatPrice($shipping),
            'total' => $total,
            'total_formatted' => $this->formatPrice($total),
            'items_count' => $items_count,
            'unique_items' => count($content),
            'free_shipping_needed' => max(0, $free_shipping_threshold - $subtotal),
            'free_shipping_needed_formatted' => $this->formatPrice(max(0, $free_shipping_threshold - $subtotal))
        ];
    }
    
    /**
     * Formater le prix
     */
    private function formatPrice($price) {
        return number_format($price, 0, ',', ' ') . ' FCFA';
    }
    
    /**
     * Obtenir le nombre total d'articles
     */
    public function getTotalItems() {
        $content = $this->getContent();
        $total = 0;
        foreach ($content as $item) {
            $total += $item['quantity'] ?? 0;
        }
        return $total;
    }
    
    /**
     * Vérifier si le panier est vide
     */
    public function isEmpty() {
        return $this->getTotalItems() == 0;
    }
    
    /**
     * Synchroniser le panier session avec la BD
     */
    public function syncSessionCart() {
        if (!$this->user_id || empty($_SESSION['panier'])) {
            return false;
        }
        
        foreach ($_SESSION['panier'] as $item) {
            $this->add($item['product_id'], $item['quantity']);
        }
        
        $_SESSION['panier'] = [];
        $this->items = [];
        
        return true;
    }
    
    /**
     * Obtenir les informations d'un produit
     */
    private function getProduct($product_id) {
        try {
            if (!$this->pdo) return null;
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM produit 
                WHERE id_produit = ?
            ");
            $stmt->execute([$product_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erreur récupération produit: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtenir l'image d'un produit
     */
    private function getProductImage($product_id) {
        try {
            if (!$this->pdo) return null;
            
            $stmt = $this->pdo->prepare("
                SELECT url_image FROM image_produit 
                WHERE id_produit = ? AND est_principale = 1 
                LIMIT 1
            ");
            $stmt->execute([$product_id]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Mettre à jour le timestamp du panier
     */
    private function updateCartTimestamp() {
        try {
            if (!$this->pdo || !$this->panier_id) return;
            
            $stmt = $this->pdo->prepare("
                UPDATE panier SET date_modification = NOW() 
                WHERE id_panier = ?
            ");
            $stmt->execute([$this->panier_id]);
        } catch (PDOException $e) {
            // Ignorer les erreurs
        }
    }
    
    /**
     * Obtenir le résumé pour l'API
     */
    public function getSummary() {
        $totals = $this->getTotals();
        
        return [
            'success' => true,
            'items_count' => $totals['items_count'],
            'unique_items' => $totals['unique_items'],
            'subtotal' => $totals['subtotal'],
            'subtotal_formatted' => $totals['subtotal_formatted'],
            'shipping' => $totals['shipping'],
            'shipping_formatted' => $totals['shipping_formatted'],
            'total' => $totals['total'],
            'total_formatted' => $totals['total_formatted'],
            'free_shipping_needed' => $totals['free_shipping_needed']
        ];
    }
}
?>