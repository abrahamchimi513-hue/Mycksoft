<?php
// dashboard_admin.php
require_once '../includes/config.php';
require_once '../classes/Notification.php';

// Vérifier si l'utilisateur est connecté ET administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Initialiser la classe Notification (CORRECTION: décommenter cette ligne)
$notification = new Notification($pdo);
$unread_notifications_count = $notification->countUnread(getUserId());

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
    $message_texte = trim($_POST['message'] ?? ''); // RENOMMER pour éviter conflit avec $message global
    $type = $_POST['type'] ?? 'info';
    $lien = trim($_POST['lien'] ?? '');
    $destinataire = $_POST['destinataire'] ?? '';
    $client_id = $_POST['client_id'] ?? '';
    
    // CORRECTION: utiliser $notification déjà initialisé
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
    $message = "Erreur : " . $e->getMessage();
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
        <?php includeSection($section); ?>
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
function includeSection($section) {
    global $pdo, $stats, $produits_recent, $commandes_recent, $avis_attente, $messages_recent;
    global $produits, $commandes, $clients, $categories, $avis, $messages, $all_categories;
    
    if ($section === 'dashboard'): ?>
        <?php if ($stats): ?><div class="stats-grid"><?php displayStats($stats); ?></div><?php endif; ?>
        
        <?php if (!empty($produits_recent)): ?>
        <div class="dashboard-section">
            <?php sectionHeader('Produits récents', 'fa-box', 'Les 8 derniers produits', [
                ['type' => 'link', 'style' => 'primary', 'icon' => 'fa-external-link-alt', 'text' => 'Voir tous', 'onclick' => 'loadSection("produits"); return false;']
            ]); ?>
            <div class="cards-grid">
                <?php foreach ($produits_recent as $p): ?>
                    <div class='card product-card'><h4><?php echo htmlspecialchars($p['nom_produit']); ?></h4></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
    <?php elseif ($section === 'notifications'): ?>
        <div class="section">
            <?php
            $notification = new Notification($pdo);
            $user_id = getUserId();
            $clients_list = $notification->getAllClients();
            $all_notifications = $notification->getUserNotifications($user_id, 100, 0);
            $unread_count = $notification->countUnread($user_id);
            $total_notifications = count($all_notifications);
            $notifications_aujourdhui = 0;
            foreach ($all_notifications as $n) {
                if (date('Y-m-d') === date('Y-m-d', strtotime($n['date_notification']))) $notifications_aujourdhui++;
            }
            ?>
            
            <div class="notification-header">
                <div class="notification-header-left">
                    <h3><i class="fas fa-bell"></i> Centre de notifications</h3>
                    <p>Gérez vos notifications et communiquez avec vos clients</p>
                </div>
                <div class="notification-header-right">
                    <?php if ($unread_count > 0): ?>
                        <button class="notification-header-btn" onclick="markAllAsRead()"><i class="fas fa-check-double"></i> Tout marquer comme lu</button>
                    <?php endif; ?>
                    <button class="notification-header-btn" onclick="refreshNotifications()"><i class="fas fa-sync-alt"></i> Actualiser</button>
                </div>
            </div>
            
            <div class="notification-stats">
                <div class="stat-item"><div class="stat-item-icon primary"><i class="fas fa-bell"></i></div><div><h4><?php echo $total_notifications; ?></h4><p>Total</p></div></div>
                <div class="stat-item"><div class="stat-item-icon warning"><i class="fas fa-envelope"></i></div><div><h4><?php echo $unread_count; ?></h4><p>Non lues</p></div></div>
                <div class="stat-item"><div class="stat-item-icon success"><i class="fas fa-calendar-day"></i></div><div><h4><?php echo $notifications_aujourdhui; ?></h4><p>Aujourd'hui</p></div></div>
            </div>
            
            <div class="card" style="margin-bottom:30px">
                <div class="card-header" style="background:linear-gradient(135deg,#2d3d48,#1e2c34);color:white;padding:15px 20px">
                    <h3><i class="fas fa-paper-plane"></i> Envoyer une notification</h3>
                </div>
                <div class="card-body" style="padding:25px">
                    <form method="POST" onsubmit="submitNotificationForm(event)">
                        <input type="hidden" name="action" value="send_notification">
                        
                        <div class="form-group">
                            <label>Destinataires</label>
                            <div style="display:flex;gap:20px;margin-bottom:20px">
                                <label><input type="radio" name="destinataire" value="all" checked onchange="toggleClientSelection()"> Tous les clients</label>
                                <label><input type="radio" name="destinataire" value="single" onchange="toggleClientSelection()"> Un client</label>
                                <label><input type="radio" name="destinataire" value="multiple" onchange="toggleClientSelection()"> Plusieurs clients</label>
                            </div>
                        </div>
                        
                        <div class="form-group" id="single-client-select" style="display:none">
                            <select name="client_id" class="form-control">
                                <option value="">Choisir un client...</option>
                                <?php foreach ($clients_list as $c): ?>
                                    <option value="<?php echo $c['id_utilisateur']; ?>"><?php echo htmlspecialchars($c['prenom'].' '.$c['nom'].' ('.$c['email'].')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="multiple-client-select" style="display:none">
                            <div style="max-height:200px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:10px;padding:15px">
                                <?php foreach ($clients_list as $c): ?>
                                    <label style="display:block;margin-bottom:10px">
                                        <input type="checkbox" name="client_ids[]" value="<?php echo $c['id_utilisateur']; ?>"> 
                                        <?php echo htmlspecialchars($c['prenom'].' '.$c['nom'].' ('.$c['email'].')'); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <select name="type" class="form-control">
                                <option value="info">📢 Information</option>
                                <option value="success">✅ Succès</option>
                                <option value="warning">⚠️ Avertissement</option>
                                <option value="danger">❗ Urgent</option>
                                <option value="system">⚙️ Système</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" name="titre" class="form-control" placeholder="Titre" required>
                        </div>
                        
                        <div class="form-group">
                            <textarea name="message" class="form-control" rows="4" placeholder="Message" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" name="lien" class="form-control" placeholder="Lien (optionnel)">
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-history"></i> Historique</h3></div>
                <div class="card-body" style="padding:0">
                    <?php if (empty($all_notifications)): ?>
                        <div style="text-align:center;padding:60px"><i class="fas fa-bell-slash fa-4x"></i><h3>Aucune notification</h3></div>
                    <?php else: ?>
                        <?php foreach ($all_notifications as $n): 
                            $unread = $n['est_lue'] == 0;
                            $icon = ['success'=>'fa-check-circle','danger'=>'fa-exclamation-triangle','warning'=>'fa-exclamation-circle','info'=>'fa-info-circle','system'=>'fa-cog'][$n['type']]??'fa-bell';
                            $bg = ['success'=>'success','danger'=>'danger','warning'=>'warning','info'=>'info'][$n['type']]??'info';
                        ?>
                            <div class="notification-item <?php echo $unread ? 'unread' : ''; ?>" data-id="<?php echo $n['id_notification']; ?>">
                                <div class="notification-icon <?php echo $bg; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                                <div class="notification-content">
                                    <div class="notification-title"><?php echo htmlspecialchars($n['titre']); ?><?php if($unread) echo ' <span class="badge">Nouveau</span>'; ?></div>
                                    <div class="notification-message"><?php echo htmlspecialchars($n['message']); ?></div>
                                    <div class="notification-time"><i class="far fa-clock"></i> <?php echo date('d/m/Y H:i',strtotime($n['date_notification'])); ?></div>
                                </div>
                                <div class="notification-actions">
                                    <?php if($unread): ?>
                                        <button class="card-btn card-btn-success" onclick="markAsRead(<?php echo $n['id_notification']; ?>)"><i class="fas fa-check"></i></button>
                                    <?php endif; ?>
                                    <?php if(!empty($n['lien'])): ?>
                                        <a href="<?php echo $n['lien']; ?>" class="card-btn card-btn-primary"><i class="fas fa-eye"></i></a>
                                    <?php endif; ?>
                                    <button class="card-btn card-btn-danger" onclick="deleteNotification(<?php echo $n['id_notification']; ?>)"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif;
}
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
        .notification-item {
            display: flex; align-items: flex-start; gap: 15px; padding: 15px;
            border-bottom: 1px solid #edf2f7; transition: all 0.3s;
        }
        .notification-item:hover { background: #f8fafc; }
        .notification-item.unread { background: #e6f7ff; border-left: 4px solid var(--primary); }
        .notification-icon {
            width: 40px; height: 40px; border-radius: 10px; display: flex;
            align-items: center; justify-content: center; color: white;
        }
        .notification-icon.success { background: linear-gradient(135deg, var(--success), #0d9488); }
        .notification-icon.danger { background: linear-gradient(135deg, var(--danger), #b91c1c); }
        .notification-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }
        .notification-icon.info { background: linear-gradient(135deg, var(--info), #2563eb); }
        .notification-content { flex: 1; }
        .notification-title { font-weight: 600; color: #1e293b; margin-bottom: 5px; }
        .notification-message { color: #64748b; font-size: 13px; margin-bottom: 5px; }
        .notification-time { color: #94a3b8; font-size: 11px; display: flex; align-items: center; gap: 5px; }
        .notification-actions { display: flex; gap: 5px; }
        .card-btn {
            padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer;
            color: white; font-size: 12px; transition: all 0.3s;
        }
        .card-btn-success { background: var(--success); }
        .card-btn-primary { background: var(--primary); }
        .card-btn-danger { background: var(--danger); }
        .notification-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; padding: 20px; background: linear-gradient(135deg, var(--primary), #1e2c34);
            border-radius: 16px; color: white;
        }
        .notification-header-btn {
            padding: 10px 20px; border-radius: 10px; background: rgba(255,255,255,0.2);
            color: white; border: none; cursor: pointer;
        }
        .notification-stats {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-item {
            background: white; padding: 20px; border-radius: 16px;
            display: flex; align-items: center; gap: 15px;
        }
        .stat-item-icon {
            width: 50px; height: 50px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; color: white;
        }
        .stat-item-icon.primary { background: var(--primary); }
        .stat-item-icon.success { background: var(--success); }
        .stat-item-icon.warning { background: var(--warning); }
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
        .notification {
            position: fixed; top: 20px; right: 20px; padding: 15px 25px;
            border-radius: 10px; color: white; z-index: 10000; animation: slideIn 0.3s;
        }
        .notification-success { background: var(--success); }
        .notification-error { background: var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
    </style>
</head>
<body>
    <div id="loadingOverlay" class="loading-overlay"><div class="spinner"></div></div>

    <div class="sidebar">
        <div class="logo"><h2>Mycksoft</h2><p>Administration</p></div>
        <div class="nav-menu">
            <?php 
            $menu = [
                'dashboard' => ['fa-tachometer-alt', 'Dashboard'],
                'produits' => ['fa-box', 'Produits'],
                'commandes' => ['fa-shopping-cart', 'Commandes'],
                'clients' => ['fa-users', 'Clients'],
                'categories' => ['fa-tags', 'Catégories'],
                'messages' => ['fa-envelope', 'Messages'],
                'avis' => ['fa-star', 'Avis'],
                'notifications' => ['fa-bell', 'Notifications'],
                'parametres' => ['fa-cog', 'Paramètres']
            ];
            foreach ($menu as $key => $item): 
                $active = ($section === $key) ? 'active' : '';
                if ($key === 'notifications' && $unread_notifications_count > 0): ?>
                    <a href="#" class="nav-item <?php echo $active; ?>" onclick="loadSection('<?php echo $key; ?>');return false;">
                        <i class="fas <?php echo $item[0]; ?>"></i>
                        <span style="display:flex;justify-content:space-between;width:100%">
                            <?php echo $item[1]; ?>
                            <span class="badge"><?php echo $unread_notifications_count > 9 ? '9+' : $unread_notifications_count; ?></span>
                        </span>
                    </a>
                <?php else: ?>
                    <a href="#" class="nav-item <?php echo $active; ?>" onclick="loadSection('<?php echo $key; ?>');return false;">
                        <i class="fas <?php echo $item[0]; ?>"></i> <span><?php echo $item[1]; ?></span>
                    </a>
                <?php endif; 
            endforeach; ?>
        </div>
        <div class="user-info">
            <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
            <div><div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'].' '.$_SESSION['nom']); ?></div><div style="color:#64748b;font-size:12px">Administrateur</div></div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas <?php echo $icons[$section] ?? 'fa-tachometer-alt'; ?>"></i> <span id="pageTitle"><?php echo $page_title; ?></span></h1>
            <div style="display:flex;gap:15px">
                <a href="#" class="nav-item" onclick="loadSection('notifications');return false;" style="background:white;border-radius:10px;padding:10px 15px;display:flex;align-items:center;gap:8px;text-decoration:none;color:#1e293b">
                    <i class="fas fa-bell" style="color:var(--primary)"></i>
                    <?php if($unread_notifications_count>0): ?>
                        <span style="background:var(--danger);color:white;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:12px">
                            <?php echo $unread_notifications_count>9?'9+':$unread_notifications_count; ?>
                        </span>
                    <?php endif; ?>
                    <span>Notifications</span>
                </a>
                <a href="?logout=1" class="btn btn-danger" onclick="return confirm('Déconnexion ?')"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        
        <div id="dynamicContent">
            <?php includeSection($section); ?>
        </div>
    </div>

    <script>
        var ADMIN_URL = window.location.pathname;
        var currentSection = '<?php echo $section; ?>';
        
        function showLoader() { document.getElementById('loadingOverlay').style.display = 'flex'; }
        function hideLoader() { document.getElementById('loadingOverlay').style.display = 'none'; }
        
        function loadSection(section) {
            if(section === currentSection) return;
            showLoader();
            var navItems = document.querySelectorAll('.nav-item');
            for(var i = 0; i < navItems.length; i++) {
                navItems[i].classList.remove('active');
            }
            for(var i = 0; i < navItems.length; i++) {
                var onclick = navItems[i].getAttribute('onclick');
                if(onclick && onclick.includes(section)) {
                    navItems[i].classList.add('active');
                }
            }
            var xhr = new XMLHttpRequest();
            xhr.open('GET', ADMIN_URL + '?section=' + section, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function() {
                if(xhr.status >= 200 && xhr.status < 400) {
                    document.getElementById('dynamicContent').innerHTML = xhr.responseText;
                    currentSection = section;
                    history.pushState({section: section}, '', '?section=' + section);
                    hideLoader();
                } else { showNotification('Erreur', 'error'); hideLoader(); }
            };
            xhr.onerror = function() { showNotification('Erreur réseau', 'error'); hideLoader(); };
            xhr.send();
        }
        
        function showNotification(msg, type) {
            var n = document.createElement('div');
            n.className = 'notification notification-' + type;
            var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            n.innerHTML = '<span><i class="fas ' + icon + '"></i> ' + msg + '</span><button class="notification-close" onclick="this.parentElement.remove()">&times;</button>';
            document.body.appendChild(n);
            setTimeout(function() { if(n.parentNode) n.remove(); }, 5000);
        }
        
        function toggleClientSelection() {
            var radios = document.getElementsByName('destinataire');
            var single = document.getElementById('single-client-select');
            var multiple = document.getElementById('multiple-client-select');
            for(var i = 0; i < radios.length; i++) {
                if(radios[i].checked) {
                    if(radios[i].value === 'single') { 
                        single.style.display = 'block'; 
                        multiple.style.display = 'none'; 
                    }
                    else if(radios[i].value === 'multiple') { 
                        single.style.display = 'none'; 
                        multiple.style.display = 'block'; 
                    }
                    else { 
                        single.style.display = 'none'; 
                        multiple.style.display = 'none'; 
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
                if(xhr.status >= 200 && xhr.status < 400) {
                    loadSection('notifications');
                    showNotification('Notification envoyée', 'success');
                } else { showNotification('Erreur', 'error'); hideLoader(); }
            };
            xhr.onerror = function() { showNotification('Erreur', 'error'); hideLoader(); };
            xhr.send(formData);
        }
        
        function refreshNotifications() { loadSection('notifications'); }
        
        function markAsRead(id) {
            showLoader();
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '../ajax/mark_notification_read.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if(xhr.status >= 200 && xhr.status < 400) loadSection('notifications');
                else showNotification('Erreur', 'error');
                hideLoader();
            };
            xhr.send('notification_id=' + id);
        }
        
        function markAllAsRead() {
            if(!confirm('Marquer tout comme lu ?')) return;
            showLoader();
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '../ajax/mark_all_notifications_read.php', true);
            xhr.onload = function() {
                if(xhr.status >= 200 && xhr.status < 400) loadSection('notifications');
                else showNotification('Erreur', 'error');
                hideLoader();
            };
            xhr.send();
        }
        
        function deleteNotification(id) {
            if(!confirm('Supprimer ?')) return;
            showLoader();
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '../ajax/delete_notification.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if(xhr.status >= 200 && xhr.status < 400) loadSection('notifications');
                else showNotification('Erreur', 'error');
                hideLoader();
            };
            xhr.send('notification_id=' + id);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            var radios = document.getElementsByName('destinataire');
            for(var i = 0; i < radios.length; i++) {
                radios[i].addEventListener('change', toggleClientSelection);
            }
        });
    </script>
</body>
</html>