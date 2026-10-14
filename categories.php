<?php
// categories.php - Page de toutes les catégories avec affichage des produits et ajout au panier
require_once 'includes/config.php';

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialiser le panier s'il n'existe pas
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer l'ID de la catégorie sélectionnée
$selected_category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$selected_category_name = '';

// Titre de la page
if ($selected_category_id > 0) {
    $page_title = "Produits de la catégorie - " . SITE_NAME;
} else {
    $page_title = "Toutes nos catégories - " . SITE_NAME;
}

$header_show_products_link = true;

// Initialiser les variables
$all_categories = [];
$popular_categories = [];
$products = [];
$total_categories = 0;
$error = null;
$message = '';
$message_type = '';

// ============================================
// TRAITEMENT AJOUT AU PANIER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $response = ['success' => false, 'error' => 'Token de sécurité invalide'];
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit;
        }
        $message = "Token de sécurité invalide";
        $message_type = "error";
    } else {
        $product_id = intval($_POST['product_id']);
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        if ($quantity < 1) $quantity = 1;
        
        try {
            // Récupérer les informations du produit
            $stmt = $pdo->prepare("
                SELECT id_produit, nom_produit, prix, prix_promotion, est_en_promotion, stock 
                FROM produit 
                WHERE id_produit = ? AND est_actif = 1
            ");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception("Produit non trouvé");
            }
            
            if ($product['stock'] < $quantity) {
                throw new Exception("Stock insuffisant. Stock disponible : " . $product['stock']);
            }
            
            // Déterminer le prix à utiliser
            $price = ($product['est_en_promotion'] && $product['prix_promotion'] > 0) 
                     ? $product['prix_promotion'] 
                     : $product['prix'];
            
            // Ajouter ou mettre à jour dans le panier
            if (isset($_SESSION['cart'][$product_id])) {
                $new_quantity = $_SESSION['cart'][$product_id]['quantity'] + $quantity;
                if ($new_quantity > $product['stock']) {
                    throw new Exception("Quantité totale demandée (" . $new_quantity . ") dépasse le stock disponible (" . $product['stock'] . ")");
                }
                $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'id' => $product['id_produit'],
                    'name' => $product['nom_produit'],
                    'price' => $price,
                    'quantity' => $quantity,
                    'image' => null
                ];
                
                // Récupérer l'image du produit
                $stmt_img = $pdo->prepare("
                    SELECT url_image FROM image_produit 
                    WHERE id_produit = ? 
                    ORDER BY est_principale DESC LIMIT 1
                ");
                $stmt_img->execute([$product_id]);
                $image = $stmt_img->fetch(PDO::FETCH_ASSOC);
                if ($image) {
                    $_SESSION['cart'][$product_id]['image'] = $image['url_image'];
                }
            }
            
            // Calculer le nombre total d'articles dans le panier
            $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Produit ajouté au panier !',
                    'cart_count' => $cart_count
                ]);
                exit;
            }
            
            $message = "Produit ajouté au panier avec succès !";
            $message_type = "success";
            
        } catch (Exception $e) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = "Erreur : " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// ============================================
