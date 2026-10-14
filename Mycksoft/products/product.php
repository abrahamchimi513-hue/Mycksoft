<?php
require_once '../includes/config.php';  // CHEMIN CORRIGÉ

// Récupérer l'ID du produit
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$product_id) {
    setFlashMessage('Produit non trouvé', 'error');
    redirect('../index.php');  // CHEMIN CORRIGÉ
}

// GESTION AJOUT AU PANIER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour ajouter des produits au panier', 'error');
        redirect('../login.php?id=' . $product_id);  // CHEMIN CORRIGÉ
    }
    
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    $result = addToCart($product_id, $quantity);
    
    if ($result['success']) {
        setFlashMessage('Produit ajouté au panier avec succès', 'success');
        redirect('product.php?id=' . $product_id);
    } else {
        setFlashMessage($result['message'], 'error');
    }
}

// GESTION AJOUT D'AVIS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour laisser un avis', 'error');
        redirect('../login.php?redirect=products/product.php?id=' . $product_id);  // CHEMIN CORRIGÉ
    }
    
    $rating = floatval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    
    // Validation
    if ($rating < 1 || $rating > 5) {
        setFlashMessage('La note doit être entre 1 et 5', 'error');
    } elseif (strlen($comment) < 10) {
        setFlashMessage('Le commentaire doit contenir au moins 10 caractères', 'error');
    } else {
        // Vérifier si l'utilisateur a déjà laissé un avis
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
                
                redirect('product.php?id=' . $product_id);
            } else {
                setFlashMessage('Erreur lors de l\'enregistrement de l\'avis', 'error');
            }
        }
    }
}

// Récupérer les informations du produit avec ses caractéristiques
$stmt = $pdo->prepare("
    SELECT p.*, c.nom_categorie,
           (SELECT AVG(note) FROM avis WHERE id_produit = p.id_produit AND est_approuve = 1) as note_moyenne,
           (SELECT COUNT(*) FROM avis WHERE id_produit = p.id_produit AND est_approuve = 1) as nb_avis
    FROM produit p
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.id_produit = ? AND p.est_actif = 1
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    setFlashMessage('Produit non trouvé', 'error');
    redirect('../index.php');  // CHEMIN CORRIGÉ
}

// Récupérer toutes les images du produit
$stmt = $pdo->prepare("SELECT * FROM image_produit WHERE id_produit = ? ORDER BY est_principale DESC, ordre ASC");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll();

// Récupérer les avis approuvés
$stmt = $pdo->prepare("
    SELECT a.*, u.prenom, u.nom, u.avatar
    FROM avis a
    JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur
    WHERE a.id_produit = ? AND a.est_approuve = 1
    ORDER BY a.date_avis DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Produits similaires (même catégorie)
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT ip.url_image FROM image_produit ip WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 LIMIT 1) as url_image
    FROM produit p
    WHERE p.id_categorie = ? AND p.id_produit != ? AND p.est_actif = 1
    LIMIT 4
");
$stmt->execute([$product['id_categorie'], $product_id]);
$similar_products = $stmt->fetchAll();

// Décoder les caractéristiques JSON
$caracteristiques = json_decode($product['caracteristiques'], true);

$page_title = htmlspecialchars($product['nom_produit']) . " - Équipements Informatiques";

// Récupérer les catégories pour le header
$categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_categories = $categories;
$header_show_products_link = true;

include '../includes/header.php';  // CHEMIN CORRIGÉ
?>

<!-- Breadcrumb -->
<section style="background: #f8f9fa; padding: 20px 0; border-bottom: 1px solid #eee;">
    <div class="container">
        <div style="display: flex; gap: 10px; color: #666;">
            <a href="../index.php" style="color: #2d3d48; text-decoration: none;">Accueil</a>  <!-- CHEMIN CORRIGÉ -->
            <span>/</span>
            <a href="../index.php?category=<?php echo $product['id_categorie']; ?>" style="color: #2d3d48; text-decoration: none;">  <!-- CHEMIN CORRIGÉ -->
                <?php echo htmlspecialchars($product['nom_categorie']); ?>
            </a>
            <span>/</span>
            <span style="color: #333;"><?php echo htmlspecialchars($product['nom_produit']); ?></span>
        </div>
    </div>
