<?php
// produits.php - Page de tous les produits par catégories

// Inclure la configuration
require_once 'includes/config.php';

$page_title = "Nos produits par catégories - " . SITE_NAME;

// Récupérer toutes les catégories avec leurs produits
try {
    $stmt = $pdo->query("
        SELECT 
            c.*,
            COUNT(p.id_produit) as total_produits,
            MIN(p.prix) as prix_min,
            MAX(p.prix) as prix_max
        FROM categorie c
        LEFT JOIN produit p ON c.id_categorie = p.id_categorie AND p.est_actif = 1
        WHERE c.est_active = 1
        GROUP BY c.id_categorie
        HAVING total_produits > 0
        ORDER BY c.ordre_display, c.nom_categorie
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    error_log("Erreur récupération catégories: " . $e->getMessage());
}

// Pour chaque catégorie, récupérer ses 8 premiers produits
$categories_with_products = [];
foreach ($categories as $categorie) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id_produit,
                p.nom_produit,
                p.description_courte,
                p.description,
                p.prix,
                p.prix_promotion,
                p.stock,
                p.note_moyenne,
                p.note_count,
                p.date_creation,
                (SELECT ip.url_image 
                 FROM image_produit ip 
                 WHERE ip.id_produit = p.id_produit 
                 AND ip.est_principale = 1 
                 LIMIT 1) as image_url
            FROM produit p
            WHERE p.id_categorie = :categorie_id 
            AND p.est_actif = 1
            ORDER BY p.date_creation DESC
            LIMIT 8
        ");
        $stmt->execute([':categorie_id' => $categorie['id_categorie']]);
        $produits = $stmt->fetchAll();
        
        if (!empty($produits)) {
            $categorie['produits'] = $produits;
            $categories_with_products[] = $categorie;
        }
    } catch (PDOException $e) {
        error_log("Erreur récupération produits pour catégorie " . $categorie['id_categorie'] . ": " . $e->getMessage());
    }
}

