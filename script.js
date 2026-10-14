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
    fetch('includes/favorites.php', {
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
// chat-notifications.js
class ChatNotifier {
    constructor() {
        this.userId = null;
        this.isAdmin = false;
        this.pollInterval = null;
        this.init();
    }
    
    init() {
        // Récupérer les informations utilisateur
        this.userId = document.body.dataset.userId;
        this.isAdmin = document.body.dataset.userRole === 'admin';
        
        if(this.userId) {
            this.startPolling();
            this.setupEventListeners();
        }
    }
    
    startPolling() {
        // Mettre à jour toutes les 20 secondes
        this.pollInterval = setInterval(() => {
            this.updateUnreadCount();
        }, 20000);
        
        // Première mise à jour
        setTimeout(() => this.updateUnreadCount(), 1000);
    }
    
    updateUnreadCount() {
        fetch(`chat_api.php?action=get_unread_count`)
            .then(response => response.json())
            .then(data => {
                this.updateBadges(data.unread_count);
                this.showDesktopNotification(data.unread_count);
            })
            .catch(error => console.error('Erreur:', error));
    }
    
    updateBadges(count) {
        // Mettre à jour tous les badges
        const badges = document.querySelectorAll('[data-chat-badge]');
        badges.forEach(badge => {
            if(count > 0) {
                badge.textContent = count > 9 ? '9+' : count;
                badge.style.display = 'inline-block';
                badge.classList.add('pulse');
            } else {
                badge.style.display = 'none';
                badge.classList.remove('pulse');
            }
        });
        
        // Mettre à jour le title de la page
        if(count > 0) {
            document.title = `(${count}) Mycksoft`;
        } else {
            document.title = 'Mycksoft';
        }
    }
    
    showDesktopNotification(count) {
        // Vérifier si les notifications sont autorisées
        if(count > 0 && this.isAdmin && 'Notification' in window) {
            if(Notification.permission === 'granted') {
                this.createNotification(count);
            } else if(Notification.permission !== 'denied') {
                Notification.requestPermission().then(permission => {
                    if(permission === 'granted') {
                        this.createNotification(count);
                    }
                });
            }
        }
    }
    
    createNotification(count) {
        // Vérifier si l'utilisateur est sur la page chat
        if(window.location.pathname.includes('chat.php')) return;
        
        new Notification('Mycksoft - Nouveaux messages', {
            body: `${count} nouveau(x) message(s) non lu(s)`,
            icon: '/logo.png',
            tag: 'chat-notification'
        });
    }
    
    setupEventListeners() {
        // Marquer comme lus en cliquant sur le lien
        const chatLinks = document.querySelectorAll('a[href*="chat.php"]');
        chatLinks.forEach(link => {
            link.addEventListener('click', () => {
                if(this.isAdmin && currentConversationId) {
                    fetch(`chat_api.php?action=mark_read&conversation_id=${currentConversationId}`)
                        .catch(error => console.error('Erreur:', error));
                }
            });
        });
    }
    
    stopPolling() {
        if(this.pollInterval) {
            clearInterval(this.pollInterval);
        }
    }
}

// Initialiser quand le DOM est chargé
document.addEventListener('DOMContentLoaded', () => {
    window.chatNotifier = new ChatNotifier();
});
// ========== FONCTIONS DE GESTION DES NOTIFICATIONS ==========

/**
 * Marquer une notification comme lue
 */
function markAsRead(notificationId) {
    showLoader();
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../ajax/mark_notification_read.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 400) {
            // Recharger la section notifications
            loadSection('notifications');
            showNotification('Notification marquée comme lue', 'success');
            
            // Mettre à jour le badge dans la sidebar
            updateNotificationBadges();
        } else {
            showNotification('Erreur lors du marquage', 'error');
            hideLoader();
        }
    };
    
    xhr.onerror = function() {
        showNotification('Erreur lors du marquage', 'error');
        hideLoader();
    };
    
    xhr.send('notification_id=' + notificationId);
}

/**
 * Marquer toutes les notifications comme lues
 */
