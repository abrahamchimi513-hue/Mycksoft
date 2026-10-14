<?php
// admin_clients.php - Gestion des clients
require_once '../includes/config.php';

// Vérifier si l'utilisateur est connecté ET administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Gestion des Clients - Mycksoft";

// Traitement des actions
$message = '';
$message_type = '';

// Activer/Désactiver client
if (isset($_GET['toggle_client'])) {
    try {
        $id_utilisateur = intval($_GET['toggle_client']);
        
        // Récupérer le statut actuel
        $stmt = $pdo->prepare("SELECT est_actif FROM utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([$id_utilisateur]);
        $client = $stmt->fetch();
        
        if ($client) {
            $new_status = $client['est_actif'] ? 0 : 1;
            
            $stmt_update = $pdo->prepare("UPDATE utilisateur SET est_actif = ? WHERE id_utilisateur = ?");
            $stmt_update->execute([$new_status, $id_utilisateur]);
            
            $action = $new_status ? 'activé' : 'désactivé';
            $message = "Client $action avec succès !";
            $message_type = "success";
        }
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Supprimer client
if (isset($_POST['supprimer_client'])) {
    try {
        $id_utilisateur = intval($_POST['id_utilisateur']);
        
        // Vérifier si le client a des commandes
        $stmt_check = $pdo->prepare("SELECT COUNT(*) as nb_commandes FROM commande WHERE id_utilisateur = ?");
        $stmt_check->execute([$id_utilisateur]);
        $result = $stmt_check->fetch();
        
        if ($result['nb_commandes'] > 0) {
            // Désactiver au lieu de supprimer
            $stmt = $pdo->prepare("UPDATE utilisateur SET est_actif = 0 WHERE id_utilisateur = ?");
            $stmt->execute([$id_utilisateur]);
            $message = "Client désactivé (a des commandes enregistrées)";
        } else {
            // Supprimer complètement
            $stmt = $pdo->prepare("DELETE FROM utilisateur WHERE id_utilisateur = ?");
            $stmt->execute([$id_utilisateur]);
            $message = "Client supprimé avec succès !";
        }
        
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Filtrer les clients
$filtre = isset($_GET['filtre']) ? $_GET['filtre'] : 'tous';
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';

// Construire la requête
$where_conditions = ["role = 'client'"];
$params = [];

if ($filtre === 'actifs') {
    $where_conditions[] = "est_actif = 1";
} elseif ($filtre === 'inactifs') {
    $where_conditions[] = "est_actif = 0";
}

if (!empty($recherche)) {
    $where_conditions[] = "(nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR telephone LIKE ?)";
    $search_term = "%$recherche%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "WHERE role = 'client'";

try {
    // Statistiques
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM utilisateur WHERE role = 'client') as total_clients,
            (SELECT COUNT(*) FROM utilisateur WHERE role = 'client' AND est_actif = 1) as clients_actifs,
            (SELECT COUNT(*) FROM utilisateur WHERE role = 'client' AND est_actif = 0) as clients_inactifs,
            (SELECT COUNT(*) FROM utilisateur WHERE role = 'client' AND DATE(date_inscription) = CURDATE()) as clients_aujourdhui,
            (SELECT COUNT(DISTINCT id_utilisateur) FROM commande) as clients_avec_commandes
    ")->fetch();
    
    // Pagination
    $limit = 20;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    
    // Compter le total
    $count_sql = "SELECT COUNT(*) as total FROM utilisateur $where_clause";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_clients = $stmt_count->fetch()['total'];
    $total_pages = ceil($total_clients / $limit);
    
    // Récupérer les clients
    $sql = "SELECT *, 
                   (SELECT COUNT(*) FROM commande c WHERE c.id_utilisateur = u.id_utilisateur) as nb_commandes,
                   (SELECT SUM(montant_total) FROM commande c WHERE c.id_utilisateur = u.id_utilisateur AND c.statut IN ('livree', 'expediee')) as montant_total_commandes
            FROM utilisateur u 
            $where_clause 
            ORDER BY date_inscription DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $clients = $stmt->fetchAll();
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des clients : " . $e->getMessage();
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
        
        /* Utiliser le même style que le dashboard admin */
        <?php 
        // On réutilise les styles du dashboard admin
        // Vous pouvez copier le CSS de votre dashboard_admin.php ici
        // ou créer un fichier CSS séparé
        ?>
        
        /* Style spécifique pour la gestion des clients */
        .client-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .client-stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .client-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 20px;
            color: white;
        }
        
        .client-stat-icon.total { background: var(--primary); }
        .client-stat-icon.active { background: var(--success); }
        .client-stat-icon.inactive { background: var(--warning); }
        .client-stat-icon.today { background: var(--info); }
        .client-stat-icon.orders { background: var(--danger); }
        
        .client-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 15px;
        }
        
        .client-info {
            flex: 1;
        }
        
        .client-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .client-email {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .client-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #888;
        }
        
        .client-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
            background: white;
            transition: all 0.3s;
        }
        
        .client-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }
        
        .client-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
            margin-right: 15px;
        }
        
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        
        .client-actions {
            display: flex;
            gap: 5px;
        }
        
        /* Modal pour voir les détails du client */
        .client-details-modal .modal-content {
            max-width: 600px;
        }
        
        .client-details-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .client-details-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        
        .client-details-info h3 {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .client-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--dark);
        }
        
        .client-orders {
            margin-top: 30px;
        }
        
        .orders-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .order-item {
            padding: 10px 15px;
            border: 1px solid #eee;
            border-radius: 5px;
            margin-bottom: 10px;
            background: white;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .order-number {
            font-weight: 600;
            color: var(--primary);
        }
        
        .order-date {
            color: #666;
            font-size: 12px;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
        }
        
        .order-amount {
            font-weight: 600;
            color: var(--dark);
        }
        
        .order-status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Sidebar (identique au dashboard) -->
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
            <a href="#" class="nav-item active">
                <i class="fas fa-users"></i>
                <span>Clients</span>
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
            <h1><i class="fas fa-users"></i> Gestion des Clients</h1>
        </div>
        
        <!-- Notifications -->
        <?php if ($message): ?>
            <div class="notification notification-<?php echo $message_type; ?>" id="notification">
                <span><?php echo htmlspecialchars($message); ?></span>
                <button class="notification-close" onclick="closeNotification()">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <div class="client-stats">
            <div class="client-stat-card">
                <div class="client-stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $stats['total_clients']; ?></h3>
                <p>Clients total</p>
            </div>
            
            <div class="client-stat-card">
                <div class="client-stat-icon active">
                    <i class="fas fa-user-check"></i>
                </div>
                <h3><?php echo $stats['clients_actifs']; ?></h3>
                <p>Clients actifs</p>
            </div>
            
            <div class="client-stat-card">
                <div class="client-stat-icon inactive">
                    <i class="fas fa-user-times"></i>
                </div>
                <h3><?php echo $stats['clients_inactifs']; ?></h3>
                <p>Clients inactifs</p>
            </div>
            
            <div class="client-stat-card">
                <div class="client-stat-icon today">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <h3><?php echo $stats['clients_aujourdhui']; ?></h3>
                <p>Inscrits aujourd'hui</p>
            </div>
            
            <div class="client-stat-card">
                <div class="client-stat-icon orders">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3><?php echo $stats['clients_avec_commandes']; ?></h3>
                <p>Clients avec commandes</p>
            </div>
        </div>
        
        <!-- Filtres et recherche -->
        <div class="filters">
            <form method="GET" class="filter-group">
                <select name="filtre" class="filter-select" onchange="this.form.submit()">
                    <option value="tous" <?php echo $filtre === 'tous' ? 'selected' : ''; ?>>Tous les clients</option>
                    <option value="actifs" <?php echo $filtre === 'actifs' ? 'selected' : ''; ?>>Clients actifs</option>
                    <option value="inactifs" <?php echo $filtre === 'inactifs' ? 'selected' : ''; ?>>Clients inactifs</option>
                </select>
                
                <input type="text" name="recherche" class="search-input" placeholder="Rechercher un client..." value="<?php echo htmlspecialchars($recherche); ?>">
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Rechercher
                </button>
                
                <?php if (!empty($recherche) || $filtre !== 'tous'): ?>
                    <a href="admin_clients.php" class="btn">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Liste des clients -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-list"></i> Liste des clients</h2>
                <div style="color: #666;">
                    <?php echo $total_clients; ?> clients trouvés
                </div>
            </div>
            
            <?php if (empty($clients)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-users-slash" style="font-size: 48px; color: #ddd;"></i>
                    <p style="color: #666; margin-top: 15px;">Aucun client trouvé</p>
                </div>
            <?php else: ?>
                <?php foreach ($clients as $client): ?>
                    <div class="client-item">
                        <div class="client-avatar">
                            <?php echo strtoupper(substr($client['prenom'], 0, 1) . substr($client['nom'], 0, 1)); ?>
                        </div>
                        
                        <div class="client-info">
                            <div class="client-name"><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></div>
                            <div class="client-email"><?php echo htmlspecialchars($client['email']); ?></div>
                            <div class="client-meta">
                                <span><i class="fas fa-phone"></i> <?php echo $client['telephone'] ? htmlspecialchars($client['telephone']) : 'Non renseigné'; ?></span>
                                <span><i class="fas fa-shopping-cart"></i> <?php echo $client['nb_commandes']; ?> commandes</span>
                                <span><i class="fas fa-calendar"></i> Inscrit le <?php echo date('d/m/Y', strtotime($client['date_inscription'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="client-status <?php echo $client['est_actif'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $client['est_actif'] ? 'Actif' : 'Inactif'; ?>
                        </div>
                        
                        <div class="client-actions">
                            <button class="action-btn action-btn-view" onclick="viewClientDetails(<?php echo $client['id_utilisateur']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <a href="?toggle_client=<?php echo $client['id_utilisateur']; ?>" 
                               class="action-btn <?php echo $client['est_actif'] ? 'action-btn-edit' : 'action-btn-view'; ?>"
                               title="<?php echo $client['est_actif'] ? 'Désactiver' : 'Activer'; ?>">
                                <i class="fas fa-power-off"></i>
                            </a>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce client ?');">
                                <input type="hidden" name="id_utilisateur" value="<?php echo $client['id_utilisateur']; ?>">
                                <button type="submit" name="supprimer_client" class="action-btn action-btn-delete">
                                    <i class="fas fa-trash"></i>
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
    
    <!-- Modal Détails Client -->
    <div id="modalClientDetails" class="modal client-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Détails du client</h2>
                <button class="modal-close" onclick="closeModal('modalClientDetails')">&times;</button>
            </div>
            
            <div id="clientDetailsContent">
                <!-- Le contenu sera chargé via AJAX -->
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #ddd;"></i>
                    <p style="color: #666; margin-top: 15px;">Chargement...</p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('modalClientDetails')">Fermer</button>
            </div>
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
        
        // Voir les détails d'un client
        function viewClientDetails(id) {
            fetch(`../api/get_client_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const client = data.client;
                        const orders = data.orders || [];
                        
                        let ordersHtml = '';
                        if (orders.length > 0) {
                            ordersHtml = `
                                <div class="client-orders">
                                    <h3 style="margin-bottom: 15px; color: var(--dark);">Historique des commandes</h3>
                                    <div class="orders-list">
                            `;
                            
                            orders.forEach(order => {
                                const statusClass = getStatusClass(order.statut);
                                ordersHtml += `
                                    <div class="order-item">
                                        <div class="order-header">
                                            <span class="order-number">Commande #${order.numero_commande}</span>
                                            <span class="order-date">${order.date_commande}</span>
                                        </div>
                                        <div class="order-footer">
                                            <span class="order-amount">${parseFloat(order.montant_total).toLocaleString('fr-FR')} FCFA</span>
                                            <span class="order-status ${statusClass}">${order.statut}</span>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            ordersHtml += `
                                    </div>
                                </div>
                            `;
                        } else {
                            ordersHtml = `
                                <div class="client-orders">
                                    <h3 style="margin-bottom: 15px; color: var(--dark);">Historique des commandes</h3>
                                    <p style="color: #666; text-align: center; padding: 20px;">
                                        <i class="fas fa-shopping-cart" style="font-size: 32px; color: #ddd; margin-bottom: 10px; display: block;"></i>
                                        Aucune commande
                                    </p>
                                </div>
                            `;
                        }
                        
                        const content = `
                            <div class="client-details-header">
                                <div class="client-details-avatar">
                                    ${client.prenom.charAt(0).toUpperCase()}${client.nom.charAt(0).toUpperCase()}
                                </div>
                                <div class="client-details-info">
                                    <h3>${client.prenom} ${client.nom}</h3>
                                    <p style="color: #666;">${client.email}</p>
                                    <span class="client-status ${client.est_actif ? 'status-active' : 'status-inactive'}" style="display: inline-block;">
                                        ${client.est_actif ? 'Actif' : 'Inactif'}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="client-details-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Téléphone</div>
                                    <div class="detail-value">${client.telephone || 'Non renseigné'}</div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Adresse</div>
                                    <div class="detail-value">${client.adresse || 'Non renseignée'}</div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Date d'inscription</div>
                                    <div class="detail-value">${client.date_inscription}</div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Commandes totales</div>
                                    <div class="detail-value">${client.nb_commandes || 0} commandes</div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Montant total</div>
                                    <div class="detail-value">${parseFloat(client.montant_total_commandes || 0).toLocaleString('fr-FR')} FCFA</div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Dernière connexion</div>
                                    <div class="detail-value">${client.last_login || 'Jamais'}</div>
                                </div>
                            </div>
                            
                            ${ordersHtml}
                        `;
                        
                        document.getElementById('clientDetailsContent').innerHTML = content;
                        openModal('modalClientDetails');
                    } else {
                        alert('Erreur lors du chargement des détails du client');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur de connexion');
                });
        }
        
        function getStatusClass(status) {
            switch(status) {
                case 'en attente': return 'status-pending';
                case 'confirmee': return 'status-confirmed';
                case 'expediee': return 'status-shipped';
                case 'livree': return 'status-delivered';
                case 'annulee': return 'status-cancelled';
                default: return 'status-pending';
            }
        }
        
        // Export des clients en CSV
        function exportClients() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = `../api/export_clients.php?${params.toString()}`;
        }
        
        // Recherche en temps réel
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            let timeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    if (this.value.length >= 2) {
                        // Pourrait implémenter la recherche en temps réel ici
                        console.log('Recherche:', this.value);
                    }
                }, 500);
            });
        }
    </script>
</body>
</html>