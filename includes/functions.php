<?php
// functions.php

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: " . $url);
        exit();
    }
}

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return number_format($price, 0, ',', ' ') . ' FCFA';
    }
}

if (!function_exists('calculateDiscountPercentage')) {
    function calculateDiscountPercentage($price, $promo_price) {
        if ($promo_price && $price > $promo_price) {
            return round((($price - $promo_price) / $price) * 100);
        }
        return 0;
    }
}

if (!function_exists('isValidCameroonPhone')) {
    function isValidCameroonPhone($phone) {
        $phone = preg_replace('/\s+/', '', $phone);
        return preg_match('/^(?:\+237[0-9]{9}|[0-9]{9})$/', $phone);
    }
}

if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('isProductInStock')) {
    function isProductInStock($product_id, $quantity = 1) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT stock FROM produit WHERE id_produit = ? AND est_actif = 1");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            return $product && $product['stock'] >= $quantity;
        } catch (PDOException $e) {
            error_log("Erreur vérification stock: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getCart')) {
    function getCart($user_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, lp.quantite, lp.prix_unitaire 
                FROM panier pa
                JOIN ligne_panier lp ON pa.id_panier = lp.id_panier
                JOIN produit p ON lp.id_produit = p.id_produit
                WHERE pa.id_utilisateur = ? AND p.est_actif = 1
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur récupération panier: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getCartItems')) {
    function getCartItems($user_id) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, lp.quantite, lp.prix_unitaire 
                FROM panier pa
                JOIN ligne_panier lp ON pa.id_panier = lp.id_panier
                JOIN produit p ON lp.id_produit = p.id_produit
                WHERE pa.id_utilisateur = ? AND p.est_actif = 1
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur récupération panier: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('updateCartQuantity')) {
    function updateCartQuantity($user_id, $product_id, $quantity) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                UPDATE ligne_panier lp
                JOIN panier pa ON lp.id_panier = pa.id_panier
                SET lp.quantite = ?
                WHERE pa.id_utilisateur = ? AND lp.id_produit = ?
            ");
            return $stmt->execute([$quantity, $user_id, $product_id]);
        } catch (PDOException $e) {
            error_log("Erreur mise à jour quantité: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('removeFromCart')) {
    function removeFromCart($user_id, $product_id) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                DELETE lp FROM ligne_panier lp
                JOIN panier pa ON lp.id_panier = pa.id_panier
                WHERE pa.id_utilisateur = ? AND lp.id_produit = ?
            ");
            return $stmt->execute([$user_id, $product_id]);
        } catch (PDOException $e) {
            error_log("Erreur suppression panier: " . $e->getMessage());
            return false;
        }
    }
}

// Fonction pour vérifier si l'utilisateur peut donner un avis
function canUserReviewProduct($user_id, $product_id) {
    global $pdo;
    
    $query = "SELECT COUNT(*) FROM commande c
              INNER JOIN ligne_commande lc ON c.id_commande = lc.id_commande
              WHERE c.id_utilisateur = :user_id 
              AND lc.id_produit = :product_id
              AND c.statut IN ('livree', 'expediee')";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':user_id' => $user_id,
        ':product_id' => $product_id
    ]);
    
    return $stmt->fetchColumn() > 0;
}

// Fonction pour ajouter un avis
function addProductReview($product_id, $user_id, $rating, $comment) {
    global $pdo;
    
    // Vérifier si l'utilisateur a déjà donné un avis
    $checkQuery = "SELECT id_avis FROM avis 
                   WHERE id_produit = :product_id 
                   AND id_utilisateur = :user_id";
    
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([
        ':product_id' => $product_id,
        ':user_id' => $user_id
    ]);
    
    if ($checkStmt->fetch()) {
        return ['success' => false, 'message' => 'Vous avez déjà donné votre avis pour ce produit'];
    }
    
    // Insérer l'avis
    $query = "INSERT INTO avis (id_produit, id_utilisateur, note, commentaire, est_approuve, date_avis)
              VALUES (:product_id, :user_id, :rating, :comment, 0, NOW())";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        ':product_id' => $product_id,
        ':user_id' => $user_id,
        ':rating' => $rating,
        ':comment' => $comment
    ]);
    
    if ($result) {
        return ['success' => true, 'message' => 'Votre avis a été soumis. Il sera visible après modération.'];
    }
    
    return ['success' => false, 'message' => 'Erreur lors de l\'ajout de l\'avis'];
}

// Fonction pour récupérer les avis d'un produit
function getProductReviews($product_id, $approved_only = true, $limit = 10) {
    global $pdo;
    
    $query = "SELECT a.*, u.nom, u.prenom, 
              DATE_FORMAT(a.date_avis, '%d/%m/%Y à %H:%i') as date_formatted
              FROM avis a
              JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur
              WHERE a.id_produit = :product_id";
    
    if ($approved_only) {
        $query .= " AND a.est_approuve = 1";
    }
    
    $query .= " ORDER BY a.date_avis DESC LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Fonction pour calculer la note moyenne
function getProductRatingStats($product_id) {
    global $pdo;
    
    $query = "SELECT 
                COALESCE(AVG(note), 0) as average_rating,
                COUNT(*) as review_count,
                SUM(CASE WHEN note = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN note = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN note = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN note = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN note = 1 THEN 1 ELSE 0 END) as one_star
              FROM avis 
              WHERE id_produit = :product_id 
              AND est_approuve = 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':product_id' => $product_id]);
    
    return $stmt->fetch();
}

// Fonction pour afficher les étoiles
function displayRatingStars($rating, $size = 'md') {
    $sizes = [
        'sm' => '0.8rem',
        'md' => '1rem',
        'lg' => '1.2rem'
    ];
    
    $size = $sizes[$size] ?? '1rem';
    
    $html = '<div class="rating-stars" style="font-size: ' . $size . '; color: #ffc107;">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($rating)) {
            $html .= '<i class="fas fa-star"></i>';
        } elseif ($i == ceil($rating) && fmod($rating, 1) >= 0.5) {
            $html .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $html .= '<i class="far fa-star"></i>';
        }
    }
    $html .= '<span style="margin-left: 5px; font-size: 0.9em; color: #666;">(' . number_format($rating, 1) . ')</span>';
    $html .= '</div>';
    return $html;
}

// Fonction pour récupérer les avis de l'utilisateur
function getUserReviews($user_id) {
    global $pdo;
    
    $query = "SELECT a.*, p.nom_produit, p.id_produit,
              DATE_FORMAT(a.date_avis, '%d/%m/%Y') as date_formatted
              FROM avis a
              JOIN produit p ON a.id_produit = p.id_produit
              WHERE a.id_utilisateur = :user_id
              ORDER BY a.date_avis DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    
    return $stmt->fetchAll();
}

// SUPPRIMÉ : La fonction getUnreadChatCount a été supprimée car elle est déjà définie dans config.php
// Cette suppression résout l'erreur "Cannot redeclare getUnreadChatCount()"