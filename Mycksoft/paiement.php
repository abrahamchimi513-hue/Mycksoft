<?php
// paiement.php - Page de formulaire de paiement
require_once 'includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$page_title = "Paiement - " . SITE_NAME;

// Récupérer l'ID de la commande
$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;

// Vérifier que la commande appartient bien à l'utilisateur et est confirmée
$stmt = $pdo->prepare("
    SELECT c.*, u.nom, u.prenom, u.email, u.telephone 
    FROM commande c
    JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
    WHERE c.id_commande = ? AND c.id_utilisateur = ?
");
$stmt->execute([$commande_id, $user_id]);
$commande = $stmt->fetch();

if (!$commande) {
    $_SESSION['error'] = "Commande non trouvée.";
    header('Location: orders.php');
    exit;
}

// Vérifier que la commande est confirmée (peut être payée)
if ($commande['statut'] !== 'confirmee' && $commande['statut'] !== 'en attente') {
    $_SESSION['error'] = "Cette commande ne peut pas être payée.";
    header('Location: orders.php');
    exit;
}

// Récupérer les articles de la commande
$stmt_articles = $pdo->prepare("
    SELECT lc.*, p.nom_produit, p.marque, p.modele 
    FROM ligne_commande lc
    JOIN produit p ON lc.id_produit = p.id_produit
    WHERE lc.id_commande = ?
");
$stmt_articles->execute([$commande_id]);
$articles = $stmt_articles->fetchAll();

// Traitement du formulaire de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode_paiement = $_POST['mode_paiement'] ?? '';
    $reference_paiement = $_POST['reference_paiement'] ?? '';
    $nom_titulaire = $_POST['nom_titulaire'] ?? '';
    $errors = [];
    
    // Validation
    if (empty($mode_paiement)) {
        $errors[] = "Veuillez choisir un mode de paiement.";
    }
    
    if (empty($reference_paiement)) {
        $errors[] = "Veuillez entrer la référence de paiement.";
    }
    
    if ($mode_paiement === 'carte' && empty($nom_titulaire)) {
        $errors[] = "Veuillez entrer le nom du titulaire de la carte.";
    }
    
    if (empty($errors)) {
        try {
            // Mettre à jour le statut de la commande
            $update = $pdo->prepare("
                UPDATE commande 
                SET statut = 'payee', 
                    mode_paiement = ?, 
                    reference_paiement = ?, 
                    date_paiement = NOW() 
                WHERE id_commande = ? AND id_utilisateur = ?
            ");
            $update->execute([$mode_paiement, $reference_paiement, $commande_id, $user_id]);
            
            // Envoyer un email de confirmation (optionnel)
            // sendPaymentConfirmationEmail($commande['email'], $commande);
            
            $_SESSION['success'] = "Paiement effectué avec succès ! Votre commande sera bientôt expédiée.";
            header('Location: orders.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors du paiement. Veuillez réessayer.";
        }
    }
}

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

.paiement-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.paiement-wrapper {
    display: grid;
    grid-template-columns: 1fr 0.8fr;
    gap: 30px;
}

/* Colonne principale */
.paiement-main {
    background: white;
    border-radius: 24px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    overflow: hidden;
}

.paiement-header {
    background: var(--payment-gradient);
    color: white;
    padding: 30px;
    text-align: center;
}

.paiement-header i {
    font-size: 60px;
    margin-bottom: 15px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.paiement-header h1 {
    font-size: 28px;
    margin-bottom: 10px;
    font-weight: 600;
}

.paiement-header p {
    font-size: 16px;
    opacity: 0.9;
}

.paiement-body {
    padding: 40px;
}

/* Messages d'erreur */
.error-message {
    background: #fee2e2;
    color: #991b1b;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    border: 1px solid #fecaca;
}

.error-message i {
    font-size: 20px;
}

/* Modes de paiement */
.section-title {
    font-size: 18px;
    color: var(--dark-color);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.section-title i {
    color: var(--primary-color);
}

.modes-paiement {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.mode-paiement {
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.mode-paiement:hover {
    border-color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.mode-paiement.selected {
    border-color: var(--primary-color);
    background: linear-gradient(135deg, #f0f9ff 0%, #e6f0f9 100%);
}

.mode-paiement i {
    font-size: 40px;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.mode-paiement h3 {
    font-size: 16px;
    margin-bottom: 5px;
    color: var(--dark-color);
    font-weight: 600;
}

.mode-paiement p {
    font-size: 12px;
    color: #64748b;
}

/* Formulaire */
.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark-color);
    font-size: 14px;
}

.form-group label i {
    margin-right: 8px;
    color: var(--primary-color);
}

.form-control {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(45, 61, 72, 0.1);
}

.form-control::placeholder {
    color: #94a3b8;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* Bouton de paiement */
.btn-payer {
    width: 100%;
    padding: 18px;
    background: var(--payment-gradient);
    color: white;
    border: none;
    border-radius: 16px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-payer:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 30px rgba(16, 185, 129, 0.3);
}

.btn-payer i {
    font-size: 20px;
}

/* Récapitulatif */
.paiement-recap {
    background: white;
    border-radius: 24px;
    padding: 30px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    position: sticky;
    top: 20px;
}

.recap-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
}

.recap-header i {
    font-size: 30px;
    color: var(--primary-color);
    background: #f1f5f9;
    padding: 15px;
    border-radius: 50%;
}

.recap-header h2 {
    font-size: 20px;
    color: var(--dark-color);
}

.recap-header p {
    color: #64748b;
    font-size: 14px;
    margin-top: 5px;
}

.commande-info {
    background: #f8fafc;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px dashed #e2e8f0;
}

.info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 0;
}

.info-label {
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
}

.info-value {
    font-weight: 700;
    color: var(--dark-color);
}

.total-montant {
    font-size: 28px;
    color: var(--primary-color);
    font-weight: 800;
}

.articles-list {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.article-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 12px;
    margin-bottom: 10px;
}

.article-image {
    width: 50px;
    height: 50px;
    background: var(--gradient);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.article-details {
    flex: 1;
}

.article-name {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 3px;
}

.article-meta {
    font-size: 12px;
    color: #64748b;
}

.article-price {
    font-weight: 700;
    color: var(--primary-color);
}

.security-badge {
    background: #f0fdf4;
    border-radius: 12px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 25px;
    border: 1px solid #bbf7d0;
}

.security-badge i {
    font-size: 30px;
    color: var(--success-color);
}

.security-badge h4 {
    font-size: 16px;
    color: var(--dark-color);
    margin-bottom: 3px;
}

.security-badge p {
    font-size: 12px;
    color: #64748b;
}

/* Bouton retour */
.btn-retour {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    margin-top: 20px;
    padding: 10px 20px;
    border-radius: 12px;
    transition: all 0.3s;
}

.btn-retour:hover {
    background: #f1f5f9;
    transform: translateX(-5px);
}

/* Responsive */
@media (max-width: 968px) {
    .paiement-wrapper {
        grid-template-columns: 1fr;
    }
    
    .modes-paiement {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .paiement-recap {
        position: static;
    }
}

@media (max-width: 480px) {
    .paiement-body {
        padding: 20px;
    }
    
    .modes-paiement {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .paiement-header h1 {
        font-size: 24px;
    }
}
</style>

<div class="paiement-container">
    <div class="paiement-wrapper">
        <!-- Colonne principale : Formulaire de paiement -->
        <div class="paiement-main">
            <div class="paiement-header">
                <i class="fas fa-credit-card"></i>
                <h1>Finaliser votre paiement</h1>
                <p>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></p>
            </div>
            
            <div class="paiement-body">
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo $error; ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="paiementForm">
                    <div class="section-title">
                        <i class="fas fa-credit-card"></i>
                        Choisissez votre mode de paiement
                    </div>
                    
                    <div class="modes-paiement">
                        <div class="mode-paiement <?php echo (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'orange') ? 'selected' : ''; ?>" onclick="selectMode('orange')" id="mode-orange">
                            <i class="fas fa-mobile-alt"></i>
                            <h3>Orange Money</h3>
                            <p>Paiement via mobile money</p>
                        </div>
                        
                        <div class="mode-paiement <?php echo (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'mtn') ? 'selected' : ''; ?>" onclick="selectMode('mtn')" id="mode-mtn">
                            <i class="fas fa-mobile-alt"></i>
                            <h3>MTN Mobile Money</h3>
                            <p>Paiement via mobile money</p>
                        </div>
                        
                        <div class="mode-paiement <?php echo (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'carte') ? 'selected' : ''; ?>" onclick="selectMode('carte')" id="mode-carte">
                            <i class="fas fa-credit-card"></i>
                            <h3>Carte Bancaire</h3>
                            <p>Visa, Mastercard</p>
                        </div>
                    </div>
                    
                    <input type="hidden" name="mode_paiement" id="mode_paiement" value="<?php echo isset($_POST['mode_paiement']) ? htmlspecialchars($_POST['mode_paiement']) : ''; ?>">
                    
                    <div class="form-group">
                        <label for="reference_paiement">
                            <i class="fas fa-hashtag"></i>
                            Référence de paiement *
                        </label>
                        <input type="text" class="form-control" id="reference_paiement" name="reference_paiement" 
                               placeholder="Ex: OR12345678" value="<?php echo isset($_POST['reference_paiement']) ? htmlspecialchars($_POST['reference_paiement']) : ''; ?>" required>
                        <small style="color: #64748b; font-size: 12px; margin-top: 5px; display: block;">
                            Entrez le numéro de transaction reçu après le paiement
                        </small>
                    </div>
                    
                    <div id="carte-fields" style="display: none;">
                        <div class="form-group">
                            <label for="nom_titulaire">
                                <i class="fas fa-user"></i>
                                Nom du titulaire *
                            </label>
                            <input type="text" class="form-control" id="nom_titulaire" name="nom_titulaire" 
                                   placeholder="John Doe" value="<?php echo isset($_POST['nom_titulaire']) ? htmlspecialchars($_POST['nom_titulaire']) : ''; ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_expiration">
                                    <i class="far fa-calendar-alt"></i>
                                    Date d'expiration
                                </label>
                                <input type="text" class="form-control" id="date_expiration" placeholder="MM/AA">
                            </div>
                            
                            <div class="form-group">
                                <label for="cvv">
                                    <i class="fas fa-lock"></i>
                                    CVV
                                </label>
                                <input type="text" class="form-control" id="cvv" placeholder="123">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-payer">
                        <i class="fas fa-lock"></i>
                        Payer <?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA
                    </button>
                </form>
                
                <a href="orders.php" class="btn-retour">
                    <i class="fas fa-arrow-left"></i>
                    Retour à mes commandes
                </a>
            </div>
        </div>
        
        <!-- Colonne latérale : Récapitulatif -->
        <div class="paiement-recap">
            <div class="recap-header">
                <i class="fas fa-shopping-bag"></i>
                <div>
                    <h2>Récapitulatif</h2>
                    <p>Détails de votre commande</p>
                </div>
            </div>
            
            <div class="commande-info">
                <div class="info-row">
                    <span class="info-label">Numéro de commande</span>
                    <span class="info-value">#<?php echo htmlspecialchars($commande['numero_commande']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Date</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Client</span>
                    <span class="info-value"><?php echo htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Total articles</span>
                    <span class="info-value"><?php echo count($articles); ?> produit(s)</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Sous-total</span>
                    <span class="info-value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Livraison</span>
                    <span class="info-value">Gratuite</span>
                </div>
                
                <div class="info-row" style="border-bottom: none;">
                    <span class="info-label" style="font-size: 16px;">Total à payer</span>
                    <span class="total-montant"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                </div>
            </div>
            
            <div class="section-title">
                <i class="fas fa-box"></i>
                Articles commandés
            </div>
            
            <div class="articles-list">
                <?php foreach ($articles as $article): ?>
                <div class="article-item">
                    <div class="article-image">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="article-details">
                        <div class="article-name"><?php echo htmlspecialchars($article['nom_produit']); ?></div>
                        <div class="article-meta">
                            <?php echo htmlspecialchars($article['marque'] . ' ' . $article['modele']); ?> × <?php echo $article['quantite']; ?>
                        </div>
                    </div>
                    <div class="article-price">
                        <?php echo number_format($article['prix_unitaire'] * $article['quantite'], 0, ',', ' '); ?> FCFA
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <div>
                    <h4>Paiement 100% sécurisé</h4>
                    <p>Vos informations sont cryptées et protégées</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sélection du mode de paiement
function selectMode(mode) {
    // Mettre à jour le champ caché
    document.getElementById('mode_paiement').value = mode;
    
    // Retirer la classe selected de tous les modes
    document.querySelectorAll('.mode-paiement').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Ajouter la classe selected au mode choisi
    document.getElementById('mode-' + mode).classList.add('selected');
    
    // Afficher/cacher les champs spécifiques à la carte
    if (mode === 'carte') {
        document.getElementById('carte-fields').style.display = 'block';
        document.getElementById('nom_titulaire').setAttribute('required', 'required');
    } else {
        document.getElementById('carte-fields').style.display = 'none';
        document.getElementById('nom_titulaire').removeAttribute('required');
    }
}

// Si un mode est déjà sélectionné (après erreur de formulaire)
<?php if (isset($_POST['mode_paiement'])): ?>
    selectMode('<?php echo $_POST['mode_paiement']; ?>');
<?php endif; ?>

// Validation du formulaire
document.getElementById('paiementForm').addEventListener('submit', function(e) {
    const mode = document.getElementById('mode_paiement').value;
    
    if (!mode) {
        e.preventDefault();
        alert('Veuillez choisir un mode de paiement');
        return false;
    }
    
    const reference = document.getElementById('reference_paiement').value;
    if (!reference) {
        e.preventDefault();
        alert('Veuillez entrer la référence de paiement');
        return false;
    }
    
    return true;
});

// Formatage automatique des dates
document.getElementById('date_expiration')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.slice(0, 2) + '/' + value.slice(2, 4);
    }
    e.target.value = value;
});

// Animation au survol
document.querySelectorAll('.mode-paiement').forEach(el => {
    el.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
    });
    
    el.addEventListener('mouseleave', function() {
        if (!this.classList.contains('selected')) {
            this.style.transform = 'translateY(0)';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>