<?php
// includes/add_to_cart.php

function renderAddToCartButton($product) {
    // Vérifier si l'utilisateur est connecté
    $isLoggedIn = isLoggedIn();
    ?>
    <div class="add-to-cart-form" data-product-id="<?= $product['id_produit'] ?>">
        <div class="input-group mb-2">
            <button class="btn btn-outline-secondary" type="button" onclick="decrementQty(this)">-</button>
            <input type="number" class="form-control text-center quantity-input" value="1" min="1" max="<?= $product['stock'] ?>" id="qty-<?= $product['id_produit'] ?>">
            <button class="btn btn-outline-secondary" type="button" onclick="incrementQty(this)">+</button>
        </div>
        
        <?php if ($product['stock'] > 0): ?>
            
            <?php if ($isLoggedIn): ?>
                <!-- UTILISATEUR CONNECTÉ : Bouton normal -->
                <button class="btn btn-primary w-100 add-to-cart-btn" onclick="addToCart(<?= $product['id_produit'] ?>, this)">
                    <i class="fas fa-cart-plus"></i> Ajouter au panier
                </button>
            <?php else: ?>
                <!-- UTILISATEUR NON CONNECTÉ : Bouton qui demande la connexion -->
                <button class="btn btn-primary w-100 add-to-cart-btn" onclick="requireLogin(<?= $product['id_produit'] ?>)" type="button">
                    <i class="fas fa-lock"></i> Connectez-vous pour acheter
                </button>
            <?php endif; ?>
            
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

    <!-- MODAL DE CONNEXION (à afficher quand l'utilisateur n'est pas connecté) -->
    <div class="modal fade" id="loginRequiredModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2d3d48, #005c46); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-lock"></i> Connexion requise
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-shopping-cart fa-4x mb-3" style="color: #005c46;"></i>
                    <h4>Vous devez être connecté</h4>
                    <p class="text-muted">Connectez-vous ou créez un compte pour ajouter des produits au panier</p>
                    
                    <div class="d-grid gap-3 mt-4">
                        <a href="connexion.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                           class="btn btn-primary btn-lg" 
                           style="background: linear-gradient(135deg, #005c46, #2d3d48); border: none;">
                            <i class="fas fa-sign-in-alt"></i> Se connecter
                        </a>
                        <a href="inscription.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                           class="btn btn-outline-primary btn-lg" 
                           style="color: #005c46; border-color: #005c46;">
                            <i class="fas fa-user-plus"></i> Créer un compte
                        </a>
                    </div>
                    
                    <div class="mt-3">
                        <a href="#" data-bs-dismiss="modal" class="text-muted">
                            <i class="fas fa-arrow-left"></i> Continuer mes achats
                        </a>
                    </div>
                </div>
            </div>
        </div>
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

    // Fonction pour demander la connexion
    function requireLogin(productId) {
        // Sauvegarder le produit que l'utilisateur voulait acheter
        let qty = document.getElementById('qty-' + productId).value;
        sessionStorage.setItem('pending_product', JSON.stringify({
            id: productId,
            quantity: qty
        }));
        
        // Afficher la modal de connexion
        var modal = new bootstrap.Modal(document.getElementById('loginRequiredModal'));
        modal.show();
    }

    // Fonction d'ajout au panier (pour utilisateurs connectés)
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
        .then(response => {
            if (response.status === 401) {
                // Non autorisé - rediriger vers connexion
                window.location.href = 'connexion.php?redirect=' + encodeURIComponent(window.location.href);
                throw new Error('Non connecté');
            }
            return response.json();
        })
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
            if (error.message !== 'Non connecté') {
                btn.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter au panier';
                btn.disabled = false;
                showNotification('Erreur de connexion', 'danger');
            }
        });
    }

    function showNotification(message, type) {
        // Vérifier si Bootstrap Toast est disponible
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            // Créer un toast Bootstrap
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.className = 'position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }
            
            let toastId = 'toast-' + Date.now();
            let toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            let toastElement = document.getElementById(toastId);
            let toast = new bootstrap.Toast(toastElement, { delay: 3000 });
            toast.show();
            
            setTimeout(() => {
                toastElement.remove();
            }, 3000);
        } else {
            // Fallback: notification simple
            let notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    }

    // Vérifier s'il y a un produit en attente après connexion
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($isLoggedIn): ?>
        // L'utilisateur vient de se connecter, vérifier s'il y a un produit en attente
        let pendingProduct = sessionStorage.getItem('pending_product');
        if (pendingProduct) {
            let product = JSON.parse(pendingProduct);
            // Ajouter automatiquement au panier
            let btn = document.querySelector(`[onclick*="addToCart(${product.id}"]`);
            if (btn) {
                addToCart(product.id, btn);
            }
            // Nettoyer
            sessionStorage.removeItem('pending_product');
        }
        <?php endif; ?>
    });

    // Fonction pour mettre à jour le compteur du panier
    function updateCartCount() {
        fetch('api/cart.php?action=get_summary')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let counters = document.querySelectorAll('.cart-count');
                    counters.forEach(counter => {
                        counter.textContent = data.items_count;
                        if (data.items_count > 0) {
                            counter.style.display = 'inline';
                        } else {
                            counter.style.display = 'none';
                        }
                    });
                }
            });
    }
    </script>

    <style>
    /* Styles pour la modal si Bootstrap n'est pas chargé */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1050;
    }
    
    .modal.show {
        display: block;
    }
    
    .modal-dialog {
        position: relative;
        width: auto;
        margin: 1.75rem auto;
        max-width: 500px;
    }
    
    .modal-content {
        position: relative;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 30px rgba(0,0,0,0.3);
    }
    
    .modal-header {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
        border-radius: 10px 10px 0 0;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .btn-close {
        background: transparent;
        border: 0;
        font-size: 1.5rem;
        cursor: pointer;
    }
    
    .btn-close-white {
        color: white;
    }
    </style>
    <?php
}
?>