<!-- Section Avis Produit -->
<section id="product-reviews" style="margin-top: 3rem; padding: 2rem 0; border-top: 1px solid #eee;">
    <div style="max-width: 800px; margin: 0 auto;">
        <h2 style="margin-bottom: 1.5rem;">Avis clients</h2>
        
        <?php
        // Traitement du formulaire d'avis
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
            if (!isLoggedIn()) {
                setFlashMessage('Veuillez vous connecter pour donner votre avis', 'error');
            } else {
                $user_id = $_SESSION['user_id'];
                $rating = intval($_POST['rating']);
                $comment = trim($_POST['comment']);
                
                // Validation
                if ($rating < 1 || $rating > 5) {
                    setFlashMessage('Veuillez sélectionner une note valide', 'error');
                } elseif (strlen($comment) < 10) {
                    setFlashMessage('Le commentaire doit contenir au moins 10 caractères', 'error');
                } elseif (!canUserReviewProduct($user_id, $product['id_produit'])) {
                    setFlashMessage('Vous devez avoir acheté ce produit pour donner votre avis', 'error');
                } else {
                    $result = addProductReview($product['id_produit'], $user_id, $rating, $comment);
                    setFlashMessage($result['message'], $result['success'] ? 'success' : 'error');
                }
            }
            redirect('product.php?id=' . $product['id_produit'] . '#product-reviews');
        }
        
        // Récupérer les statistiques des avis
        $rating_stats = getProductRatingStats($product['id_produit']);
        $reviews = getProductReviews($product['id_produit']);
        ?>
        
        <!-- Résumé des notes -->
        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 2rem;">
                <div style="text-align: center;">
                    <div style="font-size: 2.5rem; font-weight: bold; color: #ffc107;">
                        <?php echo number_format($rating_stats['average_rating'], 1); ?>/5
                    </div>
                    <div style="margin-top: 0.5rem;">
                        <?php echo displayRatingStars($rating_stats['average_rating']); ?>
                    </div>
                    <div style="margin-top: 0.5rem; color: #666;">
                        <?php echo $rating_stats['review_count']; ?> avis
                    </div>
                </div>
                
                <div style="flex: 1; max-width: 300px;">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <?php 
                        $star_count = $rating_stats[$i . '_star'] ?? 0;
                        $percentage = $rating_stats['review_count'] > 0 ? 
                            ($star_count / $rating_stats['review_count']) * 100 : 0;
                        ?>
                        <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                            <div style="width: 60px; text-align: right; margin-right: 10px;">
                                <span style="color: #666;"><?php echo $i; ?> étoiles</span>
                            </div>
                            <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?php echo $percentage; ?>%; height: 100%; background: #ffc107;"></div>
                            </div>
                            <div style="width: 40px; text-align: right; margin-left: 10px;">
                                <span style="color: #666; font-size: 0.9rem;"><?php echo $star_count; ?></span>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <!-- Bouton pour laisser un avis -->
                <div>
                    <?php if (isLoggedIn()): ?>
                        <?php if (canUserReviewProduct($_SESSION['user_id'], $product['id_produit'])): ?>
                            <button type="button" class="btn btn-primary" 
                                    onclick="document.getElementById('review-form-container').style.display='block'">
                                <i class="fas fa-star"></i> Donner mon avis
                            </button>
                        <?php else: ?>
                            <div style="color: #666; font-style: italic; max-width: 200px;">
                                <i class="fas fa-info-circle"></i> 
                                Vous devez avoir acheté ce produit pour donner votre avis
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] . '#product-reviews'); ?>" 
                           class="btn btn-outline-primary">
                            <i class="fas fa-sign-in-alt"></i> Connectez-vous pour donner votre avis
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Formulaire d'avis -->
        <div id="review-form-container" style="display: none; background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem;">
            <h3 style="margin-bottom: 1rem;">Donner votre avis</h3>
            <form method="POST" action="">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                        Votre note *
                    </label>
                    <div class="star-rating-input" style="font-size: 1.5rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="far fa-star star-rating" 
                               data-value="<?php echo $i; ?>" 
                               style="cursor: pointer; color: #ddd; margin-right: 0.25rem;"></i>
                        <?php endfor; ?>
                        <input type="hidden" name="rating" id="rating-input" required>
                    </div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label for="review-comment" style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                        Votre commentaire *
                    </label>
                    <textarea name="comment" id="review-comment" rows="5" 
                              style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;"
                              placeholder="Partagez votre expérience avec ce produit..." 
                              required></textarea>
                    <small style="color: #666;">Minimum 10 caractères</small>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="submit_review" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Envoyer mon avis
                    </button>
                    <button type="button" class="btn btn-outline" 
                            onclick="document.getElementById('review-form-container').style.display='none'">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Liste des avis -->
        <div class="reviews-list">
            <?php if (empty($reviews)): ?>
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <i class="fas fa-comment-slash fa-2x" style="margin-bottom: 1rem;"></i>
                    <p>Soyez le premier à donner votre avis sur ce produit !</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div style="border-bottom: 1px solid #eee; padding: 1.5rem 0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <div>
                                <strong><?php echo htmlspecialchars($review['prenom'] . ' ' . $review['nom']); ?></strong>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                                    <?php 
                                    // Afficher les étoiles de la note
                                    for ($i = 1; $i <= 5; $i++): 
                                        if ($i <= $review['note']):
                                            echo '<i class="fas fa-star" style="color: #ffc107; font-size: 0.9rem;"></i>';
                                        else:
                                            echo '<i class="far fa-star" style="color: #ddd; font-size: 0.9rem;"></i>';
                                        endif;
                                    endfor; 
                                    ?>
                                    <span style="color: #666; font-size: 0.9rem;"><?php echo $review['note']; ?>/5</span>
                                </div>
                            </div>
                            <div style="color: #666; font-size: 0.9rem;">
                                <i class="far fa-clock"></i> <?php echo $review['date_formatted']; ?>
                            </div>
                        </div>
                        
                        <div style="margin-top: 0.5rem; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($review['commentaire'])); ?>
                        </div>
                        
                        <?php if (isAdmin()): ?>
                            <div style="margin-top: 0.5rem;">
                                <form method="POST" action="admin_avis.php" style="display: inline;">
                                    <input type="hidden" name="id_avis" value="<?php echo $review['id_avis']; ?>">
                                    <?php if ($review['est_approuve'] == 0): ?>
                                        <button type="submit" name="approve_review" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Approuver
                                        </button>
                                    <?php endif; ?>
                                    <button type="submit" name="delete_review" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Supprimer cet avis ?')">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($reviews) == 10): ?>
                    <div style="text-align: center; margin-top: 2rem;">
                        <a href="product_reviews.php?id=<?php echo $product['id_produit']; ?>" class="btn btn-outline">
                            Voir tous les avis
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
// Script pour la sélection des étoiles
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star-rating');
    const ratingInput = document.getElementById('rating-input');
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            ratingInput.value = value;
            
            // Mettre à jour l'affichage des étoiles
            stars.forEach((s, index) => {
                if (index < value) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                    s.style.color = '#ffc107';
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                    s.style.color = '#ddd';
                }
            });
        });
        
        star.addEventListener('mouseover', function() {
            const value = this.getAttribute('data-value');
            stars.forEach((s, index) => {
                if (index < value) {
                    s.style.color = '#ffd700';
                }
            });
        });
        
        star.addEventListener('mouseout', function() {
            const currentValue = ratingInput.value || 0;
            stars.forEach((s, index) => {
                if (index < currentValue) {
                    s.style.color = currentValue ? '#ffc107' : '#ddd';
                } else {
                    s.style.color = '#ddd';
                }
            });
        });
    });
});
</script>
<?php
require_once 'includes/config.php';

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$product_id = intval($_GET['id']);

