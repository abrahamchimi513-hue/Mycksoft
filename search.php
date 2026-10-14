<?php
// search.php - Page de recherche
require_once __DIR__ . '/includes/config.php';

$page_title = "Recherche - " . SITE_NAME;

// Vérifier si la session est démarrée et récupérer les catégories
try {
    $header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
} catch (PDOException $e) {
    $header_categories = [];
    error_log("Erreur récupération catégories: " . $e->getMessage());
}
$header_show_products_link = true;

// Récupérer le terme de recherche
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_query_display = htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8');

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Filtres
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';

// Résultats
$results = [];
$total_results = 0;
$categories = [];

if (!empty($search_query)) {
    try {
        // Requête de base avec SQL_CALC_FOUND_ROWS
        $sql = "SELECT SQL_CALC_FOUND_ROWS p.*, 
                       c.nom_categorie,
                       (SELECT url_image FROM image_produit WHERE id_produit = p.id_produit AND est_principale = 1 LIMIT 1) as image
                FROM produit p
                LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
                WHERE p.est_actif = 1 
                AND (p.nom_produit LIKE :query 
                     OR p.description LIKE :query 
                     OR p.marque LIKE :query 
                     OR p.modele LIKE :query
                     OR c.nom_categorie LIKE :query)";
        
        $params = [':query' => '%' . $search_query . '%'];
        
        // Ajouter les filtres de catégorie
        if ($category_id > 0) {
            $sql .= " AND p.id_categorie = :category";
            $params[':category'] = $category_id;
        }
        
        // Ajouter les filtres de prix
        if ($min_price > 0) {
            $sql .= " AND p.prix >= :min_price";
            $params[':min_price'] = $min_price;
        }
        if ($max_price > 0) {
            $sql .= " AND p.prix <= :max_price";
            $params[':max_price'] = $max_price;
        }
        
        // Ajouter le tri
        switch ($sort) {
            case 'price_asc':
                $sql .= " ORDER BY COALESCE(p.prix_promotion, p.prix) ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY COALESCE(p.prix_promotion, p.prix) DESC";
                break;
            case 'newest':
                $sql .= " ORDER BY p.date_creation DESC";
                break;
            case 'name_asc':
                $sql .= " ORDER BY p.nom_produit ASC";
                break;
            case 'relevance':
            default:
                $sql .= " ORDER BY 
                    CASE 
                        WHEN p.nom_produit LIKE :exact_match THEN 1
                        WHEN p.nom_produit LIKE :starts_with THEN 2
                        ELSE 3
                    END,
                    p.nom_produit ASC";
                $params[':exact_match'] = $search_query;
                $params[':starts_with'] = $search_query . '%';
                break;
        }
        
        // Ajouter la pagination
        $sql .= " LIMIT :offset, :per_page";
        
        $stmt = $pdo->prepare($sql);
        
        // Lier les paramètres avec les bons types
        foreach ($params as $key => $value) {
            if ($key == ':offset') {
                $stmt->bindValue($key, $offset, PDO::PARAM_INT);
            } elseif ($key == ':per_page') {
                $stmt->bindValue($key, $per_page, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        // Récupérer le nombre total de résultats
        $total_results = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
        
        // Récupérer les catégories pour les filtres
        $categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Erreur de recherche: " . $e->getMessage());
        $results = [];
        $total_results = 0;
    }
}

// Calculer la pagination
$total_pages = $total_results > 0 ? ceil($total_results / $per_page) : 1;

include __DIR__ . '/includes/header.php';
?>

<style>
:root {
    --primary-color: #2d3d48;
    --secondary-color: #3f37c9;
    --success-color: #10b981;
    --danger-color: #ce1126;
    --warning-color: #f59e0b;
    --info-color: #4895ef;
    --dark-color: #1e293b;
    --light-color: #f8fafc;
    --gradient: linear-gradient(135deg, #2d3d48 0%, #1e2c34 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f1f5f9;
    color: var(--dark-color);
}

/* Hero section de recherche */
.search-hero {
    background: var(--gradient);
    padding: 4rem 2rem;
    text-align: center;
    color: white;
    margin-bottom: 2rem;
}

.search-hero h1 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.search-hero h1 i {
    font-size: 2.5rem;
    opacity: 0.9;
}

.search-box-large {
    max-width: 600px;
    margin: 2rem auto 0;
    display: flex;
    gap: 10px;
}

.search-box-large input {
    flex: 1;
    padding: 1rem 1.5rem;
    border: none;
    border-radius: 50px;
    font-size: 1.1rem;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.search-box-large input:focus {
    outline: none;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.search-box-large button {
    padding: 1rem 2rem;
    background: white;
    color: var(--primary-color);
    border: none;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.search-box-large button:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
}

/* Container principal */
.search-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem 4rem;
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 2rem;
}

/* Sidebar des filtres */
.filters-sidebar {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    height: fit-content;
    position: sticky;
    top: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.filters-header h3 {
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--dark-color);
}

.filters-header h3 i {
    color: var(--primary-color);
}

.clear-filters {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
    padding: 0.3rem 1rem;
    border-radius: 20px;
    background: #f1f5f9;
    transition: all 0.3s;
}

.clear-filters:hover {
    background: var(--primary-color);
    color: white;
}

.filter-section {
    margin-bottom: 2rem;
}

.filter-title {
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--dark-color);
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-title i {
    color: var(--primary-color);
    font-size: 0.9rem;
}

.filter-options {
    list-style: none;
}

.filter-option {
    margin-bottom: 0.8rem;
}

.filter-option a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #64748b;
    text-decoration: none;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s;
}

.filter-option a:hover {
    background: #f1f5f9;
    color: var(--primary-color);
}

.filter-option.active a {
    background: var(--gradient);
    color: white;
}

.filter-option .count {
    font-size: 0.8rem;
    color: #94a3b8;
}

.filter-option.active .count {
    color: rgba(255,255,255,0.8);
}

.price-inputs {
    display: flex;
    gap: 10px;
    align-items: center;
}

.price-input {
    flex: 1;
    padding: 0.8rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
}

.price-input:focus {
    outline: none;
    border-color: var(--primary-color);
}

.apply-filters {
    width: 100%;
    margin-top: 1rem;
    padding: 0.8rem;
    background: var(--gradient);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.apply-filters:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px -5px rgba(45,61,72,0.3);
}

/* Résultats de recherche */
.search-results {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 1rem;
}

.results-info h2 {
    font-size: 1.3rem;
    color: var(--dark-color);
    margin-bottom: 0.3rem;
}

.results-info p {
    color: #64748b;
    font-size: 0.9rem;
}

.results-sort {
    display: flex;
    align-items: center;
    gap: 10px;
}

.results-sort label {
    color: #64748b;
    font-size: 0.9rem;
}

.results-sort select {
    padding: 0.5rem 2rem 0.5rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    font-size: 0.9rem;
    background: white;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.7rem center;
}

.results-sort select:focus {
    outline: none;
    border-color: var(--primary-color);
}

/* Grille de produits */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.product-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: all 0.3s;
    border: 1px solid #e2e8f0;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 30px -5px rgba(0,0,0,0.1);
}

.product-image {
    width: 100%;
    height: 200px;
    overflow: hidden;
    position: relative;
    background: #f8fafc;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.product-card:hover .product-image img {
    transform: scale(1.1);
}

.product-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-new {
    background: var(--success-color);
    color: white;
}

.badge-promo {
    background: var(--danger-color);
    color: white;
}

.product-info {
    padding: 1.5rem;
}

.product-category {
    font-size: 0.8rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.product-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

.product-name a {
    color: inherit;
    text-decoration: none;
}

.product-name a:hover {
    color: var(--primary-color);
}

.product-meta {
    font-size: 0.8rem;
    color: #94a3b8;
    margin-bottom: 1rem;
}

.product-price {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 1rem;
}

.current-price {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-color);
}

.old-price {
    font-size: 0.9rem;
    color: #94a3b8;
    text-decoration: line-through;
}

.product-actions {
    display: flex;
    gap: 10px;
}

.btn-add-to-cart {
    flex: 1;
    padding: 0.8rem;
    background: var(--gradient);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    transition: all 0.3s;
}

.btn-add-to-cart:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px -5px rgba(45,61,72,0.3);
}

.btn-view {
    width: 40px;
    height: 40px;
    background: #f1f5f9;
    color: var(--dark-color);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.btn-view:hover {
    background: var(--primary-color);
    color: white;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.page-link {
    padding: 0.8rem 1.2rem;
    border-radius: 8px;
    background: white;
    color: var(--dark-color);
    text-decoration: none;
    border: 1px solid #e2e8f0;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 45px;
}

.page-link:hover,
.page-link.active {
    background: var(--gradient);
    color: white;
    border-color: transparent;
}

.page-link.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* No results */
.no-results {
    text-align: center;
    padding: 4rem 2rem;
    background: #f8fafc;
    border-radius: 20px;
}

.no-results i {
    font-size: 5rem;
    color: #cbd5e1;
    margin-bottom: 1.5rem;
}

.no-results h3 {
    font-size: 1.5rem;
    color: var(--dark-color);
    margin-bottom: 0.5rem;
}

.no-results p {
    color: #64748b;
    margin-bottom: 2rem;
}

.no-results .btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 1rem 2.5rem;
    background: var(--gradient);
    color: white;
    border-radius: 50px;
    text-decoration: none;
    transition: all 0.3s;
}

.no-results .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 30px -5px rgba(45,61,72,0.3);
}

/* Suggestions */
.search-suggestions {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 15px;
}

.suggestions-title {
    font-size: 1rem;
    color: var(--dark-color);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.suggestions-tags {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.suggestion-tag {
    padding: 0.5rem 1.2rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    color: var(--dark-color);
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.suggestion-tag:hover {
    background: var(--gradient);
    color: white;
    border-color: transparent;
}

/* Notifications */
.notification {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 9999;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    animation: slideIn 0.3s ease;
}

.notification-success {
    background: var(--success-color);
    color: white;
}

.notification-error {
    background: var(--danger-color);
    color: white;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

/* Responsive */
@media (max-width: 1024px) {
    .search-container {
        grid-template-columns: 1fr;
    }
    
    .filters-sidebar {
        position: static;
    }
}

@media (max-width: 768px) {
    .search-hero h1 {
        font-size: 2rem;
    }
    
    .search-box-large {
        flex-direction: column;
    }
    
    .results-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
}

@media (max-width: 480px) {
    .search-hero {
        padding: 3rem 1rem;
    }
    
    .search-hero h1 {
        font-size: 1.5rem;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Hero section -->
<section class="search-hero">
    <h1>
        <i class="fas fa-search"></i>
        Recherche de produits
    </h1>
    
    <form class="search-box-large" action="search.php" method="GET">
        <input type="text" name="q" placeholder="Rechercher un produit..." value="<?php echo $search_query_display; ?>" autofocus>
        <button type="submit">
            <i class="fas fa-search"></i>
            Rechercher
        </button>
    </form>
</section>

<div class="search-container">
    <!-- Sidebar des filtres -->
    <aside class="filters-sidebar">
        <div class="filters-header">
            <h3><i class="fas fa-filter"></i> Filtres</h3>
            <?php if (!empty($search_query)): ?>
            <a href="search.php?q=<?php echo urlencode($search_query); ?>" class="clear-filters">
                <i class="fas fa-times"></i> Effacer
            </a>
            <?php endif; ?>
        </div>
        
        <form method="GET" action="search.php" id="filterForm">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
            
            <!-- Catégories -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-tags"></i>
                    Catégories
                </div>
                <ul class="filter-options">
                    <li class="filter-option <?php echo $category_id == 0 ? 'active' : ''; ?>">
                        <a href="?q=<?php echo urlencode($search_query); ?>&sort=<?php echo $sort; ?>">
                            Toutes les catégories
                            <span class="count">(<?php echo $total_results; ?>)</span>
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                    <?php
                    // Compter les produits dans cette catégorie pour la recherche
                    try {
                        $count_stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM produit 
                            WHERE est_actif = 1 AND id_categorie = ?
                            AND (nom_produit LIKE ? OR description LIKE ?)
                        ");
                        $count_stmt->execute([$cat['id_categorie'], '%' . $search_query . '%', '%' . $search_query . '%']);
                        $cat_count = $count_stmt->fetchColumn();
                    } catch (Exception $e) {
                        $cat_count = 0;
                    }
                    ?>
                    <?php if ($cat_count > 0): ?>
                    <li class="filter-option <?php echo $category_id == $cat['id_categorie'] ? 'active' : ''; ?>">
                        <a href="?q=<?php echo urlencode($search_query); ?>&category=<?php echo $cat['id_categorie']; ?>&sort=<?php echo $sort; ?>">
                            <?php echo htmlspecialchars($cat['nom_categorie']); ?>
                            <span class="count">(<?php echo $cat_count; ?>)</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Prix -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Prix (FCFA)
                </div>
                <div class="price-inputs">
                    <input type="number" class="price-input" name="min_price" placeholder="Min" value="<?php echo $min_price ?: ''; ?>" min="0" step="1000">
                    <span>-</span>
                    <input type="number" class="price-input" name="max_price" placeholder="Max" value="<?php echo $max_price ?: ''; ?>" min="0" step="1000">
                </div>
                <button type="submit" class="apply-filters">
                    <i class="fas fa-check"></i>
                    Appliquer
                </button>
            </div>
            
            <!-- Tri caché pour le formulaire -->
            <input type="hidden" name="sort" id="sortInput" value="<?php echo $sort; ?>">
            <input type="hidden" name="page" value="1">
        </form>
    </aside>
    
    <!-- Résultats -->
    <main class="search-results">
        <?php if (empty($search_query)): ?>
            <!-- Pas de recherche -->
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>Effectuez une recherche</h3>
                <p>Utilisez la barre de recherche pour trouver des produits</p>
            </div>
            
        <?php elseif (empty($results)): ?>
            <!-- Aucun résultat -->
            <div class="no-results">
                <i class="fas fa-box-open"></i>
                <h3>Aucun résultat trouvé</h3>
                <p>Désolé, nous n'avons trouvé aucun produit correspondant à "<?php echo $search_query_display; ?>"</p>
                <a href="shop.php" class="btn">
                    <i class="fas fa-store"></i>
                    Voir tous nos produits
                </a>
            </div>
            
            <!-- Suggestions -->
            <div class="search-suggestions">
                <div class="suggestions-title">
                    <i class="fas fa-lightbulb"></i>
                    Suggestions de recherche
                </div>
                <div class="suggestions-tags">
                    <a href="search.php?q=ordinateur" class="suggestion-tag">ordinateur</a>
                    <a href="search.php?q=téléphone" class="suggestion-tag">téléphone</a>
                    <a href="search.php?q=accessoires" class="suggestion-tag">accessoires</a>
                    <a href="search.php?q=pc portable" class="suggestion-tag">pc portable</a>
                    <a href="search.php?q=imprimante" class="suggestion-tag">imprimante</a>
                    <a href="search.php?q=clavier" class="suggestion-tag">clavier</a>
                    <a href="search.php?q=souris" class="suggestion-tag">souris</a>
                    <a href="search.php?q=écran" class="suggestion-tag">écran</a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- En-tête des résultats -->
            <div class="results-header">
                <div class="results-info">
                    <h2>Résultats pour "<?php echo $search_query_display; ?>"</h2>
                    <p><?php echo number_format($total_results, 0, ',', ' '); ?> produit(s) trouvé(s)</p>
                </div>
                
                <div class="results-sort">
                    <label for="sort">Trier par :</label>
                    <select id="sort" onchange="updateSort(this.value)">
                        <option value="relevance" <?php echo $sort == 'relevance' ? 'selected' : ''; ?>>Pertinence</option>
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Plus récent</option>
                        <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Prix croissant</option>
                        <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Prix décroissant</option>
                        <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Nom (A-Z)</option>
                    </select>
                </div>
            </div>
            
            <!-- Grille de produits -->
            <div class="products-grid">
                <?php foreach ($results as $product): ?>
                <div class="product-card">
                    <div class="product-image">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['nom_produit']); ?>">
                        <?php else: ?>
                            <img src="assets/images/no-image.jpg" alt="Pas d'image">
                        <?php endif; ?>
                        
                        <?php
                        if (isset($product['date_creation'])) {
                            $date_added = new DateTime($product['date_creation']);
                            $now = new DateTime();
                            $diff = $now->diff($date_added)->days;
                            if ($diff < 7): ?>
                                <span class="product-badge badge-new">Nouveau</span>
                            <?php endif;
                        }
                        ?>
                        
                        <?php if (isset($product['est_en_promotion']) && $product['est_en_promotion'] && !empty($product['prix_promotion'])): ?>
                            <span class="product-badge badge-promo">Promo</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-info">
                        <div class="product-category">
                            <?php echo htmlspecialchars($product['nom_categorie'] ?? 'Non catégorisé'); ?>
                        </div>
                        
                        <h3 class="product-name">
                            <a href="product.php?id=<?php echo $product['id_produit']; ?>">
                                <?php echo htmlspecialchars($product['nom_produit']); ?>
                            </a>
                        </h3>
                        
                        <div class="product-meta">
                            <?php echo htmlspecialchars(($product['marque'] ?? '') . ' ' . ($product['modele'] ?? '')); ?>
                        </div>
                        
                        <div class="product-price">
                            <?php 
                            $prix_affiche = $product['prix'];
                            $prix_promo = $product['prix_promotion'] ?? null;
                            if (!empty($prix_promo) && $prix_promo < $prix_affiche): ?>
                                <span class="current-price"><?php echo number_format($prix_promo, 0, ',', ' '); ?> FCFA</span>
                                <span class="old-price"><?php echo number_format($prix_affiche, 0, ',', ' '); ?> FCFA</span>
                            <?php else: ?>
                                <span class="current-price"><?php echo number_format($prix_affiche, 0, ',', ' '); ?> FCFA</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-actions">
                            <button class="btn-add-to-cart" onclick="addToCart(<?php echo $product['id_produit']; ?>, this)">
                                <i class="fas fa-shopping-cart"></i>
                                Ajouter
                            </button>
                            <button class="btn-view" onclick="viewProduct(<?php echo $product['id_produit']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>&category=<?php echo $category_id; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&sort=<?php echo $sort; ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link active"><?php echo $i; ?></span>
                    <?php elseif ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
                        <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>&category=<?php echo $category_id; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&sort=<?php echo $sort; ?>" class="page-link">
                            <?php echo $i; ?>
                        </a>
                    <?php elseif ($i == 2 && $page > 4): ?>
                        <span class="page-link disabled">...</span>
                    <?php elseif ($i == $total_pages - 1 && $page < $total_pages - 3): ?>
                        <span class="page-link disabled">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>&category=<?php echo $category_id; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&sort=<?php echo $sort; ?>" class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>
    </main>
</div>

<script>
// Fonction pour mettre à jour le tri
function updateSort(value) {
    document.getElementById('sortInput').value = value;
    document.getElementById('filterForm').submit();
}

// Fonction pour ajouter au panier
function addToCart(productId, button) {
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    fetch('ajax/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'product_id=' + productId + '&quantity=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Produit ajouté au panier', 'success');
            button.innerHTML = '<i class="fas fa-check"></i> Ajouté';
            setTimeout(() => {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }, 2000);
        } else {
            showNotification(data.message || 'Erreur lors de l\'ajout au panier', 'error');
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'ajout au panier', 'error');
        button.innerHTML = originalHtml;
        button.disabled = false;
    });
}

// Fonction pour voir le produit
function viewProduct(productId) {
    window.location.href = 'product.php?id=' + productId;
}

// Fonction pour afficher les notifications
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 280);
    }, 3000);
}

// Validation des prix
document.querySelector('.apply-filters')?.addEventListener('click', function(e) {
    const minPrice = document.querySelector('input[name="min_price"]').value;
    const maxPrice = document.querySelector('input[name="max_price"]').value;
    
    if (minPrice && maxPrice && parseFloat(minPrice) > parseFloat(maxPrice)) {
        e.preventDefault();
        showNotification('Le prix minimum ne peut pas être supérieur au prix maximum', 'error');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>