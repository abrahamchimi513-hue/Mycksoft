<?php
// admin_avis.php
require_once '../includes/config.php';

// Vérifier si l'utilisateur est connecté ET administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Avis Clients - Mycksoft";

// Traitement des actions
$message = '';
$message_type = '';

// Approuver un avis
if (isset($_POST['approuver_avis'])) {
    try {
        $id_avis = intval($_POST['id_avis']);
        
        $stmt = $pdo->prepare("UPDATE avis SET est_approuve = 1 WHERE id_avis = ?");
        $stmt->execute([$id_avis]);
        
        $message = "Avis approuvé avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur lors de l'approbation de l'avis";
        $message_type = "error";
    }
}

// Supprimer un avis
if (isset($_POST['supprimer_avis'])) {
    try {
        $id_avis = intval($_POST['id_avis']);
        
        $stmt = $pdo->prepare("DELETE FROM avis WHERE id_avis = ?");
        $stmt->execute([$id_avis]);
        
        $message = "Avis supprimé avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur lors de la suppression de l'avis";
        $message_type = "error";
    }
}

// Répondre à un avis
if (isset($_POST['repondre_avis'])) {
    try {
        $id_avis = intval($_POST['id_avis']);
        $reponse = trim($_POST['reponse']);
        
        if (empty($reponse)) {
            throw new Exception('La réponse ne peut pas être vide');
        }
        
        $stmt = $pdo->prepare("UPDATE avis SET reponse = ?, date_reponse = NOW() WHERE id_avis = ?");
        $stmt->execute([$reponse, $id_avis]);
        
        $message = "Réponse envoyée avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Filtrer les avis
$filtre = isset($_GET['filtre']) ? $_GET['filtre'] : 'tous';
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';

// Construire la requête en fonction des filtres
$where_conditions = [];
$params = [];

if ($filtre === 'approuves') {
    $where_conditions[] = "a.est_approuve = 1";
} elseif ($filtre === 'en_attente') {
    $where_conditions[] = "a.est_approuve = 0";
}

if (!empty($recherche)) {
    $where_conditions[] = "(p.nom_produit LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR a.commentaire LIKE ?)";
    $search_term = "%$recherche%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Compter le nombre total d'avis
    $count_sql = "SELECT COUNT(*) as total FROM avis a 
                  JOIN produit p ON a.id_produit = p.id_produit 
                  JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur 
                  $where_clause";
    
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_avis = $stmt_count->fetch()['total'];
    
    // Pagination
    $limit = 20;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    $total_pages = ceil($total_avis / $limit);
    
    // Récupérer les avis
    $sql = "SELECT a.*, p.nom_produit, p.id_produit, u.nom, u.prenom, u.email 
            FROM avis a 
            JOIN produit p ON a.id_produit = p.id_produit 
            JOIN utilisateur u ON a.id_utilisateur = u.id_utilisateur 
            $where_clause 
            ORDER BY a.date_avis DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $avis = $stmt->fetchAll();
    
    // Statistiques
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM avis) as total_avis,
            (SELECT COUNT(*) FROM avis WHERE est_approuve = 1) as avis_approuves,
            (SELECT COUNT(*) FROM avis WHERE est_approuve = 0) as avis_en_attente,
            COALESCE(AVG(note), 0) as note_moyenne,
            (SELECT COUNT(*) FROM avis WHERE DATE(date_avis) = CURDATE()) as avis_ajourdhui
    ")->fetch();
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des avis : " . $e->getMessage();
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
        /* Utiliser le même style que le dashboard */
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.reviews { background: var(--primary); }
        .stat-icon.approved { background: var(--success); }
        .stat-icon.pending { background: var(--warning); }
        .stat-icon.rating { background: var(--info); }
        
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
        
        /* Liste des avis */
        .reviews-list {
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
        
        .review-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .review-info {
            flex: 1;
        }
        
        .review-user {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .review-product {
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
        }
        
        .review-rating {
            color: #f39c12;
            margin: 5px 0;
        }
        
        .review-date {
            font-size: 12px;
            color: #888;
        }
        
        .review-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        
        .review-comment {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 5px;
            border-left: 4px solid var(--primary);
        }
        
        .review-response {
            margin-top: 15px;
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
        
        .review-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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
        
        .btn-approve { background: var(--success); color: white; }
        .btn-delete { background: var(--danger); color: white; }
        .btn-reply { background: var(--info); color: white; }
        
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
            max-width: 500px;
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
            min-height: 100px;
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
            
            .review-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .review-actions {
                flex-wrap: wrap;
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
            <a href="#" class="nav-item active">
                <i class="fas fa-star"></i>
                <span>Avis clients</span>
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
            <h1><i class="fas fa-star"></i> Avis Clients</h1>
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
            <div class="stat-card">
                <div class="stat-icon reviews">
                    <i class="fas fa-comment-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_avis']; ?></h3>
                    <p>Avis totaux</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['avis_approuves']; ?></h3>
                    <p>Avis approuvés</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['avis_en_attente']; ?></h3>
                    <p>En attente</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon rating">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['note_moyenne'], 1); ?>/5</h3>
                    <p>Note moyenne</p>
                </div>
            </div>
        </div>
        
        <!-- Filtres et recherche -->
        <div class="filters">
            <form method="GET" class="filter-group">
                <select name="filtre" class="filter-select" onchange="this.form.submit()">
                    <option value="tous" <?php echo $filtre === 'tous' ? 'selected' : ''; ?>>Tous les avis</option>
                    <option value="approuves" <?php echo $filtre === 'approuves' ? 'selected' : ''; ?>>Avis approuvés</option>
                    <option value="en_attente" <?php echo $filtre === 'en_attente' ? 'selected' : ''; ?>>En attente d'approbation</option>
                </select>
                
                <input type="text" name="recherche" class="search-input" placeholder="Rechercher par produit, client ou contenu..." value="<?php echo htmlspecialchars($recherche); ?>">
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Rechercher
                </button>
                
                <?php if (!empty($recherche) || $filtre !== 'tous'): ?>
                    <a href="admin_avis.php" class="btn">
                        <i class="fas fa-times"></i>
                        Réinitialiser
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Liste des avis -->
        <div class="reviews-list">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-list"></i> Liste des avis</h2>
                <div style="color: #666;">
                    <?php echo $total_avis; ?> avis trouvés
                </div>
            </div>
            
            <?php if (empty($avis)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-comment-slash" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                    <p style="color: #666;">Aucun avis trouvé</p>
                </div>
            <?php else: ?>
                <?php foreach ($avis as $un_avis): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="review-info">
                                <div class="review-user">
                                    <?php echo htmlspecialchars($un_avis['prenom'] . ' ' . $un_avis['nom']); ?>
                                    <small style="color: #666;">(<?php echo htmlspecialchars($un_avis['email']); ?>)</small>
                                </div>
                                <a href="../produit.php?id=<?php echo $un_avis['id_produit']; ?>" class="review-product" target="_blank">
                                    <i class="fas fa-box"></i> <?php echo htmlspecialchars($un_avis['nom_produit']); ?>
                                </a>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $un_avis['note'] ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                    <span style="margin-left: 10px; color: #666;"><?php echo $un_avis['note']; ?>/5</span>
                                </div>
                                <div class="review-date">
                                    <i class="fas fa-clock"></i> <?php echo date('d/m/Y à H:i', strtotime($un_avis['date_avis'])); ?>
                                </div>
                            </div>
                            
                            <div class="review-status <?php echo $un_avis['est_approuve'] ? 'status-approved' : 'status-pending'; ?>">
                                <?php echo $un_avis['est_approuve'] ? 'Approuvé' : 'En attente'; ?>
                            </div>
                        </div>
                        
                        <div class="review-comment">
                            <?php echo nl2br(htmlspecialchars($un_avis['commentaire'])); ?>
                        </div>
                        
                        <?php if (!empty($un_avis['reponse'])): ?>
                            <div class="review-response">
                                <span class="response-label"><i class="fas fa-reply"></i> Réponse :</span>
                                <?php echo nl2br(htmlspecialchars($un_avis['reponse'])); ?>
                                <?php if ($un_avis['date_reponse']): ?>
                                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                        Répondu le <?php echo date('d/m/Y à H:i', strtotime($un_avis['date_reponse'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="review-actions">
                            <?php if (!$un_avis['est_approuve']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id_avis" value="<?php echo $un_avis['id_avis']; ?>">
                                    <button type="submit" name="approuver_avis" class="action-btn btn-approve">
                                        <i class="fas fa-check"></i> Approuver
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <button type="button" class="action-btn btn-reply" onclick="openReplyModal(<?php echo $un_avis['id_avis']; ?>)">
                                <i class="fas fa-reply"></i> Répondre
                            </button>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet avis ? Cette action est irréversible.');">
                                <input type="hidden" name="id_avis" value="<?php echo $un_avis['id_avis']; ?>">
                                <button type="submit" name="supprimer_avis" class="action-btn btn-delete">
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
    <div id="modalRepondre" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Répondre à l'avis</h2>
                <button class="modal-close" onclick="closeModal('modalRepondre')">&times;</button>
            </div>
            
            <form method="POST" id="formRepondre">
                <input type="hidden" name="id_avis" id="reply_id_avis">
                
                <div class="form-group">
                    <label>Votre réponse *</label>
                    <textarea name="reponse" class="form-control" rows="5" required placeholder="Écrivez votre réponse ici..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('modalRepondre')">Annuler</button>
                    <button type="submit" name="repondre_avis" class="btn btn-primary">Envoyer la réponse</button>
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
        
        setTimeout(() => {
            closeNotification();
        }, 5000);
        
        // Modal de réponse
        function openReplyModal(id) {
            document.getElementById('reply_id_avis').value = id;
            openModal('modalRepondre');
        }
    </script>
</body>
</html>