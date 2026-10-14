<?php
// products.php - Page de tous les produits
require_once 'includes/config.php';
require_once 'classes/Panier.php';

// Initialiser le panier
$user_id = getUserId();
$cart = new Panier($pdo, $user_id);

$page_title = "Tous nos produits - " . SITE_NAME;
$header_show_products_link = true;

// Récupérer les catégories pour les filtres
$categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_categories = $categories;

// Récupérer les paramètres de filtrage
$category_id = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Construire la requête pour les produits
$sql = "SELECT p.*, c.nom_categorie, 
               (SELECT ip.url_image 
                FROM image_produit ip 
                WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 
                LIMIT 1) as url_image,
               COALESCE(AVG(a.note), 0) as note_moyenne,
               COUNT(a.id_avis) as nb_avis
        FROM produit p 
        LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
        LEFT JOIN avis a ON p.id_produit = a.id_produit AND a.est_approuve = 1
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

$sql .= " GROUP BY p.id_produit";

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
    case 'rating':
        $sql .= " ORDER BY note_moyenne DESC";
        break;
    default:
        $sql .= " ORDER BY p.date_ajout DESC";
}

// Compter le total pour la pagination
$count_sql = "SELECT COUNT(DISTINCT p.id_produit) as total 
              FROM produit p 
              LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
              WHERE p.est_actif = 1";
$count_params = $params;

if (!empty($category_id)) {
    $count_sql .= " AND p.id_categorie = ?";
}

if (!empty($search)) {
    $count_sql .= " AND (p.nom_produit LIKE ? OR p.description LIKE ?)";
}

if (!empty($min_price)) {
    $count_sql .= " AND p.prix >= ?";
}

