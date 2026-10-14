<?php
// invoice.php - Page de facture
require_once 'includes/config.php';
require_once 'includes/campay_config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    header('Location: orders.php');
    exit;
}

// Récupérer les informations de la commande/facture
try {
    // Informations principales de la commande
    $stmt = $pdo->prepare("
        SELECT o.*, u.nom, u.prenom, u.email, u.phone, u.adresse 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['error'] = "Facture introuvable";
        header('Location: orders.php');
        exit;
    }
    
    // Récupérer les articles de la commande
    $stmt = $pdo->prepare("
        SELECT oi.*, p.nom as produit_nom, p.description, p.image 
        FROM order_items oi 
        JOIN produits p ON oi.produit_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
    
    // Récupérer l'historique des paiements
    $stmt = $pdo->prepare("
        SELECT * FROM payment_history 
        WHERE order_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$id]);
    $payments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur invoice: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors du chargement de la facture";
    header('Location: orders.php');
    exit;
}

$page_title = "Facture #" . str_pad($id, 6, '0', STR_PAD_LEFT) . " - " . SITE_NAME;
include 'includes/header.php';
?>

<style>
.invoice-container {
    max-width: 1000px;
    margin: 30px auto;
    padding: 40px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--primary-color);
}

.company-info h1 {
    color: var(--primary-color);
    font-size: 28px;
    margin-bottom: 5px;
    background: var(--gradient);
    /* -webkit-background-clip: text; */
    -webkit-text-fill-color: transparent;
}

.company-info p {
    color: #64748b;
    margin: 3px 0;
    font-size: 14px;
}

.invoice-title {
    text-align: right;
}

.invoice-title h2 {
    color: var(--dark-color);
    font-size: 32px;
    margin-bottom: 10px;
    letter-spacing: 2px;
}

.invoice-title p {
    color: #64748b;
    margin: 5px 0;
}

.invoice-badge {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 14px;
    margin-top: 10px;
}

.badge-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.badge-pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

.badge-failed {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.client-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

.client-info, .order-info {
    background: #f8fafc;
    border-radius: 15px;
    padding: 25px;
    border: 1px solid #e2e8f0;
}

.section-title {
    font-size: 18px;
    color: var(--dark-color);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-color);
    display: inline-block;
}

