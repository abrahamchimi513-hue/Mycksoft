<?php
// includes/add_to_cart.php
function renderAddToCartButton($product) {
    ?>
    <div class="add-to-cart-form" data-product-id="<?= $product['id_produit'] ?>">
        <div class="input-group mb-2">
            <button class="btn btn-outline-secondary" type="button" onclick="decrementQty(this)">-</button>
            <input type="number" class="form-control text-center quantity-input" value="1" min="1" max="<?= $product['stock'] ?>" id="qty-<?= $product['id_produit'] ?>">
            <button class="btn btn-outline-secondary" type="button" onclick="incrementQty(this)">+</button>
        </div>
        
        <?php if ($product['stock'] > 0): ?>
            <button class="btn btn-primary w-100 add-to-cart-btn" onclick="addToCart(<?= $product['id_produit'] ?>, this)">
                <i class="fas fa-cart-plus"></i> Ajouter au panier
            </button>
            
            <?php if ($product['stock'] < 10): ?>
                <small class="text-warning d-block mt-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    Plus que <?= $product['stock'] ?> en stock
                </small>
            <?php endif; ?>
        <?php else: ?>
            <button class="btn btn-secondary w-100" disabled>
                <i class="fas fa-times-circle"></i> Rupture de stock
            </button>
        <?php endif; ?>
    </div>

    <script>
    function incrementQty(btn) {
        let input = btn.parentElement.querySelector('.quantity-input');
        let max = parseInt(input.getAttribute('max'));
        let current = parseInt(input.value);
        if (current < max) {
            input.value = current + 1;
        }
    }

    function decrementQty(btn) {
        let input = btn.parentElement.querySelector('.quantity-input');
        let current = parseInt(input.value);
        if (current > 1) {
            input.value = current - 1;
        }
    }

    function addToCart(productId, btn) {
        let qty = document.getElementById('qty-' + productId).value;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
        
        fetch('api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=add&product_id=' + productId + '&quantity=' + qty
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Afficher notification
                showNotification('Produit ajouté au panier !', 'success');
                
                // Mettre à jour le compteur
                updateCartCount();
                if (typeof updateMiniCart === 'function') {
                    updateMiniCart();
                }
                
                // Animation bouton
                btn.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter au panier';
                    btn.disabled = false;
                }, 2000);
            } else {
                btn.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter au panier';
                btn.disabled = false;
                showNotification(data.message || 'Erreur', 'danger');
            }
        })
        .catch(error => {
            btn.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter au panier';
            btn.disabled = false;
            showNotification('Erreur de connexion', 'danger');
        });
    }

    function showNotification(message, type) {
        let notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        notification.style.zIndex = '9999';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    </script>
    <?php
}
?>