</section>

<!-- Détail du produit -->
<section style="padding: 60px 0;">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px;">
            <!-- Galerie d'images -->
            <div>
                <!-- Image principale -->
                <div style="background: #f8f9fa; border-radius: 15px; overflow: hidden; margin-bottom: 20px; height: 400px;">
                    <?php 
                    $main_image = array_filter($images, function($img) { return $img['est_principale'] == 1; });
                    $main_image = reset($main_image) ?: ($images[0] ?? null);
                    ?>
                    <?php if ($main_image): ?>
                        <img src="<?php echo $main_image['url_image']; ?>" alt="<?php echo htmlspecialchars($product['nom_produit']); ?>" 
                             style="width: 100%; height: 100%; object-fit: contain;" id="mainImage">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-image" style="font-size: 100px; color: #ccc;"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Miniatures -->
                <?php if (count($images) > 1): ?>
                <div style="display: flex; gap: 10px; overflow-x: auto; padding: 10px 0;">
                    <?php foreach ($images as $image): ?>
                        <div style="width: 80px; height: 80px; background: #f8f9fa; border-radius: 8px; overflow: hidden; cursor: pointer; 
                                    <?php echo ($image['id_image_produit'] == ($main_image['id_image_produit'] ?? 0)) ? 'border: 2px solid #2d3d48;' : ''; ?>"
                             onclick="document.getElementById('mainImage').src = '<?php echo $image['url_image']; ?>'">
                            <img src="<?php echo $image['url_image']; ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Informations produit -->
            <div>
                <!-- Catégorie et statut -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <span style="background: #e8f5e9; color: #2d3d48; padding: 5px 12px; border-radius: 20px; font-size: 14px;">
                        <?php echo htmlspecialchars($product['nom_categorie']); ?>
                    </span>
                    <?php if ($product['stock'] > 0): ?>
                        <span style="color: #27ae60;">
                            <i class="fas fa-check-circle"></i> En stock (<?php echo $product['stock']; ?>)
                        </span>
                    <?php else: ?>
                        <span style="color: #e74c3c;">
                            <i class="fas fa-times-circle"></i> Rupture de stock
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Titre -->
                <h1 style="font-size: 32px; color: #333; margin-bottom: 15px;">
                    <?php echo htmlspecialchars($product['nom_produit']); ?>
                </h1>
                
                <!-- Marque et modèle -->
                <?php if ($product['marque'] || $product['modele']): ?>
                <div style="margin-bottom: 20px; color: #666;">
                    <?php if ($product['marque']): ?>
                        <span style="margin-right: 15px;"><strong>Marque:</strong> <?php echo htmlspecialchars($product['marque']); ?></span>
                    <?php endif; ?>
                    <?php if ($product['modele']): ?>
                        <span><strong>Modèle:</strong> <?php echo htmlspecialchars($product['modele']); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Note et avis -->
                <?php if ($product['nb_avis'] > 0): ?>
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                    <div style="display: flex; gap: 5px; color: #f39c12;">
                        <?php 
                        $note = round($product['note_moyenne'] * 2) / 2;
                        for ($i = 1; $i <= 5; $i++): 
                            if ($i <= $note): 
                        ?>
                            <i class="fas fa-star"></i>
                        <?php elseif ($i - 0.5 == $note): ?>
                            <i class="fas fa-star-half-alt"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <span style="color: #666;">
                        <?php echo number_format($product['note_moyenne'], 1); ?>/5 
                        (<?php echo $product['nb_avis']; ?> avis)
                    </span>
                </div>
                <?php endif; ?>
                
                <!-- Prix -->
                <div style="margin-bottom: 30px;">
                    <?php if ($product['est_en_promotion'] && $product['prix_promotion']): ?>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-size: 36px; font-weight: bold; color: #e74c3c;">
                                <?php echo number_format($product['prix_promotion'], 0, ',', ' '); ?> FCFA
                            </span>
                            <span style="text-decoration: line-through; color: #999; font-size: 24px;">
                                <?php echo number_format($product['prix'], 0, ',', ' '); ?> FCFA
                            </span>
                            <span style="background: #e74c3c; color: white; padding: 5px 10px; border-radius: 4px;">
                                -<?php echo round((1 - $product['prix_promotion']/$product['prix']) * 100); ?>%
                            </span>
                        </div>
                    <?php else: ?>
                        <span style="font-size: 36px; font-weight: bold; color: #2d3d48;">
                            <?php echo number_format($product['prix'], 0, ',', ' '); ?> FCFA
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Description courte -->
                <?php if ($product['description_courte']): ?>
                <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                    <p style="color: #555; font-size: 16px; line-height: 1.6; margin: 0;">
                        <?php echo nl2br(htmlspecialchars($product['description_courte'])); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Ajout au panier -->
                <?php if ($product['stock'] > 0): ?>
                <form method="POST" action="" style="margin-bottom: 30px;">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div style="display: flex; align-items: center; border: 2px solid #e0e0e0; border-radius: 8px;">
                            <button type="button" onclick="decrementQuantity()" 
                                    style="padding: 12px 15px; background: none; border: none; cursor: pointer; color: #333;">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" 
                                   style="width: 60px; text-align: center; border: none; padding: 12px 0;">
                            <button type="button" onclick="incrementQuantity()" 
                                    style="padding: 12px 15px; background: none; border: none; cursor: pointer; color: #333;">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        
                        <button type="submit" name="add_to_cart" 
                                style="flex: 1; background: #005c46; color: white; padding: 15px; border: none; border-radius: 8px; 
                                       font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fas fa-cart-plus"></i>
                            Ajouter au panier
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <button disabled style="width: 100%; background: #ccc; color: #666; padding: 15px; border: none; border-radius: 8px; 
                                          font-size: 16px; font-weight: 600; margin-bottom: 30px;">
                        Produit indisponible
                    </button>
                <?php endif; ?>
                
                <!-- Garanties -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                    <div style="text-align: center;">
                        <i class="fas fa-truck" style="color: #2d3d48; font-size: 20px; margin-bottom: 5px;"></i>
                        <div style="font-size: 12px; color: #666;">Livraison rapide</div>
                    </div>
                    <div style="text-align: center;">
                        <i class="fas fa-shield-alt" style="color: #2d3d48; font-size: 20px; margin-bottom: 5px;"></i>
                        <div style="font-size: 12px; color: #666;">Garantie incluse</div>
                    </div>
                    <div style="text-align: center;">
                        <i class="fas fa-undo" style="color: #2d3d48; font-size: 20px; margin-bottom: 5px;"></i>
                        <div style="font-size: 12px; color: #666;">Retour facile</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION CARACTÉRISTIQUES DÉTAILLÉES -->
