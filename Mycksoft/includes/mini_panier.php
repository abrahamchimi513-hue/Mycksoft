<?php
// includes/mini_panier.php
// Ce fichier est inclus dans header.php
?>
<div class="dropdown">
    <button class="btn btn-outline-light position-relative" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-shopping-cart"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="mini-cart-count">
            <?= isset($cart) ? $cart->getTotalItems() : 0 ?>
        </span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 350px;">
        <?php 
        if (isset($cart) && !$cart->isEmpty()): 
            $content = $cart->getContent();
            $totals = $cart->getTotals();
        ?>
            <h6 class="dropdown-header">Mon Panier (<?= $totals['items_count'] ?>)</h6>
            
            <div style="max-height: 300px; overflow-y: auto;">
                <?php 
                $displayed = 0;
                foreach ($content as $item): 
                    if ($displayed++ >= 3) break;
                ?>
                    <div class="d-flex align-items-center mb-2 p-2 border-bottom">
                        <img src="uploads/<?= htmlspecialchars($item['image'] ?? 'default.jpg') ?>" 
                             alt="<?= htmlspecialchars($item['name']) ?>" 
                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                        <div class="ms-2 flex-grow-1">
                            <div class="small fw-bold text-truncate" style="max-width: 180px;">
                                <?= htmlspecialchars($item['name']) ?>
                            </div>
                            <small class="text-muted">
                                <?= $item['quantity'] ?> x <?= number_format($item['price'], 0, ',', ' ') ?> FCFA
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($content) > 3): ?>
                    <p class="text-center text-muted small mt-2">
                        Et <?= count($content) - 3 ?> autre(s) article(s)
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="dropdown-divider"></div>
            
            <div class="px-2">
                <div class="d-flex justify-content-between mb-2">
                    <span>Sous-total:</span>
                    <span class="fw-bold"><?= $totals['subtotal_formatted'] ?></span>
                </div>
                
                <a href="panier.php" class="btn btn-primary btn-sm w-100 mb-2">
                    Voir le panier
                </a>
                <?php if (isLoggedIn()): ?>
                    <a href="commande.php" class="btn btn-success btn-sm w-100">
                        Commander
                    </a>
                <?php else: ?>
                    <a href="connexion.php?redirect=panier.php" class="btn btn-outline-success btn-sm w-100">
                        Se connecter pour commander
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-3">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-2"></i>
                <p class="text-muted mb-0">Votre panier est vide</p>
                <a href="index.php" class="btn btn-primary btn-sm mt-2">
                    Voir les produits
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>