// Récupérer les produits en vedette
try {
    $stmt = $pdo->query("
        SELECT p.*, c.nom_categorie,
               (SELECT url_image FROM image_produit 
                WHERE id_produit = p.id_produit AND est_principale = 1 
                LIMIT 1) as image_url
        FROM produit p
        LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
        WHERE p.est_actif = 1 
        AND p.mis_en_avant = 1
        ORDER BY RAND()
        LIMIT 4
    ");
    $featured_products = $stmt->fetchAll();
} catch (PDOException $e) {
    $featured_products = [];
}

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
    /* Couleur de fond comme le dashboard */
    body {
        background-color: #f4f6f9;
    }
    
    .main-content {
        background-color: #f4f6f9;
        min-height: 100vh;
    }
    
    /* Hero section avec dégradé comme le dashboard */
    .hero-section {
        background: linear-gradient(135deg, #2d3d48, #1e2c34);
        color: white;
        padding: 3rem 0;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    
    .hero-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,170.7C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') bottom no-repeat;
        background-size: cover;
        opacity: 0.1;
    }
    
    .hero-content {
        position: relative;
        z-index: 2;
    }
    
    .hero-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }
    
    /* Section catégorie */
    .category-section {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2.5rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.03);
    }
    
    .category-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f2f5;
    }
    
    .category-title {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .category-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #2d3d48, #1e2c34);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }
    
    .category-name {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    
    .category-stats {
        display: flex;
        gap: 1rem;
        color: #64748b;
        font-size: 0.9rem;
    }
    
    .category-stats i {
        color: #2d3d48;
        margin-right: 0.3rem;
    }
    
    .view-all-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.2rem;
        background: #f8fafc;
        color: #2d3d48;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s;
        border: 1px solid #e2e8f0;
    }
    
    .view-all-link:hover {
        background: #2d3d48;
        color: white;
        border-color: #2d3d48;
        transform: translateX(5px);
    }
    
    /* Grille de produits */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
    }
    
    /* Carte produit */
    .product-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        border: 1px solid #edf2f7;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(45, 61, 72, 0.1);
        border-color: #2d3d48;
    }
    
    .product-image-wrapper {
        position: relative;
        overflow: hidden;
        aspect-ratio: 1/1;
        background: #f8fafc;
    }
    
    .product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .product-card:hover .product-image {
        transform: scale(1.08);
    }
    
    .product-badges {
        position: absolute;
        top: 1rem;
        left: 1rem;
        right: 1rem;
        display: flex;
        justify-content: space-between;
        pointer-events: none;
        z-index: 2;
    }
    
    .product-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 600;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .badge-promo {
        background: #ce1126;
        color: white;
    }
    
    .badge-new {
        background: #2d3d48;
        color: white;
    }
    
    .badge-stock {
        background: white;
        color: #1e293b;
    }
    
    .badge-stock.low {
        background: #f59e0b;
        color: white;
    }
    
    .product-content {
        padding: 1.2rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .product-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        line-height: 1.4;
        color: #1e293b;
        text-decoration: none;
        transition: color 0.3s;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-title:hover {
        color: #2d3d48;
    }
    
    .product-rating {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    
    .stars {
        color: #f59e0b;
        font-size: 0.8rem;
    }
    
    .rating-count {
        color: #64748b;
        font-size: 0.7rem;
    }
    
    .product-price-wrapper {
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
        margin-top: auto;
        margin-bottom: 1rem;
    }
    
    .current-price {
        font-size: 1.2rem;
        font-weight: 700;
        color: #2d3d48;
    }
    
    .old-price {
        font-size: 0.9rem;
        color: #94a3b8;
        text-decoration: line-through;
    }
    
    .btn-add-cart {
        width: 100%;
        background: linear-gradient(135deg, #2d3d48, #1e2c34);
        color: white;
        border: none;
        padding: 0.6rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .btn-add-cart:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(45, 61, 72, 0.3);
    }
    
    .btn-add-cart:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Section produits vedettes */
    .featured-section {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        margin: 3rem 0;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.03);
        position: relative;
        overflow: hidden;
    }
    
    .featured-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(135deg, transparent 30%, rgba(45, 61, 72, 0.03) 50%, transparent 70%);
        animation: shine 6s infinite;
    }
    
    @keyframes shine {
        0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
    }
    
    .featured-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1.5rem;
        position: relative;
        display: inline-block;
    }
    
    .featured-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 60px;
        height: 4px;
        background: #2d3d48;
        border-radius: 2px;
    }
    
    .featured-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
    }
    
    /* Message quand aucune catégorie */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }
    
    .empty-state h3 {
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    
    .empty-state p {
        color: #64748b;
        margin-bottom: 1.5rem;
    }
    
    /* Loader */
    .loader {
        text-align: center;
        padding: 2rem;
        color: #64748b;
    }
    
    .loader i {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .hero-title {
            font-size: 1.8rem;
        }
        
        .category-header {
            flex-direction: column;
            align-items: start;
            gap: 1rem;
        }
        
        .category-stats {
            flex-wrap: wrap;
        }
        
        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }
    }
    
    @media (max-width: 576px) {
        .products-grid {
            grid-template-columns: 1fr;
        }
        
        .product-card {
            max-width: 300px;
            margin: 0 auto;
        }
    }
