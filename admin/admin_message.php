<?php
// admin_messages.php - Gestion des messages
require_once '../includes/config.php';

// Vérifier si l'utilisateur est connecté ET administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Messages Clients - Mycksoft";

// Traitement des actions
$message = '';
$message_type = '';

// Marquer comme traité
if (isset($_POST['marquer_traite'])) {
    try {
        $id_message = intval($_POST['id_message']);
        $statut = $_POST['statut'];
        
        $stmt = $pdo->prepare("UPDATE message_contact SET statut = ? WHERE id_message = ?");
        $stmt->execute([$statut, $id_message]);
        
        $message = "Message marqué comme " . $statut . " !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Répondre à un message
if (isset($_POST['repondre_message'])) {
    try {
        $id_message = intval($_POST['id_message']);
        $reponse = trim($_POST['reponse']);
        
        if (empty($reponse)) {
            throw new Exception('La réponse ne peut pas être vide');
        }
        
        $stmt = $pdo->prepare("UPDATE message_contact SET reponse = ?, statut = 'repondu', date_reponse = NOW() WHERE id_message = ?");
        $stmt->execute([$reponse, $id_message]);
        
        // Envoyer un email de réponse (optionnel)
        $stmt_info = $pdo->prepare("SELECT email, nom FROM message_contact WHERE id_message = ?");
        $stmt_info->execute([$id_message]);
        $message_info = $stmt_info->fetch();
        
        if ($message_info) {
            // Ici vous pourriez envoyer un email au client
            // mail($message_info['email'], "Réponse à votre message", $reponse);
        }
        
        $message = "Réponse envoyée avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Supprimer un message
if (isset($_POST['supprimer_message'])) {
    try {
        $id_message = intval($_POST['id_message']);
        
        $stmt = $pdo->prepare("DELETE FROM message_contact WHERE id_message = ?");
        $stmt->execute([$id_message]);
        
        $message = "Message supprimé avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Filtrer les messages
$filtre = isset($_GET['filtre']) ? $_GET['filtre'] : 'nouveau';
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';

// Construire la requête
$where_conditions = [];
$params = [];

if ($filtre === 'nouveau') {
    $where_conditions[] = "statut = 'nouveau'";
} elseif ($filtre === 'repondu') {
    $where_conditions[] = "statut = 'repondu'";
} elseif ($filtre === 'en_cours') {
    $where_conditions[] = "statut = 'en cours'";
} elseif ($filtre === 'ferme') {
    $where_conditions[] = "statut = 'ferme'";
}

if (!empty($recherche)) {
    $where_conditions[] = "(nom LIKE ? OR email LIKE ? OR sujet LIKE ? OR contenu LIKE ?)";
    $search_term = "%$recherche%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Statistiques
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM message_contact) as total_messages,
            (SELECT COUNT(*) FROM message_contact WHERE statut = 'nouveau') as messages_nouveaux,
            (SELECT COUNT(*) FROM message_contact WHERE statut = 'repondu') as messages_repondus,
            (SELECT COUNT(*) FROM message_contact WHERE statut = 'en cours') as messages_en_cours,
            (SELECT COUNT(*) FROM message_contact WHERE DATE(date_message) = CURDATE()) as messages_aujourdhui
    ")->fetch();
    
    // Pagination
    $limit = 20;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    
    // Compter le total
    $count_sql = "SELECT COUNT(*) as total FROM message_contact $where_clause";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_messages = $stmt_count->fetch()['total'];
    $total_pages = ceil($total_messages / $limit);
    
    // Récupérer les messages
    $sql = "SELECT * FROM message_contact $where_clause ORDER BY date_message DESC LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $messages = $stmt->fetchAll();
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des messages : " . $e->getMessage();
    $message_type = "error";
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
        :root {
            --green-cm: #2d3d48;
            --red-cm: #ce1126;
            --yellow-cm: #fcd116;
            --green-dark: #005c46;
            --primary: var(--green-cm);
            --secondary: #3f37c9;
            --success: #10b981;
            --danger: var(--red-cm);
            --warning: #f59e0b;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 250px;
        }
        
        /* Styles du dashboard */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar identique au dashboard */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
        }
        
        .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #eee;
            background: var(--primary);
            color: white;
        }
        
        .logo h2 {
            font-size: 22px;
            margin-bottom: 5px;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .nav-item:hover, .nav-item.active {
            background: #f0f7ff;
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .nav-item i {
            width: 25px;
            margin-right: 10px;
            font-size: 18px;
        }
        
        .user-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .header h1 {
            color: var(--dark);
            font-size: 28px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--green-dark);
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            z-index: 1000;
            min-width: 300px;
            animation: slideIn 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .notification-success { background: var(--success); }
        .notification-error { background: var(--danger); }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            color: white;
        }
        
        .stat-icon.total { background: var(--primary); }
        .stat-icon.new { background: var(--danger); }
        .stat-icon.replied { background: var(--success); }
        .stat-icon.in-progress { background: var(--warning); }
        .stat-icon.today { background: var(--info); }
        
        .stat-info h3 {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stat-info p {
            color: #666;
            font-size: 14px;
        }
        
        /* Filtres et recherche */
        .filters {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-select, .search-input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
        }
        
        /* Messages List */
        .messages-list {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .message-info {
            flex: 1;
        }
        
        .message-sender {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .message-email {
            color: var(--primary);
            font-size: 14px;
            margin-bottom: 5px;
            display: block;
        }
        
        .message-subject {
            font-size: 18px;
            color: var(--dark);
            margin: 10px 0;
        }
        
        .message-date {
            font-size: 12px;
            color: #888;
        }
        
        .message-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-new { background: #fee2e2; color: #991b1b; }
        .status-replied { background: #d1fae5; color: #065f46; }
        .status-in-progress { background: #fef3c7; color: #92400e; }
        .status-closed { background: #dbeafe; color: #1e40af; }
        
        .message-content {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 5px;
            border-left: 4px solid var(--primary);
        }
        
        .message-response {
            margin-top: 20px;
            padding: 15px;
            background: #e8f4fc;
            border-radius: 5px;
            border-left: 4px solid var(--info);
        }
        
        .response-label {
            font-weight: 600;
            color: var(--info);
            margin-bottom: 5px;
            display: block;
        }
        
        .response-date {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-reply { background: var(--success); color: white; }
        .btn-status { background: var(--info); color: white; }
        .btn-delete { background: var(--danger); color: white; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .logo h2, .nav-item span, .user-info .user-details {
                display: none;
            }
            
            .nav-item {
                justify-content: center;
                padding: 15px;
            }
            
            .nav-item i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .message-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .message-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>Mycksoft</h2>
            <p>Administration</p>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard_admin.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="admin_categories.php" class="nav-item">
                <i class="fas fa-tags"></i>
                <span>Catégories</span>
            </a>
            <a href="admin_avis.php" class="nav-item">
                <i class="fas fa-star"></i>
                <span>Avis clients</span>
            </a>
            <a href="admin_clients.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Clients</span>
            </a>
            <a href="#" class="nav-item active">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>
            <a href="dashboard_admin.php#products" class="nav-item">
                <i class="fas fa-box"></i>
                <span>Produits</span>
            </a>
            <a href="dashboard_admin.php#orders" class="nav-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
            </a>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                <div class="user-role">Administrateur</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-envelope"></i> Messages Clients</h1>
        </div>
        
        <!-- Notifications -->
        <?php if ($message): ?>
            <div class="notification notification-<?php echo $message_type; ?>" id="notification">
                <span><?php echo htmlspecialchars($message); ?></span>
                <button class="notification-close" onclick="closeNotification()">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='?filtre=tous'">
                <div class="stat-icon total">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_messages']; ?></h3>
                    <p>Messages totaux</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='?filtre=nouveau'">
                <div class="stat-icon new">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['messages_nouveaux']; ?></h3>
                    <p>Nouveaux messages</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='?filtre=repondu'">
                <div class="stat-icon replied">
                    <i class="fas fa-reply"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['messages_repondus']; ?></h3>
                    <p>Messages répondu</p>
                </div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='?filtre=en_cours'">
                <div class="stat-icon in-progress">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['messages_en_cours']; ?></h3>
                    <p>En cours de traitement</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon today">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['messages_aujourdhui']; ?></h3>
                    <p>Messages aujourd'hui</p>
                </div>
            </div>
        </div>
        
        <!-- Filtres et recherche -->
        <div class="filters">
            <form method="GET" class="filter-group">
                <select name="filtre" class="filter-select" onchange="this.form.submit()">
                    <option value="nouveau" <?php echo $filtre === 'nouveau' ? 'selected' : ''; ?>>Nouveaux messages</option>
                    <option value="repondu" <?php echo $filtre === 'repondu' ? 'selected' : ''; ?>>Messages répondu</option>
                    <option value="en_cours" <?php echo $filtre === 'en_cours' ? 'selected' : ''; ?>>En cours de traitement</option>
                    <option value="ferme" <?php echo $filtre === 'ferme' ? 'selected' : ''; ?>>Messages fermés</option>
                    <option value="tous" <?php echo $filtre === 'tous' ? 'selected' : ''; ?>>Tous les messages</option>
                </select>
                
                <input type="text" name="recherche" class="search-input" placeholder="Rechercher par nom, email ou sujet..." value="<?php echo htmlspecialchars($recherche); ?>">
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Rechercher
                </button>
                
                <?php if (!empty($recherche) || $filtre !== 'nouveau'): ?>
                    <a href="admin_messages.php" class="btn">
                        <i class="fas fa-times"></i>
                        Réinitialiser
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Liste des messages -->
        <div class="messages-list">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-list"></i> Liste des messages</h2>
                <div style="color: #666;">
                    <?php echo $total_messages; ?> messages trouvés
                </div>
            </div>
            
            <?php if (empty($messages)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-envelope-open" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                    <p style="color: #666;">Aucun message trouvé</p>
                    <?php if ($filtre !== 'tous' || !empty($recherche)): ?>
                        <a href="admin_messages.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-eye"></i> Voir tous les messages
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message-item">
                        <div class="message-header">
                            <div class="message-info">
                                <div class="message-sender">
                                    <?php echo htmlspecialchars($msg['nom']); ?>
                                    <small style="color: #666;">(<?php echo htmlspecialchars($msg['email']); ?>)</small>
                                </div>
                                <?php if ($msg['telephone']): ?>
                                    <div style="font-size: 14px; color: #666;">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($msg['telephone']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="message-subject"><?php echo htmlspecialchars($msg['sujet']); ?></div>
                                <div class="message-date">
                                    <i class="fas fa-clock"></i> <?php echo date('d/m/Y à H:i', strtotime($msg['date_message'])); ?>
                                </div>
                            </div>
                            
                            <div class="message-status status-<?php echo str_replace(' ', '-', $msg['statut']); ?>">
                                <?php 
                                $status_labels = [
                                    'nouveau' => 'Nouveau',
                                    'en cours' => 'En cours',
                                    'repondu' => 'Répondu',
                                    'ferme' => 'Fermé'
                                ];
                                echo $status_labels[$msg['statut']] ?? $msg['statut'];
                                ?>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($msg['contenu'])); ?>
                        </div>
                        
                        <?php if (!empty($msg['reponse'])): ?>
                            <div class="message-response">
                                <span class="response-label"><i class="fas fa-reply"></i> Réponse :</span>
                                <?php echo nl2br(htmlspecialchars($msg['reponse'])); ?>
                                <div class="response-date">
                                    Répondu le <?php echo $msg['date_reponse'] ? date('d/m/Y à H:i', strtotime($msg['date_reponse'])) : ''; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="message-actions">
                            <?php if (empty($msg['reponse'])): ?>
                                <button type="button" class="action-btn btn-reply" onclick="openReplyModal(<?php echo $msg['id_message']; ?>, '<?php echo htmlspecialchars($msg['sujet']); ?>')">
                                    <i class="fas fa-reply"></i> Répondre
                                </button>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id_message" value="<?php echo $msg['id_message']; ?>">
                                <select name="statut" class="form-control" style="width: auto; display: inline-block;" onchange="this.form.submit()">
                                    <option value="nouveau" <?php echo $msg['statut'] == 'nouveau' ? 'selected' : ''; ?>>Nouveau</option>
                                    <option value="en cours" <?php echo $msg['statut'] == 'en cours' ? 'selected' : ''; ?>>En cours</option>
                                    <option value="repondu" <?php echo $msg['statut'] == 'repondu' ? 'selected' : ''; ?>>Répondu</option>
                                    <option value="ferme" <?php echo $msg['statut'] == 'ferme' ? 'selected' : ''; ?>>Fermé</option>
                                </select>
                                <input type="hidden" name="marquer_traite" value="1">
                            </form>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce message ? Cette action est irréversible.');">
                                <input type="hidden" name="id_message" value="<?php echo $msg['id_message']; ?>">
                                <button type="submit" name="supprimer_message" class="action-btn btn-delete">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&filtre=<?php echo $filtre; ?>&recherche=<?php echo urlencode($recherche); ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?page=<?php echo $i; ?>&filtre=<?php echo $filtre; ?>&recherche=<?php echo urlencode($recherche); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span class="page-link">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&filtre=<?php echo $filtre; ?>&recherche=<?php echo urlencode($recherche); ?>" class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Répondre -->
    <div id="modalReply" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Répondre au message</h2>
                <button class="modal-close" onclick="closeModal('modalReply')">&times;</button>
            </div>
            
            <form method="POST" id="formReply">
                <input type="hidden" name="id_message" id="reply_id_message">
                
                <div class="form-group">
                    <label>Sujet</label>
                    <input type="text" id="reply_subject" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Votre réponse *</label>
                    <textarea name="reponse" class="form-control" rows="6" required placeholder="Écrivez votre réponse ici..."></textarea>
                    <small style="color: #666;">Cette réponse sera visible par le client dans son espace et pourra être envoyée par email.</small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('modalReply')">Annuler</button>
                    <button type="submit" name="repondre_message" class="btn btn-primary">Envoyer la réponse</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Gestion des modals
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Notification
        function closeNotification() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }
        
        // Auto-fermer la notification après 5 secondes
        setTimeout(() => {
            closeNotification();
        }, 5000);
        
        // Ouvrir le modal de réponse avec les données du message
        function openReplyModal(messageId, subject) {
            document.getElementById('reply_id_message').value = messageId;
            document.getElementById('reply_subject').value = subject;
            openModal('modalReply');
        }
        
        // Fermer le modal de réponse
        function closeReplyModal() {
            closeModal('modalReply');
        }
        
        // Confirmation avant suppression
        function confirmDelete() {
            return confirm('Êtes-vous sûr de vouloir supprimer ce message ? Cette action est irréversible.');
        }
        
        // Toggle réponse existante
        function toggleResponse(responseId) {
            const response = document.getElementById('response-' + responseId);
            if (response.style.display === 'none') {
                response.style.display = 'block';
            } else {
                response.style.display = 'none';
            }
        }
        
        // Marquer tous comme lus
        function markAllAsRead() {
            if (confirm('Marquer tous les messages non lus comme lus ?')) {
                window.location.href = '?action=mark_all_read';
            }
        }
        
        // Exporter les messages
        function exportMessages() {
            const format = prompt('Choisir le format (csv ou json):');
            if (format === 'csv' || format === 'json') {
                window.location.href = 'export_messages.php?format=' + format;
            }
        }
        
        // Recherche en temps réel (optionnel)
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="recherche"]');
            if (searchInput) {
                let debounceTimer;
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function() {
                        if (e.target.value.length >= 3 || e.target.value.length === 0) {
                            e.target.form.submit();
                        }
                    }, 500);
                });
            }
            
            // Mise en évidence des nouveaux messages
            document.querySelectorAll('.message-item').forEach(item => {
                const status = item.querySelector('.message-status');
                if (status && status.textContent.includes('Nouveau')) {
                    item.style.animation = 'pulse 2s infinite';
                }
            });
            
            // Ajout de l'animation pulse
            const style = document.createElement('style');
            style.textContent = `
                @keyframes pulse {
                    0% { box-shadow: 0 0 0 0 rgba(206, 17, 38, 0.4); }
                    70% { box-shadow: 0 0 0 10px rgba(206, 17, 38, 0); }
                    100% { box-shadow: 0 0 0 0 rgba(206, 17, 38, 0); }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>