// TRAITEMENT POUR RÉCUPÉRER LES DÉTAILS D'UN PRODUIT (AJAX)
// ============================================
if (isset($_GET['get_product_details']) && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                c.nom_categorie,
                (SELECT COUNT(*) FROM avis WHERE id_produit = p.id_produit AND est_approuve = 1) as nb_avis,
                (SELECT AVG(note) FROM avis WHERE id_produit = p.id_produit AND est_approuve = 1) as note_moyenne,
                (SELECT GROUP_CONCAT(url_image ORDER BY est_principale DESC SEPARATOR '||') 
                 FROM image_produit WHERE id_produit = p.id_produit) as images
            FROM produit p
            LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
            WHERE p.id_produit = ? AND p.est_actif = 1
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $product['images_array'] = !empty($product['images']) ? explode('||', $product['images']) : [];
            $product['note_moyenne'] = round($product['note_moyenne'], 1);
            $product['nb_avis'] = intval($product['nb_avis']);
            
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Produit non trouvé']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================
try {
    if (!isset($pdo)) {
        throw new Exception('Erreur de connexion à la base de données');
    }
    
    // Récupérer TOUTES les catégories avec statistiques
    $stmt = $pdo->query("
        SELECT 
            c.id_categorie,
            c.nom_categorie,
            c.description as description_categorie,
            COUNT(p.id_produit) as nb_produits,
            COALESCE(
                (SELECT url_image FROM image_produit ip 
                 JOIN produit p2 ON ip.id_produit = p2.id_produit 
                 WHERE p2.id_categorie = c.id_categorie AND ip.est_principale = 1 
                 LIMIT 1),
                NULL
            ) as image_categorie,
            COALESCE(
                (SELECT MIN(prix) FROM produit WHERE id_categorie = c.id_categorie AND est_actif = 1),
                0
            ) as prix_min
        FROM categorie c
        LEFT JOIN produit p ON c.id_categorie = p.id_categorie AND p.est_actif = 1
        GROUP BY c.id_categorie, c.nom_categorie, c.description
        HAVING nb_produits > 0
        ORDER BY nb_produits DESC, c.nom_categorie
    ");
    
    $all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_categories = count($all_categories);
    
    // Récupérer les catégories populaires (top 3)
    if ($total_categories > 0) {
        $popular_categories = array_slice($all_categories, 0, 3);
    }
    
    // Si une catégorie est sélectionnée, récupérer ses produits
    if ($selected_category_id > 0) {
        $stmt = $pdo->prepare("SELECT nom_categorie FROM categorie WHERE id_categorie = ?");
        $stmt->execute([$selected_category_id]);
        $category_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category_info) {
            $selected_category_name = $category_info['nom_categorie'];
            $page_title = $selected_category_name . " - " . SITE_NAME;
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    (SELECT url_image FROM image_produit 
                     WHERE id_produit = p.id_produit 
                     ORDER BY est_principale DESC LIMIT 1) as image
                FROM produit p
                WHERE p.id_categorie = ? AND p.est_actif = 1
                ORDER BY p.date_ajout DESC
            ");
            $stmt->execute([$selected_category_id]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $selected_category_id = 0;
            $error = "Catégorie non trouvée";
        }
    }
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des catégories : " . $e->getMessage();
    error_log($e->getMessage());
}

include 'includes/header.php';
?>

<!-- ============================================ -->
<!-- STYLES SPÉCIFIQUES -->
<!-- ============================================ -->
<style>
/* Variables CSS - harmonisées avec le style d'inscription */
:root {
    --primary: #007a5e;
    --primary-dark: #005c46;
    --primary-light: #e6f4f0;
    --secondary: #6c757d;
    --dark: #1a202c;
    --green-light: #d1fae5;
}

/* Section principale */
.categories-section {
    padding: 60px 0;
    background: white;
}

/* En-tête de section */
.section-header {
    text-align: center;
    margin-bottom: 50px;
}

.section-header h1 {
    font-size: 42px;
    color: var(--dark);
    margin-bottom: 15px;
    font-weight: 700;
}

.section-header p {
    color: var(--secondary);
    font-size: 18px;
    max-width: 600px;
    margin: 0 auto;
}

/* Fil d'Ariane */
.breadcrumb {
    margin-bottom: 30px;
    padding: 15px 0;
    border-bottom: 1px solid #e2e8f0;
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
    transition: color 0.3s;
}

.breadcrumb a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.breadcrumb .separator {
    margin: 0 10px;
    color: #999;
    font-size: 12px;
}

.breadcrumb .current {
    color: var(--secondary);
}

/* Cartes de catégories */
.category-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    text-decoration: none;
    display: block;
    height: 100%;
}

.category-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
}

.category-image {
    height: 200px;
    overflow: hidden;
    position: relative;
}

.category-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.category-card:hover .category-image img {
    transform: scale(1.1);
}

.category-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: var(--primary);
    color: white;
    padding: 8px 15px;
    border-radius: 25px;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.category-content {
    padding: 25px;
}

.category-content h3 {
    margin: 0 0 10px 0;
    color: var(--dark);
    font-size: 20px;
    font-weight: 600;
}

.category-content p {
    color: var(--secondary);
    font-size: 14px;
    margin-bottom: 15px;
    line-height: 1.6;
}

.category-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
}

.category-price {
    color: var(--primary);
    font-weight: 600;
}

.category-link {
    background: var(--primary-light);
    color: var(--primary);
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
}

.category-card:hover .category-link {
    background: var(--primary);
    color: white;
}

.category-border {
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
}

/* Cartes de produits */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.product-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
}

.product-image-link {
    position: relative;
    cursor: pointer;
    display: block;
    overflow: hidden;
    height: 250px;
}

.product-image-link img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.product-card:hover .product-image-link img {
    transform: scale(1.1);
}

.zoom-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.7);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.product-image-link:hover .zoom-icon {
    opacity: 1;
}

.promo-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: #f39c12;
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    z-index: 2;
}

.stock-badge {
    position: absolute;
    bottom: 15px;
    left: 15px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    z-index: 2;
}

.stock-low {
    background: #f72585;
    color: white;
}

.stock-out {
    background: #999;
    color: white;
}

.product-info {
    padding: 20px;
}

.product-title {
    font-size: 18px;
    color: var(--dark);
    margin-bottom: 10px;
    line-height: 1.4;
    font-weight: 600;
}

.product-description {
    color: var(--secondary);
    font-size: 13px;
    margin-bottom: 15px;
    line-height: 1.5;
}

.product-price {
    margin-top: 15px;
}

.old-price {
    color: #999;
    text-decoration: line-through;
    font-size: 14px;
    margin-right: 8px;
}

