<?php
require_once 'includes/config.php';
require_once 'classes/Panier.php';

// Fonction pour obtenir l'ID utilisateur (à ajouter si elle n'existe pas dans config.php)
if (!function_exists('getUserId')) {
    function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

// Initialiser le panier pour l'affichage
$user_id = getUserId();
$cart = new Panier($pdo, $user_id);

// Synchroniser le panier si l'utilisateur vient de se connecter
if ($user_id && !empty($_SESSION['panier'])) {
    $cart->syncSessionCart();
}

// GESTION AJOUT D'AVIS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour laisser un avis', 'error');
        redirect('login.php');
    }
    
    $product_id = intval($_POST['product_id']);
    $rating = floatval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    
    // Validation
    if ($rating < 1 || $rating > 5) {
        setFlashMessage('La note doit être entre 1 et 5', 'error');
    } elseif (strlen($comment) < 10) {
        setFlashMessage('Le commentaire doit contenir au moins 10 caractères', 'error');
    } else {
        // Vérifier si l'utilisateur a déjà laissé un avis pour ce produit
        $stmt = $pdo->prepare("SELECT id_avis FROM avis WHERE id_produit = ? AND id_utilisateur = ?");
        $stmt->execute([$product_id, $user_id]);
        
        if ($stmt->fetch()) {
            setFlashMessage('Vous avez déjà laissé un avis pour ce produit', 'error');
        } else {
            // Insérer l'avis
            $stmt = $pdo->prepare("
                INSERT INTO avis (id_produit, id_utilisateur, note, commentaire, date_avis, est_approuve) 
                VALUES (?, ?, ?, ?, NOW(), 0)
            ");
            
            if ($stmt->execute([$product_id, $user_id, $rating, $comment])) {
                setFlashMessage('Votre avis a été soumis avec succès ! Il sera publié après modération.', 'success');
                
                // Notifier l'admin
                $pdo->prepare("
                    INSERT INTO notification (id_utilisateur, titre, message, type, lien) 
                    VALUES (NULL, 'Nouvel avis à modérer', 'Un client a soumis un nouvel avis.', 'info', 'admin/reviews.php')
                ")->execute();
                
                redirect('index.php');
            } else {
                setFlashMessage('Erreur lors de l\'enregistrement de l\'avis', 'error');
            }
        }
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

// Récupérer les catégories pour les filtres et le menu
$categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();

// Récupérer les avis récents
$recent_reviews = $pdo->query("
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

// Statistiques des avis
$rating_stats = $pdo->query("
    SELECT 
        ROUND(AVG(note), 1) as moyenne_notes,
        COUNT(*) as total_avis
    FROM avis 
    WHERE est_approuve = 1
")->fetch();

// ==============================================
// Variables pour le header
// ==============================================
$header_categories = $categories;
$header_show_products_link = true;

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero" style="background: linear-gradient(135deg, #296da1 0%, #343170 100%); color: white; padding: 100px 0; text-align: center;">
    <div class="container">
        <div class="hero-content" style="max-width: 800px; margin: 0 auto;">
            <h1 style="font-size: 48px; margin-bottom: 20px; font-weight: 700;">Votre Partenaire Technologique au Cameroun</h1>
            <p style="font-size: 18px; margin-bottom: 40px; opacity: 0.9;">
                Découvrez notre sélection d'équipements informatiques de qualité aux meilleurs prix du marché
            </p>
            
            <div style="display: flex; gap: 20px; justify-content: center; margin-bottom: 40px; flex-wrap: wrap;">
                <div style="background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 10px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-shipping-fast" style="font-size: 20px;"></i>
                    <span>Livraison Express</span>
                </div>
                <div style="background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 10px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-shield-alt" style="font-size: 20px;"></i>
                    <span>Garantie incluse</span>
                </div>
                <div style="background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 10px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-headset" style="font-size: 20px;"></i>
                    <span>Support 24/7</span>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: center;">
                <a href="#products" style="background: white; color: #1d2f2b; padding: 15px 40px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; display: inline-flex; align-items: center; gap: 10px;">
                    <i class="fas fa-shopping-bag"></i>
                    Découvrir nos produits
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Section Avantages -->
<section style="padding: 80px 0; background: #286a82;">
    <div class="container">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
            <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <div style="width: 70px; height: 70px; background: #e3f2fd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-truck" style="font-size: 30px; color: #2d3d48;"></i>
                </div>
                <h3 style="margin-bottom: 15px; color: #333;">Livraison Rapide</h3>
                <p style="color: #666;">Livraison express à Douala, Yaoundé et dans toutes les régions du Cameroun</p>
            </div>
            
            <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <div style="width: 70px; height: 70px; background: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-lock" style="font-size: 30px; color: #2d3d48;"></i>
                </div>
                <h3 style="margin-bottom: 15px; color: #333;">Paiement Sécurisé</h3>
                <p style="color: #666;">Mobile Money, carte bancaire ou espèce à la livraison</p>
            </div>
            
            <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <div style="width: 70px; height: 70px; background: #fff3e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-undo" style="font-size: 30px; color: #2d3d48;"></i>
                </div>
                <h3 style="margin-bottom: 15px; color: #333;">Retour Facile</h3>
                <p style="color: #666;">Retour sous 7 jours si le produit ne vous convient pas</p>
            </div>
            
            <div style="background: white; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <div style="width: 70px; height: 70px; background: #f3e5f5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-certificate" style="font-size: 30px; color: #2d3d48;"></i>
                </div>
                <h3 style="margin-bottom: 15px; color: #333;">Produits Garantis</h3>
                <p style="color: #666;">Tous nos produits sont garantis et testés avant livraison</p>
            </div>
        </div>
    </div>
</section>

<!-- Filtres de recherche -->
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
                    <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Meilleures notes</option>
                </select>
            </div>
            
            <div>
                <button type="submit" style="background: #2d3d48; color: white; padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    Appliquer
                </button>
                <a href="index.php" style="margin-left: 10px; color: #2d3d48; text-decoration: none;">Réinitialiser</a>
            </div>
        </form>
    </div>
</section>

<!-- Section Produits -->
<section id="products" style="padding: 80px 0;">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
            <h2 style="font-size: 32px; color: #333;">
                <?php echo empty($search) ? 'Tous nos produits' : 'Résultats de recherche'; ?>
            </h2>
            <span style="color: #666;"><?php echo $total_products; ?> produit(s)</span>
        </div>
        
        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-search" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                <h3 style="margin-bottom: 15px; color: #333;">Aucun produit trouvé</h3>
                <p style="color: #666; margin-bottom: 30px;">Essayez de modifier vos critères de recherche</p>
                <a href="index.php" style="background: #2d3d48; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none;">
                    Voir tous les produits
                </a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin-bottom: 50px;">
                <?php foreach ($products as $product): ?>
                    <div style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s;">
                        <div style="height: 200px; overflow: hidden; position: relative;">
                            <?php if ($product['url_image']): ?>
                                <img src="<?php echo $product['url_image']; ?>" alt="<?php echo htmlspecialchars($product['nom_produit']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: #f5f5f5; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-image" style="font-size: 50px; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($product['est_en_promotion'] && $product['prix_promotion']): ?>
                                <div style="position: absolute; top: 15px; right: 15px; background: #e74c3c; color: white; padding: 5px 10px; border-radius: 4px; font-size: 14px;">
                                    Promo
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($product['note_moyenne'] > 0): ?>
                                <div style="position: absolute; bottom: 15px; left: 15px; background: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 4px; font-size: 14px;">
                                    <?php echo number_format($product['note_moyenne'], 1); ?> ★
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="padding: 20px;">
                            <div style="margin-bottom: 10px;">
                                <span style="background: #e8f5e9; color: #2d3d48; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo htmlspecialchars($product['nom_categorie']); ?>
                                </span>
                            </div>
                            
                            <h3 style="margin-bottom: 10px; font-size: 18px; color: #333;">
                                <?php echo htmlspecialchars($product['nom_produit']); ?>
                            </h3>
                            
                            <p style="color: #666; font-size: 14px; margin-bottom: 15px; height: 40px; overflow: hidden;">
                                <?php echo htmlspecialchars(substr($product['description_courte'] ?? $product['description'], 0, 100)); ?>...
                            </p>
                            
                            <div style="margin-bottom: 20px;">
                                <?php if ($product['est_en_promotion'] && $product['prix_promotion']): ?>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-size: 24px; font-weight: bold; color: #e74c3c;">
                                            <?php echo number_format($product['prix_promotion'], 0, ',', ' '); ?> FCFA
                                        </span>
                                        <span style="text-decoration: line-through; color: #999;">
                                            <?php echo number_format($product['prix'], 0, ',', ' '); ?> FCFA
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 24px; font-weight: bold; color: #2d3d48;">
                                        <?php echo number_format($product['prix'], 0, ',', ' '); ?> FCFA
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; gap: 10px;">
                                <a href="products/product.php?id=<?php echo $product['id_produit']; ?>" 
                                   style="flex: 1; background: #2d3d48; color: white; padding: 12px; text-align: center; text-decoration: none; border-radius: 8px; font-weight: 600;">
                                    Voir détails
                                </a>
                                
                                <!-- Bouton Ajouter au panier avec JavaScript -->
                                <button onclick="addToCart(<?php echo $product['id_produit']; ?>, this)" 
                                        style="background: #005c46; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                            </div>
                            
                            <?php if ($product['nb_avis'] > 0): ?>
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                    <a href="products/product.php?id=<?php echo $product['id_produit']; ?>#reviews" 
                                       style="color: #666; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-comment"></i>
                                        <?php echo $product['nb_avis']; ?> avis
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php
            $total_pages = ceil($total_products / $limit);
            if ($total_pages > 1):
            ?>
            <div style="display: flex; justify-content: center; gap: 10px;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       style="padding: 10px 15px; border: 1px solid #070707; border-radius: 5px; text-decoration: none; 
                              <?php echo $i == $page ? 'background: #2d3d48; color: white;' : 'color: #333;'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333;">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Section Laisser un avis -->
<section style="padding: 80px 0; background: #f8f9fa;">
    <div class="container">
        <div style="max-width: 800px; margin: 0 auto; background: white; border-radius: 15px; padding: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <h2 style="text-align: center; margin-bottom: 40px; color: #333;">Laissez votre avis sur un produit</h2>
            
            <?php if (isLoggedIn()): ?>
                <form method="POST" action="">
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Produit</label>
                        <select name="product_id" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                            <option value="">Sélectionnez un produit</option>
                            <?php 
                            $all_products = $pdo->query("
                                SELECT p.id_produit, p.nom_produit, c.nom_categorie 
                                FROM produit p 
                                LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
                                WHERE p.est_actif = 1 
                                ORDER BY p.nom_produit
                            ")->fetchAll();
                            
                            foreach ($all_products as $prod): 
                            ?>
                                <option value="<?php echo $prod['id_produit']; ?>">
                                    <?php echo htmlspecialchars($prod['nom_produit'] . ' - ' . $prod['nom_categorie']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Note (1-5)</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label style="cursor: pointer; font-size: 24px; color: #ddd;" class="star-rating">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" required style="display: none;">
                                    ★
                                </label>
                            <?php endfor; ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; color: #666; font-size: 14px;">
                            <span>Mauvais</span>
                            <span>Excellent</span>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Votre commentaire</label>
                        <textarea name="comment" rows="5" required minlength="10" maxlength="500" 
                                  placeholder="Partagez votre expérience avec ce produit..."
                                  style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                        <div style="text-align: right; color: #666; font-size: 14px; margin-top: 5px;">
                            <span id="charCount">0</span>/500 caractères
                        </div>
                    </div>
                    
                    <div style="text-align: center;">
                        <button type="submit" name="submit_review" 
                                style="background: #005c46; color: white; padding: 15px 40px; border: none; border-radius: 8px; 
                                       font-size: 16px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
                            <i class="fas fa-paper-plane"></i>
                            Publier mon avis
                        </button>
                        <p style="margin-top: 15px; color: #666; font-size: 14px;">
                            <i class="fas fa-info-circle"></i>
                            Votre avis sera modéré avant publication
                        </p>
                    </div>
                </form>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <div style="font-size: 60px; color: #ccc; margin-bottom: 20px;">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <h3 style="margin-bottom: 15px; color: #333;">Connectez-vous pour laisser un avis</h3>
                    <p style="color: #666; margin-bottom: 30px;">
                        Pour partager votre expérience, veuillez vous connecter à votre compte.
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <a href="login.php?redirect=index.php" 
                           style="background: #2d3d48; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none;">
                            Se connecter
                        </a>
                        <a href="register.php" 
                           style="border: 2px solid #2d3d48; color: #2d3d48; padding: 12px 30px; border-radius: 8px; text-decoration: none;">
                            Créer un compte
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Section Avis récents -->
<?php if (!empty($recent_reviews)): ?>
<section style="padding: 80px 0; background: white;">
    <div class="container">
        <h2 style="text-align: center; margin-bottom: 50px; color: #333;">Avis récents des clients</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px;">
            <?php foreach ($recent_reviews as $review): ?>
            <div style="background: #f8f9fa; border-radius: 10px; padding: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 45px; height: 45px; background: #2d3d48; color: white; border-radius: 50%; 
                                    display: flex; align-items: center; justify-content: center; font-weight: 600;">
                            <?php echo strtoupper(substr($review['prenom'], 0, 1) . substr($review['nom'], 0, 1)); ?>
                        </div>
                        <div>
                            <h4 style="margin: 0; color: #333;"><?php echo htmlspecialchars($review['prenom'] . ' ' . $review['nom']); ?></h4>
                            <div style="font-size: 14px; color: #666;">
                                <?php echo date('d/m/Y', strtotime($review['date_avis'])); ?>
                            </div>
                        </div>
                    </div>
                    <div style="color: #f39c12; font-size: 18px;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $review['note']): ?>
                                ★
                            <?php else: ?>
                                ☆
                            <?php endif; ?>
                        <?php endfor; ?>
                        <span style="margin-left: 5px; font-weight: 600; color: #333;"><?php echo $review['note']; ?>/5</span>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px; padding: 12px; background: white; border-radius: 8px;">
                    <?php if ($review['url_image']): ?>
                        <img src="<?php echo $review['url_image']; ?>" alt="<?php echo htmlspecialchars($review['nom_produit']); ?>" 
                             style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                    <?php endif; ?>
                    <div>
                        <h5 style="margin: 0; color: #333;"><?php echo htmlspecialchars($review['nom_produit']); ?></h5>
                        <a href="products/product.php?id=<?php echo $review['id_produit']; ?>" 
                           style="font-size: 14px; color: #2d3d48; text-decoration: none;">
                            Voir le produit
                        </a>
                    </div>
                </div>
                
                <p style="color: #555; line-height: 1.6; margin: 0;">
                    <?php echo nl2br(htmlspecialchars($review['commentaire'])); ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($rating_stats['total_avis'] > 0): ?>
        <div style="text-align: center; margin-top: 50px;">
            <div style="display: inline-block; background: #f8f9fa; padding: 30px; border-radius: 10px;">
                <div style="font-size: 36px; font-weight: bold; color: #333; margin-bottom: 10px;">
                    <?php echo number_format($rating_stats['moyenne_notes'], 1); ?>/5
                </div>
                <div style="color: #f39c12; font-size: 24px; margin-bottom: 10px;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i <= floor($rating_stats['moyenne_notes'])): ?>
                            ★
                        <?php else: ?>
                            ☆
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <div style="color: #666;">
                    Basé sur <?php echo $rating_stats['total_avis']; ?> avis
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Newsletter -->
<section style="padding: 80px 0; background: linear-gradient(135deg, #2d3d48 0%, #005c46 100%); color: white;">
    <div class="container">
        <div style="max-width: 600px; margin: 0 auto; text-align: center;">
            <h2 style="margin-bottom: 20px;">Restez informé</h2>
            <p style="margin-bottom: 30px; opacity: 0.9;">
                Inscrivez-vous à notre newsletter pour recevoir nos offres exclusives
            </p>
            <form style="display: flex; gap: 10px;">
                <input type="email" placeholder="Votre email" required 
                       style="flex: 1; padding: 15px; border: none; border-radius: 8px;">
                <button type="submit" 
                        style="background: white; color: #005c46; padding: 15px 30px; border: none; border-radius: 8px; 
                               font-weight: 600; cursor: pointer;">
                    S'inscrire
                </button>
            </form>
        </div>
    </div>
</section>

<script>
// Script pour les étoiles de notation
document.querySelectorAll('.star-rating').forEach((label, index) => {
    label.addEventListener('click', function() {
        const stars = document.querySelectorAll('.star-rating');
        const starIndex = index + 1;
        
        // Réinitialiser toutes les étoiles
        stars.forEach(star => {
            star.style.color = '#ddd';
        });
        
        // Colorer les étoiles jusqu'à celle cliquée
        for (let i = 0; i < starIndex; i++) {
            stars[i].style.color = '#f39c12';
        }
        
        // Sélectionner le radio bouton correspondant
        this.querySelector('input').checked = true;
    });
    
    // Effet hover
    label.addEventListener('mouseover', function() {
        const stars = document.querySelectorAll('.star-rating');
        const starIndex = index + 1;
        
        for (let i = 0; i < starIndex; i++) {
            stars[i].style.color = '#f39c12';
        }
    });
    
    label.addEventListener('mouseout', function() {
        const stars = document.querySelectorAll('.star-rating');
        const selected = document.querySelector('input[name="rating"]:checked');
        
        if (!selected) {
            stars.forEach(star => {
                star.style.color = '#ddd';
            });
        } else {
            const selectedIndex = parseInt(selected.value) - 1;
            stars.forEach((star, i) => {
                star.style.color = i <= selectedIndex ? '#f39c12' : '#ddd';
            });
        }
    });
});

// Compteur de caractères
const commentTextarea = document.querySelector('textarea[name="comment"]');
const charCount = document.getElementById('charCount');

if (commentTextarea && charCount) {
    commentTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length;
        
        if (length > 450) {
            charCount.style.color = '#e74c3c';
        } else if (length > 400) {
            charCount.style.color = '#f39c12';
        } else {
            charCount.style.color = '#666';
        }
    });
}

// Validation du formulaire d'avis
const reviewForm = document.querySelector('form[method="POST"]');
if (reviewForm && reviewForm.querySelector('button[name="submit_review"]')) {
    reviewForm.addEventListener('submit', function(e) {
        const product = this.querySelector('select[name="product_id"]');
        const rating = this.querySelector('input[name="rating"]:checked');
        const comment = this.querySelector('textarea[name="comment"]');
        
        if (!product.value) {
            e.preventDefault();
            alert('Veuillez sélectionner un produit');
            product.focus();
            return false;
        }
        
        if (!rating) {
            e.preventDefault();
            alert('Veuillez attribuer une note');
            return false;
        }
        
        if (!comment.value || comment.value.length < 10) {
            e.preventDefault();
            alert('Le commentaire doit contenir au moins 10 caractères');
            comment.focus();
            return false;
        }
    });
}

// Fonction pour ajouter au panier (AJAX)
function addToCart(productId, btn) {
    // Désactiver le bouton pendant l'opération
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
            // Afficher une notification
            showNotification('Produit ajouté au panier !', 'success');
            
            // Mettre à jour le compteur du panier
            updateCartCount();
            if (typeof updateMiniCart === 'function') {
                updateMiniCart();
            }
            
            // Animation du bouton
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
    // Créer l'élément de notification
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Supprimer après 3 secondes
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Fonction pour mettre à jour le compteur du panier
function updateCartCount() {
    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=count'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cartCountElements = document.querySelectorAll('#cart-count, #mini-cart-count');
            cartCountElements.forEach(el => {
                if (el) el.textContent = data.count;
            });
        }
    })
    .catch(error => console.error('Erreur mise à jour compteur:', error));
}
</script>

<?php include 'includes/footer.php'; ?>