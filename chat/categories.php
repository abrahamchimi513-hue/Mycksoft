<?php
// chat/categories.php - Catégories de produits pour le chat
require_once __DIR__ . '/../includes/config.php';

$page_title = "Catégories - " . SITE_NAME;

// Récupérer les catégories pour le header
$header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_show_products_link = true;

// Récupérer toutes les catégories avec le nombre de produits
$stmt = $pdo->query("
    SELECT c.*, 
           COUNT(p.id_produit) as nb_produits,
           (SELECT COUNT(*) FROM categorie WHERE id_categorie_parent = c.id_categorie) as nb_sous_categories
    FROM categorie c
    LEFT JOIN produit p ON c.id_categorie = p.id_categorie AND p.est_actif = 1
    GROUP BY c.id_categorie
    ORDER BY c.nom_categorie
");
$categories = $stmt->fetchAll();

// Récupérer les catégories populaires (avec le plus de produits)
$stmt_popular = $pdo->query("
    SELECT c.*, COUNT(p.id_produit) as nb_produits
    FROM categorie c
    LEFT JOIN produit p ON c.id_categorie = p.id_categorie AND p.est_actif = 1
    GROUP BY c.id_categorie
    HAVING nb_produits > 0
    ORDER BY nb_produits DESC
    LIMIT 8
");
$popular_categories = $stmt_popular->fetchAll();

include __DIR__ . '/../includes/header.php';
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

/* Hero section */
.categories-hero {
    background: var(--gradient);
    padding: 4rem 2rem;
    text-align: center;
    color: white;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.categories-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 30s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.categories-hero h1 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    position: relative;
}

.categories-hero h1 i {
    font-size: 2.5rem;
    opacity: 0.9;
}

.categories-hero p {
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
    opacity: 0.9;
    position: relative;
}

/* Container principal */
.categories-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem 4rem;
}

/* Statistiques */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 25px -5px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: var(--gradient);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.stat-info h3 {
    font-size: 1.5rem;
    color: var(--dark-color);
    margin-bottom: 0.2rem;
}

.stat-info p {
    color: #64748b;
    font-size: 0.9rem;
}

/* Sections */
.section-title {
    font-size: 1.8rem;
    color: var(--dark-color);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: var(--primary-color);
}

.section-title:after {
    content: '';
    flex: 1;
    height: 2px;
    background: linear-gradient(90deg, var(--primary-color), transparent);
    margin-left: 1rem;
}

/* Grille des catégories */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 4rem;
}

.category-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: all 0.3s;
    text-decoration: none;
    color: inherit;
    border: 1px solid #e2e8f0;
    display: block;
}

.category-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 35px -8px rgba(0,0,0,0.15);
    border-color: var(--primary-color);
}

.category-image {
    width: 100%;
    height: 200px;
    overflow: hidden;
    position: relative;
    background: #f8fafc;
}

.category-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.category-card:hover .category-image img {
    transform: scale(1.1);
}

.category-icon {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 5rem;
}

.category-overlay {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255,255,255,0.9);
    padding: 0.5rem 1rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--primary-color);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.category-content {
    padding: 1.5rem;
}

.category-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.category-name i {
    color: var(--primary-color);
    opacity: 0;
    transform: translateX(-10px);
    transition: all 0.3s;
}

.category-card:hover .category-name i {
    opacity: 1;
    transform: translateX(0);
}

.category-description {
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 1rem;
}

.category-stats {
    display: flex;
    gap: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
}

.category-stat {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #64748b;
    font-size: 0.9rem;
}

.category-stat i {
    color: var(--primary-color);
}

.category-stat strong {
    color: var(--dark-color);
    margin-right: 3px;
}

/* Catégories populaires */
.popular-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 4rem;
}

.popular-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: all 0.3s;
    text-decoration: none;
    color: inherit;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.popular-card:hover {
    transform: translateX(10px);
    background: var(--gradient);
    color: white;
    border-color: transparent;
}

.popular-icon {
    width: 50px;
    height: 50px;
    background: var(--gradient);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    transition: all 0.3s;
}

.popular-card:hover .popular-icon {
    background: white;
    color: var(--primary-color);
}

.popular-info h4 {
    font-size: 1.1rem;
    margin-bottom: 0.2rem;
}

.popular-info p {
    font-size: 0.8rem;
    opacity: 0.8;
}

.popular-card:hover .popular-info p {
    color: rgba(255,255,255,0.9);
}

/* Arborescence */
.tree-section {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    margin-bottom: 3rem;
}

