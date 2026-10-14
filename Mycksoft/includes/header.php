<?php
// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// S'assurer que les fonctions nécessaires sont disponibles
if (!function_exists('isLoggedIn')) {
    require_once 'includes/config.php';
}

// Inclure les fonctions de chat si nécessaire
if (!function_exists('getUnreadMessagesCount') && file_exists('includes/chat_functions.php')) {
    require_once 'includes/chat_functions.php';
}

// Récupérer les catégories pour le menu (si non définies dans la page)
if (!isset($header_categories)) {
    global $pdo;
    $header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie LIMIT 5")->fetchAll();
}

// Déterminer si on affiche le lien "Tous les produits"
$show_products_link = $header_show_products_link ?? true;

// Récupérer le nombre de messages non lus pour le badge
$unread_count = 0;
if (isLoggedIn() && function_exists('getUnreadMessagesCount')) {
    $unread_count = getUnreadMessagesCount();
}

// Récupérer le nombre de notifications non lues
$notifications_count = 0;
if (isLoggedIn()) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_utilisateur = ? AND lu = 0");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $notifications_count = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Ignorer les erreurs de base de données
        $notifications_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    /* Variables couleurs Cameroun */
    :root {
        --green-cm: #2d3d48;
        --red-cm: #ce1126;
        --yellow-cm: #fcd116;
        --green-dark: #005c46;
        --green-light: #e8f5f2;
        --primary: var(--green-cm);
        --primary-dark: var(--green-dark);
        --secondary: #64748b;
        --accent: var(--yellow-cm);
        --success: #10b981;
        --danger: var(--red-cm);
        --warning: #f59e0b;
        --light: #f8fafc;
        --dark: #1e293b;
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --radius: 8px;
        --radius-lg: 12px;
        --transition: all 0.3s ease;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        color: var(--dark);
        background-color: var(--light);
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    /* Bannière promotionnelle */
    .promo-banner {
        background: linear-gradient(135deg, rgb(0 122 181), rgb(255 255 255));
        color: black;
        padding: 0.5rem 0;
        text-align: center;
        font-size: 0.9rem;
        font-weight: 500;
    }

    /* Header */
    .header {
        background: white;
        box-shadow: var(--shadow);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 0;
    }

    .nav-brand .logo {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nav-search {
        flex: 1;
        max-width: 500px;
        margin: 0 2rem;
    }

    .search-form {
        display: flex;
        background: var(--light);
        border: 2px solid var(--green-light);
        border-radius: var(--radius);
        overflow: hidden;
    }

    .search-form input {
        flex: 1;
        padding: 0.75rem 1rem;
        border: none;
        background: transparent;
        font-size: 0.9rem;
    }

    .search-form button {
        padding: 0.75rem 1.25rem;
        background: var(--primary);
        color: white;
        border: none;
        cursor: pointer;
    }

    .nav-contact {
        margin-right: 2rem;
    }

    .contact-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--dark);
    }

    .contact-info i {
        color: var(--primary);
        font-size: 1.25rem;
    }

    .contact-label {
        display: block;
        font-size: 0.8rem;
        color: var(--secondary);
    }

    .contact-phone {
        display: block;
        font-weight: 600;
        color: var(--primary);
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .nav-link {
        color: var(--dark);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        transition: var(--transition);
        position: relative;
    }

    .nav-link:hover {
        color: var(--primary);
    }

    /* Style pour le menu déroulant des catégories */
    .nav-dropdown {
        position: relative;
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        left: 0;
        background: white;
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        padding: 0.5rem 0;
        min-width: 220px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: var(--transition);
        z-index: 1001;
    }

    .nav-dropdown:hover .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1.5rem;
        color: var(--dark);
        text-decoration: none;
        transition: var(--transition);
        font-size: 0.95rem;
    }

    .dropdown-menu a:hover {
        background: var(--light);
        color: var(--primary);
    }

    .dropdown-menu a i {
        width: 20px;
        color: var(--primary);
    }

    .dropdown-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 0.5rem 0;
    }

    .cart-link {
        position: relative;
    }

    .cart-count {
        background: var(--red-cm);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        position: absolute;
        top: -8px;
        right: -8px;
        animation: pulse 0.5s ease;
    }

    /* Badge pour les notifications du chat */
    .chat-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: var(--red-cm);
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        animation: pulse 0.5s ease;
    }

    /* Badge pour les notifications système */
    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #f39c12;
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        animation: pulse 0.5s ease;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }

    .user-dropdown {
        position: relative;
    }

    .user-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        padding: 0.5rem 0;
        min-width: 200px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: var(--transition);
        z-index: 1001;
    }

    .user-dropdown:hover .user-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .user-menu a {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1.5rem;
        color: var(--dark);
        text-decoration: none;
        transition: var(--transition);
    }

    .user-menu a:hover {
        background: var(--light);
        color: var(--primary);
    }

    .nav-toggle {
        display: none;
        font-size: 1.25rem;
        cursor: pointer;
    }

    /* Boutons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: var(--radius);
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: var(--transition);
        font-size: 0.9rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--green-cm), var(--green-dark));
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    /* Messages flash */
    .flash-message {
        position: fixed;
        top: 80px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9998;
        min-width: 300px;
        text-align: center;
        animation: slideDown 0.3s ease;
        padding: 1rem 2rem;
        border-radius: var(--radius);
        font-weight: 500;
    }

    @keyframes slideDown {
        from {
            transform: translateX(-50%) translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
    }

    .flash-message.success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .flash-message.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .flash-message.info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .container {
            padding: 0 1rem;
        }
        
        .nav {
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .nav-search {
            order: 3;
            flex: 1 0 100%;
            margin: 0;
        }
        
        .nav-contact {
            display: none;
        }
        
        .nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            flex-direction: column;
            padding: 1rem;
            box-shadow: var(--shadow-lg);
            gap: 1rem;
        }
        
        .nav-links.active {
            display: flex;
        }
        
        .nav-toggle {
            display: block;
        }
        
        .flash-message {
            min-width: 90%;
            font-size: 0.9rem;
        }
        
        .dropdown-menu {
            position: static;
            opacity: 1;
            visibility: visible;
            transform: none;
            box-shadow: none;
            padding: 0.5rem 0 0.5rem 1rem;
            display: none;
        }
        
        .nav-dropdown:hover .dropdown-menu {
            display: block;
        }
    }
    </style>
