<?php
// orders.php - Page de gestion des commandes
require_once 'includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'orders.php';
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$page_title = "Mes Commandes - " . SITE_NAME;

// Récupérer les catégories pour le header
$header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_show_products_link = true;

// Annulation de commande
if (isset($_GET['cancel_order'])) {
    $commande_id = intval($_GET['cancel_order']);
    
    // Vérifier que la commande appartient à l'utilisateur et est en attente
    $check = $pdo->prepare("SELECT statut FROM commande WHERE id_commande = ? AND id_utilisateur = ?");
    $check->execute([$commande_id, $user_id]);
    $commande = $check->fetch();
    
    if ($commande && $commande['statut'] === 'en attente') {
        $update = $pdo->prepare("UPDATE commande SET statut = 'annulee' WHERE id_commande = ?");
        $update->execute([$commande_id]);
        $_SESSION['success'] = "Commande annulée avec succès.";
    } else {
        $_SESSION['error'] = "Impossible d'annuler cette commande.";
    }
    header('Location: orders.php');
    exit;
}

// Récupérer les commandes de l'utilisateur
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(lc.id_produit) as nombre_articles,
           SUM(lc.quantite) as total_articles
    FROM commande c
    LEFT JOIN ligne_commande lc ON c.id_commande = lc.id_commande
    WHERE c.id_utilisateur = ?
    GROUP BY c.id_commande
    ORDER BY c.date_commande DESC
");
$stmt->execute([$user_id]);
$commandes = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
:root {
    --primary-color: #2d3d48;
    --secondary-color: #3f37c9;
    --success-color: #10b981;
    --danger-color: #ce1126;
    --warning-color: #f59e0b;
    --info-color: #4895ef;
    --dark-color: #1e293b;
    --light-color: #f8fafc;
    --gradient: linear-gradient(135deg, #2d3d48 0%, #1e2c34 100%);
    --payment-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f1f5f9;
    color: var(--dark-color);
}

/* Header */
.orders-header {
    background: var(--gradient);
    padding: 2rem;
    color: white;
    text-align: center;
}

.orders-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.orders-header h1 i {
    font-size: 2.5rem;
    color: rgba(255,255,255,0.9);
}

.orders-header p {
    font-size: 1.1rem;
    opacity: 0.9;
}

/* Messages flash */
.alert {
    max-width: 1200px;
    margin: 20px auto 0;
    padding: 15px 25px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 15px;
    animation: slideDown 0.5s ease;
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert i {
    font-size: 20px;
}

.alert .close-btn {
    margin-left: auto;
    cursor: pointer;
    font-size: 20px;
    opacity: 0.7;
    transition: opacity 0.3s;
}

.alert .close-btn:hover {
    opacity: 1;
}

/* Container principal */
.orders-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 20px;
}

/* Statistiques */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-info h3 {
    font-size: 28px;
    color: var(--dark-color);
    margin-bottom: 5px;
}

.stat-info p {
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
}

/* Filtres */
.orders-filters {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.search-box input {
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    font-size: 14px;
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(45, 61, 72, 0.1);
}

.filter-select {
    padding: 12px 25px;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    font-size: 14px;
    background: white;
    cursor: pointer;
    min-width: 150px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary-color);
}

/* Liste des commandes */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.order-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    transition: all 0.3s;
    border: 1px solid #e2e8f0;
}

.order-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
}