// Récupérer les détails du produit
$stmt = $pdo->prepare("
    SELECT p.*, c.nom_categorie,
           COALESCE(AVG(a.note), 0) as note_moyenne,
           COUNT(a.id_avis) as nb_avis
    FROM produit p 
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
    LEFT JOIN avis a ON p.id_produit = a.id_produit AND a.est_approuve = 1
    WHERE p.id_produit = ? AND p.est_actif = 1
    GROUP BY p.id_produit
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    setFlashMessage('Produit non trouvé', 'error');
    redirect('index.php');
}

// Récupérer les images du produit
$images = $pdo->prepare("SELECT * FROM image_produit WHERE id_produit = ? ORDER BY ordre, est_principale DESC");
$images->execute([$product_id]);
$product_images = $images->fetchAll();

// Récupérer les avis du produit
$reviews = $pdo->prepare("
    SELECT a.*, u.prenom, u.nom 
    FROM avis a 
    JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur 
    WHERE a.id_produit = ? AND a.est_approuve = 1 
    ORDER BY a.date_avis DESC
");
$reviews->execute([$product_id]);
$product_reviews = $reviews->fetchAll();

// Récupérer les produits similaires
$similar_products = $pdo->prepare("
    SELECT p.*, c.nom_categorie,
           (SELECT ip.url_image 
            FROM image_produit ip 
            WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 
            LIMIT 1) as url_image
    FROM produit p 
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
    WHERE p.id_categorie = ? AND p.id_produit != ? AND p.est_actif = 1 
    LIMIT 4
");
$similar_products->execute([$product['id_categorie'], $product_id]);

// Gestion AJOUT AU PANIER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour ajouter des produits au panier', 'error');
        redirect('login.php?redirect=product.php?id=' . $product_id);
    }
    
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $result = addToCart($product_id, $quantity);
    
    if ($result['success']) {
        setFlashMessage('Produit ajouté au panier avec succès', 'success');
    } else {
        setFlashMessage($result['message'], 'error');
    }
}

