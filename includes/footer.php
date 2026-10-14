<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Section 1: À propos -->
            <div class="footer-section">
                <div class="footer-logo">
                    <h3>Mycksoft</h3>
                </div>
                <p class="footer-description">
                    Votre partenaire de confiance pour tous vos équipements informatiques au Cameroun. 
                    Qualité, performance et service client exceptionnel.
                </p>
                <div class="social-links">
                    <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            
            <!-- Section 2: Catégories -->
            <div class="footer-section">
                <h3 class="footer-title">Catégories</h3>
                <ul class="footer-links">
                    <?php
                    try {
                        if (!isset($pdo)) {
                            global $pdo;
                        }
                        $categories = $pdo->query("SELECT id_categorie, nom_categorie FROM categorie ORDER BY nom_categorie LIMIT 6")->fetchAll();
                        foreach ($categories as $category): ?>
                            <li>
                                <a href="index.php?category=<?php echo $category['id_categorie']; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                    <?php echo htmlspecialchars($category['nom_categorie']); ?>
                                </a>
                            </li>
                        <?php endforeach; 
                    } catch (Exception $e) {
                        // Fallback si erreur
                        echo '<li><a href="index.php"><i class="fas fa-chevron-right"></i> Ordinateurs</a></li>';
                        echo '<li><a href="index.php"><i class="fas fa-chevron-right"></i> Périphériques</a></li>';
                        echo '<li><a href="index.php"><i class="fas fa-chevron-right"></i> Stockage</a></li>';
                    }
                    ?>
                </ul>
            </div>
            
            <!-- Section 3: Liens rapides -->
            <div class="footer-section">
                <h3 class="footer-title">Liens rapides</h3>
                <ul class="footer-links">
                    <li><a href="index.php"><i class="fas fa-chevron-right"></i> Accueil</a></li>
                    <li><a href="products.php"><i class="fas fa-chevron-right"></i> Tous les produits</a></li>
                    <!-- <li><a href="promotions.php"><i class="fas fa-chevron-right"></i> Promotions</a></li> -->
                    <li><a href="about.php"><i class="fas fa-chevron-right"></i> À propos</a></li>
                    <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact</a></li>
                    <!-- <li><a href="faq.php"><i class="fas fa-chevron-right"></i> FAQ</a></li> -->
                </ul>
            </div>
            
            <!-- Section 4: Contact et horaires -->
            <div class="footer-section">
                <h3 class="footer-title">Contact</h3>
                <ul class="footer-contact">
                    <li>
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-info">
                            <span class="contact-label">Adresse</span>
                            <span class="contact-value">Immeuble TECNO Akwa, Douala-Cameroun</span>
                        </div>
                    </li>
                    <li>
                        <div class="contact-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div class="contact-info">
                            <span class="contact-label">Téléphone</span>
                            <span class="contact-value">+237 697 176 943</span>
                        </div>
                    </li>
                    <li>
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-info">
                            <span class="contact-label">Email</span>
                            <span class="contact-value">moukam.yann@mycksoft.cm</span>
                        </div>
                    </li>
                    <li>
                        <div class="contact-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="contact-info">
                            <span class="contact-label">Horaires</span>
                            <span class="contact-value">Lun - Sam: 8h00 - 17h00</span>
                        </div>
                    </li>
                </ul>
                
                <!-- Méthodes de paiement -->
                <div class="payment-methods">
                    <span class="payment-label">Paiement accepté :</span>
                    <div class="payment-icons">
                        <i class="fas fa-mobile-alt" title="Mobile Money"></i>
                        <i class="fab fa-cc-visa" title="Visa"></i>
                        <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                        <i class="fas fa-money-bill-wave" title="Espèces"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Barre inférieure avec copyright -->
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <div class="copyright">
                    <i class="far fa-copyright"></i> <?php echo date('Y'); ?> <strong>Mycksoft</strong>. Tous droits réservés.
                </div>
                <div class="footer-bottom-links">
                    <a href="terms.php">CGV</a>
                    <span class="separator">|</span>
                    <a href="privacy.php">Confidentialité</a>
                    <span class="separator">|</span>
                    <a href="legal.php">Mentions légales</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
