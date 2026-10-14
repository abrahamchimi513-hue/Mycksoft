<?php
require_once 'includes/config.php';

// GESTION AJOUT AU PANIER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour ajouter des produits au panier', 'error');
        redirect('login.php');
    }
    
    $product_id = intval($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    $result = addToCart($product_id, $quantity);
    
    if ($result['success']) {
        setFlashMessage('Produit ajouté au panier avec succès', 'success');
        // Recharger la page sans paramètres POST pour éviter la resoumission
        redirect('index.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
    } else {
        setFlashMessage($result['message'], 'error');
    }
}

$page_title = "Accueil - Équipements Informatiques au Cameroun";

// Récupérer les paramètres de filtrage
$category_id = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Construire la requête de base
$sql = "SELECT p.*, c.nom_categorie, 
               (SELECT ip.url_image 
                FROM image_produit ip 
                WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 
                LIMIT 1) as url_image 
        FROM produit p 
        LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
        WHERE p.est_actif = 1";
$params = [];

if (!empty($category_id)) {
    $sql .= " AND p.id_categorie = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $sql .= " AND (p.nom_produit LIKE ? OR p.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($min_price)) {
    $sql .= " AND p.prix >= ?";
    $params[] = $min_price;
}

if (!empty($max_price)) {
    $sql .= " AND p.prix <= ?";
    $params[] = $max_price;
}

// Ajouter le tri
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.prix ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.prix DESC";
        break;
    case 'name':
        $sql .= " ORDER BY p.nom_produit ASC";
        break;
    default:
        $sql .= " ORDER BY p.date_ajout DESC";
}

// Compter le total pour la pagination
$count_sql = "SELECT COUNT(DISTINCT p.id_produit) as total 
              FROM produit p 
              LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
              WHERE p.est_actif = 1";
$count_params = [];

if (!empty($category_id)) {
    $count_sql .= " AND p.id_categorie = ?";
    $count_params[] = $category_id;
}

if (!empty($search)) {
    $count_sql .= " AND (p.nom_produit LIKE ? OR p.description LIKE ?)";
    $search_term = "%$search%";
    $count_params[] = $search_term;
    $count_params[] = $search_term;
}

if (!empty($min_price)) {
    $count_sql .= " AND p.prix >= ?";
    $count_params[] = $min_price;
}

if (!empty($max_price)) {
    $count_sql .= " AND p.prix <= ?";
    $count_params[] = $max_price;
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_products = $count_stmt->fetchColumn();

// Ajouter la limite et l'offset à la requête principale
$sql .= " LIMIT $limit OFFSET $offset";

// Exécuter la requête principale
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories pour le filtre
$categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();

// Récupérer les produits populaires
$popular_products = $pdo->query("
    SELECT p.*, c.nom_categorie,
           (SELECT ip.url_image 
            FROM image_produit ip 
            WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 
            LIMIT 1) as url_image
    FROM produit p 
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
    WHERE p.est_actif = 1 
    ORDER BY p.date_ajout DESC 
    LIMIT 6
")->fetchAll();

// ============================
// RÉCUPÉRATION DES AVIS CLIENTS
// ============================
try {
    $reviews = $pdo->query("
        SELECT a.*, p.nom_produit, p.id_produit, 
               u.prenom, u.nom,
               (SELECT ip.url_image 
                FROM image_produit ip 
                WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 
                LIMIT 1) as url_image
        FROM avis a 
        JOIN produit p ON a.id_produit = p.id_produit 
        JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur 
        WHERE a.est_approuve = 1 
        ORDER BY a.date_avis DESC 
        LIMIT 6
    ")->fetchAll();

    // Calculer la note moyenne
    $average_rating_sql = $pdo->query("
        SELECT 
            ROUND(AVG(note), 1) as moyenne_notes,
            COUNT(*) as total_avis
        FROM avis 
        WHERE est_approuve = 1
    ")->fetch();
    
    $avg_rating = $average_rating_sql['moyenne_notes'] ?? 0;
    $total_reviews = $average_rating_sql['total_avis'] ?? 0;
    
} catch (Exception $e) {
    $reviews = [];
    $avg_rating = 0;
    $total_reviews = 0;
}

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Votre Partenaire Technologique au Cameroun</h1>
            <p>Découvrez notre sélection d'équipements informatiques de qualité aux meilleurs prix du marché</p>
            
            <div class="hero-badges">
                <div class="hero-badge">
                    <i class="fas fa-shipping-fast"></i>
                    Livraison Express
                </div>
                <div class="hero-badge">
                    <i class="fas fa-shield-alt"></i>
                    Garantie incluse
                </div>
                <div class="hero-badge">
                    <i class="fas fa-headset"></i>
                    Support 24/7
                </div>
            </div>
            
            <a href="#products" class="btn btn-primary btn-lg">
                <i class="fas fa-shopping-bag"></i>
                Découvrir nos produits
            </a>
        </div>
    </div>
</section>

<!-- Section Avantages -->
<section class="advantages">
    <div class="container">
        <div class="advantages-grid">
            <div class="advantage-card">
                <div class="advantage-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h3>Livraison Rapide</h3>
                <p>Livraison express à Douala, Yaoundé et dans toutes les régions du Cameroun</p>
            </div>
            
            <div class="advantage-card">
                <div class="advantage-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h3>Paiement Sécurisé</h3>
                <p>Paiement par Mobile Money, carte bancaire ou espèce à la livraison</p>
            </div>
            
            <div class="advantage-card">
                <div class="advantage-icon">
                    <i class="fas fa-undo"></i>
                </div>
                <h3>Retour Facile</h3>
                <p>Retour sous 7 jours si le produit ne vous convient pas</p>
            </div>
            
            <div class="advantage-card">
                <div class="advantage-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3>Produits Garantis</h3>
                <p>Tous nos produits sont garantis et testés avant livraison</p>
            </div>
        </div>
    </div>
</section>

<!-- Filtres et Recherche -->
<section class="controls">
    <div class="container">
        <form method="GET" action="" class="filter-grid">
            <div class="filter-group">
                <label for="category">Catégorie</label>
                <select id="category" name="category" class="filter-select">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id_categorie']; ?>" <?php echo $category_id == $category['id_categorie'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['nom_categorie']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="min_price">Prix min (FCFA)</label>
                <input type="number" id="min_price" name="min_price" class="filter-select" placeholder="0" value="<?php echo htmlspecialchars($min_price); ?>">
            </div>
            
            <div class="filter-group">
                <label for="max_price">Prix max (FCFA)</label>
                <input type="number" id="max_price" name="max_price" class="filter-select" placeholder="1000000" value="<?php echo htmlspecialchars($max_price); ?>">
            </div>
            
            <div class="filter-group">
                <label for="sort">Trier par</label>
                <select id="sort" name="sort" class="filter-select">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Nouveautés</option>
                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Prix croissant</option>
                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Prix décroissant</option>
                    <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Nom A-Z</option>
                </select>
            </div>
            
            <div class="filter-group">
                <button type="submit" class="btn btn-primary">Appliquer les filtres</button>
                <a href="index.php" class="btn btn-outline">Réinitialiser</a>
            </div>
        </form>
    </div>
</section>

<!-- Produits populaires -->
<?php if (empty($search) && empty($category_id) && !empty($popular_products)): ?>
<section class="products-section">
    <div class="container">
        <div class="section-header">
            <h2>Produits Populaires</h2>
            <p>Découvrez les produits les plus récents de notre catalogue</p>
        </div>
        
        <div class="products-grid">
            <?php foreach ($popular_products as $product): ?>
                <?php include 'includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================
SECTION AVIS CLIENTS
============================ -->
<?php if (!empty($reviews)): ?>
<section class="reviews-section" style="background: #f8f9fa; padding: 60px 0;">
    <div class="container">
        <div class="section-header" style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-size: 32px; color: #333; margin-bottom: 10px;">Ce que disent nos clients</h2>
            
            <?php if ($total_reviews > 0): ?>
            <div class="rating-summary" style="display: inline-block; margin-top: 15px; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                <div class="average-rating" style="text-align: center;">
                    <div class="rating-stars" style="font-size: 24px; color: #f39c12; margin-bottom: 5px;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= floor($avg_rating)): ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i == ceil($avg_rating) && fmod($avg_rating, 1) > 0): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-number" style="font-size: 36px; font-weight: bold; color: #333;"><?php echo number_format($avg_rating, 1); ?>/5</div>
                    <div class="rating-count" style="color: #666; font-size: 14px;">sur <?php echo $total_reviews; ?> avis</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="reviews-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; margin-bottom: 40px;">
            <?php foreach ($reviews as $review): ?>
            <div class="review-card" style="background: white; border-radius: 10px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); position: relative; overflow: hidden;">
                <div class="review-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <div class="reviewer-info" style="display: flex; align-items: center; gap: 12px;">
                        <div class="reviewer-avatar" style="width: 45px; height: 45px; background: linear-gradient(135deg, #2d3d48, #005c46); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 16px;">
                            <?php echo strtoupper(substr($review['prenom'], 0, 1) . substr($review['nom'], 0, 1)); ?>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 5px 0; color: #333; font-size: 16px;"><?php echo htmlspecialchars($review['prenom'] . ' ' . $review['nom']); ?></h4>
                            <div class="review-date" style="font-size: 13px; color: #666;">
                                <i class="fas fa-calendar" style="margin-right: 5px;"></i>
                                <?php echo date('d/m/Y', strtotime($review['date_avis'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="review-stars" style="display: flex; align-items: center; gap: 3px;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $review['note'] ? '' : '-o'; ?>" style="color: #f39c12; font-size: 14px;"></i>
                        <?php endfor; ?>
                        <span class="rating-value" style="margin-left: 8px; font-weight: 600; color: #333; background: #fff9e6; padding: 2px 8px; border-radius: 10px; font-size: 12px;"><?php echo $review['note']; ?>/5</span>
                    </div>
                </div>
                
                <div class="review-product" style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                    <div class="review-product-image" style="width: 50px; height: 50px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: #ddd;">
                        <?php if ($review['url_image']): ?>
                            <img src="<?php echo $review['url_image']; ?>" alt="<?php echo htmlspecialchars($review['nom_produit']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #666;">
                                <i class="fas fa-box"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="review-product-info">
                        <h5 style="margin: 0 0 5px 0; font-size: 14px; color: #333;"><?php echo htmlspecialchars($review['nom_produit']); ?></h5>
                        <a href="product.php?id=<?php echo $review['id_produit']; ?>" class="view-product-link" style="font-size: 12px; color: #2d3d48; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-eye"></i> Voir le produit
                        </a>
                    </div>
                </div>
                
                <div class="review-content">
                    <p style="color: #555; line-height: 1.6; font-size: 14px; margin: 0;"><?php echo nl2br(htmlspecialchars($review['commentaire'])); ?></p>
                </div>
                
                <?php if ($review['note'] >= 4): ?>
                <div class="review-badge" style="display: inline-flex; align-items: center; gap: 8px; background: #e3f2fd; color: #1976d2; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 15px;">
                    <i class="fas fa-thumbs-up"></i> Recommandé
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="reviews-footer" style="text-align: center;">
            <?php if (isLoggedIn()): ?>
                <a href="add-review.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 25px;">
                    <i class="fas fa-pen"></i>
                    Laisser un avis
                </a>
            <?php else: ?>
                <a href="login.php?redirect=add-review.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 25px;">
                    <i class="fas fa-pen"></i>
                    Connectez-vous pour noter
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Tous les produits -->
<section class="products-section" id="products">
    <div class="container">
        <div class="products-header">
            <h2><?php echo empty($search) ? 'Tous nos produits' : 'Résultats de recherche'; ?></h2>
            <span class="text-muted"><?php echo $total_products; ?> produit(s) trouvé(s)</span>
        </div>
        
        <?php if (empty($products)): ?>
            <div class="no-products">
                <i class="fas fa-search fa-3x"></i>
                <h3>Aucun produit trouvé</h3>
                <p>Essayez de modifier vos critères de recherche</p>
                <a href="index.php" class="btn btn-primary">Voir tous les produits</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <?php include 'includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php
            $total_pages = ceil($total_products / $limit);
            if ($total_pages > 1):
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<!-- Section Laissez votre avis -->
<section class="leave-review-section" style="background: #f5f7fa; padding: 60px 0;">
    <div class="container">
        <div class="section-header" style="text-align: center; margin-bottom: 40px;">
            <h2 style="font-size: 32px; color: #333; margin-bottom: 15px;">Laissez votre avis</h2>
            <p style="color: #666; font-size: 16px; max-width: 700px; margin: 0 auto;">
                Partagez votre expérience avec nos produits et aidez d'autres clients à faire leur choix
            </p>
        </div>

        <div class="leave-review-form" style="max-width: 700px; margin: 0 auto; background: white; border-radius: 15px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <?php if (isLoggedIn()): ?>
                <form id="reviewForm" method="POST" action="process-review.php">
                    <div class="form-group" style="margin-bottom: 25px;">
                        <label for="product_id" style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">
                            <i class="fas fa-box" style="margin-right: 8px; color: #2d3d48;"></i>
                            Sélectionnez le produit
                        </label>
                        <select id="product_id" name="product_id" required 
                                style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;">
                            <option value="">Choisissez un produit...</option>
                            <?php 
                            // Récupérer tous les produits actifs pour le select
                            $products_list = $pdo->query("
                                SELECT p.id_produit, p.nom_produit, c.nom_categorie 
                                FROM produit p 
                                LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
                                WHERE p.est_actif = 1 
                                ORDER BY p.nom_produit
                            ")->fetchAll();
                            
                            foreach ($products_list as $product_item): 
                            ?>
                                <option value="<?php echo $product_item['id_produit']; ?>">
                                    <?php echo htmlspecialchars($product_item['nom_produit']) . ' - ' . htmlspecialchars($product_item['nom_categorie']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">
                            <i class="fas fa-star" style="margin-right: 8px; color: #f39c12;"></i>
                            Notez ce produit
                        </label>
                        <div class="rating-input" style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <div class="star-rating">
                                    <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" style="display: none;">
                                    <label for="star<?php echo $i; ?>" class="star-label" 
                                           style="font-size: 30px; color: #ddd; cursor: pointer; transition: color 0.2s;">
                                        ★
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-labels" style="display: flex; justify-content: space-between; font-size: 13px; color: #888;">
                            <span>Mauvais</span>
                            <span>Excellent</span>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label for="comment" style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">
                            <i class="fas fa-comment" style="margin-right: 8px; color: #2d3d48;"></i>
                            Votre commentaire
                        </label>
                        <textarea id="comment" name="comment" rows="5" 
                                  placeholder="Partagez votre expérience avec ce produit... (minimum 20 caractères)" 
                                  required minlength="20" maxlength="1000"
                                  style="width: 100%; padding: 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; font-family: inherit; resize: vertical; transition: border-color 0.3s;"></textarea>
                        <div class="char-count" style="text-align: right; font-size: 13px; color: #888; margin-top: 5px;">
                            <span id="charCount">0</span>/1000 caractères
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label for="review_title" style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">
                            <i class="fas fa-heading" style="margin-right: 8px; color: #2d3d48;"></i>
                            Titre de votre avis (optionnel)
                        </label>
                        <input type="text" id="review_title" name="review_title" 
                               placeholder="Ex: Excellent produit, je recommande !" 
                               maxlength="100"
                               style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s;">
                    </div>

                    <div class="form-submit" style="text-align: center;">
                        <button type="submit" class="btn btn-primary" 
                                style="background: linear-gradient(135deg, #2d3d48, #005c46); color: white; border: none; padding: 15px 40px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: transform 0.2s;">
                            <i class="fas fa-paper-plane"></i>
                            Publier mon avis
                        </button>
                        <p style="font-size: 14px; color: #666; margin-top: 15px;">
                            <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                            Votre avis sera soumis à modération avant publication
                        </p>
                    </div>
                </form>
            <?php else: ?>
                <div class="login-required" style="text-align: center; padding: 40px 20px;">
                    <div class="icon" style="font-size: 60px; color: #2d3d48; margin-bottom: 20px;">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <h3 style="color: #333; margin-bottom: 15px;">Connectez-vous pour laisser un avis</h3>
                    <p style="color: #666; margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto;">
                        Pour partager votre expérience avec nos produits, veuillez vous connecter à votre compte.
                    </p>
                    <div class="actions" style="display: flex; gap: 15px; justify-content: center;">
                        <a href="login.php?redirect=index.php" class="btn btn-primary"
                           style="background: linear-gradient(135deg, #2d3d48, #005c46); color: white; border: none; padding: 12px 30px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-sign-in-alt"></i>
                            Se connecter
                        </a>
                        <a href="register.php" class="btn btn-outline"
                           style="border: 2px solid #2d3d48; color: #2d3d48; padding: 12px 30px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-user-plus"></i>
                            Créer un compte
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Section Newsletter -->
<section class="newsletter">
    <div class="container">
        <div class="newsletter-content">
            <h2>Restez informé</h2>
            <p>Inscrivez-vous à notre newsletter pour recevoir nos offres exclusives</p>
            <form class="newsletter-form">
                <input type="email" placeholder="Votre email" required>
                <button type="submit" class="btn btn-primary">S'inscrire</button>
            </form>
        </div>
    </div>
</section>
<script>
// Gestion des étoiles de notation
document.querySelectorAll('.star-label').forEach(label => {
    label.addEventListener('click', function() {
        const starId = this.getAttribute('for');
        const ratingValue = starId.replace('star', '');
        
        // Réinitialiser toutes les étoiles
        document.querySelectorAll('.star-label').forEach(star => {
            star.style.color = '#ddd';
        });
        
        // Colorer les étoiles jusqu'à celle cliquée
        for (let i = 5; i >= ratingValue; i--) {
            const starLabel = document.querySelector(`label[for="star${i}"]`);
            if (starLabel) {
                starLabel.style.color = '#f39c12';
            }
        }
        
        // Mettre à jour la valeur cachée
        document.querySelector(`#${starId}`).checked = true;
    });
});

// Compteur de caractères pour le commentaire
const commentTextarea = document.getElementById('comment');
const charCount = document.getElementById('charCount');

if (commentTextarea && charCount) {
    commentTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length;
        
        // Changer la couleur si approche de la limite
        if (length > 900) {
            charCount.style.color = '#e74c3c';
            charCount.style.fontWeight = 'bold';
        } else if (length > 800) {
            charCount.style.color = '#f39c12';
        } else {
            charCount.style.color = '#888';
        }
    });
}

// Validation du formulaire
const reviewForm = document.getElementById('reviewForm');
if (reviewForm) {
    reviewForm.addEventListener('submit', function(e) {
        const rating = document.querySelector('input[name="rating"]:checked');
        const product = document.getElementById('product_id');
        const comment = document.getElementById('comment');
        
        let errors = [];
        
        if (!product.value) {
            errors.push("Veuillez sélectionner un produit");
            product.style.borderColor = '#e74c3c';
        } else {
            product.style.borderColor = '#e0e0e0';
        }
        
        if (!rating) {
            errors.push("Veuillez attribuer une note");
        }
        
        if (!comment.value || comment.value.length < 20) {
            errors.push("Le commentaire doit contenir au moins 20 caractères");
            comment.style.borderColor = '#e74c3c';
        } else {
            comment.style.borderColor = '#e0e0e0';
        }
        
        if (errors.length > 0) {
            e.preventDefault();
            alert("Veuillez corriger les erreurs suivantes :\n\n• " + errors.join("\n• "));
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>