.current-price {
    color: var(--primary);
    font-size: 20px;
    font-weight: bold;
}

.promo-price {
    color: #f39c12;
}

.product-actions {
    padding: 0 20px 20px 20px;
    display: flex;
    gap: 10px;
}

.btn-add-cart {
    flex: 2;
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-add-cart:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-add-cart:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.btn-view-details {
    flex: 1;
    background: #f8f9fa;
    color: var(--primary);
    border: 1px solid #e2e8f0;
    padding: 10px;
    border-radius: 10px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-view-details:hover {
    background: var(--primary-light);
    border-color: var(--primary);
}

/* Contrôle de quantité */
.quantity-control {
    display: flex;
    gap: 5px;
    align-items: center;
    flex: 2;
}

.qty-btn {
    background: #f0f0f0;
    border: none;
    width: 35px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.2s;
}

.qty-btn:hover {
    background: #e0e0e0;
}

.qty-input {
    width: 60px;
    height: 40px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.confirm-add {
    background: #10b981;
    color: white;
    border: none;
    padding: 0 15px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
}

.cancel-add {
    background: #f0f0f0;
    border: none;
    padding: 0 15px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
}

/* Statistiques */
.stats-container {
    display: flex;
    justify-content: space-around;
    margin-bottom: 50px;
    padding: 30px;
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border-radius: 20px;
    flex-wrap: wrap;
    gap: 20px;
}

.stat-item {
    text-align: center;
    min-width: 120px;
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    color: var(--primary);
}

.stat-label {
    color: var(--secondary);
}

/* Section suggestion */
.suggestion-section {
    margin-top: 60px;
    padding: 40px;
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border-radius: 20px;
    text-align: center;
}

.suggestion-section h3 {
    font-size: 24px;
    color: var(--dark);
    margin-bottom: 15px;
}

.suggestion-section p {
    color: var(--secondary);
    margin-bottom: 25px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.suggestion-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-primary-custom {
    background: var(--primary);
    color: white;
    padding: 12px 30px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary-custom:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-outline-custom {
    border: 2px solid var(--primary);
    color: var(--primary);
    padding: 12px 30px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-outline-custom:hover {
    background: var(--primary);
    color: white;
}

/* Message d'erreur */
.error-message {
    background: #fef2f2;
    color: #dc2626;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Message vide */
.empty-message {
    text-align: center;
    padding: 80px 20px;
}

.empty-message i {
    font-size: 80px;
    color: #ccc;
    margin-bottom: 20px;
}

.empty-message h3 {
    color: var(--dark);
    margin-bottom: 10px;
}

.empty-message p {
    color: var(--secondary);
    margin-bottom: 30px;
}

/* Notification */
.cart-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 12px;
    color: white;
    z-index: 10000;
    animation: slideIn 0.3s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.notification-success {
    background: #10b981;
}

.notification-error {
    background: #dc2626;
}

.close-notif {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    cursor: pointer;
    font-size: 18px;
    padding: 0 5px;
    border-radius: 5px;
}

.close-notif:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Modale */
.modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    overflow: auto;
}

.modal-content {
    background: white;
    margin: 50px auto;
    padding: 0;
    border-radius: 20px;
    width: 90%;
    max-width: 1000px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    background: white;
    z-index: 1;
}

.modal-header h2 {
    font-size: 22px;
    color: var(--dark);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #999;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #dc2626;
}

.loading-spinner {
    text-align: center;
    padding: 50px;
    color: var(--secondary);
}

/* Détails produit dans la modale */
.product-detail-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    padding: 25px;
}

.product-detail-gallery {
    background: #f8f9fa;
    border-radius: 15px;
    overflow: hidden;
}

.main-image {
    width: 100%;
    height: 350px;
    object-fit: cover;
}

.thumbnail-images {
    display: flex;
    gap: 10px;
    padding: 15px;
    overflow-x: auto;
}

.thumbnail {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.3s;
}

.thumbnail:hover, .thumbnail.active {
    border-color: var(--primary);
}

.product-detail-title {
    font-size: 28px;
    color: var(--dark);
    margin-bottom: 10px;
}

.product-detail-category {
    color: var(--secondary);
    margin-bottom: 15px;
    display: inline-block;
    background: #f0f0f0;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
}

.product-detail-rating {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.stars {
    color: #fbbf24;
    font-size: 18px;
}

.product-detail-price {
    margin-bottom: 20px;
}

.product-detail-description {
    color: var(--secondary);
    line-height: 1.6;
    margin-bottom: 20px;
}

.product-detail-stock {
    margin-bottom: 20px;
    padding: 10px;
    border-radius: 8px;
}

.stock-available {
    background: #d1fae5;
    color: #065f46;
}

.stock-low {
    background: #fef3c7;
    color: #92400e;
}

.product-detail-actions {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.btn-add-cart-detail {
    flex: 2;
    background: var(--primary);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-add-cart-detail:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-buy-now {
    flex: 1;
    background: #f39c12;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-buy-now:hover {
    background: #e67e22;
    transform: translateY(-2px);
}

.quantity-detail {
    margin-top: 15px;
}

.quantity-detail label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark);
}

.quantity-control-detail {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 5px;
}

/* Animations */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

/* Responsive */
@media (max-width: 768px) {
    .section-header h1 {
        font-size: 32px;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .product-detail-container {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        margin: 20px auto;
        width: 95%;
    }
    
    .cart-notification {
        top: auto;
        bottom: 20px;
        right: 20px;
        left: 20px;
        min-width: auto;
    }
    
    .stats-container {
        flex-direction: column;
        align-items: center;
    }
    
    .suggestion-section {
        padding: 30px 20px;
    }
}

@media (max-width: 480px) {
    .section-header h1 {
        font-size: 28px;
    }
    
    .category-content h3 {
        font-size: 18px;
    }
    
    .product-actions {
        flex-direction: column;
    }
    
    .product-detail-actions {
        flex-direction: column;
    }
}
</style>

<!-- ============================================ -->
<!-- NOTIFICATION AJOUT PANIER -->
<!-- ============================================ -->
<?php if ($message): ?>
<div class="cart-notification notification-<?php echo $message_type; ?>" id="cartNotification">
    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
    <span><?php echo htmlspecialchars($message); ?></span>
    <button class="close-notif" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- MODALE DÉTAILS PRODUIT -->
<!-- ============================================ -->
<div id="productModal" class="modal">
    <div class="modal-content modal-product">
        <div class="modal-header">
            <h2 id="modalProductTitle">Détails du produit</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="modalProductContent">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Chargement des détails...
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- SECTION CATÉGORIES PRINCIPALE -->
<!-- ============================================ -->
<section class="categories-section">
    <div class="container">
        
        <!-- Fil d'Ariane -->
        <nav class="breadcrumb">
            <a href="index.php">Accueil</a>
            <span class="separator"><i class="fas fa-chevron-right"></i></span>
            <?php if ($selected_category_id > 0): ?>
                <a href="categories.php">Catégories</a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span class="current"><?php echo htmlspecialchars($selected_category_name); ?></span>
            <?php else: ?>
                <span class="current">Catégories</span>
            <?php endif; ?>
        </nav>
        
        <!-- Message d'erreur si nécessaire -->
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- SECTION PRODUITS DE LA CATÉGORIE SÉLECTIONNÉE -->
        <?php if ($selected_category_id > 0 && !empty($products)): ?>
        
        <!-- En-tête de la catégorie -->
        <div class="section-header">
            <h1>
                <i class="fas fa-folder-open" style="color: var(--primary);"></i> 
                <?php echo htmlspecialchars($selected_category_name); ?>
            </h1>
            <p><?php echo count($products); ?> produit<?php echo count($products) > 1 ? 's' : ''; ?> disponible<?php echo count($products) > 1 ? 's' : ''; ?></p>
        </div>
        
        <!-- Grille des produits -->
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card" data-product-id="<?php echo $product['id_produit']; ?>">
                <!-- Image du produit -->
                <div class="product-image-link" onclick="viewProductDetails(<?php echo $product['id_produit']; ?>)">
                    <div style="height: 250px; overflow: hidden; position: relative;">
                        <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['nom_produit']); ?>">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-box" style="font-size: 50px; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Badge promotion -->
                        <?php if ($product['est_en_promotion'] && $product['prix_promotion'] > 0): ?>
                        <div class="promo-badge">
                            <i class="fas fa-fire"></i> PROMO
                        </div>
                        <?php endif; ?>
                        
                        <!-- Badge stock -->
                        <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                        <div class="stock-badge stock-low">
                            <i class="fas fa-exclamation-triangle"></i> Stock limité
                        </div>
                        <?php elseif ($product['stock'] == 0): ?>
                        <div class="stock-badge stock-out">
                            <i class="fas fa-times-circle"></i> Rupture
                        </div>
                        <?php endif; ?>
                        
                        <!-- Icône zoom -->
                        <div class="zoom-icon">
                            <i class="fas fa-search-plus"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Informations produit -->
                <div class="product-info">
                    <h3 class="product-title"><?php echo htmlspecialchars($product['nom_produit']); ?></h3>
                    <p class="product-description">
                        <?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 80)); ?>...
                    </p>
                    
                    <div class="product-price">
                        <?php if ($product['est_en_promotion'] && $product['prix_promotion'] > 0): ?>
                            <span class="old-price"><?php echo number_format($product['prix'], 0, ',', ' '); ?> FCFA</span>
                            <span class="current-price promo-price"><?php echo number_format($product['prix_promotion'], 0, ',', ' '); ?> FCFA</span>
                        <?php else: ?>
                            <span class="current-price"><?php echo number_format($product['prix'], 0, ',', ' '); ?> FCFA</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Boutons d'action -->
                <div class="product-actions">
                    <?php if ($product['stock'] > 0): ?>
                        <button class="btn-add-cart" onclick="showQuantityControl(this)">
                            <i class="fas fa-shopping-cart"></i> Ajouter
                        </button>
                        <div class="quantity-control" style="display: none;">
                            <button class="qty-btn" onclick="updateQuantity(this, -1)">-</button>
                            <input type="number" class="qty-input" value="1" min="1" max="<?php echo $product['stock']; ?>">
                            <button class="qty-btn" onclick="updateQuantity(this, 1)">+</button>
                            <button class="confirm-add" onclick="addToCart(this)">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="cancel-add" onclick="hideQuantityControl(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <button class="btn-add-cart" disabled>
                            <i class="fas fa-times"></i> Indisponible
                        </button>
                    <?php endif; ?>
                    
                    <button class="btn-view-details" onclick="viewProductDetails(<?php echo $product['id_produit']; ?>)">
                        <i class="fas fa-eye"></i> Détails
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Lien retour aux catégories -->
        <div style="text-align: center; margin-top: 20px;">
            <a href="categories.php" class="btn-outline-custom">
                <i class="fas fa-arrow-left"></i>
                Retour à toutes les catégories
            </a>
        </div>
        
        <?php elseif ($selected_category_id > 0 && empty($products)): ?>
        
        <!-- Message si aucun produit dans la catégorie -->
        <div class="empty-message">
            <i class="fas fa-box-open"></i>
            <h3>Aucun produit disponible</h3>
            <p>Cette catégorie ne contient aucun produit pour le moment.</p>
            <a href="categories.php" class="btn-primary-custom">
                <i class="fas fa-arrow-left"></i> Voir toutes les catégories
            </a>
        </div>
        
        <?php else: ?>
        
        <!-- AFFICHAGE DES CATÉGORIES (quand aucune n'est sélectionnée) -->
        
        <!-- En-tête de section -->
        <div class="section-header">
            <h1>
                <i class="fas fa-tags" style="color: var(--primary);"></i> 
                Toutes nos catégories
            </h1>
            <p>
                Découvrez notre sélection complète de produits par catégorie 
                (<?php echo $total_categories; ?> catégorie<?php echo $total_categories > 1 ? 's' : ''; ?>)
            </p>
        </div>
        
        <!-- Catégories populaires en vedette -->
        <?php if (!empty($popular_categories) && count($popular_categories) >= 3): ?>
        <div id="popular" style="margin-bottom: 60px;">
            <h2 style="font-size: 24px; color: var(--dark); margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-star" style="color: #f39c12;"></i>
                Catégories populaires
            </h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
                <?php foreach ($popular_categories as $index => $category): ?>
                    <?php
                    $popular_colors = [
                        ['bg' => '#4361ee', 'light' => '#e6e9ff'],
                        ['bg' => '#f72585', 'light' => '#fee9f3'],
                        ['bg' => '#7209b7', 'light' => '#f0e6ff']
                    ];
                    $color = $popular_colors[$index % count($popular_colors)];
                    ?>
                    
                    <a href="categories.php?category=<?php echo urlencode($category['id_categorie']); ?>" 
                       style="text-decoration: none; display: block; height: 100%;">
                        <div style="background: linear-gradient(135deg, <?php echo $color['bg']; ?>, <?php echo $color['light']; ?>); 
                                    border-radius: 20px; padding: 30px; position: relative; overflow: hidden;
                                    box-shadow: 0 10px 30px rgba(0,0,0,0.1); transition: all 0.3s; height: 100%;"
                             onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='0 20px 40px rgba(0,0,0,0.15)';"
                             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 30px rgba(0,0,0,0.1)';">
                            
                            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; 
                                        background: rgba(255,255,255,0.2); border-radius: 50%;"></div>
                            <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; 
                                        background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                            
                            <div style="position: relative; z-index: 1;">
                                <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                                    <div style="width: 70px; height: 70px; background: white; border-radius: 15px; 
                                                display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-folder-open" style="font-size: 35px; color: <?php echo $color['bg']; ?>;"></i>
                                    </div>
                                    <div>
                                        <span style="background: white; color: <?php echo $color['bg']; ?>; 
                                                     padding: 5px 15px; border-radius: 25px; font-size: 14px; font-weight: 600;">
                                            <i class="fas fa-fire"></i> Populaire
                                        </span>
                                    </div>
                                </div>
                                
                                <h3 style="color: white; font-size: 28px; margin-bottom: 10px;">
                                    <?php echo htmlspecialchars($category['nom_categorie']); ?>
                                </h3>
                                
                                <p style="color: white; opacity: 0.9; margin-bottom: 20px;">
                                    <?php echo $category['nb_produits']; ?> produit<?php echo $category['nb_produits'] > 1 ? 's' : ''; ?> disponible<?php echo $category['nb_produits'] > 1 ? 's' : ''; ?>
                                </p>
                                
                                <div style="display: flex; align-items: center; color: white; font-weight: 600;">
                                    <span>Explorer la catégorie</span>
                                    <i class="fas fa-arrow-right" style="margin-left: 10px; transition: transform 0.3s;"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <?php if ($total_categories > 0): ?>
        <div class="stats-container">
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_categories; ?></div>
                <div class="stat-label">Catégorie<?php echo $total_categories > 1 ? 's' : ''; ?></div>
            </div>
            <div class="stat-item">
                <?php 
                $total_products_all = array_sum(array_column($all_categories, 'nb_produits'));
                ?>
                <div class="stat-number"><?php echo $total_products_all; ?></div>
                <div class="stat-label">Produit<?php echo $total_products_all > 1 ? 's' : ''; ?></div>
            </div>
            <div class="stat-item">
                <?php 
                $total_price = array_sum(array_column($all_categories, 'prix_min'));
                ?>
                <div class="stat-number"><?php echo number_format($total_price, 0, ',', ' '); ?>+</div>
                <div class="stat-label">Prix min cumulé (FCFA)</div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Grille des catégories -->
        <?php if (!empty($all_categories)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px;">
            <?php foreach ($all_categories as $index => $category): ?>
                <?php
                $colors = [
                    ['bg' => '#4361ee', 'light' => '#e6e9ff'],
                    ['bg' => '#3f37c9', 'light' => '#e3e0ff'],
                    ['bg' => '#4895ef', 'light' => '#e7f0ff'],
                    ['bg' => '#4cc9f0', 'light' => '#e6f7ff'],
                    ['bg' => '#f72585', 'light' => '#fee9f3'],
                    ['bg' => '#b5179e', 'light' => '#fae6ff'],
                    ['bg' => '#7209b7', 'light' => '#f0e6ff'],
                    ['bg' => '#560bad', 'light' => '#efe6ff']
                ];
                $color = $colors[$index % count($colors)];
                ?>
                
                <a href="categories.php?category=<?php echo urlencode($category['id_categorie']); ?>" class="category-card">
                    <div class="category-image">
                        <?php if (!empty($category['image_categorie']) && file_exists($category['image_categorie'])): ?>
                            <img src="<?php echo htmlspecialchars($category['image_categorie']); ?>" 
                                 alt="<?php echo htmlspecialchars($category['nom_categorie']); ?>">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, <?php echo $color['bg']; ?>, <?php echo $color['light']; ?>); 
                                        display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-folder-open" style="font-size: 60px; color: white; opacity: 0.8;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="category-badge">
                            <i class="fas fa-box"></i> <?php echo $category['nb_produits']; ?> produit<?php echo $category['nb_produits'] > 1 ? 's' : ''; ?>
                        </div>
                    </div>
                    
                    <div class="category-content">
                        <h3><?php echo htmlspecialchars($category['nom_categorie']); ?></h3>
                        
                        <?php if (!empty($category['description_categorie'])): ?>
                            <p><?php echo htmlspecialchars(substr($category['description_categorie'], 0, 80)); ?>...</p>
                        <?php endif; ?>
                        
                        <div class="category-footer">
                            <?php if ($category['prix_min'] > 0): ?>
                                <span class="category-price">
                                    <i class="fas fa-tag"></i> À partir de <?php echo number_format($category['prix_min'], 0, ',', ' '); ?> FCFA
                                </span>
                            <?php else: ?>
                                <span class="category-price">Prix sur demande</span>
                            <?php endif; ?>
                            
                            <span class="category-link">
                                Voir les produits <i class="fas fa-chevron-right"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="category-border"></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Si aucune catégorie -->
        <?php if (empty($all_categories)): ?>
            <div class="empty-message">
                <i class="fas fa-folder-open"></i>
                <h3>Aucune catégorie disponible</h3>
                <p>Revenez plus tard pour découvrir nos catégories</p>
                <a href="index.php" class="btn-primary-custom">
                    Retour à l'accueil
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Section suggestion -->
        <?php if (!empty($all_categories)): ?>
        <div class="suggestion-section">
            <h3>
                <i class="fas fa-lightbulb" style="color: #f39c12;"></i>
                Vous ne savez pas par où commencer ?
            </h3>
            <p>
                Explorez nos catégories populaires ou consultez tous nos produits pour trouver votre bonheur !
            </p>
            <div class="suggestion-buttons">
                <a href="index.php#products" class="btn-primary-custom">
                    <i class="fas fa-box"></i> Voir tous les produits
                </a>
                <?php if (!empty($popular_categories)): ?>
                <a href="#popular" class="btn-outline-custom">
                    <i class="fas fa-star"></i> Catégories populaires
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</section>

<script>
// Fonction pour afficher les détails du produit dans une modale
function viewProductDetails(productId) {
    const modal = document.getElementById('productModal');
    const modalContent = document.getElementById('modalProductContent');
    const modalTitle = document.getElementById('modalProductTitle');
    
    modalContent.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Chargement des détails...</div>';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    fetch(window.location.href + '?get_product_details=1&id=' + productId, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const product = data.product;
            modalTitle.textContent = product.nom_produit;
            
            let starsHtml = '';
            const rating = product.note_moyenne || 0;
            for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    starsHtml += '<i class="fas fa-star"></i>';
                } else if (i - 0.5 <= rating) {
                    starsHtml += '<i class="fas fa-star-half-alt"></i>';
                } else {
                    starsHtml += '<i class="far fa-star"></i>';
                }
            }
            
            let mainImage = product.images_array[0] || '';
            let thumbnailsHtml = '';
            product.images_array.forEach((img, index) => {
                thumbnailsHtml += `<img src="${img}" class="thumbnail" onclick="changeMainImage(this, '${img}')">`;
            });
            
            let priceHtml = '';
            if (product.est_en_promotion && product.prix_promotion > 0) {
                priceHtml = `
                    <span class="old-price">${formatPrice(product.prix)} FCFA</span>
                    <span class="current-price promo-price">${formatPrice(product.prix_promotion)} FCFA</span>
                `;
            } else {
                priceHtml = `<span class="current-price">${formatPrice(product.prix)} FCFA</span>`;
            }
            
            let stockHtml = '';
            let stockClass = '';
            if (product.stock > 5) {
                stockHtml = `<i class="fas fa-check-circle"></i> En stock (${product.stock} disponible${product.stock > 1 ? 's' : ''})`;
                stockClass = 'stock-available';
            } else if (product.stock > 0) {
                stockHtml = `<i class="fas fa-exclamation-triangle"></i> Stock limité (${product.stock} disponible${product.stock > 1 ? 's' : ''})`;
                stockClass = 'stock-low';
            } else {
                stockHtml = `<i class="fas fa-times-circle"></i> Rupture de stock`;
                stockClass = 'stock-low';
            }
            
            modalContent.innerHTML = `
                <div class="product-detail-container">
                    <div class="product-detail-gallery">
                        <img src="${mainImage}" alt="${escapeHtml(product.nom_produit)}" class="main-image" id="mainProductImage">
                        <div class="thumbnail-images">
                            ${thumbnailsHtml}
                        </div>
                    </div>
                    <div class="product-detail-info">
                        <h1 class="product-detail-title">${escapeHtml(product.nom_produit)}</h1>
                        <div class="product-detail-category">
                            <i class="fas fa-folder"></i> ${escapeHtml(product.nom_categorie || 'Non catégorisé')}
                        </div>
                        <div class="product-detail-rating">
                            <div class="stars">${starsHtml}</div>
                            <span>(${product.nb_avis} avis)</span>
                        </div>
                        <div class="product-detail-price">
                            ${priceHtml}
                        </div>
                        <div class="product-detail-description">
                            ${escapeHtml(product.description || 'Aucune description disponible.')}
                        </div>
                        <div class="product-detail-stock ${stockClass}">
                            ${stockHtml}
                        </div>
                        ${product.stock > 0 ? `
                        <div class="product-detail-actions">
                            <button class="btn-add-cart-detail" onclick="addToCartFromModal(${product.id_produit})">
                                <i class="fas fa-shopping-cart"></i> Ajouter au panier
                            </button>
                            <button class="btn-buy-now" onclick="buyNow(${product.id_produit})">
                                <i class="fas fa-bolt"></i> Acheter maintenant
                            </button>
                        </div>
                        <div class="quantity-detail">
                            <label>Quantité:</label>
                            <div class="quantity-control-detail">
                                <button class="qty-btn" onclick="updateDetailQuantity(-1)">-</button>
                                <input type="number" id="detailQuantity" value="1" min="1" max="${product.stock}" class="qty-input">
                                <button class="qty-btn" onclick="updateDetailQuantity(1)">+</button>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        } else {
            modalContent.innerHTML = `<div class="loading-spinner" style="color: #dc2626;">Erreur: ${data.error}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modalContent.innerHTML = '<div class="loading-spinner" style="color: #dc2626;">Erreur lors du chargement des détails</div>';
    });
}

function changeMainImage(thumbnail, imageUrl) {
    const mainImage = document.getElementById('mainProductImage');
    if (mainImage) {
        mainImage.src = imageUrl;
    }
    document.querySelectorAll('.thumbnail').forEach(img => {
        img.classList.remove('active');
    });
    thumbnail.classList.add('active');
}

function updateDetailQuantity(delta) {
    const qtyInput = document.getElementById('detailQuantity');
    if (qtyInput) {
        let currentQty = parseInt(qtyInput.value);
        let newQty = currentQty + delta;
        const maxQty = parseInt(qtyInput.getAttribute('max')) || 99;
        if (newQty < 1) newQty = 1;
        if (newQty > maxQty) newQty = maxQty;
        qtyInput.value = newQty;
    }
}

function addToCartFromModal(productId) {
    const qtyInput = document.getElementById('detailQuantity');
    const quantity = qtyInput ? parseInt(qtyInput.value) : 1;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'add_to_cart=1&product_id=' + productId + '&quantity=' + quantity + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            updateCartCount(data.cart_count);
            closeModal();
        } else {
            showNotification(data.error || 'Erreur lors de l\'ajout', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erreur lors de l\'ajout au panier', 'error');
    });
}

function buyNow(productId) {
    const qtyInput = document.getElementById('detailQuantity');
    const quantity = qtyInput ? parseInt(qtyInput.value) : 1;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'add_to_cart=1&product_id=' + productId + '&quantity=' + quantity + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'panier.php';
        } else {
            showNotification(data.error || 'Erreur lors de l\'ajout', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erreur lors de l\'ajout au panier', 'error');
    });
}

function closeModal() {
    const modal = document.getElementById('productModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

function formatPrice(price) {
    return new Intl.NumberFormat('fr-FR').format(price);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showQuantityControl(buttonElement) {
    const productCard = buttonElement.closest('.product-card');
    const addButton = productCard.querySelector('.btn-add-cart');
    const quantityControl = productCard.querySelector('.quantity-control');
    
    if (addButton && quantityControl) {
        addButton.style.display = 'none';
        quantityControl.style.display = 'flex';
        
        const qtyInput = quantityControl.querySelector('.qty-input');
        if (qtyInput) qtyInput.value = 1;
    }
}

function hideQuantityControl(buttonElement) {
    const productCard = buttonElement.closest('.product-card');
    const addButton = productCard.querySelector('.btn-add-cart');
    const quantityControl = productCard.querySelector('.quantity-control');
    
    if (addButton && quantityControl) {
        addButton.style.display = 'flex';
        quantityControl.style.display = 'none';
    }
}

function updateQuantity(buttonElement, delta) {
    const productCard = buttonElement.closest('.product-card');
    const qtyInput = productCard.querySelector('.qty-input');
    let currentQty = parseInt(qtyInput.value);
    let newQty = currentQty + delta;
    const maxQty = parseInt(qtyInput.getAttribute('max')) || 99;
    
    if (newQty < 1) newQty = 1;
    if (newQty > maxQty) newQty = maxQty;
    
    qtyInput.value = newQty;
}

function addToCart(buttonElement) {
    const productCard = buttonElement.closest('.product-card');
    const productId = productCard.getAttribute('data-product-id');
    const qtyInput = productCard.querySelector('.qty-input');
    const quantity = parseInt(qtyInput.value);
    
    const originalHtml = buttonElement.innerHTML;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    buttonElement.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'add_to_cart=1&product_id=' + productId + '&quantity=' + quantity + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            updateCartCount(data.cart_count);
            buttonElement.innerHTML = originalHtml;
            buttonElement.disabled = false;
            hideQuantityControl(buttonElement);
            
            productCard.style.animation = 'bounce 0.3s ease';
            setTimeout(() => {
                productCard.style.animation = '';
            }, 300);
        } else {
            showNotification(data.error || 'Erreur lors de l\'ajout', 'error');
            buttonElement.innerHTML = originalHtml;
            buttonElement.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erreur lors de l\'ajout au panier', 'error');
        buttonElement.innerHTML = originalHtml;
        buttonElement.disabled = false;
    });
}

function showNotification(message, type) {
    const oldNotifications = document.querySelectorAll('.cart-notification');
    oldNotifications.forEach(notif => notif.remove());
    
    const notification = document.createElement('div');
    notification.className = 'cart-notification notification-' + type;
    notification.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i>' +
                            '<span>' + message + '</span>' +
                            '<button class="close-notif" onclick="this.parentElement.remove()">&times;</button>';
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) notification.remove();
    }, 3000);
}

function updateCartCount(count) {
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        cartCountElement.textContent = count;
        if (count > 0) {
            cartCountElement.style.display = 'inline-block';
            cartCountElement.style.animation = 'bounce 0.3s ease';
            setTimeout(() => {
                cartCountElement.style.animation = '';
            }, 300);
        } else {
            cartCountElement.style.display = 'none';
        }
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('productModal');
    if (event.target === modal) {
        closeModal();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const notification = document.getElementById('cartNotification');
    if (notification) {
        setTimeout(() => {
            if (notification.parentNode) notification.remove();
        }, 5000);
    }
    
    const qtyInputs = document.querySelectorAll('.qty-input');
    qtyInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const confirmBtn = this.closest('.quantity-control')?.querySelector('.confirm-add');
                if (confirmBtn) confirmBtn.click();
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>