/* Footer Styles - Harmonisé avec l'index */
.footer {
    background: linear-gradient(135deg, #1a2634 0%, #2d3d48 100%);
    color: #fff;
    padding: 60px 0 0;
    margin-top: 60px;
    position: relative;
}

.footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--yellow-cm, #fcd116), var(--red-cm, #ce1126));
}

.footer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
    margin-bottom: 40px;
}

.footer-section {
    display: flex;
    flex-direction: column;
}

.footer-logo {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    margin-bottom: 20px;
}

.footer-logo h3 {
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    margin: 10px 0 0;
    background: linear-gradient(135deg, #fff, var(--yellow-cm, #fcd116));
    -webkit-text-fill-color: transparent;
}

.footer-description {
    color: #cbd5e1;
    line-height: 1.6;
    margin-bottom: 25px;
    font-size: 14px;
}

.footer-title {
    color: #fff;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 25px;
    position: relative;
    padding-bottom: 12px;
}

.footer-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background: linear-gradient(90deg, var(--yellow-cm, #fcd116), var(--red-cm, #ce1126));
    border-radius: 2px;
}

/* Liens du footer */
.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 12px;
}

.footer-links a {
    color: #cbd5e1;
    text-decoration: none;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.footer-links a i {
    font-size: 12px;
    color: var(--yellow-cm, #fcd116);
    transition: transform 0.3s ease;
}

.footer-links a:hover {
    color: #fff;
    transform: translateX(5px);
}

.footer-links a:hover i {
    transform: translateX(3px);
    color: var(--yellow-cm, #fcd116);
}

/* Contact */
.footer-contact {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-contact li {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.contact-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--yellow-cm, #fcd116);
    font-size: 16px;
    transition: all 0.3s ease;
}

.footer-contact li:hover .contact-icon {
    background: var(--yellow-cm, #fcd116);
    color: #1a2634;
    transform: translateY(-3px);
}

.contact-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.contact-label {
    font-size: 12px;
    color: #94a3b8;
    margin-bottom: 4px;
}

.contact-value {
    font-size: 14px;
    color: #fff;
    font-weight: 500;
}

/* Réseaux sociaux */
.social-links {
    display: flex;
    gap: 12px;
    margin-top: 10px;
}

.social-link {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 16px;
}

.social-link:hover {
    transform: translateY(-3px);
}

.social-link[title="Facebook"]:hover {
    background: #1877f2;
}

.social-link[title="Twitter"]:hover {
    background: #1da1f2;
}

.social-link[title="Instagram"]:hover {
    background: linear-gradient(45deg, #f09433, #d62976, #962fbf, #4f5bd5);
}

.social-link[title="WhatsApp"]:hover {
    background: #25d366;
}

/* Méthodes de paiement */
.payment-methods {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.payment-label {
    display: block;
    color: #94a3b8;
    font-size: 13px;
    margin-bottom: 12px;
}

.payment-icons {
    display: flex;
    gap: 15px;
    font-size: 24px;
    color: #cbd5e1;
}

.payment-icons i {
    transition: all 0.3s ease;
}

.payment-icons i:hover {
    color: var(--yellow-cm, #fcd116);
    transform: scale(1.1);
}

/* Footer bottom */
.footer-bottom {
    background: rgba(0, 0, 0, 0.2);
    padding: 25px 0;
    margin-top: 20px;
}

.footer-bottom-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.copyright {
    color: #94a3b8;
    font-size: 14px;
}

.copyright strong {
    color: #fff;
}

.footer-bottom-links {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.footer-bottom-links a {
    color: #94a3b8;
    text-decoration: none;
    font-size: 13px;
    transition: color 0.3s ease;
}

.footer-bottom-links a:hover {
    color: #fff;
}

.separator {
    color: #4a5568;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
    .footer {
        padding: 40px 0 0;
    }
    
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .footer-bottom-content {
        flex-direction: column;
        text-align: center;
    }
    
    .footer-bottom-links {
        justify-content: center;
    }
    
    .footer-logo {
        align-items: center;
        text-align: center;
    }
    
    .footer-title::after {
        left: 50%;
        transform: translateX(-50%);
    }
    
    .footer-title {
        text-align: center;
    }
    
    .footer-links a {
        justify-content: center;
    }
    
    .footer-contact li {
        justify-content: center;
    }
    
    .social-links {
        justify-content: center;
    }
    
    .payment-icons {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .footer-bottom-links {
        flex-direction: column;
        gap: 10px;
    }
    
    .separator {
        display: none;
    }
    
    .footer-contact li {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .contact-info {
        align-items: center;
    }
}
</style>

<script>
// Gestion du formulaire d'ajout au panier avec AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Intercepter les formulaires d'ajout au panier
    document.querySelectorAll('form[method="POST"] button[name="add_to_cart"]').forEach(button => {
        const form = button.closest('form');
        if (form) {
            form.classList.add('add-to-cart-form');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                // Animation de chargement
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                submitBtn.disabled = true;
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        // Notification de succès
                        showNotification('Produit ajouté au panier !', 'success');
                        // Recharger le compteur du panier
                        setTimeout(() => window.location.reload(), 1000);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showNotification('Erreur lors de l\'ajout au panier', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        }
    });
    
    // Gestion de la recherche en temps réel
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
    
    // Gestion du menu mobile
    const navToggle = document.querySelector('.nav-toggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', function() {
            if (navLinks.style.display === 'flex') {
                navLinks.style.display = 'none';
            } else {
                navLinks.style.display = 'flex';
                navLinks.style.flexDirection = 'column';
                navLinks.style.position = 'absolute';
                navLinks.style.top = '100%';
                navLinks.style.left = '0';
                navLinks.style.right = '0';
                navLinks.style.background = 'white';
                navLinks.style.padding = '20px';
                navLinks.style.boxShadow = '0 10px 15px rgba(0,0,0,0.1)';
            }
        });
    }
});

// Fonction pour afficher des notifications
function showNotification(message, type = 'info') {
    // Supprimer les anciennes notifications
    const oldNotifications = document.querySelectorAll('.custom-notification');
    oldNotifications.forEach(notif => notif.remove());
    
    // Créer la notification
    const notification = document.createElement('div');
    notification.className = `custom-notification ${type}`;
    
    // Définir les styles
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 12px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        animation: slideIn 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        max-width: 350px;
        display: flex;
        align-items: center;
        gap: 12px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    `;
    
    // Couleurs selon le type
    if (type === 'success') {
        notification.style.background = 'linear-gradient(135deg, #10b981, #059669)';
    } else if (type === 'error') {
        notification.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
    } else {
        notification.style.background = 'linear-gradient(135deg, #3b82f6, #2563eb)';
    }
    
    // Icône selon le type
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px; width: 100%;">
            <i class="fas ${icon}" style="font-size: 20px;"></i>
            <span style="flex: 1; font-size: 14px;">${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="background: none; border: none; color: white; opacity: 0.7; cursor: pointer; padding: 4px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Supprimer après 4 secondes
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Mettre à jour le compteur du panier
function updateCartCount(count) {
    const cartCount = document.getElementById('cartCount');
    if (cartCount) {
        cartCount.textContent = count;
        cartCount.style.animation = 'none';
        setTimeout(() => {
            cartCount.style.animation = 'pulse 0.5s ease';
        }, 10);
    }
}

// Animation smooth scroll pour les ancres
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#') {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    });
});
</script>
</body>
</html>