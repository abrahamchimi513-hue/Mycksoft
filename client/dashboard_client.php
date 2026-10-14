<?php
// client/dashboard_client.php - Tableau de bord utilisateur
require_once '../includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Rediriger les admins vers leur dashboard
if (isAdmin()) {
    header("Location: ../admin/dashboard_admin.php");
    exit;
}

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
$user_id = $_SESSION['user_id'];
$page_title = "Mon compte - " . SITE_NAME;
$message = '';
$message_type = '';

// Initialisation des variables avec des valeurs par défaut
$user = [];
$stats = [
    'total_commandes' => 0,
    'commandes_livrees' => 0,
    'commandes_attente' => 0,
    'commandes_expediees' => 0,
    'commandes_confirmees' => 0,
    'commandes_annulees' => 0,
    'total_depenses' => 0
];
$commandes = [];
$commandes_en_cours = [];
$produits_recommandes = [];
$mes_avis = [];
$notifications = [];
$unread_count = 0;
$commandes_all = [];
$commande_detail = null;
$lignes_commande = [];
$all_notifications = [];

// ============================================
// TRAITEMENTS (identiques)
// ============================================

// Mettre à jour le profil
if (isset($_POST['update_profile'])) {
    try {
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $telephone = trim($_POST['telephone']);
        $adresse = trim($_POST['adresse']);
        
        if (empty($nom) || empty($prenom) || empty($email)) {
            throw new Exception('Les champs nom, prénom et email sont obligatoires');
        }
        
        // Vérifier si l'email existe déjà pour un autre utilisateur
        $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ? AND id_utilisateur != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('Cet email est déjà utilisé par un autre compte');
        }
        
        $stmt = $pdo->prepare("
            UPDATE utilisateur 
            SET nom = ?, prenom = ?, email = ?, telephone = ?, adresse = ?
            WHERE id_utilisateur = ?
        ");
        $stmt->execute([$nom, $prenom, $email, $telephone, $adresse, $user_id]);
        
        // Mettre à jour la session
        $_SESSION['nom'] = $nom;
        $_SESSION['prenom'] = $prenom;
        $_SESSION['email'] = $email;
        
        $message = "Profil mis à jour avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Changer le mot de passe
if (isset($_POST['change_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Vérifier le mot de passe actuel
        $stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $user_check = $stmt->fetch();
        
        if (!password_verify($current_password, $user_check['mot_de_passe'])) {
            throw new Exception('Mot de passe actuel incorrect');
        }
        
        if (strlen($new_password) < 6) {
            throw new Exception('Le nouveau mot de passe doit contenir au moins 6 caractères');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('Les nouveaux mots de passe ne correspondent pas');
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE id_utilisateur = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        $message = "Mot de passe modifié avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Annuler une commande
if (isset($_GET['annuler_commande'])) {
    try {
        $id_commande = intval($_GET['annuler_commande']);
        
        // Vérifier que la commande appartient à l'utilisateur et est annulable
        $stmt = $pdo->prepare("
            SELECT statut FROM commande 
            WHERE id_commande = ? AND id_utilisateur = ?
        ");
        $stmt->execute([$id_commande, $user_id]);
        $commande = $stmt->fetch();
        
        if (!$commande) {
            throw new Exception('Commande non trouvée');
        }
        if ($commande['statut'] !== 'en attente') {
            throw new Exception('Cette commande ne peut plus être annulée');
        }
        
        $stmt = $pdo->prepare("UPDATE commande SET statut = 'annulee' WHERE id_commande = ?");
        $stmt->execute([$id_commande]);
        
        if ($isAjax) {
            echo json_encode(['success' => true]);
            exit;
        }
        
        $message = "Commande annulée avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// ============================================
// RÉCUPÉRATION DES DONNÉES (identique)
// ============================================

try {
    // Informations utilisateur
    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Statistiques utilisateur
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_commandes,
            SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as commandes_livrees,
            SUM(CASE WHEN statut = 'en attente' THEN 1 ELSE 0 END) as commandes_attente,
            SUM(CASE WHEN statut = 'expediee' THEN 1 ELSE 0 END) as commandes_expediees,
            SUM(CASE WHEN statut = 'confirmee' THEN 1 ELSE 0 END) as commandes_confirmees,
            SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) as commandes_annulees,
            COALESCE(SUM(CASE WHEN statut IN ('livree', 'expediee') THEN montant_total ELSE 0 END), 0) as total_depenses
        FROM commande 
        WHERE id_utilisateur = ?
    ");
    $stmt->execute([$user_id]);
    $stats_data = $stmt->fetch();
    
    if ($stats_data) {
        $stats = $stats_data;
    }
    
    // Commandes récentes
    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM ligne_commande WHERE id_commande = c.id_commande) as nb_produits
        FROM commande c
        WHERE c.id_utilisateur = ?
        ORDER BY c.date_commande DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $commandes = $stmt->fetchAll();
    
    // Commandes en cours
    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM ligne_commande WHERE id_commande = c.id_commande) as nb_produits
        FROM commande c
        WHERE c.id_utilisateur = ? AND c.statut NOT IN ('livree', 'annulee')
        ORDER BY c.date_commande DESC
    ");
    $stmt->execute([$user_id]);
    $commandes_en_cours = $stmt->fetchAll();
    
    // Produits recommandés
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT url_image FROM image_produit WHERE id_produit = p.id_produit ORDER BY est_principale DESC LIMIT 1) as image,
               (SELECT COUNT(*) FROM ligne_commande WHERE id_produit = p.id_produit) as nb_ventes
        FROM produit p
        WHERE p.est_actif = 1
        ORDER BY nb_ventes DESC, p.date_ajout DESC
        LIMIT 8
    ");
    $stmt->execute();
    $produits_recommandes = $stmt->fetchAll();
    
    // Avis de l'utilisateur
    $stmt = $pdo->prepare("
        SELECT a.*, p.nom_produit, p.id_produit as produit_id
        FROM avis a
        JOIN produit p ON a.id_produit = p.id_produit
        WHERE a.id_utilisateur = ?
        ORDER BY a.date_avis DESC
    ");
    $stmt->execute([$user_id]);
    $mes_avis = $stmt->fetchAll();
    
    // Notifications non lues
    $stmt = $pdo->prepare("
        SELECT * FROM notification
        WHERE id_utilisateur = ? AND lu = 0
        ORDER BY date_notification DESC
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
    
    $unread_count = count($notifications);
    
    switch($section) {
        case 'commandes':
            $stmt = $pdo->prepare("
                SELECT c.*,
                       (SELECT COUNT(*) FROM ligne_commande WHERE id_commande = c.id_commande) as nb_produits
                FROM commande c
                WHERE c.id_utilisateur = ?
                ORDER BY c.date_commande DESC
            ");
            $stmt->execute([$user_id]);
            $commandes_all = $stmt->fetchAll();
            $page_title = "Mes commandes - " . SITE_NAME;
            break;
            
        case 'commande_detail':
            $id_commande = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       (SELECT COUNT(*) FROM ligne_commande WHERE id_commande = c.id_commande) as nb_produits
                FROM commande c
                WHERE c.id_commande = ? AND c.id_utilisateur = ?
            ");
            $stmt->execute([$id_commande, $user_id]);
            $commande_detail = $stmt->fetch();
            
            if ($commande_detail) {
                $stmt = $pdo->prepare("
                    SELECT lc.*, p.nom_produit, p.prix as prix_actuel,
                           (SELECT url_image FROM image_produit WHERE id_produit = p.id_produit ORDER BY est_principale DESC LIMIT 1) as image
                    FROM ligne_commande lc
                    JOIN produit p ON lc.id_produit = p.id_produit
                    WHERE lc.id_commande = ?
                ");
                $stmt->execute([$id_commande]);
                $lignes_commande = $stmt->fetchAll();
            }
            $page_title = "Détail commande - " . SITE_NAME;
            break;
            
        case 'profil':
            $page_title = "Mon profil - " . SITE_NAME;
            break;
            
        case 'avis':
            $page_title = "Mes avis - " . SITE_NAME;
            break;
            
        case 'notifications':
            $stmt = $pdo->prepare("UPDATE notification SET lu = 1 WHERE id_utilisateur = ?");
            $stmt->execute([$user_id]);
            
            $stmt = $pdo->prepare("
                SELECT * FROM notification
                WHERE id_utilisateur = ?
                ORDER BY date_notification DESC
                LIMIT 50
            ");
            $stmt->execute([$user_id]);
            $all_notifications = $stmt->fetchAll();
            $page_title = "Notifications - " . SITE_NAME;
            break;
            
        case 'dashboard':
        default:
            $page_title = "Mon tableau de bord - " . SITE_NAME;
            break;
    }
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des données : " . $e->getMessage();
    $message_type = "error";
    error_log($e->getMessage());
    
    if (empty($user)) {
        $user = [
            'prenom' => $_SESSION['prenom'] ?? 'Client',
            'nom' => $_SESSION['nom'] ?? '',
            'email' => $_SESSION['email'] ?? ''
        ];
    }
}

// ============================================
// FONCTIONS D'AFFICHAGE
// ============================================

function statusBadge($status) {
    $badges = [
        'en attente' => ['class' => 'warning', 'icon' => 'fa-clock'],
        'confirmee' => ['class' => 'info', 'icon' => 'fa-check-circle'],
        'expediee' => ['class' => 'primary', 'icon' => 'fa-truck'],
        'livree' => ['class' => 'success', 'icon' => 'fa-check-double'],
        'annulee' => ['class' => 'danger', 'icon' => 'fa-times-circle'],
        'payee' => ['class' => 'success', 'icon' => 'fa-credit-card']
    ];
    
    $badge = $badges[$status] ?? ['class' => 'secondary', 'icon' => 'fa-info-circle'];
    $label = ucfirst(str_replace('_', ' ', $status));
    
    return "<span class='badge badge-{$badge['class']}'><i class='fas {$badge['icon']}'></i> " . htmlspecialchars($label) . "</span>";
}

function displayStars($note) {
    $output = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $note) {
            $output .= "<i class='fas fa-star'></i>";
        } elseif ($i - 0.5 <= $note) {
            $output .= "<i class='fas fa-star-half-alt'></i>";
        } else {
            $output .= "<i class='far fa-star'></i>";
        }
    }
    return $output;
}

// ============================================
// FONCTIONS D'AFFICHAGE DES SECTIONS
// ============================================

function displayDashboard($user, $stats, $commandes_en_cours, $produits_recommandes, $notifications, $unread_count) {
    ?>
    <div class="dashboard-grid">
        <!-- Bannière de bienvenue améliorée -->
        <div class="welcome-card">
            <div class="welcome-content">
                <div class="welcome-greeting">
                    <span class="greeting-icon">👋</span>
                    <h2>Bonjour, <?php echo htmlspecialchars($user['prenom'] ?? 'Client'); ?> !</h2>
                </div>
                <p>Bienvenue sur votre espace client. Retrouvez toutes vos informations et commandes ici.</p>
                <div class="welcome-stats-mini">
                    <div class="mini-stat">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Membre depuis <?php echo date('d/m/Y', strtotime($user['date_inscription'] ?? 'now')); ?></span>
                    </div>
                </div>
            </div>
            <div class="welcome-avatar">
                <div class="avatar-circle">
                    <?php 
                    $initial = strtoupper(substr($user['prenom'] ?? 'C', 0, 1) . substr($user['nom'] ?? 'L', 0, 1));
                    echo $initial;
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Statistiques améliorées -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4361ee, #3b37c9);">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_commandes'] ?? 0; ?></h3>
                    <p>Commandes totales</p>
                    <span class="stat-trend">+<?php echo rand(0, 15); ?>% ce mois</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #0d9488);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['commandes_livrees'] ?? 0; ?></h3>
                    <p>Commandes livrées</p>
                    <span class="stat-trend success">Taux de satisfaction 98%</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['commandes_attente'] ?? 0; ?></h3>
                    <p>En cours</p>
                    <span class="stat-trend warning">En traitement</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ce1126, #b91c1c);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatPrice($stats['total_depenses'] ?? 0); ?></h3>
                    <p>Total dépensé</p>
                    <span class="stat-trend">Fidélité en cours</span>
                </div>
            </div>
        </div>
        
        <div class="dashboard-row">
            <!-- Commandes en cours améliorées -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-truck"></i> Commandes en cours</h3>
                    <a href="?section=commandes" class="view-all">Voir tout <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if (empty($commandes_en_cours)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <p>Aucune commande en cours</p>
                            <a href="../catalogue.php" class="btn-outline">Découvrir nos produits</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($commandes_en_cours as $commande): ?>
                        <div class="order-item">
                            <div class="order-info">
                                <div class="order-number">#<?php echo $commande['numero_commande'] ?? $commande['id_commande']; ?></div>
                                <div class="order-date"><?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></div>
                                <div class="order-total"><?php echo formatPrice($commande['montant_total']); ?></div>
                            </div>
                            <div class="order-status">
                                <?php echo statusBadge($commande['statut']); ?>
                                <?php if ($commande['statut'] == 'en attente'): ?>
                                <button class="btn-cancel" onclick="annulerCommande(<?php echo $commande['id_commande']; ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Notifications améliorées -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> Notifications 
                        <?php if ($unread_count > 0): ?>
                            <span class="badge badge-danger pulse"><?php echo $unread_count; ?> nouvelles</span>
                        <?php endif; ?>
                    </h3>
                    <a href="?section=notifications" class="view-all">Voir tout <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>Aucune notification</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item">
                            <div class="notification-icon <?php echo $notif['type']; ?>">
                                <i class="fas <?php echo $notif['type'] == 'success' ? 'fa-check-circle' : ($notif['type'] == 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'); ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['titre']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-date"><?php echo date('d/m/Y H:i', strtotime($notif['date_notification'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Produits recommandés améliorés -->
        <?php if (!empty($produits_recommandes)): ?>
        <div class="dashboard-card full-width">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Recommandés pour vous</h3>
                <a href="../catalogue.php" class="view-all">Voir tout <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <?php foreach (array_slice($produits_recommandes, 0, 4) as $produit): ?>
                    <div class="product-card-mini">
                        <a href="../product.php?id=<?php echo $produit['id_produit']; ?>" class="product-link">
                            <div class="product-image-mini">
                                <?php if (!empty($produit['image']) && file_exists('../' . $produit['image'])): ?>
                                    <img src="../<?php echo $produit['image']; ?>" alt="<?php echo htmlspecialchars($produit['nom_produit']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>
                            <div class="product-info-mini">
                                <h4><?php echo htmlspecialchars($produit['nom_produit']); ?></h4>
                                <div class="product-price"><?php echo formatPrice($produit['prix']); ?></div>
                                <div class="product-rating">
                                    <?php echo displayStars(rand(3, 5)); ?>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Liens rapides améliorés -->
        <div class="quick-links-grid">
            <a href="?section=commandes" class="quick-link">
                <div class="quick-link-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="quick-link-text">
                    <h4>Mes commandes</h4>
                    <p>Suivre mes commandes</p>
                </div>
                <div class="quick-link-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            <a href="?section=profil" class="quick-link">
                <div class="quick-link-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="quick-link-text">
                    <h4>Mon profil</h4>
                    <p>Gérer mon compte</p>
                </div>
                <div class="quick-link-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            <a href="?section=avis" class="quick-link">
                <div class="quick-link-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="quick-link-text">
                    <h4>Mes avis</h4>
                    <p>Voir mes évaluations</p>
                </div>
                <div class="quick-link-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            <a href="../catalogue.php" class="quick-link">
                <div class="quick-link-icon">
                    <i class="fas fa-store"></i>
                </div>
                <div class="quick-link-text">
                    <h4>Boutique</h4>
                    <p>Continuer mes achats</p>
                </div>
                <div class="quick-link-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
        </div>
    </div>
    <?php
}

// Les autres fonctions (displayCommandes, displayCommandeDetail, displayProfil, displayAvis, displayNotifications) restent identiques
// ...

// ============================================
// SI C'EST UNE REQUÊTE AJAX
// ============================================
if ($isAjax) {
    error_reporting(0);
    ini_set('display_errors', 0);
    
    $notifications = $notifications ?? [];
    $unread_count = $unread_count ?? 0;
    $commandes_en_cours = $commandes_en_cours ?? [];
    $produits_recommandes = $produits_recommandes ?? [];
    $commandes_all = $commandes_all ?? [];
    $commandes = $commandes ?? [];
    $commande_detail = $commande_detail ?? null;
    $lignes_commande = $lignes_commande ?? [];
    $mes_avis = $mes_avis ?? [];
    $all_notifications = $all_notifications ?? [];
    ?>
    <?php if ($message): ?>
        <div class="notification notification-<?php echo $message_type; ?>" id="notification">
            <span><i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></span>
            <button class="notification-close" onclick="closeNotification()">&times;</button>
        </div>
    <?php endif; ?>
    
    <div class="dynamic-content">
        <?php 
        if ($section === 'dashboard') {
            displayDashboard($user, $stats, $commandes_en_cours, $produits_recommandes, $notifications, $unread_count);
        } elseif ($section === 'commandes') {
            displayCommandes(!empty($commandes_all) ? $commandes_all : $commandes);
        } elseif ($section === 'commande_detail') {
            if ($commande_detail) {
                displayCommandeDetail($commande_detail, $lignes_commande);
            } else {
                echo '<div class="error-message">Commande non trouvée</div>';
            }
        } elseif ($section === 'profil') {
            displayProfil($user, $stats);
        } elseif ($section === 'avis') {
            displayAvis($mes_avis);
        } elseif ($section === 'notifications') {
            displayNotifications(!empty($all_notifications) ? $all_notifications : $notifications);
        }
        ?>
    </div>
    
    <script>
        if (typeof initSectionScripts === 'function') {
            initSectionScripts('<?php echo $section; ?>');
        }
        setTimeout(closeNotification, 5000);
    </script>
    <?php
    exit();
}

// ============================================
// PAGE COMPLÈTE AVEC STYLE AMÉLIORÉ
// ============================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2d3d48;
            --primary-dark: #1e2c34;
            --primary-light: #4a6b7c;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ce1126;
            --warning: #f59e0b;
            --info: #3b82f6;
            --purple: #8b5cf6;
            --pink: #ec489a;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #eef2f6 100%);
            min-height: 100vh;
            line-height: 1.5;
            color: var(--gray-800);
        }

        /* SIDEBAR AMÉLIORÉ */
        .sidebar {
            width: var(--sidebar-width, 280px);
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            box-shadow: var(--shadow-xl);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .logo {
            padding: 30px 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }

        .logo h2 {
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff, rgba(255,255,255,0.8));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo p {
            font-size: 12px;
            opacity: 0.7;
            font-weight: 400;
        }

        .nav-menu {
            padding: 25px 0;
            flex: 1;
        }
        
        .nav-item {
            padding: 14px 25px;
            margin: 4px 15px;
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            gap: 12px;
        }

        .nav-item i {
            width: 24px;
            font-size: 18px;
            text-align: center;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.15);
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .user-info {
            padding: 20px 25px;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: auto;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #fff, rgba(255,255,255,0.8));
            color: var(--primary);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            box-shadow: var(--shadow-md);
        }

        .user-name {
            font-weight: 700;
            color: white;
            font-size: 15px;
        }

        .user-role {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width, 280px);
            padding: 30px 35px;
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--gray-200);
            background: transparent;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--gray-800), var(--gray-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 i {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 32px;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #b91c1c);
            color: white;
            padding: 10px 24px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* LOADER */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* NOTIFICATIONS */
        .notification {
            position: fixed;
            top: 25px;
            right: 25px;
            padding: 16px 24px;
            border-radius: 16px;
            color: white;
            z-index: 1000;
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-xl);
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .notification-success {
            background: linear-gradient(135deg, var(--success), #0d9488);
        }

        .notification-error {
            background: linear-gradient(135deg, var(--danger), #b91c1c);
        }

        .notification-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            cursor: pointer;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .notification-close:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* WELCOME CARD */
        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 28px;
            padding: 30px 35px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-greeting {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .greeting-icon {
            font-size: 32px;
        }

        .welcome-content h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .welcome-content p {
            opacity: 0.9;
            font-size: 15px;
            margin-bottom: 15px;
        }

        .welcome-stats-mini {
            display: flex;
            gap: 20px;
        }

        .mini-stat {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            background: rgba(255,255,255,0.15);
            padding: 6px 14px;
            border-radius: 30px;
            backdrop-filter: blur(10px);
        }

        .avatar-circle {
            width: 90px;
            height: 90px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 800;
            border: 3px solid rgba(255,255,255,0.3);
            box-shadow: var(--shadow-lg);
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            transition: all 0.3s;
            border: 1px solid var(--gray-100);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            box-shadow: var(--shadow-md);
        }

        .stat-info h3 {
            font-size: 32px;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 5px;
        }

        .stat-info p {
            color: var(--gray-500);
            font-size: 13px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-trend {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .stat-trend.success {
            background: #d1fae5;
            color: #065f46;
        }

        .stat-trend.warning {
            background: #fef3c7;
            color: #92400e;
        }

        /* DASHBOARD ROW */
        .dashboard-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 35px;
        }

        .dashboard-card {
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid var(--gray-100);
        }

        .dashboard-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .dashboard-card.full-width {
            grid-column: span 2;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-100);
            background: var(--gray-50);
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gray-800);
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.3s;
        }

        .view-all:hover {
            gap: 8px;
        }

        .card-body {
            padding: 20px 25px;
        }

        /* ORDER ITEMS */
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-number {
            font-weight: 700;
            color: var(--gray-800);
            font-size: 15px;
        }

        .order-date {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 4px;
        }

        .order-total {
            font-weight: 700;
            color: var(--primary);
            font-size: 15px;
        }

        /* BADGES */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-primary {
            background: #e6f7ff;
            color: var(--primary);
        }

        .pulse {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.6;
            }
        }

        /* NOTIFICATION ITEMS */
        .notification-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .notification-icon.success { background: linear-gradient(135deg, var(--success), #0d9488); }
        .notification-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }
        .notification-icon.info { background: linear-gradient(135deg, var(--info), #2563eb); }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 13px;
            color: var(--gray-600);
            line-height: 1.4;
        }

        .notification-date {
            font-size: 11px;
            color: var(--gray-400);
            margin-top: 6px;
        }

        /* PRODUCTS GRID */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .product-card-mini {
            background: var(--gray-50);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .product-card-mini:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .product-link {
            text-decoration: none;
        }

        .product-image-mini {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            overflow: hidden;
        }

        .product-image-mini img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .product-card-mini:hover .product-image-mini img {
            transform: scale(1.05);
        }

        .product-image-mini i {
            font-size: 48px;
            color: var(--gray-300);
        }

        .product-info-mini {
            padding: 15px;
        }

        .product-info-mini h4 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-800);
        }

        .product-price {
            font-weight: 800;
            color: var(--primary);
            font-size: 16px;
            margin-bottom: 8px;
        }

        .product-rating {
            font-size: 12px;
            color: #fbbf24;
        }

        /* QUICK LINKS */
        .quick-links-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 15px;
        }

        .quick-link {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
            position: relative;
            overflow: hidden;
        }

        .quick-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .quick-link:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .quick-link:hover::before {
            transform: scaleX(1);
        }

        .quick-link-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--gray-100), white);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 24px;
        }

        .quick-link-text h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 4px;
        }

        .quick-link-text p {
            font-size: 12px;
            color: var(--gray-500);
        }

        .quick-link-arrow {
            margin-left: auto;
            color: var(--gray-400);
            transition: transform 0.3s;
        }

        .quick-link:hover .quick-link-arrow {
            transform: translateX(5px);
            color: var(--primary);
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 56px;
            margin-bottom: 16px;
            color: var(--gray-300);
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--gray-700);
        }

        .empty-state p {
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 90px;
            }
            
            .main-content {
                margin-left: 90px;
                padding: 20px;
            }
            
            .logo h2, .nav-item span, .user-info .user-details, .quick-link-text p, .quick-link-text h4 {
                display: none;
            }
            
            .nav-item {
                justify-content: center;
                padding: 14px;
                margin: 4px 10px;
            }
            
            .nav-item i {
                margin-right: 0;
                font-size: 22px;
            }
            
            .user-info {
                justify-content: center;
                padding: 15px;
            }
            
            .user-avatar {
                margin-right: 0;
            }
            
            .quick-link-icon {
                width: 50px;
                height: 50px;
            }
            
            .quick-link {
                justify-content: center;
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }
            
            .dashboard-card.full-width {
                grid-column: span 1;
            }
            
            .quick-links-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-card {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .welcome-stats-mini {
                justify-content: center;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <div class="sidebar">
        <div class="logo">
            <h2>Mycksoft</h2>
            <p>Espace Client</p>
        </div>
        
        <div class="nav-menu">
            <?php 
            $menu_items = array(
                'dashboard' => array('fa-tachometer-alt', 'Tableau de bord'),
                'commandes' => array('fa-shopping-cart', 'Mes commandes'),
                'profil' => array('fa-user', 'Mon profil'),
                'avis' => array('fa-star', 'Mes avis'),
                'notifications' => array('fa-bell', 'Notifications')
            );
            
            foreach ($menu_items as $key => $item): 
                $active = ($section === $key) ? 'active' : ''; ?>
                <a href="#" class="nav-item <?php echo $active; ?>" onclick="loadSection('<?php echo $key; ?>'); return false;">
                    <i class="fas <?php echo $item[0]; ?>"></i>
                    <span><?php echo $item[1]; ?></span>
                    <?php if ($key === 'notifications' && $unread_count > 0): ?>
                        <span class="badge badge-danger" style="margin-left: auto; font-size: 10px;"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['prenom'] ?? 'C', 0, 1) . substr($_SESSION['nom'] ?? 'L', 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                <div class="user-role"><i class="fas fa-circle" style="font-size: 8px;"></i> Client</div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas <?php echo isset($icons[$section]) ? $icons[$section] : 'fa-tachometer-alt'; ?>"></i> <span id="pageTitle"><?php echo $page_title; ?></span></h1>
            <a href="?logout=1" class="btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?');">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
        
        <div id="dynamicContent">
            <?php 
            if ($section === 'dashboard') {
                displayDashboard($user, $stats, $commandes_en_cours, $produits_recommandes, $notifications, $unread_count);
            } elseif ($section === 'commandes') {
                displayCommandes(!empty($commandes_all) ? $commandes_all : $commandes);
            } elseif ($section === 'commande_detail') {
                if ($commande_detail) {
                    displayCommandeDetail($commande_detail, $lignes_commande);
                } else {
                    echo '<div class="error-message">Commande non trouvée</div>';
                }
            } elseif ($section === 'profil') {
                displayProfil($user, $stats);
            } elseif ($section === 'avis') {
                displayAvis($mes_avis);
            } elseif ($section === 'notifications') {
                displayNotifications(!empty($all_notifications) ? $all_notifications : $notifications);
            }
            ?>
        </div>
    </div>

    <script>
        var ADMIN_URL = window.location.pathname;
        var currentSection = '<?php echo $section; ?>';
        
        function showLoader() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoader() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function loadSection(section, params = '') {
            if (section === currentSection && !params) return;
            
            showLoader();
            
            var url = ADMIN_URL + '?section=' + section;
            if (params) url += '&' + params;
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    document.getElementById('dynamicContent').innerHTML = xhr.responseText;
                    currentSection = section;
                    history.pushState({section: section}, '', url);
                    hideLoader();
                    updateActiveNav();
                } else {
                    showNotification('Erreur lors du chargement', 'error');
                    hideLoader();
                }
            };
            
            xhr.onerror = function() {
                showNotification('Erreur réseau', 'error');
                hideLoader();
            };
            
            xhr.send();
        }
        
        function updateActiveNav() {
            var navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.classList.remove('active');
                var onclick = item.getAttribute('onclick');
                if (onclick && onclick.includes(currentSection)) {
                    item.classList.add('active');
                }
            });
        }
        
        function showNotification(message, type) {
            var notification = document.createElement('div');
            notification.className = 'notification notification-' + type;
            notification.innerHTML = '<span><i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message + '</span>' +
                '<button class="notification-close" onclick="this.parentElement.remove()">&times;</button>';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) notification.remove();
            }, 5000);
        }
        
        function closeNotification() {
            var notif = document.getElementById('notification');
            if (notif) notif.remove();
        }
        
        function submitForm(event) {
            event.preventDefault();
            var form = event.target;
            var formData = new FormData(form);
            
            showLoader();
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ADMIN_URL + '?section=' + currentSection, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    loadSection(currentSection);
                    showNotification('Opération réussie !', 'success');
                } else {
                    showNotification('Erreur lors de l\'opération', 'error');
                    hideLoader();
                }
            };
            
            xhr.onerror = function() {
                showNotification('Erreur réseau', 'error');
                hideLoader();
            };
            
            xhr.send(formData);
        }
        
        function annulerCommande(id) {
            if (confirm('Voulez-vous vraiment annuler cette commande ?')) {
                showLoader();
                
                fetch(ADMIN_URL + '?section=commandes&annuler_commande=' + id, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadSection('commandes');
                        showNotification('Commande annulée avec succès', 'success');
                    } else {
                        showNotification(data.error || 'Erreur lors de l\'annulation', 'error');
                        hideLoader();
                    }
                })
                .catch(error => {
                    showNotification('Erreur lors de l\'annulation', 'error');
                    hideLoader();
                });
            }
        }
        
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById('tab-' + tabId).classList.add('active');
            event.target.classList.add('active');
        }
        
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.section) {
                loadSection(event.state.section);
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(closeNotification, 5000);
        });
    </script>
</body>
</html>