</head>
<body>
    <!-- Bannière promotionnelle -->
    <div class="promo-banner">
        <div class="container">
            <span>Livraison gratuite à Douala et Yaoundé pour les commandes supérieures à 50 000 FCFA</span>
        </div>
    </div>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="nav">
                <div class="nav-brand">
                    <a href="index.php" class="logo">
                        <img src="assets/images/icones.jpg" alt="Mycksoft" style="height: 50px; border-radius: 100%;">
                        <span></span>
                    </a>
                </div>

                <div class="nav-search">
                    <form action="search.php" method="GET" class="search-form">
                        <input type="text" name="q" placeholder="Rechercher des produits..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>

                <!-- Liens de navigation -->
                <div class="nav-links">
                    <!-- Accueil -->
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                    
                    <!-- Menu déroulant CATÉGORIES -->
                    <div class="nav-dropdown">
                        <a href="#" class="nav-link">
                            <i class="fas fa-list"></i> Catégories
                            <i class="fas fa-chevron-down" style="font-size: 12px; margin-left: 5px;"></i>
                        </a>
                        <div class="dropdown-menu">
                            <?php foreach ($header_categories as $cat): ?>
                                <a href="index.php?category=<?php echo $cat['id_categorie']; ?>">
                                    <i class="fas fa-folder"></i>
                                    <?php echo htmlspecialchars($cat['nom_categorie']); ?>
                                </a>
                            <?php endforeach; ?>
                            
                            <?php if (count($header_categories) >= 5): ?>
                                <div class="dropdown-divider"></div>
                                <a href="categories.php">
                                    <i class="fas fa-th-large"></i>
                                    Toutes les catégories
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Lien TOUS LES PRODUITS -->
                    <?php if ($show_products_link): ?>
                    <a href="index.php#products" class="nav-link">
                        <i class="fas fa-box"></i> Tous les produits
                    </a>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn()): ?>
                        
                        </a>
                        
                        <!-- CHAT - Intégré dans les nav-links -->
                        <a href="chat/" class="nav-link" style="position: relative;">
                            <i class="fas fa-comments"></i> Chat
                            <?php if ($unread_count > 0): ?>
                                <span class="chat-badge"><?php echo min($unread_count, 9); ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <!-- Panier -->
                        <a href="cart.php" class="nav-link cart-link">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count" id="cartCount">
                                <?php 
                                if (function_exists('getCartCount')) {
                                    echo getCartCount(getUserId()); 
                                } else {
                                    echo 0;
                                }
                                ?>
                            </span>
                        </a>
                        
                        <!-- Menu utilisateur -->
                        <div class="user-dropdown">
                            <a href="#" class="nav-link">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($_SESSION['prenom'] ?? 'Mon Compte'); ?>
                                <i class="fas fa-chevron-down" style="font-size: 12px; margin-left: 5px;"></i>
                            </a>
                            <div class="user-menu">
                                <a href="pages/profile.php"><i class="fas fa-user-circle"></i> Mon Profil</a>
                                <a href="orders.php"><i class="fas fa-shopping-bag"></i> Mes Commandes</a>
                                <a href="notifications.php"><i class="fas fa-bell"></i> Mes Notifications</a>
                                <?php if (function_exists('isAdmin') && isAdmin()): ?>                     
                                    <div class="dropdown-divider"></div>
                                    <a href="admin/"><i class="fas fa-cog"></i> Tableau de bord</a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Non connecté -->
                        <a href="login.php" class="nav-link">
                            <i class="fas fa-sign-in-alt"></i> Connexion
                        </a>
                        <a href="register.php" class="btn btn-primary">
                            Inscription
                        </a>
                    <?php endif; ?>
                </div>

                <div class="nav-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </nav>
        </div>
    </header>
    
    <!-- Messages flash -->
    <?php 
    if (function_exists('displayFlashMessage')) {
        displayFlashMessage(); 
    }
    ?>