.info-grid {
    display: grid;
    gap: 15px;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-row i {
    width: 20px;
    color: var(--primary-color);
    font-size: 16px;
}

.info-row .label {
    color: #64748b;
    font-weight: 500;
    min-width: 80px;
}

.info-row .value {
    color: #1e293b;
    font-weight: 600;
}

.products-table {
    margin: 40px 0;
    overflow-x: auto;
}

.products-table table {
    width: 100%;
    border-collapse: collapse;
}

.products-table th {
    background: var(--gradient);
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 600;
}

.products-table td {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
}

.products-table tbody tr:hover {
    background: #f8fafc;
}

.product-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.product-image {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
}

.product-details h4 {
    color: var(--dark-color);
    margin-bottom: 5px;
}

.product-details p {
    color: #64748b;
    font-size: 13px;
}

.text-right {
    text-align: right;
}

.amount {
    font-weight: 600;
    color: #1e293b;
}

.total-section {
    margin: 30px 0;
    padding: 20px;
    background: #f8fafc;
    border-radius: 15px;
    border: 1px solid #e2e8f0;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
}

.total-row:last-child {
    border-bottom: none;
}

.total-row.grand-total {
    font-size: 20px;
    font-weight: 700;
    color: var(--primary-color);
    padding-top: 15px;
    margin-top: 5px;
    border-top: 2px solid var(--primary-color);
}

.payment-section {
    margin: 40px 0;
}

.payment-card {
    background: #f8fafc;
    border-radius: 15px;
    padding: 25px;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
}

.payment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.payment-method {
    display: flex;
    align-items: center;
    gap: 10px;
}

.payment-method i {
    font-size: 24px;
    color: var(--primary-color);
}

.payment-status {
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.payment-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.payment-detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.payment-detail-item .label {
    color: #64748b;
    font-size: 13px;
}

.payment-detail-item .value {
    font-weight: 600;
    color: #1e293b;
}

.notes-section {
    margin: 40px 0;
    padding: 25px;
    background: #fff3cd;
    border-radius: 15px;
    border: 1px solid #ffeeba;
}

.notes-section h3 {
    color: #856404;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.notes-section p {
    color: #856404;
    line-height: 1.6;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 40px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 12px 30px;
    border-radius: 30px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-primary {
    background: var(--gradient);
    color: white;
}

.btn-secondary {
    background: #e2e8f0;
    color: #1e293b;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.btn-print {
    background: #6c757d;
    color: white;
}

.footer-note {
    margin-top: 50px;
    text-align: center;
    color: #94a3b8;
    font-size: 13px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

/* Styles pour l'impression */
@media print {
    .action-buttons, .whatsapp-support, .btn-action {
        display: none !important;
    }
    
    .invoice-container {
        box-shadow: none;
        padding: 20px;
        margin: 0;
    }
    
    .products-table th {
        background: #f0f0f0 !important;
        color: black !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .badge-success, .badge-pending, .badge-failed {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

@media (max-width: 768px) {
    .invoice-container {
        padding: 20px;
        margin: 15px;
    }
    
    .invoice-header {
        flex-direction: column;
        gap: 20px;
    }
    
    .invoice-title {
        text-align: left;
    }
    
    .client-section {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .product-info {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .payment-details {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
}

.whatsapp-support {
    margin-top: 30px;
    padding: 15px;
    background: #f0f9ff;
    border-radius: 10px;
    border: 1px solid #bae6fd;
    text-align: center;
}

.whatsapp-support a {
    color: #25D366;
    font-weight: 600;
    text-decoration: none;
}

.whatsapp-support a:hover {
    text-decoration: underline;
}

.qr-code {
    text-align: center;
    margin: 30px 0;
}

.qr-code img {
    width: 150px;
    height: 150px;
}
</style>

<div class="invoice-container" id="invoice">
    <!-- En-tête de la facture -->
    <div class="invoice-header">
        <div class="company-info">
            <h1><?php echo SITE_NAME; ?></h1>
            <p><i class="fas fa-map-marker-alt"></i> Douala, Cameroun</p>
            <p><i class="fas fa-phone"></i> +237 6XX XXX XXX</p>
            <p><i class="fas fa-envelope"></i> contact@<?php echo strtolower(str_replace(' ', '', SITE_NAME)); ?>.com</p>
        </div>
        <div class="invoice-title">
            <h2>FACTURE</h2>
            <p>N° <?php echo str_pad($id, 6, '0', STR_PAD_LEFT); ?></p>
            <p>Date: <?php echo date('d/m/Y', strtotime($order['created_at'])); ?></p>
            <div class="invoice-badge badge-<?php echo strtolower($order['payment_status']); ?>">
                <?php 
                $status_labels = [
                    'SUCCESSFUL' => '✓ Payée',
                    'PENDING' => '⏳ En attente',
                    'FAILED' => '✗ Échouée'
                ];
                echo $status_labels[$order['payment_status']] ?? $order['payment_status'];
                ?>
            </div>
        </div>
    </div>

    <!-- Informations client et commande -->
    <div class="client-section">
        <div class="client-info">
            <h3 class="section-title"><i class="fas fa-user"></i> Client</h3>
            <div class="info-grid">
                <div class="info-row">
                    <i class="fas fa-user-circle"></i>
                    <span class="label">Nom:</span>
                    <span class="value"><?php echo htmlspecialchars($order['prenom'] . ' ' . $order['nom']); ?></span>
                </div>
                <div class="info-row">
                    <i class="fas fa-envelope"></i>
                    <span class="label">Email:</span>
                    <span class="value"><?php echo htmlspecialchars($order['email']); ?></span>
                </div>
                <div class="info-row">
                    <i class="fas fa-phone"></i>
                    <span class="label">Téléphone:</span>
                    <span class="value"><?php echo htmlspecialchars($order['phone']); ?></span>
                </div>
                <?php if (!empty($order['adresse'])): ?>
                <div class="info-row">
                    <i class="fas fa-map-marker-alt"></i>
                    <span class="label">Adresse:</span>
                    <span class="value"><?php echo htmlspecialchars($order['adresse']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="order-info">
            <h3 class="section-title"><i class="fas fa-shopping-cart"></i> Commande</h3>
            <div class="info-grid">
                <div class="info-row">
                    <i class="fas fa-hashtag"></i>
                    <span class="label">N° commande:</span>
                    <span class="value">#<?php echo str_pad($id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-row">
                    <i class="fas fa-calendar"></i>
                    <span class="label">Date:</span>
                    <span class="value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="info-row">
                    <i class="fas fa-credit-card"></i>
                    <span class="label">Référence:</span>
                    <span class="value"><?php echo htmlspecialchars($order['reference']); ?></span>
                </div>
                <?php if ($order['updated_at'] != $order['created_at']): ?>
                <div class="info-row">
                    <i class="fas fa-clock"></i>
                    <span class="label">Mise à jour:</span>
                    <span class="value"><?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tableau des produits -->
    <div class="products-table">
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Prix unitaire</th>
                    <th>Quantité</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sous_total = 0;
                foreach ($items as $item): 
                    $total_ligne = $item['quantite'] * $item['prix_unitaire'];
                    $sous_total += $total_ligne;
                ?>
                <tr>
                    <td>
                        <div class="product-info">
                            <?php if (!empty($item['image'])): ?>
                            <img src="uploads/produits/<?php echo htmlspecialchars($item['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['produit_nom']); ?>" 
                                 class="product-image">
                            <?php else: ?>
                            <div class="product-image" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-box" style="color: #94a3b8;"></i>
                            </div>
                            <?php endif; ?>
                            <div class="product-details">
                                <h4><?php echo htmlspecialchars($item['produit_nom']); ?></h4>
                                <?php if (!empty($item['description'])): ?>
                                <p><?php echo htmlspecialchars(substr($item['description'], 0, 50)) . '...'; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="amount"><?php echo number_format($item['prix_unitaire'], 0, ',', ' '); ?> FCFA</td>
                    <td><?php echo $item['quantite']; ?></td>
                    <td class="amount"><?php echo number_format($total_ligne, 0, ',', ' '); ?> FCFA</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Section des totaux -->
    <div class="total-section">
        <div class="total-row">
            <span>Sous-total</span>
            <span class="amount"><?php echo number_format($sous_total, 0, ',', ' '); ?> FCFA</span>
        </div>
        <?php if (isset($order['frais_livraison']) && $order['frais_livraison'] > 0): ?>
        <div class="total-row">
            <span>Frais de livraison</span>
            <span class="amount"><?php echo number_format($order['frais_livraison'], 0, ',', ' '); ?> FCFA</span>
        </div>
        <?php endif; ?>
        <?php if (isset($order['remise']) && $order['remise'] > 0): ?>
        <div class="total-row">
            <span>Remise</span>
            <span class="amount">-<?php echo number_format($order['remise'], 0, ',', ' '); ?> FCFA</span>
        </div>
        <?php endif; ?>
        <div class="total-row grand-total">
            <span>Total à payer</span>
            <span><?php echo number_format($order['total'] ?? $sous_total, 0, ',', ' '); ?> FCFA</span>
        </div>
    </div>

    <!-- Section des paiements -->
    <?php if (!empty($payments)): ?>
    <div class="payment-section">
        <h3 class="section-title"><i class="fas fa-credit-card"></i> Historique des paiements</h3>
        <?php foreach ($payments as $payment): ?>
        <div class="payment-card">
            <div class="payment-header">
                <div class="payment-method">
                    <i class="fas fa-mobile-alt"></i>
                    <span>Mobile Money</span>
                </div>
                <span class="payment-status badge-<?php echo strtolower($payment['status']); ?>">
                    <?php echo $status_labels[$payment['status']] ?? $payment['status']; ?>
                </span>
            </div>
            <div class="payment-details">
                <div class="payment-detail-item">
                    <span class="label">Montant</span>
                    <span class="value"><?php echo number_format($payment['amount'], 0, ',', ' '); ?> FCFA</span>
                </div>
                <div class="payment-detail-item">
                    <span class="label">Référence</span>
                    <span class="value"><?php echo htmlspecialchars($payment['reference']); ?></span>
                </div>
                <div class="payment-detail-item">
                    <span class="label">Date</span>
                    <span class="value"><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></span>
                </div>
                <?php if (!empty($payment['transaction_id'])): ?>
                <div class="payment-detail-item">
                    <span class="label">Transaction ID</span>
                    <span class="value"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Notes et informations importantes -->
    <div class="notes-section">
        <h3><i class="fas fa-info-circle"></i> Informations importantes</h3>
        <p>✓ Cette facture est valable comme justificatif d'achat.</p>
        <p>✓ Pour toute réclamation, contactez notre service client dans les 72 heures suivant la réception.</p>
        <p>✓ Les articles retournés doivent être dans leur état d'origine.</p>
        <?php if ($order['payment_status'] === 'PENDING'): ?>
        <p style="margin-top: 15px; color: #856404; background: #fff3cd; padding: 10px; border-radius: 8px;">
            <i class="fas fa-exclamation-triangle"></i>
            Cette commande est en attente de paiement. Veuillez procéder au paiement pour finaliser votre commande.
        </p>
        <?php endif; ?>
    </div>

    <!-- QR Code (optionnel) -->
    <?php if (function_exists('qr_code')): ?>
    <div class="qr-code">
        <img src="qr_code.php?data=<?php echo urlencode(json_encode([
            'facture' => str_pad($id, 6, '0', STR_PAD_LEFT),
            'montant' => $order['total'] ?? $sous_total,
            'date' => $order['created_at']
        ])); ?>" alt="QR Code">
        <p style="color: #94a3b8; font-size: 12px; margin-top: 5px;">Scannez pour vérifier la facture</p>
    </div>
    <?php endif; ?>

    <!-- Boutons d'action -->
    <div class="action-buttons">
        <button onclick="window.print()" class="btn-action btn-print">
            <i class="fas fa-print"></i> Imprimer la facture
        </button>
        
        <a href="orders.php" class="btn-action btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour aux commandes
        </a>
        
        <?php if ($order['payment_status'] === 'PENDING'): ?>
        <a href="payment.php?commande_id=<?php echo $id; ?>" class="btn-action btn-primary">
            <i class="fas fa-credit-card"></i> Payer maintenant
        </a>
        <?php endif; ?>
        
        <a href="download_invoice.php?id=<?php echo $id; ?>" class="btn-action btn-success">
            <i class="fas fa-download"></i> Télécharger PDF
        </a>
    </div>

    <!-- Support WhatsApp -->
    <div class="whatsapp-support">
        <i class="fab fa-whatsapp" style="color: #25D366;"></i>
        Une question sur cette facture ? <a href="https://wa.me/237XXXXXXXXX?text=Facture%20N°<?php echo str_pad($id, 6, '0', STR_PAD_LEFT); ?>%20-%20<?php echo urlencode($order['prenom'] . ' ' . $order['nom']); ?>" target="_blank">Contactez le support WhatsApp</a>
    </div>

    <!-- Pied de page -->
    <div class="footer-note">
        <p>Merci de votre confiance ! <?php echo SITE_NAME; ?> - Votre partenaire de confiance</p>
        <p>Cette facture est générée automatiquement et est valable sans signature.</p>
    </div>
</div>

<script>
// Fonction pour imprimer la facture
function printInvoice() {
    window.print();
}

// Ajouter une classe pour masquer les boutons lors de l'impression
window.onbeforeprint = function() {
    document.querySelectorAll('.action-buttons, .whatsapp-support').forEach(el => {
        el.style.display = 'none';
    });
};

window.onafterprint = function() {
    document.querySelectorAll('.action-buttons, .whatsapp-support').forEach(el => {
        el.style.display = 'flex';
    });
};

// Vérifier si la facture peut être téléchargée en PDF
document.addEventListener('DOMContentLoaded', function() {
    console.log('Facture #<?php echo str_pad($id, 6, '0', STR_PAD_LEFT); ?> chargée');
});
</script>

<?php include 'includes/footer.php'; ?>