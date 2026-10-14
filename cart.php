<?php
// panier.php
require_once 'includes/config.php';
require_once 'classes/Panier.php';

$user_id = getUserId();
$cart = new Panier($pdo, $user_id);

// Synchroniser le panier si l'utilisateur vient de se connecter
if ($user_id && !empty($_SESSION['panier'])) {
    $cart->syncSessionCart();
}

// Récupérer les informations de l'utilisateur si connecté
$user_info = [];
if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch();
}

$content = $cart->getContent();
$totals = $cart->getTotals();

$page_title = "Mon Panier - " . SITE_NAME;
$header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_show_products_link = true;

include 'includes/header.php';
?>

<style>
:root {
    --primary: #2d3d48;
    --primary-dark: #1a2a33;
    --secondary: #005c46;
    --accent: #fcd116;
    --danger: #ce1126;
    --success: #28a745;
    --warning: #ffc107;
    --light: #f8f9fa;
    --dark: #343a40;
    --gray: #6c757d;
    --border-radius: 10px;
    --box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    --transition: all 0.3s ease;
}

body {
    background-color: #f4f7fb;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 40px 0;
    margin-bottom: 40px;
    border-radius: 0 0 100px 100px;
    box-shadow: var(--box-shadow);
}

.page-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.page-header .breadcrumb {
    background: transparent;
    padding: 0;
    margin: 0;
}

.page-header .breadcrumb-item a {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
}

.page-header .breadcrumb-item.active {
    color: white;
}

/* Cart Items */
.cart-container {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    margin-bottom: 30px;
}

.cart-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}

.cart-item {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    transition: var(--transition);
}

.cart-item:hover {
    background-color: #f8f9fa;
}

.cart-item:last-child {
    border-bottom: none;
}

.product-image {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.product-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
}

.product-category {
    color: var(--gray);
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.product-category i {
    color: var(--secondary);
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    border-radius: 8px;
    padding: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.quantity-btn {
    width: 35px;
    height: 35px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 8px;
    color: var(--primary);
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantity-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.quantity-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.quantity-input {
    width: 60px;
    text-align: center;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 8px;
    font-weight: 600;
}

.price {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
}

.price-original {
    text-decoration: line-through;
    color: var(--gray);
    font-size: 0.9rem;
    margin-right: 8px;
}

.price-promo {
    color: var(--danger);
    font-weight: 700;
}

.stock-warning {
    background: #fff3cd;
    color: #856404;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 0.85rem;
    margin-top: 5px;
}

.remove-btn {
    color: var(--danger);
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    transition: var(--transition);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.remove-btn:hover {
    background: #fee2e2;
    color: #dc2626;
}

/* Cart Summary */
.cart-summary {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 25px;
    position: sticky;
    top: 100px;
}

.summary-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px dashed #dee2e6;
}

.summary-row.total {
    border-bottom: none;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
    margin-top: 15px;
}

.summary-label {
    color: var(--gray);
}

.summary-value {
    font-weight: 600;
    color: var(--dark);
}

.shipping-progress {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.progress-text {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.progress-bar-container {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent), var(--secondary));
    transition: width 0.3s ease;
}

/* Checkout Button */
.btn-checkout {
    background: linear-gradient(135deg, var(--secondary), var(--primary));
    color: white;
    border: none;
    padding: 15px 30px;
    font-size: 1.2rem;
    font-weight: 600;
    border-radius: 50px;
    width: 100%;
    cursor: pointer;
    transition: var(--transition);
    margin-top: 20px;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-checkout:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,92,70,0.3);
    color: white;
}

.btn-checkout:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-checkout i {
    margin-right: 10px;
}

/* Empty Cart */
.empty-cart {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
}

.empty-cart i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-cart h3 {
    font-size: 1.8rem;
    color: var(--dark);
    margin-bottom: 10px;
}

.empty-cart p {
    color: var(--gray);
    margin-bottom: 30px;
}

.btn-shop {
    background: linear-gradient(135deg, var(--secondary), var(--primary));
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
}

.btn-shop:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,92,70,0.3);
    color: white;
}

/* Toast Notifications */
#toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
}