// Gestion AJOUT D'AVIS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour laisser un avis', 'error');
        redirect('login.php?redirect=product.php?id=' . $product_id);
    }
    
    $rating = floatval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    
    // Vérifier si l'utilisateur a déjà laissé un avis pour ce produit
    $check_stmt = $pdo->prepare("SELECT id_avis FROM avis WHERE id_produit = ? AND id_utilisateur = ?");
    $check_stmt->execute([$product_id, $user_id]);
    
    if ($check_stmt->fetch()) {
        setFlashMessage('Vous avez déjà laissé un avis pour ce produit', 'error');
    } else {
        // Insérer l'avis
        $insert_stmt = $pdo->prepare("
            INSERT INTO avis (id_produit, id_utilisateur, note, commentaire, date_avis, est_approuve) 
            VALUES (?, ?, ?, ?, NOW(), 0)
        ");
        
        if ($insert_stmt->execute([$product_id, $user_id, $rating, $comment])) {
            setFlashMessage('Votre avis a été soumis avec succès ! Il sera publié après modération.', 'success');
            redirect('product.php?id=' . $product_id);
        } else {
            setFlashMessage('Erreur lors de l\'enregistrement de l\'avis', 'error');
        }
    }
}

$page_title = $product['nom_produit'] . " - Détails Produit";
include 'includes/header.php';
?>