if (!empty($max_price)) {
    $count_sql .= " AND p.prix <= ?";
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_products = $count_stmt->fetchColumn();

// Ajouter la limite et l'offset à la requête principale
$sql .= " LIMIT $limit OFFSET $offset";

// Exécuter la requête principale
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$total_pages = ceil($total_products / $limit);

include 'includes/header.php';
?>

<!-- Hero Section -->
<section style="background: linear-gradient(135deg, #2d3d48 0%, #005c46 100%); color: white; padding: 60px 0; text-align: center;">
    <div class="container">
        <h1 style="font-size: 42px; margin-bottom: 15px;">Tous nos produits</h1>
        <p style="font-size: 18px; max-width: 600px; margin: 0 auto; opacity: 0.9;">
            Découvrez notre sélection complète d'équipements informatiques
        </p>
    </div>
</section>

<!-- Filtres et recherche -->
<section style="padding: 40px 0; background: white; border-bottom: 1px solid #eee;">
    <div class="container">
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: end;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Catégorie</label>
                <select name="category" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id_categorie']; ?>" <?php echo $category_id == $category['id_categorie'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['nom_categorie']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Recherche</label>
                <input type="text" name="search" placeholder="Nom du produit..." value="<?php echo htmlspecialchars($search); ?>" 
                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Prix min (FCFA)</label>
                <input type="number" name="min_price" placeholder="0" value="<?php echo htmlspecialchars($min_price); ?>" 
                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Prix max (FCFA)</label>
                <input type="number" name="max_price" placeholder="1000000" value="<?php echo htmlspecialchars($max_price); ?>" 
                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Trier par</label>
                <select name="sort" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Nouveautés</option>
                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Prix croissant</option>
                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Prix décroissant</option>
                    <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Nom (A-Z)</option>
                    <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Meilleures notes</option>
                </select>
            </div>
            
            <div>
                <button type="submit" style="background: #2d3d48; color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-search"></i> Filtrer
                </button>
                <a href="products.php" style="margin-left: 10px; color: #2d3d48; text-decoration: none;">Réinitialiser</a>
            </div>
        </form>
    </div>
</section>

<!-- Section Produits -->
<section style="padding: 60px 0;">
    <div class="container">
        <!-- En-tête avec nombre de produits -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h2 style="font-size: 24px; color: #333;">
                <?php if (!empty($search)): ?>
                    Résultats pour "<?php echo htmlspecialchars($search); ?>"
                <?php elseif (!empty($category_id)): ?>
                    <?php 
                    $cat_name = '';
                    foreach ($categories as $cat) {
                        if ($cat['id_categorie'] == $category_id) {
                            $cat_name = $cat['nom_categorie'];
                            break;
                        }
                    }
                    echo "Catégorie : " . htmlspecialchars($cat_name);
                    ?>
                <?php else: ?>
                    Tous nos produits
                <?php endif; ?>
            </h2>
            <span style="background: #2d3d48; color: white; padding: 5px 15px; border-radius: 20px;">
                <?php echo $total_products; ?> produit(s)
            </span>
        </div>
        
        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 10px;">
                <i class="fas fa-search" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                <h3 style="margin-bottom: 15px; color: #333;">Aucun produit trouvé</h3>
                <p style="color: #666; margin-bottom: 30px;">Essayez de modifier vos critères de recherche</p>
                <a href="products.php" style="background: #2d3d48; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none;">
                    Voir tous les produits
                </a>
            </div>
        <?php else: ?>
            <!-- Grille des produits -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin-bottom: 50px;">
                <?php foreach ($products as $product): ?>
                    <div style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s;"
                         onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.1)';"
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.05)';">
                        
                        <!-- Image du produit -->
                        <div style="height: 200px; overflow: hidden; position: relative;">
                            <?php if ($product['url_image']): ?>
                                <img src="<?php echo $product['url_image']; ?>" alt="<?php echo htmlspecialchars($product['nom_produit']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s;"
                                     onmouseover="this.style.transform='scale(1.1)'"
                                     onmouseout="this.style.transform='scale(1)'">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #f5f5f5, #e0e0e0); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-image" style="font-size: 50px; color: #999;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badges -->
                            <?php if ($product['est_en_promotion'] && $product['prix_promotion']): ?>
                                <div style="position: absolute; top: 15px; right: 15px; background: #e74c3c; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                    -<?php echo round((1 - $product['prix_promotion']/$product['prix']) * 100); ?>%
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($product['note_moyenne'] > 0): ?>
                                <div style="position: absolute; bottom: 15px; left: 15px; background: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px;">
                                    <i class="fas fa-star" style="color: #f39c12;"></i> <?php echo number_format($product['note_moyenne'], 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Informations produit -->
                        <div style="padding: 20px;">
                            <div style="margin-bottom: 10px;">
                                <span style="background: #e8f5e9; color: #2d3d48; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo htmlspecialchars($product['nom_categorie']); ?>
                                </span>
                            </div>
                            
                            <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #333; height: 50px; overflow: hidden;">
                                <a href="products/product.php?id=<?php echo $product['id_produit']; ?>" style="color: #333; text-decoration: none;">
                                    <?php echo htmlspecialchars($product['nom_produit']); ?>
                                </a>
                            </h3>
                            
                            <p style="color: #666; font-size: 14px; margin-bottom: 15px; height: 40px; overflow: hidden;">
                                <?php echo htmlspecialchars(substr($product['description_courte'] ?? $product['description'], 0, 80)); ?>...
                            </p>
                            
                            <!-- Prix -->
                            <div style="margin-bottom: 15px;">
                                <?php if ($product['est_en_promotion'] && $product['prix_promotion']): ?>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-size: 22px; font-weight: bold; color: #e74c3c;">
                                            <?php echo number_format($product['prix_promotion'], 0, ',', ' '); ?> FCFA
                                        </span>
                                        <span style="text-decoration: line-through; color: #999; font-size: 14px;">
                                            <?php echo number_format($product['prix'], 0, ',', ' '); ?> FCFA
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 22px; font-weight: bold; color: #2d3d48;">
                                        <?php echo number_format($product['prix'], 0, ',', ' '); ?> FCFA
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Actions -->
                            <div style="display: flex; gap: 10px;">
                                <a href="products/product.php?id=<?php echo $product['id_produit']; ?>" 
                                   style="flex: 1; background: #2d3d48; color: white; padding: 10px; text-align: center; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                                    Détails
                                </a>
                                
                                <button onclick="addToCart(<?php echo $product['id_produit']; ?>, this)" 
                                        style="background: #005c46; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                            </div>
                            
                            <?php if ($product['nb_avis'] > 0): ?>
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee;">
                                    <span style="color: #666; font-size: 13px;">
                                        <i class="fas fa-comment"></i> <?php echo $product['nb_avis']; ?> avis
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; transition: all 0.3s;"
                           onmouseover="this.style.background='#2d3d48'; this.style.color='white'; this.style.borderColor='#2d3d48';"
                           onmouseout="this.style.background='white'; this.style.color='#333'; this.style.borderColor='#ddd';">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" 
                               style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333;">1</a>';
                        if ($start > 2) {
                            echo '<span style="padding: 10px 15px;">...</span>';
                        }
                    }
                    
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           style="padding: 10px 15px; border: 1px solid <?php echo $i == $page ? '#2d3d48' : '#ddd'; ?>; 
                                  border-radius: 5px; text-decoration: none; 
                                  background: <?php echo $i == $page ? '#2d3d48' : 'white'; ?>; 
                                  color: <?php echo $i == $page ? 'white' : '#333'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) {
                            echo '<span style="padding: 10px 15px;">...</span>';
                        }
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '" 
                               style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333;">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; transition: all 0.3s;"
                           onmouseover="this.style.background='#2d3d48'; this.style.color='white'; this.style.borderColor='#2d3d48';"
                           onmouseout="this.style.background='white'; this.style.color='#333'; this.style.borderColor='#ddd';">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Section catégories populaires -->
