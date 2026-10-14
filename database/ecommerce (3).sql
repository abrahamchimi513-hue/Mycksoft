<?php
// dashboard_admin.php
require_once '../includes/config.php';
require_once '../classes/Notification.php';

// Vérifier si l'utilisateur est connecté ET administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Initialiser la classe Notification
$notification = new Notification($pdo);
$unread_notifications_count = $notification->countUnread(getUserId());
$recent_notifications = $notification->getUnreadNotifications(getUserId());

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Détection AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Déterminer quelle section afficher
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
$page_title = "Dashboard Admin - Mycksoft";
$message = '';
$message_type = '';

// Définir les icônes pour le header
$icons = [
    'dashboard' => 'fa-tachometer-alt',
    'produits' => 'fa-box',
    'commandes' => 'fa-shopping-cart',
    'clients' => 'fa-users',
    'categories' => 'fa-tags',
    'messages' => 'fa-envelope',
    'avis' => 'fa-star',
    'notifications' => 'fa-bell',
    'parametres' => 'fa-cog'
];

// ============================================
// TRAITEMENT ENVOI NOTIFICATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_notification') {
    $titre = trim($_POST['titre'] ?? '');
    $message_texte = trim($_POST['message'] ?? '');
    $type = $_POST['type'] ?? 'info';
    $lien = trim($_POST['lien'] ?? '');
    $destinataire = $_POST['destinataire'] ?? '';
    $client_id = $_POST['client_id'] ?? '';
    
    $success = false;
    $result_message = '';
    $result_type = 'error';
    
    if (empty($titre) || empty($message_texte)) {
        $result_message = "Le titre et le message sont obligatoires";
    } else {
        switch ($destinataire) {
            case 'all':
                $success = $notification->createForAllClients($titre, $message_texte, $type, $lien);
                $result_message = "Notification envoyée à tous les clients";
                $result_type = "success";
                break;
                
            case 'single':
                if (!empty($client_id)) {
                    $success = $notification->create($client_id, $titre, $message_texte, $type, $lien);
                    $stmt = $pdo->prepare("SELECT nom, prenom FROM utilisateur WHERE id_utilisateur = ?");
                    $stmt->execute([$client_id]);
                    $client = $stmt->fetch();
                    $result_message = "Notification envoyée à " . htmlspecialchars($client['prenom'] . ' ' . $client['nom']);
                    $result_type = "success";
                } else {
                    $result_message = "Veuillez sélectionner un client";
                }
                break;
                
            case 'multiple':
                if (!empty($_POST['client_ids']) && is_array($_POST['client_ids'])) {
                    $success = $notification->createForMultipleUsers($_POST['client_ids'], $titre, $message_texte, $type, $lien);
                    $result_message = "Notification envoyée à " . count($_POST['client_ids']) . " clients";
                    $result_type = "success";
                } else {
                    $result_message = "Veuillez sélectionner au moins un client";
                }
                break;
                
            default:
                $result_message = "Type de destinataire invalide";
        }
    }
    
    if ($success) {
        $message = $result_message;
        $message_type = $result_type;
    } else if (empty($result_message)) {
        $message = "Erreur lors de l'envoi de la notification";
        $message_type = "error";
    } else {
        $message = $result_message;
        $message_type = $result_type;
    }
}

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================
try {
    $stats = null;
    $produits_recent = array();
    $commandes_recent = array();
    $avis_attente = array();
    $messages_recent = array();
    $produits = array();
    $commandes = array();
    $clients = array();
    $categories = array();
    $avis = array();
    $messages = array();
    $all_categories = array();

    // Récupération des catégories
    $all_categories_stmt = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie");
    $all_categories = $all_categories_stmt->fetchAll();

    // Statistiques générales
    $stats_sql = "
        SELECT 
            (SELECT COUNT(*) FROM produit WHERE est_actif = 1) as total_produits,
            (SELECT COUNT(*) FROM utilisateur WHERE role = 'client') as total_clients,
            (SELECT COUNT(*) FROM commande) as total_commandes,
            COALESCE((SELECT SUM(montant_total) FROM commande WHERE statut IN ('livree', 'expediee')), 0) as chiffre_affaires,
            (SELECT COUNT(*) FROM commande WHERE DATE(date_commande) = CURDATE()) as commandes_aujourdhui,
            (SELECT COUNT(*) FROM avis WHERE est_approuve = 0) as avis_en_attente,
            (SELECT COUNT(*) FROM commande WHERE statut = 'en attente') as commandes_en_attente
    ";
    $stats = $pdo->query($stats_sql)->fetch();

    switch($section) {
        case 'produits':
            $produits = $pdo->query("
                SELECT p.*, c.nom_categorie 
                FROM produit p 
                LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
                WHERE p.est_actif = 1 
                ORDER BY p.date_ajout DESC 
                LIMIT 50
            ")->fetchAll();
            $page_title = "Gestion des produits - Mycksoft";
            break;
            
        case 'commandes':
            $commandes = $pdo->query("
                SELECT c.*, u.nom, u.prenom, 
                       (SELECT COUNT(*) FROM ligne_commande lc WHERE lc.id_commande = c.id_commande) as nb_produits
                FROM commande c
                JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
                ORDER BY c.date_commande DESC
                LIMIT 50
            ")->fetchAll();
            $page_title = "Gestion des commandes - Mycksoft";
            break;
            
        case 'clients':
            $clients = $pdo->query("
                SELECT *, 
                       (SELECT COUNT(*) FROM commande c WHERE c.id_utilisateur = u.id_utilisateur) as nb_commandes
                FROM utilisateur u 
                WHERE u.role = 'client'
                ORDER BY u.date_inscription DESC 
                LIMIT 50
            ")->fetchAll();
            $page_title = "Gestion des clients - Mycksoft";
            break;
            
        case 'categories':
            $categories = $pdo->query("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM produit p WHERE p.id_categorie = c.id_categorie) as nb_produits,
                       (SELECT COUNT(*) FROM categorie c2 WHERE c2.id_categorie_parent = c.id_categorie) as nb_sous_categories
                FROM categorie c 
                ORDER BY c.nom_categorie
            ")->fetchAll();
            $page_title = "Gestion des catégories - Mycksoft";
            break;
            
        case 'avis':
            $avis = $pdo->query("
                SELECT a.*, p.nom_produit, u.nom, u.prenom 
                FROM avis a 
                JOIN produit p ON a.id_produit = p.id_produit 
                JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur 
                ORDER BY a.date_avis DESC
            ")->fetchAll();
            $page_title = "Gestion des avis - Mycksoft";
            break;
            
        case 'messages':
            $messages = $pdo->query("
                SELECT * FROM message_contact 
                ORDER BY date_message DESC
            ")->fetchAll();
            $page_title = "Messages de contact - Mycksoft";
            break;
            
        case 'notifications':
            $page_title = "Centre de notifications - Mycksoft";
            break;
            
        case 'parametres':
            $page_title = "Paramètres - Mycksoft";
            break;
            
        case 'dashboard':
        default:
            $produits_recent = $pdo->query("
                SELECT p.*, c.nom_categorie 
                FROM produit p 
                LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
                WHERE p.est_actif = 1 
                ORDER BY p.date_ajout DESC 
                LIMIT 8
            ")->fetchAll();
            
            $commandes_recent = $pdo->query("
                SELECT c.*, u.nom, u.prenom,
                       (SELECT COUNT(*) FROM ligne_commande lc WHERE lc.id_commande = c.id_commande) as nb_produits
                FROM commande c
                JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
                ORDER BY c.date_commande DESC
                LIMIT 6
            ")->fetchAll();
            
            $avis_attente = $pdo->query("
                SELECT a.*, p.nom_produit, u.nom, u.prenom 
                FROM avis a 
                JOIN produit p ON a.id_produit = p.id_produit 
                JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur 
                WHERE a.est_approuve = 0 
                ORDER BY a.date_avis DESC 
                LIMIT 5
            ")->fetchAll();
            
            $messages_recent = $pdo->query("
                SELECT * FROM message_contact 
                WHERE statut = 'nouveau' 
                ORDER BY date_message DESC 
                LIMIT 5
            ")->fetchAll();
            break;
    }

} catch (Exception $e) {
    $message = "Erreur lors du chargement des données : " . $e->getMessage();
    $message_type = "error";
}

// ============================================
// FONCTIONS D'AFFICHAGE
// ============================================
function displayStats($stats) {
    $stat_items = [
        ['total_produits', 'Produits', 'fa-box', '#4361ee'],
        ['total_clients', 'Clients', 'fa-users', '#3f37c9'],
        ['total_commandes', 'Commandes', 'fa-shopping-cart', '#4895ef'],
        ['chiffre_affaires', 'CA Total', 'fa-euro-sign', '#10b981'],
        ['commandes_aujourdhui', "Aujourd'hui", 'fa-calendar-day', '#f59e0b'],
        ['avis_en_attente', 'Avis en attente', 'fa-star', '#ce1126']
    ];
    
    foreach ($stat_items as $item) {
        $value = $stats[$item[0]] ?? 0;
        if ($item[0] == 'chiffre_affaires') $value = number_format($value, 0, ',', ' ') . ' FCFA';
        echo "<div class='stat-card'>
                <div class='stat-icon' style='background: {$item[3]}20; color: {$item[3]};'><i class='fas {$item[2]}'></i></div>
                <div class='stat-details'><h3>{$value}</h3><p>{$item[1]}</p></div>
              </div>";
    }
}

function sectionHeader($title, $icon, $subtitle = '', $actions = []) {
    echo "<div class='section-header'>
            <div><h2><i class='fas $icon'></i> $title</h2><p>$subtitle</p></div>
            <div style='display: flex; gap: 10px;'>";
    foreach ($actions as $action) {
        if ($action['type'] == 'button') {
            echo "<button class='btn btn-{$action['style']}' onclick='{$action['action']}'><i class='fas {$action['icon']}'></i> {$action['text']}</button>";
        } else {
            echo "<a href='#' class='btn btn-{$action['style']}' onclick='{$action['onclick']}'><i class='fas {$action['icon']}'></i> {$action['text']}</a>";
        }
    }
    echo "</div></div>";
}

function productCard($pdo, $produit) {
    // Fonction à implémenter selon vos besoins
    echo "<div class='card product-card'>
            <h4>" . htmlspecialchars($produit['nom_produit']) . "</h4>
          </div>";
}

function orderCard($commande) {
    // Fonction à implémenter selon vos besoins
    echo "<div class='card order-card'>
            <h4>Commande #" . $commande['id_commande'] . "</h4>
          </div>";
}

function reviewCard($avis) {
    // Fonction à implémenter selon vos besoins
    echo "<div class='card review-card'>
            <h4>Avis #" . $avis['id_avis'] . "</h4>
          </div>";
}

function messageCard($message) {
    // Fonction à implémenter selon vos besoins
    echo "<div class='card message-card'>
            <h4>Message #" . $message['id_message'] . "</h4>
          </div>";
}

function clientCard($client) {
    // Fonction à implémenter selon vos besoins
    echo "<div class='card client-card'>
            <h4>" . htmlspecialchars($client['prenom'] . ' ' . $client['nom']) . "</h4>
          </div>";
}

function categoryCard($categorie) {
    // Fonction à implémenter selon vos besoins
    echo "<div class='card category-card'>
            <h4>" . htmlspecialchars($categorie['nom_categorie']) . "</h4>
          </div>";
}

// ============================================
// SI C'EST UNE REQUÊTE AJAX
// ============================================
if ($isAjax) {
    error_reporting(0);
    ?>
    <?php if ($message): ?>
        <div class="notification notification-<?php echo $message_type; ?>" id="notification">
            <span><i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></span>
            <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>
    
    <div class="dynamic-content">
        <?php includeSection($section, $pdo, $stats, $produits_recent, $commandes_recent, $avis_attente, $messages_recent, $produits, $commandes, $clients, $categories, $avis, $messages, $all_categories); ?>
    </div>
    
    <script>
        if (typeof initSectionScripts === 'function') initSectionScripts('<?php echo $section; ?>');
        setTimeout(function() {
            var notif = document.getElementById('notification');
            if(notif) notif.remove();
        }, 5000);
    </script>
    <?php
    exit();
}

// ============================================
// FONCTION D'INCLUSION DES SECTIONS
// ============================================
function includeSection($section, $pdo, $stats, $produits_recent, $commandes_recent, $avis_attente, $messages_recent, $produits, $commandes, $clients, $categories, $avis, $messages, $all_categories) {
    
    if ($section === 'dashboard'): ?>
        <!-- STATISTIQUES -->
        <?php if ($stats): ?>
            <div class="stats-grid"><?php displayStats($stats); ?></div>
        <?php endif; ?>
        
        <!-- PRODUITS RÉCENTS -->
        <?php if (!empty($produits_recent)): ?>
        <div class="dashboard-section">
            <?php sectionHeader('Produits récents', 'fa-box', 'Les 8 derniers produits ajoutés', [
                ['type' => 'link', 'style' => 'primary', 'icon' => 'fa-external-link-alt', 'text' => 'Voir tous', 'onclick' => 'loadSection("produits"); return false;']
            ]); ?>
            <div class="cards-grid">
                <?php foreach ($produits_recent as $produit) productCard($pdo, $produit); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- COMMANDES RÉCENTES -->
        <?php if (!empty($commandes_recent)): ?>
        <div class="dashboard-section">
            <?php sectionHeader('Commandes récentes', 'fa-shopping-cart', 'Les 6 dernières commandes', [
                ['type' => 'link', 'style' => 'primary', 'icon' => 'fa-external-link-alt', 'text' => 'Voir toutes', 'onclick' => 'loadSection("commandes"); return false;']
            ]); ?>
            <div class="cards-grid">
                <?php foreach ($commandes_recent as $commande) orderCard($commande); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- AVIS EN ATTENTE -->
        <?php if (!empty($avis_attente)): ?>
        <div class="dashboard-section">
            <?php sectionHeader('Avis en attente de validation', 'fa-star', count($avis_attente) . ' avis à modérer'); ?>
            <div class="cards-grid">
                <?php foreach ($avis_attente as $avis_item) reviewCard($avis_item); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- MESSAGES RÉCENTS -->
        <?php if (!empty($messages_recent)): ?>
        <div class="dashboard-section">
            <?php sectionHeader('Messages récents', 'fa-envelope', count($messages_recent) . ' nouveaux messages'); ?>
            <div class="cards-grid">
                <?php foreach ($messages_recent as $message_item) messageCard($message_item); ?>
            </div>
        </div>
        <?php endif; ?>
        
    <?php elseif ($section === 'produits'): ?>
        <!-- SECTION PRODUITS -->
        <div class="section">
            <?php 
            sectionHeader('Gestion des produits', 'fa-box', count($produits) . ' produits trouvés', [
                ['type' => 'button', 'style' => 'primary', 'icon' => 'fa-plus', 'text' => 'Ajouter un produit', 'action' => "openModal('modalAjouter')"],
                ['type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;']
            ]); 
            ?>
            
            <!-- Filtres et recherche -->
            <div class="card" style="margin-bottom: 25px;">
                <div class="card-body">
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 250px;">
                            <input type="text" class="form-control" placeholder="Rechercher un produit..." id="searchProduct">
                        </div>
                        <div style="min-width: 200px;">
                            <select class="form-control" id="filterCategory">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($all_categories as $cat): ?>
                                    <option value="<?php echo $cat['id_categorie']; ?>"><?php echo htmlspecialchars($cat['nom_categorie']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="min-width: 150px;">
                            <select class="form-control" id="filterStatus">
                                <option value="">Tous les statuts</option>
                                <option value="1">Actif</option>
                                <option value="0">Inactif</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" onclick="filterProducts()"><i class="fas fa-filter"></i> Filtrer</button>
                    </div>
                </div>
            </div>
            
            <!-- Grille de produits -->
            <div class="cards-grid">
                <?php foreach ($produits as $produit) productCard($pdo, $produit); ?>
            </div>
        </div>
        
    <?php elseif ($section === 'commandes'): ?>
        <div class="section">
            <?php 
            sectionHeader('Gestion des commandes', 'fa-shopping-cart', count($commandes) . ' commandes trouvées', [
                ['type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;']
            ]); 
            ?>
            <div class="cards-grid">
                <?php foreach ($commandes as $commande) orderCard($commande); ?>
            </div>
        </div>
        
    <?php elseif ($section === 'clients'): ?>
        <div class="section">
            <?php 
            sectionHeader('Gestion des clients', 'fa-users', count($clients) . ' clients inscrits', [
                ['type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;']
            ]); 
            ?>
            <div class="cards-grid">
                <?php foreach ($clients as $client) clientCard($client); ?>
            </div>
        </div>
        
    <?php elseif ($section === 'categories'): ?>
        <div class="section">
            <?php 
            sectionHeader('Gestion des catégories', 'fa-tags', count($categories) . ' catégories créées', [
                ['type' => 'button', 'style' => 'primary', 'icon' => 'fa-plus', 'text' => 'Nouvelle catégorie', 'action' => "openModal('modalAjouterCategorie')"],
                ['type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;']
            ]); 
            ?>
            <div class="cards-grid">
                <?php foreach ($categories as $cat) categoryCard($cat); ?>
            </div>
        </div>
        
    <?php elseif ($section === 'avis'): ?>
        <div class="section">
            <?php 
            sectionHeader('Gestion des avis', 'fa-star', count($avis) . ' avis clients', [
                ['type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;']
            ]); 
            ?>
            <div class="cards-grid">
                <?php foreach ($avis as $avis_item) reviewCard($avis_item); ?>
            </div>
        </div>
        
    <?php elseif ($section === 'messages'): ?>
        <div class="section">
            <?php 
            sectionHeader('Messages de contact', 'fa-envelope', count($messages) . ' messages reçus', [
                ['type' => 'link', 'style' => '', 'icon' => 'fa-tachometer-alt', 'text' => 'Dashboard', 'onclick' => 'loadSection("dashboard"); return false;']
            ]); 
            ?>
            <div class="cards-grid">
                <?php foreach ($messages as $message_item) messageCard($message_item); ?>
            </div>
        </div>
        
    <?php elseif ($section === 'notifications'): ?>
        <!-- SECTION NOTIFICATIONS AVEC FORMULAIRE D'ENVOI -->
        <div class="section">
            <?php
            // Récupérer les clients pour le formulaire
            $clients_list = $notification->getAllClients();
            $all_notifications = $notification->getUserNotifications(getUserId(), 100, 0);
            $unread_count = $notification->countUnread(getUserId());
            $total_notifications = count($all_notifications);
            $notifications_aujourdhui = 0;
            foreach ($all_notifications as $n) {
                if (date('Y-m-d') === date('Y-m-d', strtotime($n['date_notification']))) $notifications_aujourdhui++;
            }
            ?>
            
            <!-- En-tête notifications -->
            <div class="notification-header">
                <div class="notification-header-left">
                    <h3><i class="fas fa-bell"></i> Centre de notifications</h3>
                    <p>Gérez vos notifications et communiquez avec vos clients</p>
                </div>
                <div class="notification-header-right">
                    <?php if ($unread_count > 0): ?>
                        <button class="notification-header-btn" onclick="markAllAsRead()">
                            <i class="fas fa-check-double"></i> Tout marquer comme lu
                        </button>
                    <?php endif; ?>
                    <button class="notification-header-btn" onclick="refreshNotifications()">
                        <i class="fas fa-sync-alt"></i> Actualiser
                    </button>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="notification-stats">
                <div class="stat-item">
                    <div class="stat-item-icon primary"><i class="fas fa-bell"></i></div>
                    <div class="stat-item-content">
                        <h4><?php echo $total_notifications; ?></h4>
                        <p>Total notifications</p>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-item-icon warning"><i class="fas fa-envelope"></i></div>
                    <div class="stat-item-content">
                        <h4><?php echo $unread_count; ?></h4>
                        <p>Non lues</p>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-item-icon success"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-item-content">
                        <h4><?php echo $notifications_aujourdhui; ?></h4>
                        <p>Aujourd'hui</p>
                    </div>
                </div>
            </div>
            
            <!-- FORMULAIRE D'ENVOI DE NOTIFICATION -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header" style="background:linear-gradient(135deg,#2d3d48,#1e2c34);color:white;padding:15px 20px">
                    <h3><i class="fas fa-paper-plane"></i> Envoyer une notification aux clients</h3>
                </div>
                <div class="card-body" style="padding:25px">
                    <form method="POST" onsubmit="submitNotificationForm(event)">
                        <input type="hidden" name="action" value="send_notification">
                        
                        <!-- Type de destinataire -->
                        <div class="form-group">
                            <label style="font-weight:600; color:#1e293b; margin-bottom:10px; display:block;">
                                <i class="fas fa-users"></i> Destinataires
                            </label>
                            <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="radio" name="destinataire" value="all" checked onchange="toggleClientSelection()"> 
                                    Tous les clients
                                </label>
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="radio" name="destinataire" value="single" onchange="toggleClientSelection()"> 
                                    Un client spécifique
                                </label>
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="radio" name="destinataire" value="multiple" onchange="toggleClientSelection()"> 
                                    Plusieurs clients
                                </label>
                            </div>
                        </div>
                        
                        <!-- Sélection client unique -->
                        <div class="form-group" id="single-client-select" style="display:none;">
                            <label for="client_id" style="font-weight:600; color:#1e293b; margin-bottom:8px; display:block;">
                                <i class="fas fa-user"></i> Sélectionner un client
                            </label>
                            <select name="client_id" id="client_id" class="form-control" style="padding:12px; border-radius:10px;">
                                <option value="">Choisir un client...</option>
                                <?php foreach ($clients_list as $client): ?>
                                    <option value="<?php echo $client['id_utilisateur']; ?>">
                                        <?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom'] . ' (' . $client['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Sélection multiple clients -->
                        <div class="form-group" id="multiple-client-select" style="display:none;">
                            <label style="font-weight:600; color:#1e293b; margin-bottom:8px; display:block;">
                                <i class="fas fa-users"></i> Sélectionner plusieurs clients
                            </label>
                            <div style="max-height:200px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:10px; padding:15px;">
                                <?php foreach ($clients_list as $client): ?>
                                    <label style="display:flex; align-items:center; gap:8px; margin-bottom:10px; cursor:pointer;">
                                        <input type="checkbox" name="client_ids[]" value="<?php echo $client['id_utilisateur']; ?>">
                                        <?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom'] . ' (' . $client['email'] . ')'); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Type de notification -->
                        <div class="form-group">
                            <label for="type" style="font-weight:600; color:#1e293b; margin-bottom:8px; display:block;">
                                <i class="fas fa-tag"></i> Type de notification
                            </label>
                            <select name="type" id="type" class="form-control" style="padding:12px; border-radius:10px;">
                                <option value="info">📢 Information générale</option>
                                <option value="success">✅ Succès / Confirmation</option>
                                <option value="warning">⚠️ Avertissement</option>
                                <option value="danger">❗ Urgent / Important</option>
                                <option value="system">⚙️ Système / Maintenance</option>
                            </select>
                        </div>
                        
                        <!-- Titre -->
                        <div class="form-group">
                            <label for="titre" style="font-weight:600; color:#1e293b; margin-bottom:8px; display:block;">
                                <i class="fas fa-heading"></i> Titre de la notification
                            </label>
                            <input type="text" name="titre" id="titre" class="form-control" 
                                   placeholder="Ex: Nouvelle promotion, Maintenance, etc." 
                                   style="padding:12px; border-radius:10px;" required>
                        </div>
                        
                        <!-- Message -->
                        <div class="form-group">
                            <label for="message" style="font-weight:600; color:#1e293b; margin-bottom:8px; display:block;">
                                <i class="fas fa-comment"></i> Message
                            </label>
                            <textarea name="message" id="message" rows="4" class="form-control" 
                                      placeholder="Écrivez votre message ici..." 
                                      style="padding:12px; border-radius:10px;" required></textarea>
                        </div>
                        
                        <!-- Lien (optionnel) -->
                        <div class="form-group">
                            <label for="lien" style="font-weight:600; color:#1e293b; margin-bottom:8px; display:block;">
                                <i class="fas fa-link"></i> Lien (optionnel)
                            </label>
                            <input type="text" name="lien" id="lien" class="form-control" 
                                   placeholder="Ex: page_produit.php?id=5, promotions.php, etc." 
                                   style="padding:12px; border-radius:10px;">
                            <small style="color:#64748b; margin-top:5px; display:block;">
                                Lien vers lequel le client sera redirigé en cliquant sur la notification
                            </small>
                        </div>
                        
                        <!-- Bouton d'envoi -->
                        <div class="form-group" style="margin-top:25px;">
                            <button type="submit" class="btn btn-primary" style="padding:14px 30px; font-size:16px;">
                                <i class="fas fa-paper-plane"></i> Envoyer la notification
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Historique des notifications -->
            <div class="card">
                <div class="card-header" style="background:white; border-bottom:1px solid #e2e8f0; padding:15px20px;">
                    <h3 style="margin:0; display:flex; align-items:center; gap:10px; color:#1e293b;">
                        <i class="fas fa-history"></i> Historique des notifications
                    </h3>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if (empty($all_notifications)): ?>
                        <div style="text-align:center; padding:60px20px;">
                            <i class="fas fa-bell-slash" style="font-size:64px; color:#cbd5e1; margin-bottom:20px;"></i>
                            <h3 style="color:#1e293b; margin-bottom:10px;">Aucune notification</h3>
                            <p style="color:#64748b;">Vous n'avez pas encore de notifications.</p>
                        </div>
                    <?php else: ?>
                        <div class="notification-list">
                            <?php foreach ($all_notifications as $notif): 
                                $is_unread = $notif['est_lue'] == 0;
                                $icon_class = [
                                    'success' => 'fa-check-circle',
                                    'danger' => 'fa-exclamation-triangle',
                                    'warning' => 'fa-exclamation-circle',
                                    'info' => 'fa-info-circle',
                                    'system' => 'fa-cog'
                                ][$notif['type']] ?? 'fa-bell';
                                
                                $bg_class = [
                                    'success' => 'success',
                                    'danger' => 'danger',
                                    'warning' => 'warning',
                                    'info' => 'info'
                                ][$notif['type']] ?? 'info';
                            ?>
                                <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>" 
                                     data-id="<?php echo $notif['id_notification']; ?>"
                                     data-status="<?php echo $notif['est_lue'] ? 'read' : 'unread'; ?>"
                                     data-date="<?php echo date('Y-m-d', strtotime($notif['date_notification'])); ?>">
                                    
                                    <div class="notification-icon <?php echo $bg_class; ?>">
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </div>
                                    
                                    <div class="notification-content">
                                        <div class="notification-title">
                                            <?php echo htmlspecialchars($notif['titre']); ?>
                                            <?php if ($is_unread): ?>
                                                <span class="badge bg-danger" style="margin-left:10px; font-size:10px;">Nouveau</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-message">
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                        </div>
                                        <div class="notification-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($notif['date_notification'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="notification-actions">
                                        <?php if ($is_unread): ?>
                                            <button class="card-btn card-btn-success" onclick="markAsRead(<?php echo $notif['id_notification']; ?>)" 
                                                    title="Marquer comme lu" style="padding:6px10px;">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($notif['lien'])): ?>
                                            <a href="<?php echo $notif['lien']; ?>" class="card-btn card-btn-primary" 
                                               title="Voir" style="padding:6px10px; text-decoration:none;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button class="card-btn card-btn-danger" onclick="deleteNotification(<?php echo $notif['id_notification']; ?>)" 
                                                title="Supprimer" style="padding:6px10px;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    <?php elseif ($section === 'parametres'): ?>
        <div class="section">
            <?php sectionHeader('Paramètres du site', 'fa-cog'); ?>
            
            <div class="form-grid">
                <div class="form-group"><label>Nom du site</label><input type="text" class="form-control" value="Mycksoft" id="site_name"></div>
                <div class="form-group"><label>Email de contact</label><input type="email" class="form-control" value="contact@mycksoft.com" id="contact_email"></div>
                <div class="form-group"><label>Téléphone</label><input type="text" class="form-control" value="+237 6XX XX XX XX" id="phone"></div>
                <div class="form-group"><label>Adresse</label><input type="text" class="form-control" value="Yaoundé, Cameroun" id="address"></div>
                <div class="form-group form-full"><label>Description du site</label><textarea class="form-control" rows="3" id="site_description">Votre partenaire informatique au Cameroun</textarea></div>
                <div class="form-group">
                    <label>Maintenance</label>
                    <div style="display: flex; gap: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px;"><input type="radio" name="maintenance" value="0" checked id="maintenance_off"> Site actif</label>
                        <label style="display: flex; align-items: center; gap: 8px;"><input type="radio" name="maintenance" value="1" id="maintenance_on"> Mode maintenance</label>
                    </div>
                </div>
                <div class="form-group form-full">
                    <button class="btn btn-primary" onclick="saveSettings()">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </div>
        </div>
    <?php endif;
}

// ============================================
// PAGE COMPLÈTE
// ============================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        :root {
            --primary: #2d3d48; --danger: #ce1126; --success: #10b981; 
            --warning: #f59e0b; --info: #4895ef; --sidebar-width: 260px;
            --green-dark: #1e2c34;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            --shadow-md: 0 5px 15px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 20px;
        }
        body { display: flex; background: #f1f5f9; min-height: 100vh; }
        .sidebar {
            width: var(--sidebar-width); background: white; box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed; height: 100vh; overflow-y: auto;
        }
        .logo { padding: 25px 20px; border-bottom: 1px solid #edf2f7; }
        .logo h2 { color: var(--primary); margin-bottom: 5px; }
        .logo p { color: #64748b; font-size: 12px; }
        .nav-menu { padding: 20px 0; }
        .nav-item {
            display: flex; align-items: center; gap: 12px; padding: 12px 20px;
            color: #475569; text-decoration: none; transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .nav-item:hover, .nav-item.active { background: #f8fafc; border-left-color: var(--primary); color: var(--primary); }
        .nav-item i { width: 20px; }
        .badge { background: var(--danger); color: white; border-radius: 20px; padding: 3px 8px; font-size: 11px; }
        .user-info {
            position: absolute; bottom: 0; width: 100%; padding: 20px;
            border-top: 1px solid #edf2f7; display: flex; align-items: center; gap: 12px;
        }
        .user-avatar { width: 45px; height: 45px; border-radius: 12px; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; }
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 30px; }
        .header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; background: white; padding: 20px 25px;
            border-radius: 16px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .header h1 { color: #1e293b; font-size: 24px; }
        .btn {
            padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer;
            font-weight: 600; display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; transition: all 0.3s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-primary:hover, .btn-danger:hover { opacity: 0.9; transform: translateY(-2px); }
        .form-control {
            width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px;
            font-size: 14px; transition: all 0.3s;
        }
        .form-control:focus { border-color: var(--primary); outline: none; }
        .card {
            background: white; border-radius: 16px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .card-header { padding: 15px 20px; border-bottom: 1px solid #e2e8f0; }
        .card-body { padding: 20px; }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; padding: 25px; border-radius: 16px; display: flex;
            align-items: center; gap: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .stat-icon {
            width: 60px; height: 60px; border-radius: 16px; display: flex;
            align-items: center; justify-content: center; font-size: 24px;
        }
        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }
        .section-header h2 { color: #1e293b; display: flex; align-items: center; gap: 10px; }
        
        /* Styles pour les notifications */
        .notification-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 11px;
            position: absolute;
            top: 0;
            right: 0;
        }
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #edf2f7;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        .notification-item:hover {
            background: #f8fafc;
        }
        .notification-item.unread {
            background: #e6f7ff;
            border-left: 4px solid var(--primary);
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }
        .notification-icon.success { background: linear-gradient(135deg, var(--success), #0d9488); }
        .notification-icon.danger { background: linear-gradient(135deg, var(--danger), #b91c1c); }
        .notification-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }
        .notification-icon.info { background: linear-gradient(135deg, var(--info), #2563eb); }
        .notification-content { flex: 1; }
        .notification-title { font-weight: 600; color: #1e293b; margin-bottom: 5px; font-size: 15px; }
        .notification-message { color: #64748b; font-size: 13px; margin-bottom: 5px; line-height: 1.5; }
        .notification-time { color: #94a3b8; font-size: 11px; display: flex; align-items: center; gap: 5px; }
        .notification-actions { display: flex; gap: 5px; }
        .notification-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            border-radius: 30px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .filter-btn:hover {
            border-color: var(--primary);
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px 20px;
            background: linear-gradient(135deg, var(--primary), var(--green-dark));
            border-radius: 16px;
            color: white;
        }
        .notification-header-left h3 {
            font-size: 22px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notification-header-left p {
            opacity: 0.9;
            font-size: 14px;
        }
        .notification-header-right {
            display: flex;
            gap: 10px;
        }
        .notification-header-btn {
            padding: 10px 20px;
            border-radius: 10px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notification-header-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .notification-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-item {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-item-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }
        .stat-item-icon.primary { background: var(--primary); }
        .stat-item-icon.success { background: var(--success); }
        .stat-item-icon.warning { background: var(--warning); }
        .stat-item-content h4 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 5px;
            font-weight: 700;
        }
        .stat-item-content p {
            color: #64748b;
            font-size: 13px;
        }
        .card-btn {
            padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer;
            color: white; font-size: 12px; transition: all 0.3s;
        }
        .card-btn-success { background: var(--success); }
        .card-btn-primary { background: var(--primary); }
        .card-btn-danger { background: var(--danger); }
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.9); display: none; justify-content: center;
            align-items: center; z-index: 9999;
        }
        .spinner {
            width: 50px; height: 50px; border: 5px solid #e2e8f0;
            border-top-color: var(--primary); border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .notification-global {
            position: fixed; top: 20px; right: 20px; padding: 15px 25px;
            border-radius: 10px; color: white; z-index: 10000; animation: slideIn 0.3s;
        }
        .notification-success { background: var(--success); }
        .notification-error { background: var(--danger); }
        .notification-info { background: var(--info); }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
    </style>
</head>
<body>
    <!-- LOADER GLOBAL -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>Mycksoft</h2>
            <p>Administration</p>
        </div>
        
        <div class="nav-menu">
            <?php 
            $menu_items = [
                'dashboard' => ['fa-tachometer-alt', 'Tableau de bord'],
                'produits' => ['fa-box', 'Produits'],
                'commandes' => ['fa-shopping-cart', 'Commandes'],
                'clients' => ['fa-users', 'Clients'],
                'categories' => ['fa-tags', 'Catégories'],
                'messages' => ['fa-envelope', 'Messages'],
                'avis' => ['fa-star', 'Avis clients'],
                'notifications' => ['fa-bell', 'Notifications'],
                'parametres' => ['fa-cog', 'Paramètres']
            ];
            
            foreach ($menu_items as $key => $item): 
                $active = ($section === $key) ? 'active' : ''; 
                
                if ($key === 'notifications' && $unread_notifications_count > 0): ?>
                    <a href="#" class="nav-item <?php echo $active; ?>" onclick="loadSection('<?php echo $key; ?>'); return false;">
                        <i class="fas <?php echo $item[0]; ?>"></i>
                        <span style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                            <?php echo $item[1]; ?>
                            <span class="badge"><?php echo $unread_notifications_count > 9 ? '9+' : $unread_notifications_count; ?></span>
                        </span>
                    </a>
                <?php else: ?>
                    <a href="#" class="nav-item <?php echo $active; ?>" onclick="loadSection('<?php echo $key; ?>'); return false;">
                        <i class="fas <?php echo $item[0]; ?>"></i>
                        <span><?php echo $item[1]; ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <div class="user-info">
            <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                <div class="user-role"><i class="fas fa-circle" style="font-size: 8px; color: #10b981;"></i> Administrateur</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas <?php echo isset($icons[$section]) ? $icons[$section] : 'fa-tachometer-alt'; ?>"></i> <span id="pageTitle"><?php echo $page_title; ?></span></h1>
            
            <div style="display: flex; gap: 15px; align-items: center;">
                <!-- BOUTON NOTIFICATIONS DANS LE HEADER -->
                <div style="position: relative;">
                    <a href="#" class="nav-item" onclick="loadSection('notifications'); return false;" 
                       style="background: white; border-radius: 10px; padding: 10px 15px; display: flex; align-items: center; gap: 8px; text-decoration: none; color: #1e293b;">
                        <i class="fas fa-bell" style="font-size: 20px; color: var(--primary);"></i>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span style="position: absolute; top: 0; right: 0; background: var(--danger); color: white; 
                                         border-radius: 50%; width: 20px; height: 20px; font-size: 12px; 
                                         display: flex; align-items: center; justify-content: center;">
                                <?php echo $unread_notifications_count > 9 ? '9+' : $unread_notifications_count; ?>
                            </span>
                        <?php endif; ?>
                        <span>Notifications</span>
                    </a>
                </div>
                
                <a href="?logout=1" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?');">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </div>
        
        <!-- Zone de contenu dynamique -->
        <div id="dynamicContent">
            <?php includeSection($section, $pdo, $stats, $produits_recent, $commandes_recent, $avis_attente, $messages_recent, $produits, $commandes, $clients, $categories, $avis, $messages, $all_categories); ?>
        </div>
    </div>
    
    <!-- Modal Ajouter Produit (à compléter) -->
    <div id="modalAjouter" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; width:500px; margin:50px auto; padding:20px; border-radius:16px;">
            <h3>Ajouter un produit</h3>
            <p>Formulaire à compléter</p>
            <button onclick="closeModal('modalAjouter')">Fermer</button>
        </div>
    </div>
    
    <!-- Modal Ajouter Catégorie (à compléter) -->
    <div id="modalAjouterCategorie" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; width:500px; margin:50px auto; padding:20px; border-radius:16px;">
            <h3>Ajouter une catégorie</h3>
            <p>Formulaire à compléter</p>
            <button onclick="closeModal('modalAjouterCategorie')">Fermer</button>
        </div>
    </div>

    <script>
        // ========== CONFIGURATION AJAX ==========
        var ADMIN_URL = window.location.pathname;
        var currentSection = '<?php echo $section; ?>';
        
        // ========== GESTIONNAIRE DE CHARGEMENT ==========
        function showLoader() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoader() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // ========== NAVIGATION AJAX ==========
        function loadSection(section) {
            if (section === currentSection) return;
            
            showLoader();
            
            var navItems = document.querySelectorAll('.nav-item');
            for (var i = 0; i < navItems.length; i++) {
                navItems[i].classList.remove('active');
            }
            
            for (var i = 0; i < navItems.length; i++) {
                var onclick = navItems[i].getAttribute('onclick');
                if (onclick && onclick.includes(section)) {
                    navItems[i].classList.add('active');
                }
            }
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', ADMIN_URL + '?section=' + section, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    document.getElementById('dynamicContent').innerHTML = xhr.responseText;
                    updatePageTitle(section);
                    currentSection = section;
                    history.pushState({section: section}, '', '?section=' + section);
                    hideLoader();
                } else {
                    console.error('Erreur:', xhr.status);
                    showNotification('Erreur lors du chargement de la page', 'error');
                    hideLoader();
                }
            };
            
            xhr.onerror = function() {
                console.error('Erreur réseau');
                showNotification('Erreur lors du chargement de la page', 'error');
                hideLoader();
            };
            
            xhr.send();
        }
        
        // ========== MISE À JOUR DU TITRE ==========
        function updatePageTitle(section) {
            var titles = {
                'dashboard': 'Dashboard Admin - Mycksoft',
                'produits': 'Gestion des produits - Mycksoft',
                'commandes': 'Gestion des commandes - Mycksoft',
                'clients': 'Gestion des clients - Mycksoft',
                'categories': 'Gestion des catégories - Mycksoft',
                'avis': 'Gestion des avis - Mycksoft',
                'messages': 'Messages de contact - Mycksoft',
                'notifications': 'Centre de notifications - Mycksoft',
                'parametres': 'Paramètres - Mycksoft'
            };
            document.title = titles[section] || 'Dashboard Admin - Mycksoft';
            var pageTitleEl = document.getElementById('pageTitle');
            if (pageTitleEl) pageTitleEl.textContent = titles[section] || 'Dashboard Admin - Mycksoft';
        }
        
        // ========== GESTION DES NOTIFICATIONS ==========
        function showNotification(message, type) {
            if (!type) type = 'success';
            
            var oldNotifications = document.querySelectorAll('.notification-global');
            for (var i = 0; i < oldNotifications.length; i++) {
                oldNotifications[i].remove();
            }
            
            var notification = document.createElement('div');
            notification.className = 'notification-global notification-' + type;
            
            var icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
            
            notification.innerHTML = '<span><i class="fas ' + icon + '"></i> ' + message + '</span>' +
                '<button class="notification-close" style="background:none; border:none; color:white; margin-left:15px; cursor:pointer; font-size:20px;" onclick="this.parentElement.remove()">&times;</button>';
            
            document.body.appendChild(notification);
            
            setTimeout(function() {
                if (notification.parentNode) {
                    notification.style.animation = 'slideIn 0.3s reverse';
                    setTimeout(function() { 
                        if (notification.parentNode) notification.remove(); 
                    }, 280);
                }
            }, 5000);
        }
        
        function closeNotification() {
            var notification = document.getElementById('notification');
            if (notification) {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(function() { 
                    if (notification.parentNode) notification.remove(); 
                }, 280);
            }
        }
        
        // ========== FONCTIONS POUR LES NOTIFICATIONS ==========
        function toggleClientSelection() {
            var radios = document.getElementsByName('destinataire');
            var single = document.getElementById('single-client-select');
            var multiple = document.getElementById('multiple-client-select');
            
            for (var i = 0; i < radios.length; i++) {
                if (radios[i].checked) {
                    if (radios[i].value === 'single') {
                        if (single) single.style.display = 'block';
                        if (multiple) multiple.style.display = 'none';
                    } else if (radios[i].value === 'multiple') {
                        if (single) single.style.display = 'none';
                        if (multiple) multiple.style.display = 'block';
                    } else {
                        if (single) single.style.display = 'none';
                        if (multiple) multiple.style.display = 'none';
                    }
                    break;
                }
            }
        }
        
        function submitNotificationForm(e) {
            e.preventDefault();
            showLoader();
            
            var form = e.target;
            var formData = new FormData(form);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ADMIN_URL, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    loadSection('notifications');
                    showNotification('Notification envoyée avec succès', 'success');
                } else {
                    showNotification('Erreur lors de l\'envoi', 'error');
                    hideLoader();
                }
            };
            
            xhr.onerror = function() {
                showNotification('Erreur lors de l\'envoi', 'error');
                hideLoader();
            };
            
            xhr.send(formData);
        }
        
        function markAsRead(notificationId) {
            showLoader();
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '../ajax/mark_notification_read.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    loadSection('notifications');
                    showNotification('Notification marquée comme lue', 'success');
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
        
        function refreshNotifications() {
            loadSection('notifications');
        }
        
        function filterNotifications(filter) {
            var items = document.querySelectorAll('.notification-item');
            var today = new Date().toISOString().split('T')[0];
            var oneWeekAgo = new Date();
            oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
            var weekStart = oneWeekAgo.toISOString().split('T')[0];
            
            var filterBtns = document.querySelectorAll('.filter-btn');
            filterBtns.forEach(function(btn) {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            items.forEach(function(item) {
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
        
        function updateNotificationBadges() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '../ajax/get_unread_count.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    var response = JSON.parse(xhr.responseText);
                    var count = response.unread_count || 0;
                    
                    var sidebarBadges = document.querySelectorAll('.nav-item .badge');
                    sidebarBadges.forEach(function(badge) {
                        if (count > 0) {
                            badge.textContent = count > 9 ? '9+' : count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    });
                    
                    var headerBadges = document.querySelectorAll('.header .badge');
                    headerBadges.forEach(function(badge) {
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
                            updateNotificationBadges();
                            if (response.count > 0) {
                                showNotification(response.count + ' nouvelle(s) notification(s)', 'info');
                            }
                        }
                    }
                };
                
                xhr.send();
            }
        }, 30000);
        
        // ========== GESTION DES MODALS ==========
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // ========== FILTRES PRODUITS ==========
        function filterProducts() {
            var searchInput = document.getElementById('searchProduct');
            var searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            
            var productCards = document.querySelectorAll('.product-card');
            for (var i = 0; i < productCards.length; i++) {
                var card = productCards[i];
                var show = true;
                
                if (searchTerm) {
                    var titleEl = card.querySelector('.card-title');
                    var productName = titleEl ? titleEl.textContent.toLowerCase() : '';
                    show = show && productName.indexOf(searchTerm) !== -1;
                }
                
                card.style.display = show ? 'block' : 'none';
            }
        }
        
        // ========== INITIALISATION DES SCRIPTS PAR SECTION ==========
        function initSectionScripts(section) {
            if (section === 'produits') {
                var searchInput = document.getElementById('searchProduct');
                if (searchInput) {
                    searchInput.addEventListener('keyup', filterProducts);
                }
                
                var filterCategory = document.getElementById('filterCategory');
                if (filterCategory) {
                    filterCategory.addEventListener('change', filterProducts);
                }
                
                var filterStatus = document.getElementById('filterStatus');
                if (filterStatus) {
                    filterStatus.addEventListener('change', filterProducts);
                }
            }
            
            if (section === 'notifications') {
                // Initialiser la sélection des clients
                var radios = document.getElementsByName('destinataire');
                for (var i = 0; i < radios.length; i++) {
                    radios[i].addEventListener('change', toggleClientSelection);
                }
            }
        }
        
        // ========== GESTION DU BOUTON PRÉCÉDENT ==========
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.section) {
                loadSection(event.state.section);
            }
        });
        
        // ========== INITIALISATION AU CHARGEMENT ==========
        document.addEventListener('DOMContentLoaded', function() {
            initSectionScripts('<?php echo $section; ?>');
            
            // Initialiser la sélection des clients si on est dans la section notifications
            if ('<?php echo $section; ?>' === 'notifications') {
                var radios = document.getElementsByName('destinataire');
                for (var i = 0; i < radios.length; i++) {
                    radios[i].addEventListener('change', toggleClientSelection);
                }
            }
        });
        
        // ========== AUTRES FONCTIONS ==========
        function saveSettings() {
            var siteName = document.getElementById('site_name');
            var contactEmail = document.getElementById('contact_email');
            var phone = document.getElementById('phone');
            var address = document.getElementById('address');
            var description = document.getElementById('site_description');
            var maintenanceRadios = document.querySelectorAll('input[name="maintenance"]:checked');
            
            showLoader();
            
            setTimeout(function() {
                showNotification('Paramètres enregistrés avec succès', 'success');
                hideLoader();
            }, 1000);
        }
    </script>
</body>
</html>