.toast-message {
    padding: 15px 20px;
    margin-bottom: 10px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    animation: slideIn 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.toast-message.success {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.toast-message.error {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

.toast-message.warning {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
}

.toast-message.info {
    background: linear-gradient(135deg, #17a2b8, #138496);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        padding: 30px 0;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .cart-item .row > div {
        margin-bottom: 15px;
        text-align: center;
    }
    
    .product-image {
        margin: 0 auto 15px;
    }
    
    .quantity-control {
        justify-content: center;
    }
    
    .cart-summary {
        position: static;
        margin-top: 30px;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-shopping-cart"></i> Mon Panier</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
            </ol>
        </nav>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<div class="container mb-5">
    <?php if ($cart->isEmpty()): ?>
        <!-- Panier vide -->
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <h3>Votre panier est vide</h3>
            <p>Découvrez nos produits et commencez vos achats !</p>
            <a href="produits.php" class="btn-shop">
                <i class="fas fa-box"></i> Voir les Produits
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Liste des produits -->
            <div class="col-lg-8">
                <div class="cart-container">
                    <div class="cart-header">
                        <div class="row">
                            <div class="col-md-5">Produit</div>
                            <div class="col-md-3 text-center">Quantité</div>
                            <div class="col-md-2 text-center">Prix</div>
                            <div class="col-md-2 text-end">Action</div>
                        </div>
                    </div>
                    
                    <?php foreach ($content as $index => $item): 
                        $final_price = $item['final_price'] ?? $item['price'] ?? $item['original_price'] ?? 0;
                        $original_price = $item['original_price'] ?? $item['price'] ?? 0;
                        $has_promo = ($final_price < $original_price);
                        $item_id = $item['item_id'] ?? $index;
                    ?>
                        <div class="cart-item" data-item-id="<?php echo $item_id; ?>">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <div class="d-flex align-items-center">
                                        <img src="uploads/<?php echo htmlspecialchars($item['image'] ?? 'default.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="product-image">
                                        <div class="ms-3">
                                            <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <?php if (!empty($item['description'])): ?>
                                                <div class="product-category">
                                                    <i class="fas fa-tag"></i> 
                                                    <?php echo htmlspecialchars(substr($item['description'], 0, 50)) . '...'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="quantity-control">
                                        <button class="quantity-btn" 
                                                onclick="updateQuantity('<?php echo $item_id; ?>', <?php echo $item['quantity'] - 1; ?>)"
                                                <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="quantity-input" 
                                               value="<?php echo $item['quantity']; ?>" 
                                               min="1" 
                                               max="<?php echo $item['stock'] ?? 99; ?>"
                                               onchange="updateQuantity('<?php echo $item_id; ?>', this.value)"
                                               readonly>
                                        <button class="quantity-btn" 
                                                onclick="updateQuantity('<?php echo $item_id; ?>', <?php echo $item['quantity'] + 1; ?>)"
                                                <?php echo ($item['quantity'] >= ($item['stock'] ?? 99)) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    
                                    <?php if (isset($item['stock']) && $item['stock'] < 10): ?>
                                        <div class="stock-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Plus que <?php echo $item['stock']; ?> en stock
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-2 text-center">
                                    <?php if ($has_promo): ?>
                                        <div>
                                            <span class="price-original"><?php echo number_format($original_price, 0, ',', ' '); ?> FCFA</span>
                                        </div>
                                        <div class="price-promo">
                                            <?php echo number_format($final_price, 0, ',', ' '); ?> FCFA
                                        </div>
                                    <?php else: ?>
                                        <div class="price">
                                            <?php echo number_format($final_price, 0, ',', ' '); ?> FCFA
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-2 text-end">
                                    <button class="remove-btn" onclick="removeItem('<?php echo $item_id; ?>')" title="Supprimer">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <a href="produits.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Continuer les achats
                    </a>
                    <button class="btn btn-outline-danger" onclick="clearCart()">
                        <i class="fas fa-trash"></i> Vider le panier
                    </button>
                </div>
            </div>
            
            <!-- Résumé de la commande -->
            <div class="col-lg-4">
                <div class="cart-summary">
                    <h3 class="summary-title">
                        <i class="fas fa-file-invoice"></i> Résumé de la commande
                    </h3>
                    
                    <div class="summary-row">
                        <span class="summary-label">Sous-total</span>
                        <span class="summary-value"><?php echo $totals['subtotal_formatted']; ?></span>
                    </div>
                    
                    <!-- <div class="summary-row">
                        <span class="summary-label">Livraison</span>
                        <span class="summary-value">
                            <?php if ($totals['shipping'] > 0): ?>
                                <?php echo $totals['shipping_formatted']; ?>
                            <?php else: ?>
                                <span class="text-success">Gratuite</span>
                            <?php endif; ?>
                        </span>
                    </div>
                     -->
                    <?php if ($totals['free_shipping_needed'] > 0): ?>
                        <div class="shipping-progress">
                            <div class="progress-text">
                                <span>Livraison gratuite à partir de <?php echo number_format(FREE_SHIPPING_THRESHOLD, 0, ',', ' '); ?> FCFA</span>
                                <span class="text-success"><?php echo number_format($totals['subtotal'], 0, ',', ' '); ?> FCFA</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?php echo min(100, ($totals['subtotal'] / FREE_SHIPPING_THRESHOLD) * 100); ?>%"></div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                Plus que <?php echo number_format($totals['free_shipping_needed'], 0, ',', ' '); ?> FCFA pour la livraison gratuite
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <!-- <div class="summary-row total">
                        <span class="summary-label">Total TTC</span>
                        <span class="summary-value"><?php echo $totals['total_formatted']; ?></span>
                    </div>
                     -->
                    <?php if (!isLoggedIn()): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <a href="connexion.php?redirect=panier.php" class="alert-link">Connectez-vous</a> 
                            pour finaliser votre commande
                        </div>
                    <?php else: ?>
                        <!-- Bouton pour passer à la page de commande -->
                        <a href="commande.php" class="btn-checkout">
                            <i class="fas fa-arrow-right"></i> Passer la commande
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Mettre à jour la quantité
function updateQuantity(itemId, quantity) {
    if (quantity < 1) return;
    
    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update&item_id=${encodeURIComponent(itemId)}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showNotification(data.message || 'Erreur de mise à jour', 'error');
        }
    })
    .catch(error => {
        showNotification('Erreur de connexion', 'error');
    });
}

// Supprimer un article
function removeItem(itemId) {
    if (!confirm('Supprimer cet article du panier ?')) return;
    
    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=remove&item_id=${encodeURIComponent(itemId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showNotification(data.message || 'Erreur de suppression', 'error');
        }
    });
}

// Vider le panier
function clearCart() {
    if (!confirm('Vider votre panier ?')) return;
    
    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=clear'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showNotification(data.message || 'Erreur', 'error');
        }
    });
}

// Afficher notification
function showNotification(message, type) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast-message ${type}`;
    toast.innerHTML = `
        ${message}
        <button type="button" style="float:right; background:none; border:none; color:white; font-size:20px; cursor:pointer; margin-left:10px;" onclick="this.parentElement.remove()">&times;</button>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 5000);
}
</script>

<?php include 'includes/footer.php'; ?>