<?php
$popular_categories = $pdo->query("
    SELECT c.*, COUNT(p.id_produit) as nb_produits
    FROM categorie c
    LEFT JOIN produit p ON c.id_categorie = p.id_categorie AND p.est_actif = 1
    GROUP BY c.id_categorie
    HAVING nb_produits > 0
    ORDER BY nb_produits DESC
    LIMIT 4
")->fetchAll();
?>

<?php if (!empty($popular_categories)): ?>
<section style="padding: 60px 0; background: #f8f9fa;">
    <div class="container">
        <h2 style="text-align: center; font-size: 28px; color: #333; margin-bottom: 40px;">
            <i class="fas fa-star" style="color: #f39c12;"></i> Catégories populaires
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px;">
            <?php foreach ($popular_categories as $cat): ?>
                <a href="products.php?category=<?php echo $cat['id_categorie']; ?>" 
                   style="background: white; padding: 25px; border-radius: 10px; text-decoration: none; text-align: center; 
                          box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: all 0.3s;"
                   onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.1)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.05)';">
                    
                    <div style="width: 70px; height: 70px; background: #2d3d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                        <i class="fas fa-folder" style="font-size: 30px; color: white;"></i>
                    </div>
                    
                    <h3 style="color: #333; margin-bottom: 10px;"><?php echo htmlspecialchars($cat['nom_categorie']); ?></h3>
                    <p style="color: #666;"><?php echo $cat['nb_produits']; ?> produits</p>
                    <span style="color: #2d3d48; font-weight: 600;">
                        Voir la catégorie <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Newsletter -->
<section style="padding: 60px 0; background: linear-gradient(135deg, #2d3d48 0%, #005c46 100%); color: white;">
    <div class="container">
        <div style="max-width: 600px; margin: 0 auto; text-align: center;">
            <h2 style="margin-bottom: 20px;">Restez informé</h2>
            <p style="margin-bottom: 30px; opacity: 0.9;">
                Inscrivez-vous pour recevoir nos offres exclusives et nouveaux produits
            </p>
            <form style="display: flex; gap: 10px;">
                <input type="email" placeholder="Votre email" required 
                       style="flex: 1; padding: 15px; border: none; border-radius: 8px;">
                <button type="submit" 
                        style="background: white; color: #005c46; padding: 15px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    S'inscrire
                </button>
            </form>
        </div>
    </div>
</section>

<script>
// Fonction pour ajouter au panier (AJAX)
function addToCart(productId, btn) {
    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=add&product_id=' + productId + '&quantity=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Produit ajouté au panier !', 'success');
            updateCartCount();
            
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }, 2000);
        } else {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            
            if (data.message === 'Veuillez vous connecter pour ajouter des produits au panier') {
                if (confirm('Veuillez vous connecter pour ajouter des produits au panier. Voulez-vous vous connecter maintenant ?')) {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                }
            } else {
                showNotification(data.message || 'Erreur lors de l\'ajout', 'danger');
            }
        }
    })
    .catch(error => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
        showNotification('Erreur de connexion', 'danger');
    });
}

// Fonction pour afficher une notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.style.padding = '15px';
    notification.style.borderRadius = '8px';
    notification.style.color = type === 'success' ? '#155724' : '#721c24';
    notification.style.background = type === 'success' ? '#d4edda' : '#f8d7da';
    notification.style.border = type === 'success' ? '1px solid #c3e6cb' : '1px solid #f5c6cb';
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Fonction pour mettre à jour le compteur du panier
function updateCartCount() {
    const cartCount = document.querySelector('.cart-count');
    if (cartCount) {
        fetch('api/cart.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cartCount.textContent = data.count;
                }
            });
    }
}
</script>

<?php include 'includes/footer.php'; ?>