<section style="padding: 40px 0; background: #f8f9fa;">
    <div class="container">
        <!-- Navigation -->
        <div style="margin-bottom: 20px;">
            <a href="index.php" style="color: #666; text-decoration: none;">Accueil</a>
            <span style="margin: 0 10px; color: #ccc;">/</span>
            <a href="index.php?category=<?php echo $product['id_categorie']; ?>" style="color: #666; text-decoration: none;">
                <?php echo htmlspecialchars($product['nom_categorie']); ?>
            </a>
            <span style="margin: 0 10px; color: #ccc;">/</span>
            <span style="color: #333;"><?php echo htmlspecialchars($product['nom_produit']); ?></span>
        </div>

        <div style="background: white; border-radius: 10px; padding: 40px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            <!-- Produit -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 50px;">
                <!-- Images produit -->
                <div>
                    <div style="margin-bottom: 20px;">
                        <img id="mainImage" src="<?php echo $product_images[0]['url_image'] ?? 'assets/no-image.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($product['nom_produit']); ?>" 
                             style="width: 100%; height: 400px; object-fit: contain; border-radius: 8px;">
                    </div>
                    
                    <?php if (count($product_images) > 1): ?>
                    <div style="display: flex; gap: 10px; overflow-x: auto; padding: 10px 0;">
                        <?php foreach ($product_images as $image): ?>
                            <img src="<?php echo $image['url_image']; ?>" 
                                 alt="Image produit" 
                                 style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 2px solid #ddd;"
                                 onclick="document.getElementById('mainImage').src = this.src">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Détails produit -->
                <div>
                    <h1 style="font-size: 32px; margin-bottom: 15px; color: #333;">
                        <?php echo htmlspecialchars($product['nom_produit']); ?>
                    </h1>
                    
                    <div style="margin-bottom: 20px;">
                        <span style="background: #e8f5e9; color: #2d3d48; padding: 5px 10px; border-radius: 4px; font-size: 14px;">
                            <?php echo htmlspecialchars($product['nom_categorie']); ?>
                        </span>
                    </div>
                    
                    <!-- Notation -->
                    <?php if ($product['note_moyenne'] > 0): ?>
                    <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <div style="color: #f39c12; font-size: 20px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= floor($product['note_moyenne'])): ?>
                                    ★
                                <?php elseif ($i == ceil($product['note_moyenne']) && fmod($product['note_moyenne'], 1) > 0): ?>
                                    ★
                                <?php else: ?>
                                    ☆
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span style="font-weight: 600; color: #333;">
                            <?php echo number_format($product['note_moyenne'], 1); ?>/5
                        </span>
                        <span style="color: #666;">
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
                                <span style="background: #e74c3c; color: white; padding: 5px 10px; border-radius: 4px; font-size: 14px;">
                                    Économisez <?php echo number_format($product['prix'] - $product['prix_promotion'], 0, ',', ' '); ?> FCFA
                                </span>
                            </div>
                        <?php else: ?>
                            <span style="font-size: 36px; font-weight: bold; color: #2d3d48;">
                                <?php echo number_format($product['prix'], 0, ',', ' '); ?> FCFA
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stock -->
                    <div style="margin-bottom: 30px;">
                        <?php if ($product['stock'] > 0): ?>
                            <span style="color: #27ae60; font-weight: 600;">
                                <i class="fas fa-check-circle"></i> En stock (<?php echo $product['stock']; ?> unités)
                            </span>
                        <?php else: ?>
                            <span style="color: #e74c3c; font-weight: 600;">
                                <i class="fas fa-times-circle"></i> Rupture de stock
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description courte -->
                    <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <p style="color: #666; line-height: 1.6; margin: 0;">
                            <?php echo htmlspecialchars($product['description_courte'] ?? $product['description']); ?>
                        </p>
                    </div>
                    
                    <!-- Ajouter au panier -->
                    <?php if ($product['stock'] > 0): ?>
                    <form method="POST" action="">
                        <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label style="font-weight: 600; color: #333;">Quantité :</label>
                                <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" 
                                       style="width: 80px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px;">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px;">
                            <button type="submit" name="add_to_cart" 
                                    style="flex: 1; background: #005c46; color: white; padding: 15px; border: none; border-radius: 8px; 
                                           font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;">
                                <i class="fas fa-cart-plus"></i>
                                Ajouter au panier
                            </button>
                            
                            <button type="button" 
                                    style="background: #2d3d48; color: white; padding: 15px 30px; border: none; border-radius: 8px; 
                                           font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-heart"></i>
                                Favoris
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                        <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; text-align: center;">
                            <p style="margin: 0; color: #856404;">
                                Ce produit est actuellement en rupture de stock. Contactez-nous pour être prévenu de sa réapprovisionnement.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Description détaillée -->
            <div style="margin-bottom: 50px;">
                <h2 style="margin-bottom: 20px; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                    Description détaillée
                </h2>
                <div style="color: #555; line-height: 1.8;">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
            </div>
            
            <!-- Section Avis -->
            <div id="reviews" style="margin-bottom: 50px;">
                <h2 style="margin-bottom: 20px; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                    Avis clients (<?php echo $product['nb_avis']; ?>)
                </h2>
                
                <!-- Formulaire d'avis -->
                <div style="margin-bottom: 40px; padding: 30px; background: #f8f9fa; border-radius: 8px;">
                    <h3 style="margin-bottom: 20px; color: #333;">Laisser votre avis</h3>
                    
                    <?php if (isLoggedIn()): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Votre note</label>
                                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label style="cursor: pointer; font-size: 24px; color: #ddd;" class="product-star-rating">
                                            <input type="radio" name="rating" value="<?php echo $i; ?>" required style="display: none;">
                                            ★
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Votre commentaire</label>
                                <textarea name="comment" rows="4" required minlength="10" 
                                          placeholder="Partagez votre expérience avec ce produit..."
                                          style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                            </div>
                            
                            <button type="submit" name="submit_review" 
                                    style="background: #005c46; color: white; padding: 12px 30px; border: none; border-radius: 8px; 
                                           font-weight: 600; cursor: pointer;">
                                Publier mon avis
                            </button>
                        </form>
                    <?php else: ?>
                        <p style="color: #666; margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i> Connectez-vous pour laisser un avis sur ce produit.
                        </p>
                        <div style="display: flex; gap: 15px;">
                            <a href="login.php?redirect=product.php?id=<?php echo $product_id; ?>" 
                               style="background: #2d3d48; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none;">
                                Se connecter
                            </a>
                            <a href="register.php" 
                               style="border: 2px solid #2d3d48; color: #2d3d48; padding: 10px 20px; border-radius: 8px; text-decoration: none;">
                                Créer un compte
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Liste des avis -->
                <?php if (!empty($product_reviews)): ?>
                    <div style="display: flex; flex-direction: column; gap: 25px;">
                        <?php foreach ($product_reviews as $review): ?>
                            <div style="padding: 20px; border: 1px solid #eee; border-radius: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; background: #2d3d48; color: white; border-radius: 50%; 
                                                    display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                            <?php echo strtoupper(substr($review['prenom'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0; color: #333; font-size: 16px;">
                                                <?php echo htmlspecialchars($review['prenom'] . ' ' . $review['nom']); ?>
                                            </h4>
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
                                    </div>
                                </div>
                                
                                <p style="color: #555; line-height: 1.6; margin: 0;">
                                    <?php echo nl2br(htmlspecialchars($review['commentaire'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; background: #f8f9fa; border-radius: 8px;">
                        <i class="fas fa-comment-slash" style="font-size: 50px; color: #ccc; margin-bottom: 20px;"></i>
                        <p style="color: #666; margin-bottom: 0;">
                            Aucun avis pour ce produit. Soyez le premier à donner votre avis !
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Produits similaires -->
            <?php if ($similar_products->rowCount() > 0): ?>
            <div>
                <h2 style="margin-bottom: 30px; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                    Produits similaires
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px;">
                    <?php while ($similar = $similar_products->fetch()): ?>
                        <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 3px 10px rgba(0,0,0,0.05);">
                            <div style="height: 150px; overflow: hidden;">
                                <?php if ($similar['url_image']): ?>
                                    <img src="<?php echo $similar['url_image']; ?>" 
                                         alt="<?php echo htmlspecialchars($similar['nom_produit']); ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php endif; ?>
                            </div>
                            
                            <div style="padding: 15px;">
                                <h3 style="margin-bottom: 10px; font-size: 16px; color: #333;">
                                    <?php echo htmlspecialchars(substr($similar['nom_produit'], 0, 50)); ?>...
                                </h3>
                                
                                <div style="margin-bottom: 15px;">
                                    <span style="font-weight: bold; color: #2d3d48;">
                                        <?php echo number_format($similar['prix'], 0, ',', ' '); ?> FCFA
                                    </span>
                                </div>
                                
                                <a href="product.php?id=<?php echo $similar['id_produit']; ?>" 
                                   style="display: block; text-align: center; background: #f8f9fa; color: #333; 
                                          padding: 8px; border-radius: 4px; text-decoration: none; font-size: 14px;">
                                    Voir détails
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
// Script pour les étoiles de notation sur la page produit
document.querySelectorAll('.product-star-rating').forEach((label, index) => {
    label.addEventListener('click', function() {
        const stars = document.querySelectorAll('.product-star-rating');
        const starIndex = index + 1;
        
        stars.forEach(star => {
            star.style.color = '#ddd';
        });
        
        for (let i = 0; i < starIndex; i++) {
            stars[i].style.color = '#f39c12';
        }
        
        this.querySelector('input').checked = true;
    });
    
    label.addEventListener('mouseover', function() {
        const stars = document.querySelectorAll('.product-star-rating');
        const starIndex = index + 1;
        
        for (let i = 0; i < starIndex; i++) {
            stars[i].style.color = '#f39c12';
        }
    });
    
    label.addEventListener('mouseout', function() {
        const stars = document.querySelectorAll('.product-star-rating');
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
createReviewNotification($pdo, $product_id, $user_id);
</script>


<?php include 'includes/footer.php'; ?>