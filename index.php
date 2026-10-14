<?php
require_once 'includes/config.php';
require_once 'classes/Panier.php';

// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    error_log("Review submission started");
    // Vérification du token CSRF
    // if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     setFlashMessage('Token de sécurité invalide', 'error');
    //     redirect('index.php');
    // }
    
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour laisser un avis', 'error');
        redirect('login.php');
    }
    
    $product_id = intval($_POST['product_id']);
    $rating = floatval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    
    error_log("Review data: product_id=$product_id, rating=$rating, comment length=" . strlen($comment) . ", user_id=$user_id");
    
    // Validation
    if ($rating < 1 || $rating > 5) {
        setFlashMessage('La note doit être entre 1 et 5', 'error');
    } elseif (strlen($comment) < 10) {
        setFlashMessage('Le commentaire doit contenir au moins 10 caractères', 'error');
    } elseif (strlen($comment) > 500) {
        setFlashMessage('Le commentaire ne doit pas dépasser 500 caractères', 'error');
    } else {
        // Vérifier si l'utilisateur a déjà laissé un avis pour ce produit
        $stmt = $pdo->prepare("SELECT id_avis FROM avis WHERE id_produit = ? AND id_utilisateur = ?");
        $stmt->execute([$product_id, $user_id]);
        
        if ($stmt->fetch()) {
            setFlashMessage('Vous avez déjà laissé un avis pour ce produit', 'error');
        } else {
            // Vérifier si l'utilisateur a acheté le produit (optionnel)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM commande c
                JOIN commande_produit cp ON c.id_commande = cp.id_commande
                WHERE c.id_utilisateur = ? AND cp.id_produit = ? AND c.statut = 'livree'
            ");
            $stmt->execute([$user_id, $product_id]);
            $hasPurchased = $stmt->fetchColumn() > 0;
            
            error_log("Has purchased: $hasPurchased, proceeding to insert");
            
            // Insérer l'avis
            $stmt = $pdo->prepare("
                INSERT INTO avis (id_produit, id_utilisateur, note, commentaire, date_avis, est_approuve, a_achete) 
                VALUES (?, ?, ?, ?, NOW(), 1, ?)
            ");
            
            if ($stmt->execute([$product_id, $user_id, $rating, $comment, $hasPurchased])) {
                error_log("Review inserted successfully");
                setFlashMessage('Votre avis a été publié avec succès !', 'success');
                
                // Notifier les administrateurs
                $admin_stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE role = 'admin'");
                $admin_stmt->execute();
                $admins = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($admins as $admin_id) {
                    $pdo->prepare("
                        INSERT INTO notifications (id_utilisateur, titre, message, type, lien) 
                        VALUES (?, 'Nouvel avis publié', 'Un client a soumis un nouvel avis qui a été automatiquement approuvé.', 'info', 'admin/reviews.php')
                    ")->execute([$admin_id]);
                }
                
                redirect('index.php');
            } else {
                error_log("Erreur lors de l'enregistrement de l'avis: " . print_r($stmt->errorInfo(), true));
                setFlashMessage('Erreur technique lors de l\'enregistrement, veuillez réessayer', 'error');
            }
        }
    }
}

$page_title = "Accueil - Équipements Informatiques au Cameroun";

// Récupérer et VALIDER les paramètres de filtrage
$category_id = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Validation du tri
$allowed_sorts = ['newest', 'price_asc', 'price_desc', 'name', 'rating'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'newest';
}

// Construire la requête pour les produits
$sql = "SELECT p.*, c.nom_categorie, 
               (SELECT ip.url_image 
                FROM image_produit ip 
                WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 
                LIMIT 1) as url_image,
               COALESCE(AVG(a.note), 0) as note_moyenne,
               COUNT(a.id_avis) as nb_avis,
               (p.stock > 0) as en_stock
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

if (!empty($min_price) && $min_price > 0) {
    $sql .= " AND p.prix >= ?";
    $params[] = $min_price;
}

if (!empty($max_price) && $max_price > 0) {
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

// Compter le total pour la pagination (sans LIMIT)
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
    $count_params[] = $search_term;
    $count_params[] = $search_term;
}

if (!empty($min_price) && $min_price > 0) {
    $count_sql .= " AND p.prix >= ?";
    $count_params[] = $min_price;
}

if (!empty($max_price) && $max_price > 0) {
    $count_sql .= " AND p.prix <= ?";
    $count_params[] = $max_price;
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_products = $count_stmt->fetchColumn();

// Exécuter la requête principale avec pagination sécurisée
$sql .= " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

// Lier les paramètres de pagination
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Récupérer les catégories pour les filtres et le menu (avec cache mémoire simple)
$cache_key = 'all_categories';
if (!isset($GLOBALS[$cache_key])) {
    $GLOBALS[$cache_key] = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
}
$categories = $GLOBALS[$cache_key];

// Récupérer les avis récents (limités à 6)
$recent_reviews = $pdo->query("
    SELECT a.*, p.nom_produit, p.id_produit, p.stock,
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

// Récupérer les produits pour le formulaire d'avis (limités à 50 pour performance)
$all_products = $pdo->query("
    SELECT p.id_produit, p.nom_produit, c.nom_categorie, p.stock
    FROM produit p 
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
    WHERE p.est_actif = 1 AND p.stock > 0
    ORDER BY p.nom_produit
    LIMIT 50
")->fetchAll();

// ==============================================
// Variables pour le header
// ==============================================
$header_categories = $categories;
$header_show_products_link = true;

include 'includes/header.php';
?>

<!-- Affichage des messages flash -->
<?php if ($flash_message = getFlashMessage()): ?>
<div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
    <?php echo htmlspecialchars($flash_message['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

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
                <div style="width: 70px; height: 70px; background: #1f4865; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
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
                        <option value="<?php echo (int)$category['id_categorie']; ?>" <?php echo $category_id == $category['id_categorie'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['nom_categorie']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Prix min (FCFA)</label>
                <input type="number" name="min_price" placeholder="0" value="<?php echo htmlspecialchars($min_price); ?>" 
                       min="0" step="1000"
                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Prix max (FCFA)</label>
                <input type="number" name="max_price" placeholder="1000000" value="<?php echo htmlspecialchars($max_price); ?>" 
                       min="0" step="1000"
                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Trier par</label>
                <select name="sort" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Nouveautés</option>
                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Prix croissant</option>
                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Prix décroissant</option>
                    <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Nom A-Z</option>
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; flex-wrap: wrap; gap: 15px;">
            <h2 style="font-size: 32px; color: #333;">
                <?php echo empty($search) ? 'Tous nos produits' : 'Résultats de recherche pour : ' . htmlspecialchars($search); ?>
            </h2>
            <span style="color: #666;"><?php echo number_format($total_products, 0, ',', ' '); ?> produit(s)</span>
        </div>
        
        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-search" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                <h3 style="margin-bottom: 15px; color: #333;">Aucun produit trouvé</h3>
                <p style="color: #666; margin-bottom: 30px;">Essayez de modifier vos critères de recherche</p>
                <a href="index.php" style="background: #2d3d48; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; display: inline-block;">
                    Voir tous les produits
                </a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin-bottom: 60px;">
                <?php foreach ($products as $product): ?>
                    <div style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: transform 0.3s, box-shadow 0.3s; position: relative;">
                        <!-- Badge de stock -->
                        <?php if (!$product['en_stock']): ?>
                            <div style="position: absolute; top: 15px; left: 15px; background: #e74c3c; color: white; padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; z-index: 2;">
                                Rupture de stock
                            </div>
                        <?php endif; ?>
                        
                        <div style="height: 250px; overflow: hidden; position: relative; background: #f8f9fa;">
                            <?php if ($product['url_image']): ?>
                                <img src="<?php echo htmlspecialchars($product['url_image']); ?>" alt="<?php echo htmlspecialchars($product['nom_produit']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: contain; transition: transform 0.3s;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: #f5f5f5; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-image" style="font-size: 48px; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($product['est_en_promotion'] && $product['prix_promotion']): ?>
                                <div style="position: absolute; top: 15px; right: 15px; background: #e74c3c; color: white; padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                    -<?php echo round((1 - $product['prix_promotion']/$product['prix']) * 100); ?>%
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($product['note_moyenne'] > 0): ?>
                                <div style="position: absolute; bottom: 15px; left: 15px; background: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 4px; font-size: 13px;">
                                    <?php echo number_format($product['note_moyenne'], 1); ?> ★
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="padding: 20px;">
                            <div style="margin-bottom: 10px;">
                                <span style="background: #e8f5e9; color: #2d3d48; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 500;">
                                    <?php echo htmlspecialchars($product['nom_categorie']); ?>
                                </span>
                            </div>
                            
                            <h3 style="margin-bottom: 10px; font-size: 18px; color: #333; height: 50px; overflow: hidden;">
                                <?php echo htmlspecialchars($product['nom_produit']); ?>
                            </h3>
                            
                            <p style="color: #666; font-size: 13px; margin-bottom: 15px; height: 40px; overflow: hidden; line-height: 1.4;">
                                <?php echo htmlspecialchars(substr($product['description_courte'] ?? $product['description'], 0, 80)); ?>...
                            </p>
                            
                            <div style="margin-bottom: 20px;">
                                <?php if ($product['est_en_promotion'] && $product['prix_promotion']): ?>
                                    <div style="display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;">
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
                            
                            <div style="display: flex; gap: 10px;">
                                <a href="products/product.php?id=<?php echo $product['id_produit']; ?>" 
                                   style="flex: 1; background: #2d3d48; color: white; padding: 10px; text-align: center; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 14px;">
                                    Voir détails
                                </a>
                                
                                <?php if ($product['en_stock']): ?>
                                    <button onclick="addToCart(<?php echo $product['id_produit']; ?>, this)" 
                                            style="background: #005c46; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                <?php else: ?>
                                    <button disabled 
                                            style="background: #ccc; color: #666; padding: 10px 15px; border: none; border-radius: 6px; cursor: not-allowed;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($product['nb_avis'] > 0): ?>
                                <div style="margin-top: 15px; padding-top: 12px; border-top: 1px solid #eee;">
                                    <a href="products/product.php?id=<?php echo $product['id_produit']; ?>#reviews" 
                                       style="color: #666; text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-comment"></i>
                                        <?php echo $product['nb_avis']; ?> avis client(s)
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination sécurisée -->
            <?php
            $total_pages = ceil($total_products / $limit);
            if ($total_pages > 1):
            ?>
            <div style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333; background: white;">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </a>
                <?php endif; ?>
                
                <?php 
                // Afficher un nombre limité de pages
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                       style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333; background: white;">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span style="padding: 10px 5px;">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; 
                              <?php echo $i == $page ? 'background: #2d3d48; color: white; border-color: #2d3d48;' : 'background: white; color: #333;'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span style="padding: 10px 5px;">...</span>
                    <?php endif; ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                       style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333; background: white;">
                        <?php echo $total_pages; ?>
                    </a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333; background: white;">
                        Suivant <i class="fas fa-chevron-right"></i>
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
        <div style="max-width: 800px; margin: 0 auto; background: white; border-radius: 15px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <h2 style="text-align: center; margin-bottom: 15px; color: #333;">Partagez votre expérience</h2>
            <p style="text-align: center; margin-bottom: 30px; color: #666;">Votre avis aide les autres clients à faire leur choix</p>
            
            <?php if (isLoggedIn()): ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Produit *</label>
                        <select name="product_id" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                            <option value="">Sélectionnez un produit</option>
                            <?php foreach ($all_products as $prod): ?>
                                <option value="<?php echo (int)$prod['id_produit']; ?>">
                                    <?php echo htmlspecialchars($prod['nom_produit'] . ' - ' . $prod['nom_categorie']); ?>
                                    <?php echo $prod['stock'] > 0 ? '' : ' (Rupture)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Votre note *</label>
                        <div style="display: flex; gap: 15px; margin-bottom: 10px; justify-content: center;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="star-rating" data-value="<?php echo $i; ?>" 
                                       style="cursor: pointer; font-size: 32px; color: #ddd; transition: color 0.2s;">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" required style="display: none;">
                                    ★
                                </label>
                            <?php endfor; ?>
                        </div>
                        <div style="display: flex; justify-content: space-between; color: #666; font-size: 14px; max-width: 300px; margin: 0 auto;">
                            <span>Déçu(e)</span>
                            <span>Moyen</span>
                            <span>Satisfait(e)</span>
                            <span>Très bien</span>
                            <span>Excellent</span>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Votre commentaire *</label>
                        <textarea name="comment" rows="5" required minlength="10" maxlength="500" 
                                  placeholder="Partagez votre expérience avec ce produit (minimum 10 caractères)..."
                                  style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; resize: vertical;"></textarea>
                        <div style="text-align: right; color: #666; font-size: 13px; margin-top: 8px;">
                            <span id="charCount">0</span>/500 caractères
                        </div>
                    </div>
                    
                    <div style="text-align: center;">
                        <button type="submit" name="submit_review" 
                                style="background: #005c46; color: white; padding: 14px 40px; border: none; border-radius: 8px; 
                                       font-size: 16px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;
                                       transition: background 0.3s;">
                            <i class="fas fa-paper-plane"></i>
                            Publier mon avis
                        </button>
                        <p style="margin-top: 15px; color: #666; font-size: 13px;">
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
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
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
        <h2 style="text-align: center; margin-bottom: 15px; color: #333;">Ce que disent nos clients</h2>
        <p style="text-align: center; margin-bottom: 50px; color: #666;">Des avis authentiques de clients satisfaits</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px;">
            <?php foreach ($recent_reviews as $review): ?>
            <div style="background: #f8f9fa; border-radius: 12px; padding: 25px; transition: transform 0.3s;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 45px; height: 45px; background: #2d3d48; color: white; border-radius: 50%; 
                                    display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 18px;">
                            <?php echo strtoupper(substr($review['prenom'], 0, 1) . substr($review['nom'], 0, 1)); ?>
                        </div>
                        <div>
                            <h4 style="margin: 0; color: #333;"><?php echo htmlspecialchars($review['prenom'] . ' ' . $review['nom']); ?></h4>
                            <div style="font-size: 12px; color: #999;">
                                <?php echo date('d/m/Y', strtotime($review['date_avis'])); ?>
                            </div>
                        </div>
                    </div>
                    <div style="color: #f39c12; font-size: 16px;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $review['note']): ?>
                                ★
                            <?php else: ?>
                                ☆
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px; padding: 10px; background: white; border-radius: 8px;">
                    <?php if ($review['url_image']): ?>
                        <img src="<?php echo htmlspecialchars($review['url_image']); ?>" alt="<?php echo htmlspecialchars($review['nom_produit']); ?>" 
                             style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 50px; height: 50px; background: #eee; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-box" style="color: #ccc;"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h5 style="margin: 0 0 5px 0; color: #333; font-size: 14px;"><?php echo htmlspecialchars($review['nom_produit']); ?></h5>
                        <a href="products/product.php?id=<?php echo (int)$review['id_produit']; ?>" 
                           style="font-size: 12px; color: #2d3d48; text-decoration: none;">
                            Voir le produit →
                        </a>
                    </div>
                </div>
                
                <p style="color: #555; line-height: 1.6; margin: 0; font-size: 14px;">
                    "<?php echo nl2br(htmlspecialchars(substr($review['commentaire'], 0, 200))); ?><?php echo strlen($review['commentaire']) > 200 ? '...' : ''; ?>"
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($rating_stats && $rating_stats['total_avis'] > 0): ?>
        <div style="text-align: center; margin-top: 50px;">
            <div style="display: inline-block; background: #f8f9fa; padding: 30px 40px; border-radius: 15px;">
                <div style="font-size: 42px; font-weight: bold; color: #333; margin-bottom: 10px;">
                    <?php echo number_format($rating_stats['moyenne_notes'], 1); ?>/5
                </div>
                <div style="color: #f39c12; font-size: 28px; margin-bottom: 10px;">
                    <?php 
                    $full_stars = floor($rating_stats['moyenne_notes']);
                    $half_star = ($rating_stats['moyenne_notes'] - $full_stars) >= 0.5;
                    for ($i = 1; $i <= 5; $i++): 
                        if ($i <= $full_stars): ?>
                            ★
                        <?php elseif ($half_star && $i == $full_stars + 1): ?>
                            <span style="position: relative; display: inline-block;">
                                ★<span style="position: absolute; left: 0; top: 0; width: 50%; overflow: hidden; color: #f39c12;">★</span>
                            </span>
                        <?php else: ?>
                            ☆
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <div style="color: #666;">
                    Basé sur <?php echo number_format($rating_stats['total_avis'], 0, ',', ' '); ?> avis vérifiés
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
            <i class="fas fa-envelope" style="font-size: 48px; margin-bottom: 20px; opacity: 0.8;"></i>
            <h2 style="margin-bottom: 15px;">Restez informé</h2>
            <p style="margin-bottom: 30px; opacity: 0.9;">
                Inscrivez-vous à notre newsletter pour recevoir nos offres exclusives et les nouveautés
            </p>
            <form id="newsletterForm" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="email" id="newsletter_email" placeholder="Votre adresse email" required 
                       style="flex: 1; padding: 15px; border: none; border-radius: 8px; min-width: 200px;">
                <button type="submit" 
                        style="background: white; color: #005c46; padding: 15px 30px; border: none; border-radius: 8px; 
                               font-weight: 600; cursor: pointer; transition: transform 0.2s;">
                    S'inscrire
                </button>
            </form>
            <p style="font-size: 12px; margin-top: 15px; opacity: 0.7;">
                <i class="fas fa-lock"></i> Vos données sont confidentielles
            </p>
        </div>
    </div>
</section>

<script>
// Script pour les étoiles de notation
document.querySelectorAll('.star-rating').forEach((label) => {
    const starValue = parseInt(label.getAttribute('data-value'));
    
    label.addEventListener('click', function(e) {
        const stars = document.querySelectorAll('.star-rating');
        const selectedValue = parseInt(this.getAttribute('data-value'));
        
        // Mettre à jour l'apparence des étoiles
        stars.forEach(star => {
            const starVal = parseInt(star.getAttribute('data-value'));
            if (starVal <= selectedValue) {
                star.style.color = '#f39c12';
            } else {
                star.style.color = '#ddd';
            }
        });
        
        // Sélectionner le radio bouton correspondant
        const radio = this.querySelector('input');
        if (radio) radio.checked = true;
    });
    
    // Effet hover
    label.addEventListener('mouseenter', function() {
        const stars = document.querySelectorAll('.star-rating');
        const hoverValue = parseInt(this.getAttribute('data-value'));
        
        stars.forEach(star => {
            const starVal = parseInt(star.getAttribute('data-value'));
            if (starVal <= hoverValue) {
                star.style.color = '#f39c12';
            } else {
                star.style.color = '#ddd';
            }
        });
    });
    
    label.addEventListener('mouseleave', function() {
        const stars = document.querySelectorAll('.star-rating');
        const selected = document.querySelector('input[name="rating"]:checked');
        
        if (!selected) {
            stars.forEach(star => {
                star.style.color = '#ddd';
            });
        } else {
            const selectedValue = parseInt(selected.value);
            stars.forEach(star => {
                const starVal = parseInt(star.getAttribute('data-value'));
                star.style.color = starVal <= selectedValue ? '#f39c12' : '#ddd';
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
            charCount.style.fontWeight = 'bold';
        } else if (length > 400) {
            charCount.style.color = '#f39c12';
            charCount.style.fontWeight = 'normal';
        } else {
            charCount.style.color = '#666';
            charCount.style.fontWeight = 'normal';
        }
    });
    
    // Initialiser le compteur
    charCount.textContent = commentTextarea.value.length;
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
            alert('Veuillez attribuer une note (1 à 5 étoiles)');
            return false;
        }
        
        if (!comment.value || comment.value.trim().length < 10) {
            e.preventDefault();
            alert('Le commentaire doit contenir au moins 10 caractères');
            comment.focus();
            return false;
        }
        
        if (comment.value.length > 500) {
            e.preventDefault();
            alert('Le commentaire ne doit pas dépasser 500 caractères');
            comment.focus();
            return false;
        }
        
        // Désactiver le bouton pour éviter les doubles soumissions
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
    });
}

// Fonction pour ajouter au panier (AJAX)
function addToCart(productId, btn) {
    // Vérifier si le bouton n'est pas déjà désactivé
    if (btn.disabled) return;
    
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
            showNotification('✓ Produit ajouté au panier !', 'success');
            
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
        console.error('Erreur:', error);
        btn.innerHTML = originalHtml;
        btn.disabled = false;
        showNotification('Erreur de connexion au serveur', 'danger');
    });
}

// Fonction pour afficher une notification
function showNotification(message, type = 'success') {
    // Supprimer les anciennes notifications
    const oldNotifications = document.querySelectorAll('.custom-notification');
    oldExceptions.forEach(notif => notif.remove());
    
    // Créer l'élément de notification
    const notification = document.createElement('div');
    notification.className = `custom-notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 10000;
        min-width: 280px;
        max-width: 350px;
        padding: 15px 20px;
        border-radius: 8px;
        font-weight: 500;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        animation: slideInRight 0.3s ease;
        background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
        color: ${type === 'success' ? '#155724' : '#721c24'};
        border-left: 4px solid ${type === 'success' ? '#28a745' : '#dc3545'};
    `;
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="margin-left: auto; background: none; border: none; font-size: 20px; cursor: pointer; opacity: 0.6;">
                ×
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Supprimer après 4 secondes
    setTimeout(() => {
        if (notification && notification.remove) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 4000);
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

// Animation CSS pour les notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Newsletter
const newsletterForm = document.getElementById('newsletterForm');
if (newsletterForm) {
    newsletterForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const email = document.getElementById('newsletter_email').value;
        const submitBtn = this.querySelector('button');
        
        if (!email || !email.includes('@')) {
            showNotification('Veuillez entrer une adresse email valide', 'danger');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
        
        try {
            const response = await fetch('api/newsletter.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            });
            const data = await response.json();
            
            if (data.success) {
                showNotification('Inscription réussie ! Merci de votre confiance.', 'success');
                document.getElementById('newsletter_email').value = '';
            } else {
                showNotification(data.message || 'Erreur lors de l\'inscription', 'danger');
            }
        } catch (error) {
            showNotification('Erreur de connexion', 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'S\'inscrire';
        }
    });
}

// Animation au scroll pour les produits
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

document.querySelectorAll('#products .product-card, .products-grid > div').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(card);
});
</script>

<style>
/* Améliorations responsives */
@media (max-width: 768px) {
    .hero h1 {
        font-size: 32px !important;
    }
    
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .products-grid {
        gap: 15px !important;
    }
    
    .filters-form {
        grid-template-columns: 1fr !important;
    }
}

/* Amélioration du focus pour l'accessibilité */
button:focus, a:focus, input:focus, select:focus, textarea:focus {
    outline: 2px solid #2d3d48;
    outline-offset: 2px;
}

/* Transition douce pour les liens */
a, button {
    transition: all 0.3s ease;
}

/* Style pour le hover des produits */
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
</style>

<?php include 'includes/footer.php'; ?>