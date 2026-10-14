<?php
// paiement_attente.php - Page d'attente de confirmation Campay
require_once 'includes/config.php';
require_once 'includes/campay_config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$reference = isset($_GET['reference']) ? $_GET['reference'] : '';
$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;

if (!$reference || !$commande_id) {
    header('Location: orders.php');
    exit;
}

// Vérifier que la commande appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT id_commande, numero_commande, montant_total FROM commande WHERE id_commande = ? AND id_utilisateur = ?");
$stmt->execute([$commande_id, getUserId()]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: orders.php');
    exit;
}

$page_title = "Paiement en cours - " . SITE_NAME;
include 'includes/header.php';
?>

<style>
.waiting-container {
    max-width: 600px;
    margin: 100px auto;
    padding: 40px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    text-align: center;
}

.spinner {
    width: 80px;
    height: 80px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 30px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.reference-box {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    margin: 20px 0;
    font-family: monospace;
    font-size: 18px;
    color: var(--primary-color);
}

.phone-icon {
    font-size: 60px;
    color: #667eea;
    margin-bottom: 20px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.amount {
    font-size: 32px;
    font-weight: 800;
    color: var(--primary-color);
    margin: 20px 0;
}

.progress-bar {
    width: 100%;
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    margin: 30px 0;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    width: 0%;
    animation: progress 3s ease-in-out infinite;
}

@keyframes progress {
    0% { width: 0%; }
    50% { width: 70%; }
    100% { width: 100%; }
}

.btn-refresh {
    background: #f1f5f9;
    border: none;
    padding: 12px 30px;
    border-radius: 30px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 20px;
}

.btn-refresh:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
}

.instructions {
    text-align: left;
    background: #f0f9ff;
    padding: 20px;
    border-radius: 12px;
    margin-top: 30px;
}

.instructions h4 {
    color: var(--dark-color);
    margin-bottom: 15px;
}

.instructions ol {
    padding-left: 20px;
    color: #64748b;
}

.instructions li {
    margin-bottom: 10px;
}
</style>

<div class="waiting-container">
    <div class="spinner"></div>
    <i class="fas fa-mobile-alt phone-icon"></i>
    
    <h2>Paiement en cours de traitement</h2>
    <p>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></p>
    
    <div class="amount">
        <?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA
    </div>
    
    <div class="reference-box">
        Référence: <?php echo htmlspecialchars($reference); ?>
    </div>
    
    <p>Une demande de paiement a été envoyée à votre téléphone.</p>
    <p>Veuillez vérifier vos notifications et entrer votre code PIN pour confirmer.</p>
    
    <div class="progress-bar">
        <div class="progress-bar-fill"></div>
    </div>
    
    <p style="color: #94a3b8; font-size: 14px;">
        Cette page se mettra à jour automatiquement dès que le paiement sera confirmé.
    </p>
    
    <button onclick="window.location.reload()" class="btn-refresh">
        <i class="fas fa-sync-alt"></i> Rafraîchir manuellement
    </button>
    
    <div class="instructions">
        <h4><i class="fas fa-info-circle"></i> Instructions</h4>
        <ol>
            <li>Vous allez recevoir une notification sur votre téléphone</li>
            <li>Ouvrez la notification ou composez le #150#</li>
            <li>Entrez votre code PIN pour confirmer le paiement</li>
            <li>Une fois confirmé, cette page se mettra à jour automatiquement</li>
        </ol>
    </div>
</div>

<script>
// Vérifier le statut toutes les 5 secondes
const checkInterval = setInterval(function() {
    fetch('verifier_paiement.php?reference=<?php echo $reference; ?>&commande_id=<?php echo $commande_id; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.statut === 'SUCCESSFUL') {
                clearInterval(checkInterval);
                window.location.href = 'paiement.php?campay_success=1&reference=<?php echo $reference; ?>&commande_id=<?php echo $commande_id; ?>';
            }
        })
        .catch(error => console.error('Erreur:', error));
}, 5000);

// Nettoyer après 5 minutes
setTimeout(function() {
    clearInterval(checkInterval);
    window.location.href = 'orders.php?timeout=1';
}, 300000);
</script>

<?php include 'includes/footer.php'; ?>