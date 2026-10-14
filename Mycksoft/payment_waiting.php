<?php
// payment_waiting.php - Page d'attente de confirmation de paiement
require_once 'includes/config.php';
require_once 'includes/campay_config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$reference = $_GET['reference'] ?? '';
$commande_id = $_GET['commande_id'] ?? 0;

if (!$reference || !$commande_id) {
    header('Location: orders.php');
    exit;
}

$page_title = "Traitement du paiement - " . SITE_NAME;
include 'includes/header.php';
?>

<style>
.waiting-container {
    max-width: 600px;
    margin: 50px auto;
    padding: 40px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    text-align: center;
}

.spinner {
    width: 80px;
    height: 80px;
    margin: 0 auto 30px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid var(--success-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.success-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 30px;
    background: var(--success-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
    }
    to {
        transform: scale(1);
    }
}

.error-icon {
    background: var(--danger-color);
}

.waiting-title {
    font-size: 24px;
    color: var(--dark-color);
    margin-bottom: 15px;
}

.waiting-message {
    color: #64748b;
    margin-bottom: 30px;
    line-height: 1.6;
}

.info-box {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
    text-align: left;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
}

.info-row:last-child {
    border-bottom: none;
}

.btn-retour {
    display: inline-block;
    padding: 12px 30px;
    background: var(--gradient);
    color: white;
    text-decoration: none;
    border-radius: 30px;
    font-weight: 600;
    margin-top: 20px;
    transition: all 0.3s;
}

.btn-retour:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.progress-bar {
    width: 100%;
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    margin: 20px 0;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: var(--success-color);
    width: 0%;
    animation: progress 30s linear forwards;
}

@keyframes progress {
    from { width: 0%; }
    to { width: 100%; }
}
</style>

<div class="waiting-container" id="waitingContainer">
    <div class="spinner" id="spinner"></div>
    <h1 class="waiting-title">Traitement du paiement en cours</h1>
    <p class="waiting-message">
        Veuillez vérifier votre téléphone et entrer votre code PIN pour confirmer le paiement.
        Cette opération peut prendre quelques instants.
    </p>
    
    <div class="progress-bar">
        <div class="progress-bar-fill"></div>
    </div>
    
    <div class="info-box">
        <div class="info-row">
            <span>Référence:</span>
            <strong><?php echo htmlspecialchars($reference); ?></strong>
        </div>
        <div class="info-row">
            <span>Commande:</span>
            <strong>#<?php echo htmlspecialchars($commande_id); ?></strong>
        </div>
    </div>
    
    <p style="color: #94a3b8; font-size: 14px;">
        <i class="fas fa-clock"></i>
        Vous serez redirigé automatiquement après confirmation
    </p>
</div>

<script>
let checkInterval;
let timeout;
let attempts = 0;
const maxAttempts = 30; // 30 tentatives (30 secondes)

function checkPaymentStatus() {
    fetch(`check_payment_status.php?reference=<?php echo $reference; ?>&commande_id=<?php echo $commande_id; ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.status === 'SUCCESSFUL') {
                // Paiement réussi
                clearInterval(checkInterval);
                clearTimeout(timeout);
                showSuccess();
            } else if (data.status === 'FAILED') {
                // Paiement échoué
                clearInterval(checkInterval);
                clearTimeout(timeout);
                showError('Le paiement a échoué. Veuillez réessayer.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    
    attempts++;
    if (attempts >= maxAttempts) {
        clearInterval(checkInterval);
        showError('Le délai d\'attente est dépassé. Veuillez vérifier le statut de votre paiement dans vos commandes.');
    }
}

function showSuccess() {
    const container = document.getElementById('waitingContainer');
    container.innerHTML = `
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h1 class="waiting-title">Paiement réussi !</h1>
        <p class="waiting-message">
            Votre paiement a été confirmé avec succès.<br>
            Votre commande sera traitée dans les plus brefs délais.
        </p>
        <a href="orders.php" class="btn-retour">
            <i class="fas fa-arrow-left"></i>
            Retour à mes commandes
        </a>
    `;
    
    setTimeout(() => {
        window.location.href = 'orders.php?payment_success=1&commande_id=<?php echo $commande_id; ?>';
    }, 3000);
}

function showError(message) {
    const container = document.getElementById('waitingContainer');
    container.innerHTML = `
        <div class="success-icon error-icon">
            <i class="fas fa-times"></i>
        </div>
        <h1 class="waiting-title">Paiement non confirmé</h1>
        <p class="waiting-message">
            ${message || 'Le paiement n\'a pas pu être confirmé.'}
        </p>
        <a href="orders.php" class="btn-retour">
            <i class="fas fa-arrow-left"></i>
            Retour à mes commandes
        </a>
    `;
} 

// Démarrer la vérification
checkInterval = setInterval(checkPaymentStatus, 2000); // Vérifier toutes les 2 secondes
timeout = setTimeout(() => {
    clearInterval(checkInterval);
    showError('Le délai d\'attente est dépassé.');
}, 60000); // Timeout après 60 secondes

// Vérifier immédiatement au chargement
checkPaymentStatus();
</script>

<?php include 'includes/footer.php'; ?>