.category-tree {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.tree-item {
    border-left: 3px solid var(--primary-color);
    padding-left: 1rem;
}

.tree-main {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tree-main a {
    color: inherit;
    text-decoration: none;
}

.tree-main a:hover {
    color: var(--primary-color);
}

.tree-main span {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: normal;
}

.tree-subs {
    list-style: none;
}

.tree-subs li {
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.tree-subs a {
    color: #64748b;
    text-decoration: none;
    font-size: 0.95rem;
    transition: all 0.3s;
}

.tree-subs a:hover {
    color: var(--primary-color);
    transform: translateX(5px);
}

.tree-subs .count {
    font-size: 0.8rem;
    color: #94a3b8;
}

/* Chat flottant */
.chat-float {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    z-index: 100;
}

.chat-button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    transition: all 0.3s;
    border: none;
    text-decoration: none;
}

.chat-button:hover {
    transform: scale(1.1) rotate(10deg);
    box-shadow: 0 10px 30px rgba(45,61,72,0.4);
}

.chat-tooltip {
    position: absolute;
    right: 70px;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    padding: 0.5rem 1rem;
    border-radius: 30px;
    font-size: 0.9rem;
    color: var(--dark-color);
    white-space: nowrap;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
}

.chat-button:hover .chat-tooltip {
    opacity: 1;
    visibility: visible;
    right: 80px;
}

/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 2rem;
    color: #64748b;
    font-size: 0.9rem;
}

.breadcrumb a {
    color: var(--primary-color);
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.breadcrumb i {
    font-size: 0.8rem;
    color: #94a3b8;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .categories-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}

@media (max-width: 768px) {
    .categories-hero h1 {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .category-tree {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .categories-hero {
        padding: 3rem 1rem;
    }
    
    .categories-hero h1 {
        font-size: 1.5rem;
    }
    
    .popular-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Hero section -->
<section class="categories-hero">
    <h1>
        <i class="fas fa-tags"></i>
        Nos Catégories
    </h1>
    <p>Explorez notre gamme complète de produits par catégorie</p>
</section>

<div class="categories-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="../index.php">Accueil</a>
        <i class="fas fa-chevron-right"></i>
        <span>Catégories</span>
    </div>
    
    <!-- Statistiques -->
    <?php
    $total_categories = count($categories);
    $total_produits = 0;
    $total_sous_categories = 0;
    
    foreach ($categories as $cat) {
        $total_produits += $cat['nb_produits'];
        $total_sous_categories += $cat['nb_sous_categories'];
    }
    ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-tags"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_categories; ?></h3>
                <p>Catégories</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_produits; ?></h3>
                <p>Produits</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-sitemap"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total_sous_categories; ?></h3>
                <p>Sous-catégories</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <h3>
                    <?php 
                    $pourcentage = $total_produits > 0 ? round(($total_produits / $total_categories)) : 0;
                    echo $pourcentage; 
                    ?>
                </h3>
                <p>Moy. produits/cat.</p>
            </div>
        </div>
    </div>
    
    <!-- Catégories populaires -->
    <?php if (!empty($popular_categories)): ?>
    <div class="section-title">
        <i class="fas fa-fire"></i>
        Catégories populaires
    </div>
    
    <div class="popular-grid">
        <?php foreach ($popular_categories as $cat): ?>
        <a href="category.php?id=<?php echo $cat['id_categorie']; ?>" class="popular-card">
            <div class="popular-icon">
                <i class="fas fa-folder"></i>
            </div>
            <div class="popular-info">
                <h4><?php echo htmlspecialchars($cat['nom_categorie']); ?></h4>
                <p><?php echo $cat['nb_produits']; ?> produit(s)</p>
            </div>
            <i class="fas fa-chevron-right" style="margin-left: auto; opacity: 0.5;"></i>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Toutes les catégories -->
    <div class="section-title">
        <i class="fas fa-list"></i>
        Toutes les catégories
    </div>
    
    <div class="categories-grid">
        <?php foreach ($categories as $category): ?>
        <a href="category.php?id=<?php echo $category['id_categorie']; ?>" class="category-card">
            <div class="category-image">
                <?php if (!empty($category['image_categorie'])): ?>
                    <img src="../<?php echo htmlspecialchars($category['image_categorie']); ?>" alt="<?php echo htmlspecialchars($category['nom_categorie']); ?>">
                <?php else: ?>
                    <div class="category-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                <?php endif; ?>
                
                <?php if ($category['nb_produits'] > 0): ?>
                <div class="category-overlay">
                    <i class="fas fa-box"></i>
                    <?php echo $category['nb_produits']; ?> produits
                </div>
                <?php endif; ?>
            </div>
            
            <div class="category-content">
                <div class="category-name">
                    <?php echo htmlspecialchars($category['nom_categorie']); ?>
                    <i class="fas fa-arrow-right"></i>
                </div>
                
                <?php if (!empty($category['description'])): ?>
                <div class="category-description">
                    <?php echo htmlspecialchars(substr($category['description'], 0, 100)) . (strlen($category['description']) > 100 ? '...' : ''); ?>
                </div>
                <?php endif; ?>
                
                <div class="category-stats">
                    <?php if ($category['nb_produits'] > 0): ?>
                    <div class="category-stat">
                        <i class="fas fa-box"></i>
                        <span><strong><?php echo $category['nb_produits']; ?></strong> produits</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($category['nb_sous_categories'] > 0): ?>
                    <div class="category-stat">
                        <i class="fas fa-sitemap"></i>
                        <span><strong><?php echo $category['nb_sous_categories']; ?></strong> sous-catégories</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Arborescence des catégories -->
    <?php
    // Récupérer les catégories principales (sans parent)
    $stmt_parents = $pdo->query("
        SELECT c.*, 
               COUNT(p.id_produit) as nb_produits
        FROM categorie c
        LEFT JOIN produit p ON c.id_categorie = p.id_categorie AND p.est_actif = 1
        WHERE c.id_categorie_parent IS NULL
        GROUP BY c.id_categorie
        ORDER BY c.nom_categorie
    ");
    $parent_categories = $stmt_parents->fetchAll();
    
    if (!empty($parent_categories)):
    ?>
    <div class="section-title">
        <i class="fas fa-sitemap"></i>
        Arborescence des catégories
    </div>
    
    <div class="tree-section">
        <div class="category-tree">
            <?php foreach ($parent_categories as $parent): ?>
            <div class="tree-item">
                <div class="tree-main">
                    <a href="category.php?id=<?php echo $parent['id_categorie']; ?>">
                        <?php echo htmlspecialchars($parent['nom_categorie']); ?>
                    </a>
                    <span>(<?php echo $parent['nb_produits']; ?> produits)</span>
                </div>
                
                <?php
                // Récupérer les sous-catégories
                $stmt_subs = $pdo->prepare("
                    SELECT c.*, 
                           COUNT(p.id_produit) as nb_produits
                    FROM categorie c
                    LEFT JOIN produit p ON c.id_categorie = p.id_categorie AND p.est_actif = 1
                    WHERE c.id_categorie_parent = ?
                    GROUP BY c.id_categorie
                    ORDER BY c.nom_categorie
                ");
                $stmt_subs->execute([$parent['id_categorie']]);
                $sub_categories = $stmt_subs->fetchAll();
                ?>
                
                <?php if (!empty($sub_categories)): ?>
                <ul class="tree-subs">
                    <?php foreach ($sub_categories as $sub): ?>
                    <li>
                        <a href="category.php?id=<?php echo $sub['id_categorie']; ?>">
                            <?php echo htmlspecialchars($sub['nom_categorie']); ?>
                        </a>
                        <span class="count"><?php echo $sub['nb_produits']; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Section Chat -->
    <div class="section-title">
        <i class="fas fa-comments"></i>
        Besoin d'aide ?
    </div>
    
    <div style="background: white; border-radius: 20px; padding: 2rem; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
        <i class="fas fa-headset" style="font-size: 4rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
        <h3 style="margin-bottom: 0.5rem;">Vous ne trouvez pas ce que vous cherchez ?</h3>
        <p style="color: #64748b; margin-bottom: 1.5rem;">Notre équipe est là pour vous aider à trouver le produit idéal</p>
        <a href="chat.php" class="btn" style="display: inline-flex; align-items: center; gap: 10px; padding: 1rem 2.5rem; background: var(--gradient); color: white; border-radius: 50px; text-decoration: none; font-weight: 600;">
            <i class="fas fa-comment"></i>
            Démarrer une conversation
        </a>
    </div>
</div>

<!-- Bouton flottant pour le chat -->
<div class="chat-float">
    <a href="chat.php" class="chat-button">
        <i class="fas fa-comment"></i>
        <span class="chat-tooltip">Discuter avec nous</span>
    </a>
</div>

<script>
// Animation au scroll
document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.category-card, .popular-card, .stat-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s ease';
        observer.observe(el);
    });
});

// Recherche rapide dans les catégories
function filterCategories() {
    const searchInput = document.getElementById('categorySearch');
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase();
    const cards = document.querySelectorAll('.category-card');
    
    cards.forEach(card => {
        const name = card.querySelector('.category-name').textContent.toLowerCase();
        const desc = card.querySelector('.category-description')?.textContent.toLowerCase() || '';
        
        if (name.includes(searchTerm) || desc.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Ajouter une barre de recherche si désiré
// Vous pouvez décommenter ce code pour ajouter une recherche
/*
document.querySelector('.categories-container').insertAdjacentHTML('afterbegin', `
    <div style="margin-bottom: 2rem;">
        <input type="text" id="categorySearch" placeholder="Rechercher une catégorie..." style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 50px; font-size: 1rem;">
    </div>
`);
document.getElementById('categorySearch')?.addEventListener('keyup', filterCategories);
*/
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>