</style>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="hero-title">
                    <i class="fas fa-boxes me-3"></i>
                    Nos produits par catégories
                </h1>
                <p class="mb-3 opacity-75">
                    Découvrez notre sélection de produits organisés par catégories
                </p>
                
                <div class="hero-breadcrumb">
                    <a href="index.php" style="color: white; text-decoration: none; opacity: 0.8;">Accueil</a>
                    <span class="separator mx-2">/</span>
                    <span>Nos produits</span>
                </div>
                
                <div class="hero-stats mt-4">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo count($categories); ?></span>
                        <span class="stat-label">Catégories</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php 
                            $total = 0;
                            foreach ($categories_with_products as $cat) {
                                $total += count($cat['produits']);
                            }
                            echo $total;
                        ?></span>
                        <span class="stat-label">Produits</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container main-content pb-5">
    
    <?php displayFlashMessage(); ?>
    
    <?php if (empty($categories_with_products)): ?>
        <!-- Aucune catégorie avec produits -->
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>Aucun produit disponible</h3>
            <p>Nous n'avons pas encore de produits dans nos catégories.</p>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Retour à l'accueil
            </a>
        </div>
    <?php else: ?>
        
        <!-- Produits vedettes -->
        <?php if (!empty($featured_products)): ?>
        <div class="featured-section">
            <h2 class="featured-title">
                <i class="fas fa-star text-warning me-2"></i>
                Produits vedettes
            </h2>
            
            <div class="featured-grid">
                <?php foreach ($featured_products as $product): 
                    $prix_actuel = $product['prix_promotion'] ?? $product['prix'];
                    $ancien_prix = $product['prix_promotion'] ? $product['prix'] : null;
                    $promotion = $ancien_prix ? round((($ancien_prix - $prix_actuel) / $ancien_prix) * 100) : 0;
                ?>
                <div class="product-card">
                    <div class="product-image-wrapper">
                        <?php if ($product['image_url']): ?>
                        <img src="<?php echo UPLOADS_URL . '/' . $product['image_url']; ?>" 
                             class="product-image" 
                             alt="<?php echo htmlspecialchars($product['nom_produit']); ?>">
                        <?php else: ?>
                        <div class="product-image d-flex align-items-center justify-content-center">
                            <i class="fas fa-image fa-3x text-muted opacity-50"></i>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($promotion > 0): ?>
                        <div class="product-badges">
                            <span class="product-badge badge-promo">-<?php echo $promotion; ?>%</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-content">
                        <span class="product-category text-muted small mb-1">
                            <?php echo htmlspecialchars($product['nom_categorie']); ?>
                        </span>
                        
                        <a href="product.php?id=<?php echo $product['id_produit']; ?>" class="product-title">
                            <?php echo htmlspecialchars($product['nom_produit']); ?>
                        </a>
                        
                        <div class="product-price-wrapper">
                            <span class="current-price"><?php echo formatPrice($prix_actuel); ?></span>
                            <?php if ($ancien_prix): ?>
                            <span class="old-price"><?php echo formatPrice($ancien_prix); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($product['stock'] > 0): ?>
                        <button class="btn-add-cart add-to-cart" 
                                data-product-id="<?php echo $product['id_produit']; ?>"
                                data-product-name="<?php echo htmlspecialchars($product['nom_produit']); ?>">
                            <i class="fas fa-cart-plus"></i>
                            Ajouter
                        </button>
                        <?php else: ?>
                        <button class="btn-add-cart" disabled>
                            <i class="fas fa-times-circle"></i>
                            Indisponible
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Catégories avec leurs produits -->
        <?php foreach ($categories_with_products as $index => $categorie): ?>
            <div class="category-section">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">
                            <i class="fas <?php echo $categorie['icone_categorie'] ?? 'fa-tag'; ?>"></i>
                        </div>
                        <div>
                            <h2 class="category-name">
                                <?php echo htmlspecialchars($categorie['nom_categorie']); ?>
                            </h2>
                            <div class="category-stats">
                                <span>
                                    <i class="fas fa-box"></i>
                                    <?php echo $categorie['total_produits']; ?> produits
                                </span>
                                <?php if ($categorie['prix_min'] > 0): ?>
                                <span>
                                    <i class="fas fa-tag"></i>
                                    À partir de <?php echo formatPrice($categorie['prix_min']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <a href="categorie.php?id=<?php echo $categorie['id_categorie']; ?>" class="view-all-link">
                        Voir tous les produits
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <div class="products-grid">
                    <?php foreach ($categorie['produits'] as $product): 
                        $prix_actuel = $product['prix_promotion'] ?? $product['prix'];
                        $ancien_prix = $product['prix_promotion'] ? $product['prix'] : null;
                        $promotion = $ancien_prix ? round((($ancien_prix - $prix_actuel) / $ancien_prix) * 100) : 0;
                        
                        // Vérifier si le produit est récent (moins de 7 jours)
                        $est_nouveau = (time() - strtotime($product['date_creation'])) < 7 * 24 * 60 * 60;
                    ?>
                    <div class="product-card">
                        <div class="product-image-wrapper">
                            <?php if ($product['image_url']): ?>
                            <img src="<?php echo UPLOADS_URL . '/' . $product['image_url']; ?>" 
                                 class="product-image" 
                                 alt="<?php echo htmlspecialchars($product['nom_produit']); ?>">
                            <?php else: ?>
                            <div class="product-image d-flex align-items-center justify-content-center">
                                <i class="fas fa-image fa-3x text-muted opacity-50"></i>
                            </div>
                            <?php endif; ?>
                            
                            <div class="product-badges">
                                <div class="badge-left">
                                    <?php if ($promotion > 0): ?>
                                    <span class="product-badge badge-promo">-<?php echo $promotion; ?>%</span>
                                    <?php endif; ?>
                                    <?php if ($est_nouveau): ?>
                                    <span class="product-badge badge-new">Nouveau</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($product['stock'] < 5 && $product['stock'] > 0): ?>
                                <span class="product-badge badge-stock low">Stock limité</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="product-content">
                            <a href="product.php?id=<?php echo $product['id_produit']; ?>" class="product-title">
                                <?php echo htmlspecialchars($product['nom_produit']); ?>
                            </a>
                            
                            <?php if ($product['note_moyenne'] > 0): ?>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php 
                                    $note = $product['note_moyenne'];
                                    for ($i = 1; $i <= 5; $i++): 
                                        if ($i <= $note): ?>
                                            <i class="fas fa-star"></i>
                                        <?php elseif ($i - 0.5 <= $note): ?>
                                            <i class="fas fa-star-half-alt"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-count">(<?php echo $product['note_count'] ?: 0; ?>)</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="product-price-wrapper">
                                <span class="current-price"><?php echo formatPrice($prix_actuel); ?></span>
                                <?php if ($ancien_prix): ?>
                                <span class="old-price"><?php echo formatPrice($ancien_prix); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($product['stock'] > 0): ?>
                            <button class="btn-add-cart add-to-cart" 
                                    data-product-id="<?php echo $product['id_produit']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['nom_produit']); ?>">
                                <i class="fas fa-cart-plus"></i>
                                Ajouter au panier
                            </button>
                            <?php else: ?>
                            <button class="btn-add-cart" disabled>
                                <i class="fas fa-times-circle"></i>
                                Rupture de stock
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
    <?php endif; ?>
</div>

<!-- Scripts -->
<script>
// Ajout au panier
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const originalText = this.innerHTML;
            
            // Animation de chargement
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
            this.disabled = true;
            
            // Ajouter au panier via AJAX
            fetch('ajax/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=1&csrf_token=<?php echo generateCSRFToken(); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(productName + ' ajouté au panier', 'success');
                    
                    // Mettre à jour le compteur
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount) {
                        cartCount.textContent = data.cart_count;
                        cartCount.style.animation = 'pulse 0.5s ease';
                        setTimeout(() => {
                            cartCount.style.animation = '';
                        }, 500);
                    }
                    
                    // Animation de succès
                    this.innerHTML = '<i class="fas fa-check"></i> Ajouté';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 2000);
                } else {
                    showNotification(data.message || 'Erreur lors de l\'ajout', 'error');
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur technique', 'error');
                this.innerHTML = originalText;
                this.disabled = false;
            });
        });
    });
});

// Notification
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = 'notification ' + type;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        min-width: 300px;
        padding: 1rem 1.5rem;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 1rem;
        z-index: 9999;
        border-left: 4px solid ${type === 'success' ? '#10b981' : '#ce1126'};
        animation: slideIn 0.3s ease;
    `;
    
    notification.innerHTML = `
        <div style="width: 30px; height: 30px; border-radius: 50%; background: ${type === 'success' ? '#10b981' : '#ce1126'}; display: flex; align-items: center; justify-content: center; color: white;">
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}"></i>
        </div>
        <div style="flex: 1; color: #1e293b;">${message}</div>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #64748b; cursor: pointer;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Styles pour les animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.3); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(style);
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?>