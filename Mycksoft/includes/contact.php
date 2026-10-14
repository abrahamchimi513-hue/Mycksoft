<?php
// dashboard_admin.php
require_once '../includes/config.php';

// Vérifier si l'utilisateur est connecté ET administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Déterminer quelle section afficher
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// Initialiser les variables pour toutes les sections
$page_title = "Dashboard Admin - Mycksoft";
$message = '';
$message_type = '';

// ============================================
// TRAITEMENTS COMMUNS POUR TOUTES LES SECTIONS
// ============================================

// Ajouter produit
if (isset($_POST['ajouter_produit'])) {
    try {
        // ... (votre code d'ajout de produit existant) ...
        $message = "Produit ajouté avec succès !";
        $message_type = "success";
        $section = 'produits'; // Rediriger vers la section produits
        
    } catch (Exception $e) {
        $message = "Erreur lors de l'ajout du produit : " . $e->getMessage();
        $message_type = "error";
    }
}

// Mettre à jour produit
if (isset($_POST['modifier_produit'])) {
    // ... (votre code de modification) ...
}

// Supprimer produit
if (isset($_GET['supprimer_produit'])) {
    // ... (votre code de suppression) ...
}

// Changer statut commande
if (isset($_POST['changer_statut'])) {
    // ... (votre code de changement de statut) ...
}

// Approuver un avis
if (isset($_POST['approuver_avis'])) {
    // ... (votre code d'approbation d'avis) ...
}

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================

