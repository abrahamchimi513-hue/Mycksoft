<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-star"></i> Avis de nos clients</h1>
        <p class="lead">Découvrez ce que nos clients pensent de nos produits</p>
    </div>
    
    <!-- Statistiques -->
    <div class="reviews-stats">
        <div class="average-rating-large">
            <div class="rating-number"><?php echo number_format($stats['moyenne'], 1); ?>/5</div>
            <div class="rating-stars-large">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php if ($i <= floor($stats['moyenne'])): ?>
                        <i class="fas fa-star"></i>
                    <?php elseif ($i == ceil($stats['moyenne']) && fmod($stats['moyenne'], 1) > 0): ?>
                        <i class="fas fa-star-half-alt"></i>
                    <?php else: ?>
                        <i class="far fa-star"></i>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <div class="rating-count">Basé sur <?php echo $total_reviews; ?> avis</div>
        </div>
        
        <!-- Répartition des notes -->
        <div class="rating-distribution">
            <?php for ($i = 5; $i >= 1; $i--): 
                $count = $stats[$i . '_stars'] ?? 0;
                $percentage = $total_reviews > 0 ? ($count / $total_reviews) * 100 : 0;
            ?>
            <div class="rating-bar">
                <div class="rating-label"><?php echo $i; ?> étoiles</div>
                <div class="rating-progress">
                    <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                </div>
                <div class="rating-count"><?php echo $count; ?></div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="reviews-filters">
        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">Tous les avis</button>
            <button class="filter-btn" data-filter="5">5 étoiles</button>
            <button class="filter-btn" data-filter="4">4 étoiles</button>
            <button class="filter-btn" data-filter="3">3 étoiles</button>
        </div>
    </div>
    
    <!-- Liste des avis -->
    <div class="reviews-list">
        <?php if (empty($reviews)): ?>
            <div class="no-reviews">
                <i class="fas fa-comments fa-3x"></i>
                <h3>Aucun avis pour le moment</h3>
                <p>Soyez le premier à laisser un avis !</p>
            </div>
        <?php else: ?>
            <div class="reviews-grid">
                <?php foreach ($reviews as $review): ?>
                <div class="review-card-full" data-rating="<?php echo $review['note']; ?>">
                    <!-- Même structure que dans index.php mais plus détaillée -->
                    <!-- ... -->
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php
    $total_pages = ceil($total_reviews / $limit);
    if ($total_pages > 1):
    ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>" class="page-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>" 
               class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>" class="page-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- CTA pour laisser un avis -->
    <div class="reviews-cta">
        <div class="cta-content">
            <h3>Partagez votre expérience</h3>
            <p>Votre avis nous aide à améliorer nos produits et services</p>
            <?php if (isLoggedIn()): ?>
                <a href="add-review.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-pen"></i>
                    Laisser un avis
                </a>
            <?php else: ?>
                <a href="login.php?redirect=add-review.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt"></i>
                    Connectez-vous pour noter
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>



<?php
require_once 'includes/config.php';

// GESTION AJOUT AU PANIER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour ajouter des produits au panier', 'error');
        redirect('../login.php');
    }
    
    $product_id = intval($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    $result = addToCart($product_id, $quantity);
    
    if ($result['success']) {
        setFlashMessage('Produit ajouté au panier avec succès', 'success');
        // Recharger la page sans paramètres POST pour éviter la resoumission
        redirect('../index.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
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

<?php include 'includes/footer.php'; ?> <?php
require_once 'includes/config.php';

// GESTION AJOUT AU PANIER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour ajouter des produits au panier', 'error');
        redirect('../login.php');
    }
    
    $product_id = intval($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    $result = addToCart($product_id, $quantity);
    
    if ($result['success']) {
        setFlashMessage('Produit ajouté au panier avec succès', 'success');
        // Recharger la page sans paramètres POST pour éviter la resoumission
        redirect('../index.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
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
<!-- Section Avis Clients -->
<?php
// Récupérer les derniers avis approuvés
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
    LIMIT 8
")->fetchAll();

// Calculer la note moyenne des produits
$average_rating_sql = $pdo->query("
    SELECT 
        ROUND(AVG(note), 1) as moyenne_notes,
        COUNT(*) as total_avis
    FROM avis 
    WHERE est_approuve = 1
")->fetch();
?>

<?php if (!empty($reviews)): ?>
<section class="reviews-section">
    <div class="container">
        <div class="section-header">
            <h2>Ce que disent nos clients</h2>
            <div class="rating-summary">
                <div class="average-rating">
                    <div class="rating-stars">
                        <?php
                        $avg_rating = $average_rating_sql['moyenne_notes'] ?? 0;
                        $total_reviews = $average_rating_sql['total_avis'] ?? 0;
                        for ($i = 1; $i <= 5; $i++):
                            if ($i <= floor($avg_rating)):
                        ?>
                            <i class="fas fa-star"></i>
                        <?php elseif ($i == ceil($avg_rating) && fmod($avg_rating, 1) > 0): ?>
                            <i class="fas fa-star-half-alt"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; endfor; ?>
                    </div>
                    <div class="rating-number"><?php echo number_format($avg_rating, 1); ?>/5</div>
                    <div class="rating-count">sur <?php echo $total_reviews; ?> avis</div>
                </div>
            </div>
        </div>
        
        <div class="reviews-grid">
            <?php foreach ($reviews as $review): ?>
            <div class="review-card-home">
                <div class="review-header">
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">
                            <?php echo strtoupper(substr($review['prenom'], 0, 1) . substr($review['nom'], 0, 1)); ?>
                        </div>
                        <div>
                            <h4><?php echo htmlspecialchars($review['prenom'] . ' ' . $review['nom']); ?></h4>
                            <div class="review-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d/m/Y', strtotime($review['date_avis'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="review-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $review['note'] ? '' : '-o'; ?>"></i>
                        <?php endfor; ?>
                        <span class="rating-value"><?php echo $review['note']; ?>/5</span>
                    </div>
                </div>
                
                <div class="review-product">
                    <div class="review-product-image">
                        <?php if ($review['url_image'] && file_exists($review['url_image'])): ?>
                            <img src="<?php echo $review['url_image']; ?>" alt="<?php echo htmlspecialchars($review['nom_produit']); ?>">
                        <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-box"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="review-product-info">
                        <h5><?php echo htmlspecialchars($review['nom_produit']); ?></h5>
                        <a href="product.php?id=<?php echo $review['id_produit']; ?>" class="view-product-link">
                            Voir le produit
                        </a>
                    </div>
                </div>
                
                <div class="review-content">
                    <p><?php echo nl2br(htmlspecialchars($review['commentaire'])); ?></p>
                </div>
                
                <?php if ($review['note'] >= 4): ?>
                <div class="review-badge">
                    <i class="fas fa-thumbs-up"></i> Recommandé
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="reviews-footer">
            <a href="reviews.php" class="btn btn-outline">
                <i class="fas fa-star"></i>
                Voir tous les avis
            </a>
            
            <?php if (isLoggedIn()): ?>
                <a href="add-review.php" class="btn btn-primary">
                    <i class="fas fa-pen"></i>
                    Laisser un avis
                </a>
            <?php else: ?>
                <a href="login.php?redirect=add-review.php" class="btn btn-primary">
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

<?php include 'includes/footer.php'; ?> 
