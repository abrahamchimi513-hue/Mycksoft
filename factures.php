<?php
// facture.php - Page de facture après paiement
require_once 'includes/config.php';

$page_title = "Facture - " . SITE_NAME;

// Récupérer l'ID de la commande (depuis l'URL ou la session)
$commande_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['derniere_facture_id']) ? $_SESSION['derniere_facture_id'] : 0);

if ($commande_id <= 0) {
    $_SESSION['error'] = "Aucune facture à afficher.";
    header('Location: orders.php');
    exit;
}

// Vérifier que l'utilisateur est connecté et a accès à cette commande
$user_id = getUserId();

$stmt = $pdo->prepare("
    SELECT c.*, 
           u.nom, 
           u.prenom, 
           u.email, 
           u.telephone, 
           u.adresse,
           u.ville,
           p.mode_paiement as mode_paiement,
           p.reference_paiement,
           p.date_paiement,
           p.statut_paiement
    FROM commande c
    JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
    LEFT JOIN paiement p ON c.id_commande = p.id_commande
    WHERE c.id_commande = ? AND c.id_utilisateur = ?
");
$stmt->execute([$commande_id, $user_id]);
$commande = $stmt->fetch();

if (!$commande) {
    $_SESSION['error'] = "Commande non trouvée.";
    header('Location: orders.php');
    exit;
}

// Récupérer les articles de la commande
$stmt_articles = $pdo->prepare("
    SELECT lc.*, 
           p.nom_produit, 
           p.marque, 
           p.modele,
           (SELECT url_image FROM image_produit WHERE id_produit = p.id_produit AND est_principale = 1 LIMIT 1) as image
    FROM ligne_commande lc
    JOIN produit p ON lc.id_produit = p.id_produit
    WHERE lc.id_commande = ?
");
$stmt_articles->execute([$commande_id]);
$articles = $stmt_articles->fetchAll();

// Générer un numéro de facture unique
$numero_facture = 'FACT-' . date('Ymd') . '-' . str_pad($commande_id, 6, '0', STR_PAD_LEFT);

include 'includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

:root {
    --primary: #4361ee;
    --primary-dark: #3a56d4;
    --primary-light: #eef2ff;
    --success: #10b981;
    --success-dark: #059669;
    --success-light: #d1fae5;
    --warning: #f59e0b;
    --dark: #1e293b;
    --gray: #64748b;
    --light: #f8fafc;
    --border: #e2e8f0;
    --shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1);
    --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    --gradient-primary: linear-gradient(135deg, #4361ee, #3a56d4);
    --gradient-success: linear-gradient(135deg, #10b981, #059669);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0e7ff 100%);
    min-height: 100vh;
    padding: 40px 20px;
}

/* Container principal */
.facture-container {
    max-width: 1100px;
    margin: 0 auto;
}

/* En-tête de la facture */
.facture-header {
    text-align: center;
    margin-bottom: 40px;
    animation: slideDown 0.6s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.facture-header .logo {
    width: 80px;
    height: 80px;
    background: var(--gradient-primary);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
}

.facture-header .logo i {
    font-size: 40px;
    color: white;
}

.facture-header h1 {
    font-size: 32px;
    font-weight: 800;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 8px;
}

.facture-header p {
    color: var(--gray);
}

/* Badge de succès */
.success-badge {
    background: var(--gradient-success);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.success-badge i {
    font-size: 20px;
}

/* Carte facture */
.facture-card {
    background: white;
    border-radius: 32px;
    box-shadow: var(--shadow);
    overflow: hidden;
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Entête de la carte */
.card-header {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: white;
    padding: 30px 40px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}

.company-info h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 8px;
}

.company-info p {
    font-size: 13px;
    opacity: 0.8;
    margin-bottom: 4px;
}

.facture-number {
    text-align: right;
}

.facture-number .label {
    font-size: 12px;
    opacity: 0.7;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.facture-number .number {
    font-size: 28px;
    font-weight: 800;
    font-family: monospace;
    margin-top: 5px;
}

/* Corps de la carte */
.card-body {
    padding: 40px;
}

/* Grille d'informations */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 2px dashed var(--border);
}

.info-section h4 {
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--gray);
    margin-bottom: 15px;
}

.info-section h4 i {
    margin-right: 8px;
    color: var(--primary);
}

.info-section p {
    margin-bottom: 8px;
    color: var(--dark);
}

.info-section strong {
    font-weight: 700;
}

/* Tableau des articles */
.articles-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
}

.articles-table th {
    background: var(--light);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    border-bottom: 2px solid var(--border);
}

.articles-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.articles-table tr:last-child td {
    border-bottom: none;
}

.article-image {
    width: 50px;
    height: 50px;
    background: var(--light);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.article-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.article-image i {
    font-size: 24px;
    color: var(--gray);
}

.article-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
}

.article-meta {
    font-size: 12px;
    color: var(--gray);
}

/* Totaux */
.totaux {
    background: var(--light);
    border-radius: 20px;
    padding: 25px;
    margin-top: 20px;
}

.totaux-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.totaux-row:last-child {
    border-bottom: none;
}

.totaux-row .label {
    color: var(--gray);
}

.totaux-row .value {
    font-weight: 600;
    color: var(--dark);
}

.totaux-row.total {
    margin-top: 10px;
    padding-top: 15px;
    border-top: 2px solid var(--border);
    font-size: 20px;
}

.totaux-row.total .label {
    font-weight: 700;
    color: var(--dark);
}

.totaux-row.total .value {
    font-size: 28px;
    font-weight: 800;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Pied de page */
.card-footer {
    background: var(--light);
    padding: 30px 40px;
    text-align: center;
    border-top: 1px solid var(--border);
}

.card-footer p {
    color: var(--gray);
    font-size: 13px;
    margin-bottom: 5px;
}

/* Boutons d'action */
.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    padding: 14px 28px;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    border: none;
    font-family: 'Inter', sans-serif;
}

.btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
}

.btn-secondary {
    background: white;
    color: var(--dark);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-3px);
}

.btn-success {
    background: var(--gradient-success);
    color: white;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
}

.btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

/* Message si commande non payée */
.warning-message {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 2px solid var(--warning);
    border-radius: 20px;
    padding: 20px 30px;
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.warning-message i {
    font-size: 40px;
    color: var(--warning);
}

.warning-message p {
    color: #92400e;
    font-weight: 500;
}

/* Responsive */
@media print {
    body {
        background: white;
        padding: 0;
        margin: 0;
    }
    
    .action-buttons,
    .facture-header .btn,
    .warning-message .btn,
    .success-badge {
        display: none !important;
    }
    
    .facture-card {
        box-shadow: none;
        border-radius: 0;
    }
    
    .card-header {
        background: #1e293b;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .totaux-row.total .value {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

@media (max-width: 768px) {
    .card-header {
        flex-direction: column;
        text-align: center;
    }
    
    .facture-number {
        text-align: center;
    }
    
    .card-body {
        padding: 25px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .articles-table {
        font-size: 13px;
    }
    
    .articles-table th,
    .articles-table td {
        padding: 10px;
    }
    
    .btn {
        padding: 12px 20px;
        font-size: 14px;
    }
    
    .totaux-row.total .value {
        font-size: 22px;
    }
}

/* Animation pour les icônes */
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

.facture-header .logo i {
    animation: bounce 2s ease-in-out infinite;
}
</style>

<div class="facture-container">
    <div class="facture-header">
        <div class="logo">
            <i class="fas fa-receipt"></i>
        </div>
        <h1>Facture</h1>
        <p>Confirmation de votre commande</p>
    </div>
    
    <?php if ($commande['statut'] === 'payee'): ?>
    <div style="text-align: center;">
        <div class="success-badge">
            <i class="fas fa-check-circle"></i>
            <span>Paiement confirmé !</span>
        </div>
    </div>
    <?php else: ?>
    <div class="warning-message">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Commande en attente de paiement</strong>
            <p>Cette facture sera disponible après confirmation du paiement.</p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="facture-card">
        <div class="card-header">
            <div class="company-info">
                <h3><?php echo SITE_NAME; ?></h3>
                <p><i class="fas fa-map-marker-alt"></i> Douala, Cameroun</p>
                <p><i class="fas fa-phone"></i> +237 6XX XXX XXX</p>
                <p><i class="fas fa-envelope"></i> <?php echo SMTP_FROM_EMAIL; ?></p>
            </div>
            <div class="facture-number">
                <div class="label">NUMÉRO DE FACTURE</div>
                <div class="number"><?php echo $numero_facture; ?></div>
                <div class="label" style="margin-top: 15px;">DATE D'ÉMISSION</div>
                <div class="number" style="font-size: 18px;"><?php echo date('d/m/Y H:i'); ?></div>
            </div>
        </div>
        
        <div class="card-body">
            <div class="info-grid">
                <div class="info-section">
                    <h4><i class="fas fa-user"></i> FACTURÉ À</h4>
                    <p><strong><?php echo htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']); ?></strong></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($commande['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($commande['telephone']); ?></p>
                    <?php if (!empty($commande['adresse'])): ?>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo nl2br(htmlspecialchars($commande['adresse'])); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="info-section">
                    <h4><i class="fas fa-shopping-cart"></i> DÉTAILS COMMANDE</h4>
                    <p><strong>Commande N° :</strong> <?php echo htmlspecialchars($commande['numero_commande']); ?></p>
                    <p><strong>Date commande :</strong> <?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></p>
                    <?php if ($commande['date_paiement']): ?>
                    <p><strong>Date paiement :</strong> <?php echo date('d/m/Y H:i', strtotime($commande['date_paiement'])); ?></p>
                    <?php endif; ?>
                    <?php if ($commande['mode_paiement']): ?>
                    <p><strong>Mode paiement :</strong> 
                        <?php 
                        if ($commande['mode_paiement'] === 'mobile_money') {
                            echo '<i class="fas fa-mobile-alt"></i> Mobile Money';
                        } elseif ($commande['mode_paiement'] === 'orange') {
                            echo '<i class="fas fa-mobile-alt" style="color: #ff8c00;"></i> Orange Money';
                        } elseif ($commande['mode_paiement'] === 'mtn') {
                            echo '<i class="fas fa-mobile-alt" style="color: #fcd116;"></i> MTN Money';
                        } else {
                            echo ucfirst($commande['mode_paiement']);
                        }
                        ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($commande['reference_paiement']): ?>
                    <p><strong>Référence :</strong> <code><?php echo htmlspecialchars($commande['reference_paiement']); ?></code></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <h4 style="margin-bottom: 20px;"><i class="fas fa-boxes"></i> Articles commandés</h4>
            
            <table class="articles-table">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Référence</th>
                        <th>Quantité</th>
                        <th>Prix unitaire</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="article-image">
                                    <?php if (!empty($article['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['nom_produit']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="article-name"><?php echo htmlspecialchars($article['nom_produit']); ?></div>
                                    <div class="article-meta"><?php echo htmlspecialchars($article['marque'] . ' ' . $article['modele']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($article['id_produit']); ?></td>
                        <td><?php echo $article['quantite']; ?></td>
                        <td><?php echo number_format($article['prix_unitaire'], 0, ',', ' '); ?> FCFA</td>
                        <td><strong><?php echo number_format($article['prix_unitaire'] * $article['quantite'], 0, ',', ' '); ?> FCFA</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="totaux">
                <div class="totaux-row">
                    <span class="label">Sous-total</span>
                    <span class="value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="totaux-row">
                    <span class="label">Frais de livraison</span>
                    <span class="value" style="color: var(--success);">Gratuit</span>
                </div>
                <div class="totaux-row total">
                    <span class="label">Total TTC</span>
                    <span class="value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                </div>
            </div>
        </div>
        
        <div class="card-footer">
            <p><i class="fas fa-check-circle" style="color: var(--success);"></i> Merci de votre confiance !</p>
            <p>Cette facture fait office de justificatif de paiement.</p>
            <p style="font-size: 11px;">En cas de question, contactez notre service client.</p>
        </div>
    </div>
    
    <div class="action-buttons">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimer / Télécharger PDF
        </button>
        <a href="orders.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour à mes commandes
        </a>
        <a href="index.php" class="btn btn-success">
            <i class="fas fa-home"></i> Continuer mes achats
        </a>
    </div>
</div>

<script>
// Animation de chargement
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier si on vient d'un paiement réussi
    <?php if (isset($_SESSION['derniere_facture_id']) && $_SESSION['derniere_facture_id'] == $commande_id): ?>
    // Afficher une notification de succès
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        padding: 15px 25px;
        border-radius: 50px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        z-index: 1000;
        animation: slideInRight 0.5s ease-out;
        cursor: pointer;
    `;
    notification.innerHTML = '<i class="fas fa-check-circle"></i> Paiement confirmé ! Votre facture est prête.';
    notification.onclick = function() {
        this.style.animation = 'slideOutRight 0.5s ease-out';
        setTimeout(() => this.remove(), 500);
    };
    document.body.appendChild(notification);
    
    // Disparition automatique après 5 secondes
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.5s ease-out';
            setTimeout(() => notification.remove(), 500);
        }
    }, 5000);
    
    <?php unset($_SESSION['derniere_facture_id']); ?>
    <?php endif; ?>
});

// Style pour l'animation de disparition
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'includes/footer.php'; ?>