try {
    // Récupérer les statistiques pour le dashboard
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

    // Récupérer les données selon la section active
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
            
        case 'parametres':
            $page_title = "Paramètres - Mycksoft";
            break;
            
        case 'dashboard':
        default:
            // Données pour le tableau de bord
            $produits_recent = $pdo->query("
                SELECT p.*, c.nom_categorie 
                FROM produit p 
                LEFT JOIN categorie c ON p.id_categorie = c.id_categorie 
                WHERE p.est_actif = 1 
                ORDER BY p.date_ajout DESC 
                LIMIT 10
            ")->fetchAll();
            
            $commandes_recent = $pdo->query("
                SELECT c.*, u.nom, u.prenom
                FROM commande c
                JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
                ORDER BY c.date_commande DESC
                LIMIT 10
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
    
    // Catégories pour formulaire (toujours nécessaire)
    $all_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();

} catch (Exception $e) {
    $message = "Erreur lors du chargement des données : " . $e->getMessage();
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
        /* VOTRE CSS EXISTANT - COPIEZ TOUT LE CSS ICI */
        /* ... [Tout votre CSS actuel] ... */
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
            <?php
            // Fonction pour vérifier si une section est active
            function isActive($section_name) {
                global $section;
                return ($section === $section_name) ? 'active' : '';
            }
            ?>
            
            <a href="?section=dashboard" class="nav-item <?php echo isActive('dashboard'); ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="?section=produits" class="nav-item <?php echo isActive('produits'); ?>">
                <i class="fas fa-box"></i>
                <span>Produits</span>
            </a>
            <a href="?section=commandes" class="nav-item <?php echo isActive('commandes'); ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
            </a>
            <a href="?section=clients" class="nav-item <?php echo isActive('clients'); ?>">
                <i class="fas fa-users"></i>
                <span>Clients</span>
            </a>
            <a href="?section=categories" class="nav-item <?php echo isActive('categories'); ?>">
                <i class="fas fa-tags"></i>
                <span>Catégories</span>
            </a>
            <a href="?section=messages" class="nav-item <?php echo isActive('messages'); ?>">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>
            <a href="?section=avis" class="nav-item <?php echo isActive('avis'); ?>">
                <i class="fas fa-star"></i>
                <span>Avis clients</span>
            </a>
            <a href="?section=parametres" class="nav-item <?php echo isActive('parametres'); ?>">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
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
        <!-- Header avec titre dynamique -->
        <div class="header">
            <h1>
                <?php 
                $icons = [
                    'dashboard' => 'fa-tachometer-alt',
                    'produits' => 'fa-box',
                    'commandes' => 'fa-shopping-cart',
                    'clients' => 'fa-users',
                    'categories' => 'fa-tags',
                    'messages' => 'fa-envelope',
                    'avis' => 'fa-star',
                    'parametres' => 'fa-cog'
                ];
                ?>
                <i class="fas <?php echo $icons[$section] ?? 'fa-tachometer-alt'; ?>"></i> 
                <?php echo $page_title; ?>
            </h1>
            <a href="?logout=1" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
        </div>
        
        <!-- Notifications -->
        <?php if ($message): ?>
            <div class="notification notification-<?php echo $message_type; ?>" id="notification">
                <span><?php echo htmlspecialchars($message); ?></span>
                <button class="notification-close" onclick="closeNotification()">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Contenu selon la section -->
        <?php if ($section === 'dashboard'): ?>
            <!-- ==================== SECTION TABLEAU DE BORD ==================== -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon product">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_produits']; ?></h3>
                        <p>Produits actifs</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon client">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_clients']; ?></h3>
                        <p>Clients inscrits</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon order">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_commandes']; ?></h3>
                        <p>Commandes totales</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['chiffre_affaires'], 0, ',', ' '); ?> FCFA</h3>
                        <p>Chiffre d'affaires</p>
                    </div>
                </div>
            </div>
            
            <!-- Produits récents -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-box"></i> Produits récents</h2>
                    <a href="?section=produits" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i>
                        Voir tous
                    </a>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits_recent as $produit): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($produit['nom_produit']); ?></strong><br>
                                <small><?php echo htmlspecialchars($produit['marque'] . ' ' . $produit['modele']); ?></small>
                            </td>
                            <td><?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA</td>
                            <td>
                                <span class="<?php echo $produit['stock'] <= 5 ? 'badge badge-warning' : 'badge badge-success'; ?>">
                                    <?php echo $produit['stock']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $produit['est_actif'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $produit['est_actif'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Commandes récentes -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-shopping-cart"></i> Commandes récentes</h2>
                    <a href="?section=commandes" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i>
                        Voir toutes
                    </a>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes_recent as $commande): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($commande['numero_commande']); ?></td>
                            <td><?php echo htmlspecialchars($commande['nom'] . ' ' . $commande['prenom']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></td>
                            <td><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</td>
                            <td>
                                <?php
                                $status_class = '';
                                switch($commande['statut']) {
                                    case 'en attente': $status_class = 'status-pending'; break;
                                    case 'confirmee': $status_class = 'status-confirmed'; break;
                                    case 'en preparation': $status_class = 'status-pending'; break;
                                    case 'expediee': $status_class = 'status-shipped'; break;
                                    case 'livree': $status_class = 'status-delivered'; break;
                                    case 'annulee': $status_class = 'status-cancelled'; break;
                                    default: $status_class = 'status-pending';
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($commande['statut']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Avis en attente -->
            <?php if (!empty($avis_attente)): ?>
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-star"></i> Avis en attente</h2>
                </div>
                
                <?php foreach ($avis_attente as $avis): ?>
                <div class="review-item" style="padding: 15px; border: 1px solid #eee; margin-bottom: 10px; border-radius: 5px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <strong><?php echo htmlspecialchars($avis['nom'] . ' ' . $avis['prenom']); ?></strong>
                            sur <strong><?php echo htmlspecialchars($avis['nom_produit']); ?></strong>
                            <div style="color: #f39c12; margin: 5px 0;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $avis['note'] ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                                <span style="margin-left: 10px; color: #666;"><?php echo $avis['note']; ?>/5</span>
                            </div>
                            <p style="margin-top: 5px;"><?php echo htmlspecialchars($avis['commentaire']); ?></p>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id_avis" value="<?php echo $avis['id_avis']; ?>">
                                <button type="submit" name="approuver_avis" class="btn btn-primary">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <small style="color: #999; font-size: 12px;">
                        <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($avis['date_avis'])); ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
        <?php elseif ($section === 'produits'): ?>
            <!-- ==================== SECTION PRODUITS ==================== -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-box"></i> Liste des produits</h2>
                    <button class="btn btn-primary" onclick="openModal('modalAjouter')">
                        <i class="fas fa-plus"></i>
                        Ajouter un produit
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $produit): ?>
                        <tr>
                            <td>#<?php echo $produit['id_produit']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($produit['nom_produit']); ?></strong><br>
                                <small><?php echo htmlspecialchars($produit['marque'] . ' ' . $produit['modele']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($produit['nom_categorie']); ?></td>
                            <td><?php echo number_format($produit['prix'], 0, ',', ' '); ?> FCFA</td>
                            <td>
                                <span class="<?php echo $produit['stock'] <= 5 ? 'badge badge-warning' : 'badge badge-success'; ?>">
                                    <?php echo $produit['stock']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $produit['est_actif'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $produit['est_actif'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn action-btn-view" onclick="viewProduct(<?php echo $produit['id_produit']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn action-btn-edit" onclick="editProduct(<?php echo $produit['id_produit']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?section=produits&supprimer_produit=<?php echo $produit['id_produit']; ?>" 
                                       class="action-btn action-btn-delete"
                                       onclick="return confirm('Voulez-vous vraiment désactiver ce produit?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($section === 'commandes'): ?>
            <!-- ==================== SECTION COMMANDES ==================== -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-shopping-cart"></i> Liste des commandes</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $commande): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($commande['numero_commande']); ?></td>
                            <td><?php echo htmlspecialchars($commande['nom'] . ' ' . $commande['prenom']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></td>
                            <td><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</td>
                            <td>
                                <?php
                                $status_class = '';
                                switch($commande['statut']) {
                                    case 'en attente': $status_class = 'status-pending'; break;
                                    case 'confirmee': $status_class = 'status-confirmed'; break;
                                    case 'en preparation': $status_class = 'status-pending'; break;
                                    case 'expediee': $status_class = 'status-shipped'; break;
                                    case 'livree': $status_class = 'status-delivered'; break;
                                    case 'annulee': $status_class = 'status-cancelled'; break;
                                    default: $status_class = 'status-pending';
                                }
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($commande['statut']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_commande" value="<?php echo $commande['id_commande']; ?>">
                                        <select name="statut" class="form-control" style="width: auto;" onchange="this.form.submit()">
                                            <option value="en attente" <?php echo $commande['statut'] == 'en attente' ? 'selected' : ''; ?>>En attente</option>
                                            <option value="confirmee" <?php echo $commande['statut'] == 'confirmee' ? 'selected' : ''; ?>>Confirmée</option>
                                            <option value="en preparation" <?php echo $commande['statut'] == 'en preparation' ? 'selected' : ''; ?>>En préparation</option>
                                            <option value="expediee" <?php echo $commande['statut'] == 'expediee' ? 'selected' : ''; ?>>Expédiée</option>
                                            <option value="livree" <?php echo $commande['statut'] == 'livree' ? 'selected' : ''; ?>>Livrée</option>
                                            <option value="annulee" <?php echo $commande['statut'] == 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                                        </select>
                                        <input type="hidden" name="changer_statut" value="1">
                                    </form>
                                    <button class="action-btn action-btn-view">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($section === 'clients'): ?>
            <!-- ==================== SECTION CLIENTS ==================== -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-users"></i> Liste des clients</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Commandes</th>
                            <th>Inscription</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>#<?php echo $client['id_utilisateur']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo $client['telephone'] ? htmlspecialchars($client['telephone']) : '-'; ?></td>
                            <td>
                                <span class="badge <?php echo $client['nb_commandes'] > 0 ? 'badge-success' : 'badge-info'; ?>">
                                    <?php echo $client['nb_commandes']; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($client['date_inscription'])); ?></td>
                            <td>
                                <span class="badge <?php echo $client['est_actif'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $client['est_actif'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn action-btn-view" onclick="viewClient(<?php echo $client['id_utilisateur']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn action-btn-edit" onclick="editClient(<?php echo $client['id_utilisateur']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($section === 'categories'): ?>
            <!-- ==================== SECTION CATÉGORIES ==================== -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-tags"></i> Liste des catégories</h2>
                    <button class="btn btn-primary" onclick="openModal('modalAjouterCategorie')">
                        <i class="fas fa-plus"></i>
                        Ajouter une catégorie
                    </button>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php foreach ($categories as $cat): ?>
                        <div style="border: 1px solid #eee; border-radius: 10px; padding: 20px; background: white; transition: all 0.3s;">
                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                <div style="width: 50px; height: 50px; border-radius: 8px; overflow: hidden; margin-right: 15px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                    <?php if ($cat['image_categorie']): ?>
                                        <img src="../<?php echo htmlspecialchars($cat['image_categorie']); ?>" alt="<?php echo htmlspecialchars($cat['nom_categorie']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-folder" style="color: #666;"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 style="margin: 0; color: var(--dark);"><?php echo htmlspecialchars($cat['nom_categorie']); ?></h3>
                                    <div style="display: flex; gap: 10px; margin-top: 5px;">
                                        <span style="font-size: 12px; color: #666;">
                                            <i class="fas fa-box"></i> <?php echo $cat['nb_produits']; ?> produits
                                        </span>
                                        <span style="font-size: 12px; color: #666;">
                                            <i class="fas fa-sitemap"></i> <?php echo $cat['nb_sous_categories']; ?> sous-cat.
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($cat['description']): ?>
                                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                                    <?php echo htmlspecialchars(substr($cat['description'], 0, 100)); ?>
                                    <?php if (strlen($cat['description']) > 100): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 10px;">
                                <button class="btn" style="padding: 8px 15px; font-size: 12px;" onclick="editCategory(<?php echo $cat['id_categorie']; ?>)">
                                    <i class="fas fa-edit"></i> Modifier
                                </button>
                                <a href="?section=categories&supprimer_categorie=<?php echo $cat['id_categorie']; ?>" 
                                   class="btn btn-danger" style="padding: 8px 15px; font-size: 12px;"
                                   onclick="return confirm('Êtes-vous sûr ?')">
                                    <i class="fas fa-trash"></i> Supprimer
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        <?php elseif ($section === 'avis'): ?>
            <!-- ==================== SECTION AVIS ==================== -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-star"></i> Avis clients</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Produit</th>
                            <th>Note</th>
                            <th>Commentaire</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avis as $avis_item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($avis_item['nom'] . ' ' . $avis_item['prenom']); ?></td>
                            <td><?php echo htmlspecialchars($avis_item['nom_produit']); ?></td>
                            <td>
                                <div style="color: #f39c12;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $avis_item['note'] ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                    <span style="margin-left: 5px;"><?php echo $avis_item['note']; ?>/5</span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars(substr($avis_item['commentaire'], 0, 100)); ?>...</td>
                            <td><?php echo date('d/m/Y', strtotime($avis_item['date_avis'])); ?></td>
                            <td>
                                <span class="badge <?php echo $avis_item['est_approuve'] ? 'badge-success' : 'badge-warning'; ?>">
                                    <?php echo $avis_item['est_approuve'] ? 'Approuvé' : 'En attente'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (!$avis_item['est_approuve']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id_avis" value="<?php echo $avis_item['id_avis']; ?>">
                                            <button type="submit" name="approuver_avis" class="action-btn action-btn-view" title="Approuver">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="action-btn action-btn-delete" onclick="deleteReview(<?php echo $avis_item['id_avis']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($section === 'messages'): ?>
            <!-- ==================== SECTION MESSAGES ==================== -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-envelope"></i> Messages de contact</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Expéditeur</th>
                            <th>Email</th>
                            <th>Sujet</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $message_item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($message_item['nom']); ?></td>
                            <td><?php echo htmlspecialchars($message_item['email']); ?></td>
                            <td><?php echo htmlspecialchars($message_item['sujet']); ?></td>
                            <td><?php echo htmlspecialchars(substr($message_item['contenu'], 0, 100)); ?>...</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($message_item['date_message'])); ?></td>
                            <td>
                                <span class="badge <?php echo $message_item['statut'] == 'nouveau' ? 'badge-warning' : 'badge-success'; ?>">
                                    <?php echo ucfirst($message_item['statut']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn action-btn-view" onclick="viewMessage(<?php echo $message_item['id_message']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn action-btn-delete" onclick="deleteMessage(<?php echo $message_item['id_message']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($section === 'parametres'): ?>
            <!-- ==================== SECTION PARAMÈTRES ==================== -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-cog"></i> Paramètres du site</h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nom du site</label>
                        <input type="text" class="form-control" value="Mycksoft">
                    </div>
                    
                    <div class="form-group">
                        <label>Email de contact</label>
                        <input type="email" class="form-control" value="contact@mycksoft.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" class="form-control" value="+237 6XX XX XX XX">
                    </div>
                    
                    <div class="form-group">
                        <label>Adresse</label>
                        <input type="text" class="form-control" value="Yaoundé, Cameroun">
                    </div>
                    
                    <div class="form-group form-full">
                        <label>Description du site</label>
                        <textarea class="form-control" rows="3">Votre partenaire informatique au Cameroun</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Maintenance</label>
                        <div>
                            <label style="margin-right: 20px;">
                                <input type="radio" name="maintenance" value="0" checked> Site actif
                            </label>
                            <label>
                                <input type="radio" name="maintenance" value="1"> Mode maintenance
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group form-full">
                        <button class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
    
    <!-- Modal Ajouter Produit -->
    <div id="modalAjouter" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajouter un produit</h2>
                <button class="modal-close" onclick="closeModal('modalAjouter')">&times;</button>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="section" value="<?php echo $section; ?>">
                <div class="form-grid">
                    <!-- ... Votre formulaire d'ajout de produit existant ... -->
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('modalAjouter')">Annuler</button>
                    <button type="submit" name="ajouter_produit" class="btn btn-primary">Ajouter le produit</button>
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
        
        // Fermer modal en cliquant en dehors
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Gestion des notifications
        function closeNotification() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 300);
            }
        }
        
        // Auto-fermeture des notifications après 5 secondes
        setTimeout(() => {
            closeNotification();
        }, 5000);
        
        // Fonctions pour les produits
        function viewProduct(id) {
            alert('Affichage détaillé du produit #' + id + ' - À implémenter');
        }
        
        function editProduct(id) {
            alert('Modification du produit #' + id + ' - À implémenter');
        }
        
        function viewClient(id) {
            alert('Affichage du client #' + id + ' - À implémenter');
        }
        
        function editClient(id) {
            alert('Modification du client #' + id + ' - À implémenter');
        }
        
        function editCategory(id) {
            alert('Modification de la catégorie #' + id + ' - À implémenter');
        }
        
        function viewMessage(id) {
            alert('Affichage du message #' + id + ' - À implémenter');
        }
        
        function deleteReview(id) {
            if (confirm('Voulez-vous vraiment supprimer cet avis?')) {
                window.location.href = '?section=avis&delete_review=' + id;
            }
        }
        
        function deleteMessage(id) {
            if (confirm('Voulez-vous vraiment supprimer ce message?')) {
                window.location.href = '?section=messages&delete_message=' + id;
            }
        }
    </script>
</body>
</html>