// Gestion des favoris et panier
document.addEventListener('DOMContentLoaded', function() {
    // Favoris
    document.querySelectorAll('.favorite-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const isActive = this.classList.contains('active');

            toggleFavorite(productId, !isActive, this);
        });
    });

    // Ajout au panier
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            addToCart(productId);
        });
    });
});

function toggleFavorite(productId, add, element) {
    // Dans votre structure, les favoris sont gérés via la table 'donner' et 'avis'
    // Nous allons simuler cette fonctionnalité
    fetch('favorites.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=${add ? 'add' : 'remove'}&product_id=${productId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (add) {
                    element.classList.add('active');
                    showNotification('Produit ajouté aux favoris', 'success');
                } else {
                    element.classList.remove('active');
                    showNotification('Produit retiré des favoris', 'info');
                }
            } else {
                showNotification(data.message || 'Erreur', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('probleme de connexion', 'error');
        });
}

function addToCart(productId, quantity = 1) {
    fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add&product_id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartCount(data.cart_count);
                showNotification('Produit ajouté au panier', 'success');
            } else {
                showNotification(data.message || 'Erreur', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('probleme de connexion', 'error');
        });
}

function updateCartCount(count) {
    const cartCount = document.getElementById('cartCount');
    if (cartCount) {
        cartCount.textContent = count;
    }
}

function showNotification(message, type = 'info') {
    // Créer une notification toast
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Animation d'entrée
    setTimeout(() => notification.style.transform = 'translateX(0)', 100);

    // Supprimer après 3 secondes
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Recherche en temps réel
const searchInput = document.querySelector('.search-form input');
if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (this.value.length >= 3 || this.value.length === 0) {
                this.form.submit();
            }
        }, 500);
    });
}