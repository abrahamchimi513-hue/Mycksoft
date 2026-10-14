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

<?php include 'includes/footer.php'; ?> 