<?php if (!empty($caracteristiques) && is_array($caracteristiques)): ?>
<section style="padding: 60px 0; background: #f8f9fa;">
    <div class="container">
        <h2 style="font-size: 28px; color: #333; margin-bottom: 30px;">Caractéristiques techniques</h2>
        
        <div style="background: white; border-radius: 15px; padding: 40px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            <!-- Vue d'ensemble des caractéristiques -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                <?php foreach ($caracteristiques as $categorie => $details): ?>
                    <?php if (is_array($details)): ?>
                        <!-- Groupe de caractéristiques -->
                        <div style="background: #f8f9fa; border-radius: 10px; padding: 20px;">
                            <h3 style="color: #2d3d48; margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #2d3d48; padding-bottom: 10px;">
                                <?php echo ucfirst(str_replace('_', ' ', $categorie)); ?>
                            </h3>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($details as $key => $value): ?>
                                    <?php if (!is_array($value) && !empty($value)): ?>
                                        <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #ddd;">
                                            <span style="color: #666;"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                                            <span style="color: #333; font-weight: 500;"><?php echo htmlspecialchars($value); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Caractéristique simple -->
                        <div style="background: #f8f9fa; border-radius: 10px; padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #666;"><?php echo ucfirst(str_replace('_', ' ', $categorie)); ?>:</span>
                                <span style="color: #333; font-weight: 600;"><?php echo htmlspecialchars($details); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Affichage sous forme de tableau si c'est un format différent -->
            <?php 
            // Vérifier si c'est un format simple clé-valeur
            $isSimpleFormat = true;
            foreach ($caracteristiques as $value) {
                if (is_array($value)) {
                    $isSimpleFormat = false;
                    break;
                }
            }
            
            if ($isSimpleFormat): 
            ?>
            <div style="margin-top: 30px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <?php foreach ($caracteristiques as $key => $value): ?>
                        <?php if (!is_array($value) && !empty($value)): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 15px; background: #f8f9fa; width: 200px; font-weight: 600; color: #333;">
                                <?php echo ucfirst(str_replace('_', ' ', $key)); ?>
                            </td>
                            <td style="padding: 15px; color: #666;">
                                <?php echo htmlspecialchars($value); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Description détaillée -->