function markAllAsRead() {
    if (!confirm('Marquer toutes les notifications comme lues ?')) {
        return;
    }
    
    showLoader();
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../ajax/mark_all_notifications_read.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 400) {
            loadSection('notifications');
            showNotification('Toutes les notifications ont été marquées comme lues', 'success');
            
            // Mettre à jour le badge dans la sidebar
            updateNotificationBadges();
        } else {
            showNotification('Erreur lors de l\'opération', 'error');
            hideLoader();
        }
    };
    
    xhr.onerror = function() {
        showNotification('Erreur lors de l\'opération', 'error');
        hideLoader();
    };
    
    xhr.send();
}

/**
 * Supprimer une notification
 */
function deleteNotification(notificationId) {
    if (!confirm('Supprimer cette notification ?')) {
        return;
    }
    
    showLoader();
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../ajax/delete_notification.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 400) {
            loadSection('notifications');
            showNotification('Notification supprimée', 'success');
            
            // Mettre à jour le badge dans la sidebar
            updateNotificationBadges();
        } else {
            showNotification('Erreur lors de la suppression', 'error');
            hideLoader();
        }
    };
    
    xhr.onerror = function() {
        showNotification('Erreur lors de la suppression', 'error');
        hideLoader();
    };
    
    xhr.send('notification_id=' + notificationId);
}

/**
 * Filtrer les notifications
 */
function filterNotifications(filter) {
    var items = document.querySelectorAll('.notification-item');
    var today = new Date().toISOString().split('T')[0];
    var oneWeekAgo = new Date();
    oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
    var weekStart = oneWeekAgo.toISOString().split('T')[0];
    
    // Mettre à jour les boutons actifs
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    items.forEach(item => {
        var status = item.dataset.status;
        var date = item.dataset.date;
        var show = true;
        
        switch(filter) {
            case 'unread':
                show = status === 'unread';
                break;
            case 'today':
                show = date === today;
                break;
            case 'week':
                show = date >= weekStart;
                break;
            default:
                show = true;
        }
        
        item.style.display = show ? 'flex' : 'none';
    });
}

/**
 * Rafraîchir les notifications
 */
function refreshNotifications() {
    loadSection('notifications');
}

/**
 * Mettre à jour les badges de notification dans toute l'interface
 */
function updateNotificationBadges() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '../ajax/get_unread_count.php', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 400) {
            var response = JSON.parse(xhr.responseText);
            var count = response.unread_count || 0;
            
            // Mettre à jour le badge dans la sidebar
            var sidebarBadges = document.querySelectorAll('.nav-item .badge');
            sidebarBadges.forEach(badge => {
                if (count > 0) {
                    badge.textContent = count > 9 ? '9+' : count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            });
            
            // Mettre à jour le badge dans le header
            var headerBadges = document.querySelectorAll('.header .badge');
            headerBadges.forEach(badge => {
                if (count > 0) {
                    badge.textContent = count > 9 ? '9+' : count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            });
        }
    };
    
    xhr.send();
}

// Vérifier les nouvelles notifications toutes les 30 secondes
setInterval(function() {
    if (currentSection !== 'notifications') {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '../ajax/check_new_notifications.php', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                var response = JSON.parse(xhr.responseText);
                if (response.has_new) {
                    // Mettre à jour les badges
                    updateNotificationBadges();
                    
                    // Afficher une notification toast
                    if (response.count > 0) {
                        showNotification(response.count + ' nouvelle(s) notification(s)', 'info');
                    }
                }
            }
        };
        
        xhr.send();
    }
}, 30000);
// Version fetch pour updateQuantity
async function updateQuantity(lineId, newQuantity) {
    if (newQuantity < 1) return;
    
    const $row = $(`tr[data-line-id="${lineId}"]`);
    const maxStock = $row.find('.quantity-input').data('stock');
    
    if (newQuantity > maxStock) {
        showNotification(`Stock maximum: ${maxStock}`, 'warning');
        return;
    }
    
    showLoading(true);
    
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'update');
        formData.append('line_id', lineId);
        formData.append('quantity', newQuantity);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateCartDisplay(lineId, newQuantity);
            showNotification(data.message, 'success');
            updateCartCount();
        } else {
            showNotification(data.message, 'danger');
            const currentQty = $row.find('.quantity-input').data('old-value') || $row.find('.quantity-input').val();
            $row.find('.quantity-input').val(currentQty);
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur de connexion au serveur', 'danger');
    } finally {
        showLoading(false);
    }
}