.order-header {
    background: linear-gradient(90deg, #f8fafc, white);
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.order-info h3 {
    font-size: 18px;
    color: var(--dark-color);
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.order-info h3 i {
    color: var(--primary-color);
}

.order-date {
    color: #64748b;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.order-status {
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-en-attente {
    background: #fef3c7;
    color: #92400e;
}

.status-confirmee {
    background: #dbeafe;
    color: #1e40af;
}

.status-payee {
    background: #d1fae5;
    color: #065f46;
}

.status-expediee {
    background: #cffafe;
    color: #0891b2;
}

.status-livree {
    background: #d1fae5;
    color: #065f46;
}

.status-annulee {
    background: #fee2e2;
    color: #991b1b;
}

/* Badge de paiement */
.payment-badge {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    animation: pulse 2s infinite;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
}

@keyframes pulse {
    0%, 100% { 
        opacity: 1;
        transform: scale(1);
    }
    50% { 
        opacity: 0.9;
        transform: scale(1.05);
    }
}

.order-body {
    padding: 20px;
}

.order-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 12px;
}

.summary-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: var(--gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.summary-details h4 {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 5px;
    font-weight: 500;
}

.summary-details p {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark-color);
}

.products-list {
    border-top: 1px solid #e2e8f0;
    padding-top: 20px;
}

.products-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--dark-color);
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 12px;
    transition: transform 0.3s;
}

.product-item:hover {
    transform: translateX(5px);
    background: #f1f5f9;
}

.product-image {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    overflow: hidden;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-image i {
    font-size: 24px;
    color: #94a3b8;
}

.product-details {
    flex: 1;
}

.product-name {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 3px;
    font-size: 14px;
}

.product-meta {
    font-size: 12px;
    color: #64748b;
}

.product-price {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 14px;
}

.order-footer {
    background: #f8fafc;
    padding: 15px 20px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.order-total {
    display: flex;
    align-items: center;
    gap: 20px;
}

.total-label {
    color: #64748b;
    font-size: 14px;
}

.total-amount {
    font-size: 24px;
    font-weight: 800;
    color: var(--primary-color);
}

.order-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: var(--gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px -5px rgba(45, 61, 72, 0.4);
}

.btn-outline {
    background: white;
    color: var(--dark-color);
    border: 1px solid #e2e8f0;
}

.btn-outline:hover {
    background: #f8fafc;
    border-color: var(--primary-color);
}

.btn-danger {
    background: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
    transform: translateY(-2px);
}

.btn-success {
    background: var(--success-color);
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-2px);
}

/* Bouton de paiement */
.btn-payment {
    background: var(--payment-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    position: relative;
    overflow: hidden;
    font-weight: 600;
    padding: 12px 25px;
}

.btn-payment::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-payment:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4);
}

.btn-payment:active::before {
    width: 300px;
    height: 300px;
}

.btn-payment i {
    font-size: 16px;
    animation: bounce 1s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(3px); }
}