<?php if ($product['description']): ?>
<section style="padding: 60px 0;">
    <div class="container">
        <h2 style="font-size: 28px; color: #333; margin-bottom: 30px;">Description du produit</h2>
        <div style="background: white; border-radius: 15px; padding: 40px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            <div style="color: #555; line-height: 1.8;">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section avis -->
<section id="reviews" style="padding: 60px 0; background: #f8f9fa;">
    <div class="container">
        <h2 style="font-size: 28px; color: #333; margin-bottom: 30px;">Avis clients</h2>
        
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 30px;">
            <!-- Résumé des avis -->
            <div style="background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); height: fit-content;">
                <div style="text-align: center;">
                    <div style="font-size: 48px; font-weight: bold; color: #333;">
                        <?php echo number_format($product['note_moyenne'], 1); ?>
                    </div>
                    <div style="color: #f39c12; font-size: 24px; margin: 10px 0;">
                        <?php 
                        $note = round($product['note_moyenne']);
                        for ($i = 1; $i <= 5; $i++): 
                            echo $i <= $note ? '★' : '☆';
                        endfor; 
                        ?>
                    </div>
                    <div style="color: #666;">
                        Basé sur <?php echo $product['nb_avis']; ?> avis
                    </div>
                </div>
                
                <?php if ($product['nb_avis'] > 0): ?>
                <div style="margin-top: 30px;">
                    <?php
                    $rating_counts = $pdo->prepare("
                        SELECT note, COUNT(*) as count 
                        FROM avis 
                        WHERE id_produit = ? AND est_approuve = 1 
                        GROUP BY note 
                        ORDER BY note DESC
                    ");
                    $rating_counts->execute([$product_id]);
                    $counts = $rating_counts->fetchAll();
                    ?>
                    
                    <?php for ($i = 5; $i >= 1; $i--): 
                        $count = 0;
                        foreach ($counts as $c) {
                            if ($c['note'] == $i) {
                                $count = $c['count'];
                                break;
                            }
                        }
                        $percentage = $product['nb_avis'] > 0 ? ($count / $product['nb_avis'] * 100) : 0;
                    ?>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <span style="min-width: 30px;"><?php echo $i; ?> ★</span>
                        <div style="flex: 1; height: 8px; background: #eee; border-radius: 4px;">
                            <div style="width: <?php echo $percentage; ?>%; height: 100%; background: #f39c12; border-radius: 4px;"></div>
                        </div>
                        <span style="min-width: 40px;"><?php echo $count; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Liste des avis -->
            <div>
                <?php if (empty($reviews)): ?>
                    <div style="background: white; border-radius: 15px; padding: 50px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                        <i class="fas fa-comments" style="font-size: 50px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3 style="margin-bottom: 15px; color: #333;">Aucun avis pour le moment</h3>
                        <p style="color: #666;">Soyez le premier à donner votre avis sur ce produit</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div style="background: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div style="width: 50px; height: 50px; background: #2d3d48; color: white; border-radius: 50%; 
                                            display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 18px;">
                                    <?php echo strtoupper(substr($review['prenom'], 0, 1) . substr($review['nom'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h4 style="margin: 0; color: #333;"><?php echo htmlspecialchars($review['prenom'] . ' ' . $review['nom']); ?></h4>
                                    <div style="font-size: 14px; color: #666;">
                                        <?php echo date('d/m/Y', strtotime($review['date_avis'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div style="color: #f39c12;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $review['note']): ?>★<?php else: ?>☆<?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p style="color: #555; line-height: 1.6; margin: 0;">
                            <?php echo nl2br(htmlspecialchars($review['commentaire'])); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Formulaire pour laisser un avis -->
                <?php if (isLoggedIn()): ?>
                <div style="background: white; border-radius: 15px; padding: 30px; margin-top: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                    <h3 style="margin-bottom: 20px; color: #333;">Laisser un avis</h3>
                    
                    <form method="POST" action="">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">Votre note</label>
                            <div style="display: flex; gap: 10px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label style="cursor: pointer; font-size: 30px; color: #ddd;" class="star-rating">
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" required style="display: none;">
                                        ★
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #333;">Votre commentaire</label>
                            <textarea name="comment" rows="5" required minlength="10" maxlength="500" 
                                      placeholder="Partagez votre expérience avec ce produit..."
                                      style="width: 100%; padding: 15px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                            <div style="text-align: right; color: #666; font-size: 14px; margin-top: 5px;">
                                <span class="charCount">0</span>/500 caractères
                            </div>
                        </div>
                        
                        <button type="submit" name="submit_review" 
                                style="background: #005c46; color: white; padding: 15px 30px; border: none; border-radius: 8px; 
                                       font-size: 16px; font-weight: 600; cursor: pointer;">
                            Publier mon avis
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div style="background: white; border-radius: 15px; padding: 30px; text-align: center; margin-top: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                    <p style="margin-bottom: 15px;">Connectez-vous pour laisser un avis</p>
                    <a href="../login.php?redirect=products/product.php?id=<?php echo $product_id; ?>"  <!-- CHEMIN CORRIGÉ -->
                       style="background: #2d3d48; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">
                        Se connecter
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Produits similaires -->
<?php if (!empty($similar_products)): ?>
<section style="padding: 60px 0;">
    <div class="container">
        <h2 style="font-size: 28px; color: #333; margin-bottom: 30px;">Produits similaires</h2>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
            <?php foreach ($similar_products as $similar): ?>
            <a href="product.php?id=<?php echo $similar['id_produit']; ?>" style="text-decoration: none;">
                <div style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s;">
                    <div style="height: 150px; overflow: hidden;">
                        <?php if ($similar['url_image']): ?>
                            <img src="<?php echo $similar['url_image']; ?>" alt="<?php echo htmlspecialchars($similar['nom_produit']); ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: #f5f5f5; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 30px; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 15px;">
                        <h3 style="margin: 0 0 5px 0; font-size: 14px; color: #333;">
                            <?php echo htmlspecialchars($similar['nom_produit']); ?>
                        </h3>
                        <div style="font-weight: bold; color: #2d3d48;">
                            <?php echo number_format($similar['prix'], 0, ',', ' '); ?> FCFA
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
// Fonctions pour la quantité
function incrementQuantity() {
    const input = document.getElementById('quantity');
    const max = parseInt(input.max);
    if (parseInt(input.value) < max) {
        input.value = parseInt(input.value) + 1;
    }
}

function decrementQuantity() {
    const input = document.getElementById('quantity');
    if (parseInt(input.value) > 1) {
        input.value = parseInt(input.value) - 1;
    }
}

// Script pour les étoiles de notation
document.querySelectorAll('.star-rating').forEach((label, index) => {
    label.addEventListener('click', function() {
        const stars = document.querySelectorAll('.star-rating');
        stars.forEach((star, i) => {
            star.style.color = i <= index ? '#f39c12' : '#ddd';
        });
    });
});

// Compteur de caractères
const commentTextarea = document.querySelector('textarea[name="comment"]');
const charCount = document.querySelector('.charCount');

if (commentTextarea && charCount) {
    commentTextarea.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });
}
// Juste après le traitement POST, ajoutez :
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    error_log("=== DÉBUT AJOUT PANIER ===");
    error_log("User ID: " . getUserId());
    error_log("Product ID: " . $product_id);
    error_log("Quantity: " . $quantity);
    
    $result = addToCart($product_id, $quantity);
    
    error_log("Résultat: " . print_r($result, true));
    error_log("=== FIN AJOUT PANIER ===");
    
    // ... suite du code
}
</script>

<?php include '../includes/footer.php'; ?>  <!-- CHEMIN CORRIGÉ -->