</body>
</html>

<!-- Script pour le menu mobile et notifications chat -->
<script>
// Menu mobile toggle
document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.querySelector('.nav-toggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (navToggle) {
        navToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }
    
    // Fermer le menu mobile quand on clique sur un lien
    const mobileLinks = document.querySelectorAll('.nav-links a');
    mobileLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                navLinks.classList.remove('active');
            }
        });
    });
    
    // Demander la permission pour les notifications
    if (window.Notification && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    
    // Initialiser les notifications du chat
    <?php if (isLoggedIn()): ?>
    initChatNotifications();
    <?php endif; ?>
});

// Système de notifications du chat en temps réel
function initChatNotifications() {
    // Vérifier les nouvelles notifications toutes les 10 secondes
    setInterval(checkChatNotifications, 10000);
    
    // Vérifier immédiatement au chargement
    checkChatNotifications();
}

function checkChatNotifications() {
    fetch('api/chat_api.php?action=check_notifications&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            // Mettre à jour le badge du chat
            const chatLink = document.querySelector('.nav-link[href*="chat/"]');
            if (chatLink) {
                let badge = chatLink.querySelector('.chat-badge');
                
                if (data.unreadCount > 0) {
                    if (badge) {
                        badge.textContent = Math.min(data.unreadCount, 9);
                    } else {
                        badge = document.createElement('span');
                        badge.className = 'chat-badge';
                        badge.textContent = Math.min(data.unreadCount, 9);
                        chatLink.appendChild(badge);
                    }
                } else {
                    if (badge) {
                        badge.remove();
                    }
                }
            }
            
            // Afficher une notification pour les nouveaux messages
            if (data.hasNewMessages) {
                // Notification sonore
                try {
                    const audio = new Audio('assets/sounds/notification.mp3');
                    audio.volume = 0.5;
                    audio.play().catch(e => console.log('Audio auto-play bloqué'));
                } catch (e) {
                    console.log('Erreur audio:', e);
                }
                
                // Notification desktop
                if (window.Notification && Notification.permission === 'granted') {
                    new Notification('Nouveau message', {
                        body: data.message || 'Vous avez un nouveau message',
                        icon: 'assets/images/logo.png',
                        badge: 'assets/images/logo-small.png',
                        tag: 'chat-notification'
                    });
                }
                
                // Faire vibrer le badge si existant
                const badge = document.querySelector('.chat-badge');
                if (badge) {
                    badge.style.animation = 'none';
                    badge.offsetHeight; // Force reflow
                    badge.style.animation = 'pulse 0.5s ease 3';
                }
            }
        })
        .catch(error => console.error('Erreur notifications chat:', error));
}

// Fonction pour mettre à jour le compteur de notifications
function updateNotificationCount() {
    fetch('api/notifications_api.php?action=count')
        .then(response => response.json())
        .then(data => {
            const notificationLink = document.querySelector('.nav-link[href*="notifications.php"]');
            if (notificationLink) {
                let badge = notificationLink.querySelector('.notification-badge');
                
                if (data.count > 0) {
                    if (badge) {
                        badge.textContent = Math.min(data.count, 9);
                    } else {
                        badge = document.createElement('span');
                        badge.className = 'notification-badge';
                        badge.textContent = Math.min(data.count, 9);
                        notificationLink.appendChild(badge);
                    }
                } else {
                    if (badge) {
                        badge.remove();
                    }
                }
            }
        })
        .catch(error => console.error('Erreur mise à jour notifications:', error));
}

// Mettre à jour les notifications toutes les 30 secondes
<?php if (isLoggedIn()): ?>
setInterval(updateNotificationCount, 30000);
<?php endif; ?>
</script>