.btn-payment .urgent-badge {
    background: rgba(255,255,255,0.2);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    margin-left: 8px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Empty state */
.empty-orders {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.empty-orders i {
    font-size: 80px;
    color: var(--primary-color);
    opacity: 0.3;
    margin-bottom: 20px;
}

.empty-orders h2 {
    font-size: 24px;
    color: var(--dark-color);
    margin-bottom: 10px;
}

.empty-orders p {
    color: #64748b;
    margin-bottom: 25px;
    font-size: 16px;
}

.empty-orders .btn {
    padding: 12px 30px;
    font-size: 16px;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
}

.page-link {
    padding: 10px 15px;
    border-radius: 8px;
    background: white;
    color: var(--dark-color);
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid #e2e8f0;
}

.page-link:hover {
    background: var(--gradient);
    color: white;
    border-color: transparent;
}

.page-link.active {
    background: var(--gradient);
    color: white;
    border-color: transparent;
}

.page-link.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* Responsive */
@media (max-width: 768px) {
    .orders-header h1 {
        font-size: 2rem;
    }
    
    .orders-filters {
        flex-direction: column;
    }
    
    .search-box {
        width: 100%;
    }
    
    .filter-select {
        width: 100%;
    }
    
    .order-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .order-summary {
        grid-template-columns: 1fr;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .order-footer {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .order-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .order-actions .btn {
        flex: 1;
        justify-content: center;
    }
}
</style>

<!-- Header de la page -->
<div class="orders-header">
    <h1>
        <i class="fas fa-shopping-bag"></i>
        Mes Commandes
    </h1>
    <p>Suivez et gérez toutes vos commandes en un seul endroit</p>
</div>

<!-- Messages flash -->
<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?php 
    echo $_SESSION['success'];
    unset($_SESSION['success']);
    ?>
    <span class="close-btn" onclick="this.parentElement.remove()">&times;</span>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i>
    <?php 
    echo $_SESSION['error'];
    unset($_SESSION['error']);
    ?>
    <span class="close-btn" onclick="this.parentElement.remove()">&times;</span>
</div>
<?php endif; ?>

<!-- Contenu principal -->
<div class="orders-container">
    <?php if (empty($commandes)): ?>
        <!-- Aucune commande -->
        <div class="empty-orders">
            <i class="fas fa-box-open"></i>
            <h2>Vous n'avez pas encore passé de commande</h2>
            <p>Découvrez nos produits et passez votre première commande !</p>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-store"></i>
                Voir les produits
            </a>
        </div>
    <?php else: ?>
        <!-- Statistiques -->
        <?php
        $total_commandes = count($commandes);
        $commandes_terminees = 0;
        $commandes_annulees = 0;
        $commandes_payees = 0;
        $montant_total = 0;
        
        foreach ($commandes as $cmd) {
            if ($cmd['statut'] === 'livree') $commandes_terminees++;
            if ($cmd['statut'] === 'annulee') $commandes_annulees++;
            if ($cmd['statut'] === 'payee') $commandes_payees++;
            $montant_total += floatval($cmd['montant_total']);
        }
        ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_commandes; ?></h3>
                    <p>Commandes totales</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $commandes_terminees; ?></h3>
                    <p>Commandes terminées</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $commandes_payees; ?></h3>
                    <p>Commandes payées</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($montant_total, 0, ',', ' '); ?> FCFA</h3>
                    <p>Montant total</p>
                </div>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="orders-filters">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchOrder" placeholder="Rechercher une commande...">
            </div>
            
            <select class="filter-select" id="filterStatus">
                <option value="">Tous les statuts</option>
                <option value="en attente">En attente</option>
                <option value="confirmee">Confirmée</option>
                <option value="payee">Payée</option>
                <option value="expediee">Expédiée</option>
                <option value="livree">Livrée</option>
                <option value="annulee">Annulée</option>
            </select>
            
            <select class="filter-select" id="sortBy">
                <option value="recent">Plus récent</option>
                <option value="ancien">Plus ancien</option>
                <option value="montant_desc">Montant (plus élevé)</option>
                <option value="montant_asc">Montant (plus faible)</option>
            </select>
        </div>
        
        <!-- Liste des commandes -->
        <div class="orders-list" id="ordersList">
            <?php foreach ($commandes as $commande): 
                // Récupérer les détails de la commande
                $stmt_details = $pdo->prepare("
                    SELECT lc.*, p.nom_produit, p.marque, p.modele,
                           (SELECT url_image FROM image_produit WHERE id_produit = p.id_produit AND est_principale = 1 LIMIT 1) as image
                    FROM ligne_commande lc
                    JOIN produit p ON lc.id_produit = p.id_produit
                    WHERE lc.id_commande = ?
                ");
                $stmt_details->execute([$commande['id_commande']]);
                $details = $stmt_details->fetchAll();
            ?>
            <div class="order-card" data-status="<?php echo $commande['statut']; ?>" data-date="<?php echo $commande['date_commande']; ?>" data-total="<?php echo $commande['montant_total']; ?>">
                <!-- En-tête de la commande -->
                <div class="order-header">
                    <div class="order-info">
                        <h3>
                            <i class="fas fa-receipt"></i>
                            Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?>
                        </h3>
                        <div class="order-date">
                            <i class="far fa-calendar-alt"></i>
                            <?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <div class="order-status status-<?php echo $commande['statut']; ?>">
                            <?php 
                            $status_labels = [
                                'en attente' => '⏳ En attente',
                                'confirmee' => '✅ Confirmée',
                                'payee' => '💰 Payée',
                                'expediee' => '🚚 Expédiée',
                                'livree' => '📦 Livrée',
                                'annulee' => '❌ Annulée'
                            ];
                            echo $status_labels[$commande['statut']] ?? ucfirst($commande['statut']);
                            ?>
                        </div>
                        
                        <?php if ($commande['statut'] === 'confirmee'): ?>
                        <span class="payment-badge">
                            <i class="fas fa-exclamation-circle"></i>
                            Paiement requis
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Corps de la commande -->
                <div class="order-body">
                    <div class="order-summary">
                        <div class="summary-item">
                            <div class="summary-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="summary-details">
                                <h4>Articles</h4>
                                <p><?php echo $commande['nombre_articles']; ?> produits</p>
                            </div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="summary-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="summary-details">
                                <h4>Quantité totale</h4>
                                <p><?php echo $commande['total_articles']; ?> unités</p>
                            </div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="summary-icon">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="summary-details">
                                <h4>Livraison</h4>
                                <p><?php echo $commande['mode_livraison'] ?? 'Standard'; ?></p>
                            </div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="summary-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="summary-details">
                                <h4>Paiement</h4>
                                <p>
                                    <?php 
                                    if ($commande['statut'] === 'payee') {
                                        echo 'Payé';
                                    } else {
                                        echo 'Non payé';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Liste des produits -->
                    <?php if (!empty($details)): ?>
                    <div class="products-list">
                        <div class="products-title">
                            <i class="fas fa-boxes"></i>
                            Produits commandés
                        </div>
                        
                        <div class="products-grid">
                            <?php foreach ($details as $item): ?>
                            <div class="product-item">
                                <div class="product-image">
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nom_produit']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="product-details">
                                    <div class="product-name"><?php echo htmlspecialchars($item['nom_produit']); ?></div>
                                    <div class="product-meta">
                                        <?php echo $item['marque'] . ' ' . $item['modele']; ?> × <?php echo $item['quantite']; ?>
                                    </div>
                                    <div class="product-price">
                                        <?php echo number_format($item['prix_unitaire'], 0, ',', ' '); ?> FCFA
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pied de la commande avec le bouton de paiement intégré -->
                <div class="order-footer">
                    <div class="order-total">
                        <span class="total-label">Total de la commande :</span>
                        <span class="total-amount"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                    </div>
                    
                    <div class="order-actions">
                        <a href="order-details.php?id=<?php echo $commande['id_commande']; ?>" class="btn btn-outline">
                            <i class="fas fa-eye"></i>
                            Détails
                        </a>
                        
                        <!-- BOUTON DE PAIEMENT - Visible pour les commandes en attente ou confirmées -->
                        <?php if ($commande['statut'] === 'confirmee' || $commande['statut'] === 'en attente'): ?>
                        <a href="paiement.php?commande_id=<?php echo $commande['id_commande']; ?>" class="btn btn-payment" title="Cliquez pour procéder au paiement sécurisé">
                            <i class="fas fa-credit-card"></i>
                            Payer maintenant
                            <?php if ($commande['statut'] === 'confirmee'): ?>
                            <span class="urgent-badge">
                                <i class="fas fa-clock"></i>
                                Urgent
                            </span>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($commande['statut'] === 'en attente'): ?>
                        <a href="?cancel_order=<?php echo $commande['id_commande']; ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette commande ?')">
                            <i class="fas fa-times"></i>
                            Annuler
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($commande['statut'] === 'livree'): ?>
                        <a href="review.php?order=<?php echo $commande['id_commande']; ?>" class="btn btn-primary">
                            <i class="fas fa-star"></i>
                            Noter
                        </a>
                        <?php endif; ?>
                        
                        <a href="invoice.php?id=<?php echo $commande['id_commande']; ?>" class="btn btn-outline" target="_blank">
                            <i class="fas fa-print"></i>
                            Facture
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if (count($commandes) > 10): ?>
        <div class="pagination">
            <a href="#" class="page-link disabled"><i class="fas fa-chevron-left"></i></a>
            <a href="#" class="page-link active">1</a>
            <a href="#" class="page-link">2</a>
            <a href="#" class="page-link">3</a>
            <a href="#" class="page-link">4</a>
            <a href="#" class="page-link">5</a>
            <a href="#" class="page-link"><i class="fas fa-chevron-right"></i></a>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Recherche et filtres
document.getElementById('searchOrder')?.addEventListener('keyup', function() {
    filterOrders();
});

document.getElementById('filterStatus')?.addEventListener('change', function() {
    filterOrders();
});

document.getElementById('sortBy')?.addEventListener('change', function() {
    filterOrders();
});

function filterOrders() {
    const searchTerm = document.getElementById('searchOrder')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('filterStatus')?.value || '';
    const sortBy = document.getElementById('sortBy')?.value || 'recent';
    
    const orders = document.querySelectorAll('.order-card');
    let ordersArray = Array.from(orders);
    
    // Filtrer
    ordersArray.forEach(order => {
        const orderText = order.textContent.toLowerCase();
        const orderStatus = order.dataset.status;
        
        let show = true;
        
        if (searchTerm && !orderText.includes(searchTerm)) {
            show = false;
        }
        
        if (statusFilter && orderStatus !== statusFilter) {
            show = false;
        }
        
        order.style.display = show ? 'block' : 'none';
    });
    
    // Trier
    if (sortBy) {
        const container = document.getElementById('ordersList');
        ordersArray = ordersArray.filter(order => order.style.display !== 'none');
        
        ordersArray.sort((a, b) => {
            const dateA = new Date(a.dataset.date);
            const dateB = new Date(b.dataset.date);
            const totalA = parseFloat(a.dataset.total);
            const totalB = parseFloat(b.dataset.total);
            
            switch(sortBy) {
                case 'recent':
                    return dateB - dateA;
                case 'ancien':
                    return dateA - dateB;
                case 'montant_desc':
                    return totalB - totalA;
                case 'montant_asc':
                    return totalA - totalB;
                default:
                    return 0;
            }
        });
        
        // Réorganiser dans le DOM
        ordersArray.forEach(order => {
            container.appendChild(order);
        });
    }
}

// Animation et suivi des clics sur le bouton de paiement
document.addEventListener('DOMContentLoaded', function() {
    // Ajouter un effet de clic sur les boutons de paiement
    document.querySelectorAll('.btn-payment').forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Animation de clic
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 200);
            
            // Récupérer l'ID de la commande depuis le lien
            const match = this.href.match(/commande_id=(\d+)/);
            if (match) {
                const orderId = match[1];
                console.log('Redirection vers le paiement pour la commande #' + orderId);
            }
        });
        
        // Ajouter un tooltip au survol
        btn.addEventListener('mouseenter', function() {
            this.setAttribute('title', 'Cliquez pour procéder au paiement sécurisé');
        });
    });
    
    // Compter le nombre de commandes en attente de paiement
    const pendingPayments = document.querySelectorAll('.payment-badge').length;
    if (pendingPayments > 0) {
        console.log(`${pendingPayments} commande(s) en attente de paiement`);
    }
});
</script>

<?php include 'includes/footer.php'; ?>