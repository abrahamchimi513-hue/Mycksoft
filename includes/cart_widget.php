<?php
// includes/cart_widget.php
// À inclure dans le header pour afficher le mini-panier
if (!isset($pdo)) {
    require_once __DIR__ . '/config.php';
}

$cart_count = 0;
$cart_total = 0;

if (isLoggedIn()) {
    $user_id = getUserId();
    $cart_count = getCartCount($user_id);
    
    $cart_items = getCartData($user_id);
    foreach ($cart_items as $item) {
        $cart_total += $item['prix_unitaire'] * $item['quantite'];
    }
}
?>

<!-- Widget Panier dans le header -->
<div class="dropdown">
    <a href="#" class="nav-link position-relative" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-shopping-cart fa-lg"></i>
        <?php if ($cart_count > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count">
            <?= $cart_count ?>
            <span class="visually-hidden">articles dans le panier</span>
        </span>
        <?php endif; ?>
    </a>
    
    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 350px;">
        <?php if ($cart_count === 0): ?>
            <div class="text-center py-3">
                <i class="fas fa-shopping-basket fa-3x text-muted mb-2"></i>
                <p class="mb-0 text-muted">Votre panier est vide</p>
                <a href="index.php" class="btn btn-sm btn-primary mt-2">Découvrir nos produits</a>
            </div>
        <?php else: ?>
            <div class="cart-dropdown-items" style="max-height: 350px; overflow-y: auto;">
                <?php 
                $cart_items = getCartData(getUserId());
                foreach (array_slice($cart_items, 0, 3) as $item): 
                ?>
                <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                    <?php if (!empty($item['image_url'])): ?>
                        <img src="<?= UPLOADS_URL . '/' . htmlspecialchars($item['image_url']) ?>" 
                             class="rounded me-2" style="width: 50px; height: 50px; object-fit: cover;">
                    <?php else: ?>
                        <img src="<?= IMAGES_URL ?>/placeholder-product.jpg" 
                             class="rounded me-2" style="width: 50px; height: 50px; object-fit: cover;">
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <a href="product.php?id=<?= $item['id_produit'] ?>" class="text-decoration-none small fw-bold">
                            <?= htmlspecialchars(substr($item['nom_produit'], 0, 30)) ?>
                        </a>
                        <div class="small text-muted">
                            <?= $item['quantite'] ?> x <?= formatPrice($item['prix_unitaire']) ?>
                        </div>
                    </div>
                    <div class="fw-bold ms-2">
                        <?= formatPrice($item['prix_unitaire'] * $item['quantite']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($cart_items) > 3): ?>
                <div class="text-center small text-muted mb-2">
                    + <?= count($cart_items) - 3 ?> autre(s) article(s)
                </div>
                <?php endif; ?>
            </div>
            
            <div class="dropdown-divider"></div>
            
            <div class="d-flex justify-content-between mb-3">
                <strong>Total:</strong>
                <strong class="text-primary"><?= formatPrice($cart_total) ?></strong>
            </div>
            
            <div class="d-grid gap-2">
                <a href="cart.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-eye"></i> Voir le panier
                </a>
                <a href="checkout.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-check-circle"></i> Commander
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cart-dropdown-items::-webkit-scrollbar {
    width: 5px;
}
.cart-dropdown-items::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.cart-dropdown-items::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 5px;
}
</style>