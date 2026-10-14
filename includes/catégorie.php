<?php
// categories.php - Page complète des catégories
require_once 'includes/config.php';

// Titre de la page
$page_title = "Toutes nos catégories";
$header_show_products_link = true;

// Récupérer TOUTES les catégories avec le nombre de produits
try {
    $stmt = $pdo->query("
        SELECT c.*, 
               COUNT(p.id_produit) as nb_produits,
               (SELECT image_produit FROM produit WHERE id_categorie = c.id_categorie LIMIT 1) as image_categorie
        FROM categorie c
        LEFT JOIN produit p ON c.id_categorie = p.id_categorie
        GROUP BY c.id_categorie
        ORDER BY c.nom_categorie
    ");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
    $error = "Erreur lors du chargement des catégories";
}

// Récupérer les catégories populaires (avec le plus de produits)
try {
    $stmt_popular = $pdo->query("
        SELECT c.*, COUNT(p.id_produit) as nb_produits
        FROM categorie c
        LEFT JOIN produit p ON c.id_categorie = p.id_categorie
        GROUP BY c.id_categorie
        HAVING nb_produits > 0
        ORDER BY nb_produits DESC
        LIMIT 3
    ");
    $popular_categories = $stmt_popular->fetchAll();
} catch (Exception $e) {
    $popular_categories = [];
}

include 'includes/header.php';
?>

<main class="categories-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="index.php">Accueil</a>
            <span class="separator"><i class="fas fa-chevron-right"></i></span>
            <span>Catégories</span>
        </nav>

        <!-- En-tête de la page -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-th-large"></i>
                Toutes nos catégories
            </h1>
            <p class="page-description">
                Découvrez tous nos produits par catégorie. 
                <?php echo count($categories); ?> catégories disponibles
            </p>
        </div>

        <!-- Affichage des erreurs -->
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Catégories populaires (si disponibles) -->
        <?php if (!empty($popular_categories)): ?>
        <section class="popular-categories">
            <h2 class="section-title">
                <i class="fas fa-star"></i>
                Catégories populaires
            </h2>
            <div class="popular-grid">
                <?php foreach ($popular_categories as $cat): ?>
                <a href="categorie.php?id=<?php echo $cat['id_categorie']; ?>" class="popular-card">
                    <div class="popular-content">
                        <h3><?php echo htmlspecialchars($cat['nom_categorie']); ?></h3>
                        <p><?php echo $cat['nb_produits']; ?> produits</p>
                        <span class="btn-link">Voir les produits <i class="fas fa-arrow-right"></i></span>
                    </div>
                    <?php if ($cat['image_categorie']): ?>
                    <img src="assets/images/categories/<?php echo htmlspecialchars($cat['image_categorie']); ?>" 
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Grille des catégories -->
        <section class="all-categories">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Toutes les catégories
            </h2>
            
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>Aucune catégorie disponible pour le moment</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($categories as $categorie): ?>
                    <a href="categorie.php?id=<?php echo $categorie['id_categorie']; ?>" 
                       class="category-card">
                        <div class="category-icon">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="category-info">
                            <h3 class="category-name">
                                <?php echo htmlspecialchars($categorie['nom_categorie']); ?>
                            </h3>
                            <p class="category-desc">
                                <?php echo htmlspecialchars($categorie['description_categorie'] ?? 'Découvrez nos produits dans cette catégorie'); ?>
                            </p>
                            <div class="category-meta">
                                <span class="product-count">
                                    <i class="fas fa-box"></i>
                                    <?php echo $categorie['nb_produits']; ?> produits
                                </span>
                                <span class="view-link">
                                    Voir la catégorie 
                                    <i class="fas fa-arrow-right"></i>
                                </span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Section avantages -->
        <section class="benefits-section">
            <h2 class="section-title">Pourquoi choisir nos catégories ?</h2>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <i class="fas fa-search"></i>
                    <h3>Recherche facile</h3>
                    <p>Naviguez facilement entre nos différentes catégories</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-tags"></i>
                    <h3>Meilleurs prix</h3>
                    <p>Des prix compétitifs dans toutes nos catégories</p>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-truck"></i>
                    <h3>Livraison rapide</h3>
                    <p>Livraison dans tout le Cameroun</p>
                </div>
            </div>
        </section>
    </div>
</main>

<style>
/* Styles pour la page catégories */
.categories-page {
    padding: 2rem 0 4rem;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

/* Breadcrumb */
.breadcrumb {
    margin-bottom: 2rem;
    padding: 1rem 0;
    font-size: 0.95rem;
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
    transition: var(--transition);
}

.breadcrumb a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.breadcrumb .separator {
    margin: 0 0.75rem;
    color: var(--secondary);
    font-size: 0.8rem;
}

.breadcrumb span:last-child {
    color: var(--secondary);
    font-weight: 500;
}

/* En-tête */
.page-header {
    text-align: center;
    margin-bottom: 3rem;
    padding: 2rem;
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
}

.page-title {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.page-title i {
    background: linear-gradient(135deg, var(--green-cm), var(--green-dark));
    color: white;
    padding: 1rem;
    border-radius: 50%;
    font-size: 2rem;
}

.page-description {
    font-size: 1.1rem;
    color: var(--secondary);
    max-width: 600px;
    margin: 0 auto;
}

/* Titres de section */
.section-title {
    font-size: 1.8rem;
    color: var(--dark);
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 3px solid var(--green-light);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title i {
    color: var(--primary);
}

/* Catégories populaires */
.popular-categories {
    margin-bottom: 4rem;
}

.popular-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.popular-card {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
    display: flex;
    position: relative;
    min-height: 200px;
}

.popular-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.popular-card:hover .btn-link i {
    transform: translateX(5px);
}

.popular-content {
    flex: 1;
    padding: 2rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.popular-content h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.popular-content p {
    opacity: 0.9;
    margin-bottom: 1.5rem;
}

.btn-link {
    color: white;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-link i {
    transition: transform 0.3s ease;
}

.popular-card img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 50%;
    margin: 2rem;
    border: 4px solid white;
    box-shadow: var(--shadow);
}

/* Grille des catégories */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.category-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    text-decoration: none;
    color: inherit;
    display: flex;
    gap: 1.5rem;
    transition: var(--transition);
    border: 2px solid transparent;
    box-shadow: var(--shadow);
}

.category-card:hover {
    border-color: var(--primary);
    transform: translateX(5px);
    box-shadow: var(--shadow-lg);
}

.category-icon {
    width: 60px;
    height: 60px;
    background: var(--green-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.category-icon i {
    font-size: 1.8rem;
    color: var(--primary);
}

.category-info {
    flex: 1;
}

.category-name {
    font-size: 1.3rem;
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.category-desc {
    color: var(--secondary);
    font-size: 0.95rem;
    margin-bottom: 1rem;
    line-height: 1.4;
}

.category-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-count {
    color: var(--secondary);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.view-link {
    color: var(--primary);
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0;
    transition: var(--transition);
}

.category-card:hover .view-link {
    opacity: 1;
}

.view-link i {
    font-size: 0.8rem;
}

/* État vide */
.empty-state {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
}

.empty-state i {
    font-size: 4rem;
    color: var(--secondary);
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state p {
    font-size: 1.2rem;
    color: var(--secondary);
}

/* Section avantages */
.benefits-section {
    margin-top: 4rem;
    padding: 3rem;
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.benefit-item {
    text-align: center;
    padding: 2rem;
    transition: var(--transition);
}

.benefit-item:hover {
    transform: translateY(-5px);
}

.benefit-item i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
    background: var(--green-light);
    padding: 1rem;
    border-radius: 50%;
}

.benefit-item h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--dark);
}

.benefit-item p {
    color: var(--secondary);
    font-size: 0.95rem;
}

/* Message d'alerte */
.alert {
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* Responsive */
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .page-title i {
        font-size: 1.5rem;
        padding: 0.75rem;
    }
    
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .category-card {
        padding: 1rem;
    }
    
    .popular-card {
        flex-direction: column;
    }
    
    .popular-card img {
        width: 80px;
        height: 80px;
        margin: 1rem auto;
    }
    
    .benefits-section {
        padding: 2rem 1rem;
    }
    
    .benefits-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>