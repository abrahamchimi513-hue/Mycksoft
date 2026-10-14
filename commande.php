<?php
// commande.php
require_once 'includes/config.php';
require_once 'classes/Panier.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setFlashMessage('Veuillez vous connecter pour passer commande', 'error');
    redirect('connexion.php?redirect=commande.php');
}

$user_id = getUserId();
$cart = new Panier($pdo, $user_id);

// Vérifier si le panier n'est pas vide
if ($cart->isEmpty()) {
    setFlashMessage('Votre panier est vide', 'error');
    redirect('panier.php');
}

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();

$content = $cart->getContent();
$totals = $cart->getTotals();

// Traitement de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_commande'])) {
    
    // Récupérer les données du formulaire
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $code_postal = trim($_POST['code_postal'] ?? '');
    $pays = trim($_POST['pays'] ?? 'Cameroun');
    $mode_paiement = $_POST['mode_paiement'] ?? 'mobile_money';
    $mode_livraison = $_POST['mode_livraison'] ?? 'standard';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($nom)) $errors[] = "Le nom est requis";
    if (empty($prenom)) $errors[] = "Le prénom est requis";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if (empty($telephone)) $errors[] = "Le téléphone est requis";
    if (empty($adresse)) $errors[] = "L'adresse est requise";
    if (empty($ville)) $errors[] = "La ville est requise";
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Générer un numéro de commande unique
            $numero_commande = 'CMD-' . date('Ymd') . '-' . rand(1000, 9999);
            
            // Calculer les frais de livraison selon le mode choisi
            $frais_livraison = $totals['shipping'];
            if ($mode_livraison === 'express') {
                $frais_livraison = defined('EXPRESS_SHIPPING_FEE') ? EXPRESS_SHIPPING_FEE : 10000;
            }
            
            $montant_total = $totals['subtotal'] + $frais_livraison;
            
            // Adresse complète
            $adresse_complete = $adresse . "\n" . $ville . "\n" . $code_postal . "\n" . $pays;
            
            // Insérer la commande
            $stmt = $pdo->prepare("
                INSERT INTO commande (
                    numero_commande, id_utilisateur, date_commande, statut,
                    sous_total, frais_livraison, montant_total,
                    adresse_facturation, adresse_livraison, notes_commande
                ) VALUES (
                    ?, ?, NOW(), 'en attente',
                    ?, ?, ?,
                    ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $numero_commande,
                $user_id,
                $totals['subtotal'],
                $frais_livraison,
                $montant_total,
                $adresse_complete,
                $adresse_complete,
                $notes
            ]);
            
            $id_commande = $pdo->lastInsertId();
            
            // Insérer les lignes de commande
            foreach ($content as $item) {
                $prix = $item['final_price'] ?? $item['price'] ?? 0;
                
                $stmt = $pdo->prepare("
                    INSERT INTO ligne_commande (id_commande, id_produit, quantite, prix_unitaire)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$id_commande, $item['product_id'], $item['quantity'], $prix]);
                
                // Mettre à jour le stock
                $stmt = $pdo->prepare("UPDATE produit SET stock = stock - ? WHERE id_produit = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Insérer les informations de livraison
            $delai = ($mode_livraison === 'express') ? 1 : 3;
            $transporteur = ($mode_livraison === 'express') ? 'Express DHL' : 'Expresso';
            
            $stmt = $pdo->prepare("
                INSERT INTO livraison (
                    id_commande, transporteur, adresse_livraison, ville,
                    code_postal, pays, telephone_livraison, statut_livraison,
                    date_livraison_estimee
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'en preparation', DATE_ADD(NOW(), INTERVAL ? DAY))
            ");
            
            $stmt->execute([
                $id_commande,
                $transporteur,
                $adresse,
                $ville,
                $code_postal,
                $pays,
                $telephone,
                $delai
            ]);
            
            // Insérer le paiement
            $details_paiement = json_encode([
                'mode' => $mode_paiement,
                'telephone' => $telephone,
                'date' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $pdo->prepare("
                INSERT INTO paiement (id_commande, mode_paiement, montant, statut_paiement, details_paiement)
                VALUES (?, ?, ?, 'en attente', ?)
            ");
            $stmt->execute([$id_commande, $mode_paiement, $montant_total, $details_paiement]);
            
            // Vider le panier
            $cart->clear();
            
            $pdo->commit();
            
            // Rediriger vers la page de confirmation
            setFlashMessage('Commande enregistrée avec succès !', 'success');
            redirect('commande_confirmation.php?id=' . $id_commande);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur commande: " . $e->getMessage());
            setFlashMessage('Erreur lors de l\'enregistrement de la commande', 'error');
        }
    } else {
        foreach ($errors as $error) {
            setFlashMessage($error, 'error');
        }
    }
}

$page_title = "Finaliser la commande - " . SITE_NAME;
$header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_show_products_link = true;

include 'includes/header.php';
?>

<style>
/* Variables de couleur */
:root {
    --primary-color: #667eea;
    --primary-dark: #5a67d8;
    --primary-light: #eef2ff;
    --success-color: #48bb78;
    --success-light: #c6f6d5;
    --error-color: #f56565;
    --error-light: #fed7d7;
    --warning-color: #ecc94b;
    --pending-color: #ed8936;
    --pending-light: #feebc8;
    --info-color: #4299e1;
    --info-light: #bee3f8;
    --orange: #ff8c00;
    --orange-light: #fff4e6;
    --mtn: #fcd116;
    --mtn-light: #fef9e7;
    --gray-100: #f7fafc;
    --gray-200: #edf2f7;
    --gray-300: #e2e8f0;
    --gray-600: #718096;
    --gray-700: #4a5568;
    --gray-800: #2d3748;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    --border-radius: 0.5rem;
    --border-radius-lg: 0.75rem;
    --border-radius-xl: 1rem;
    --transition: all 0.3s ease;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 3rem 0;
    margin-bottom: 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%);
    background-size: 3rem 3rem;
    opacity: 0.1;
    animation: moveBackground 20s linear infinite;
}

@keyframes moveBackground {
    0% { background-position: 0 0; }
    100% { background-position: 3rem 3rem; }
}

.page-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    position: relative;
    animation: fadeInDown 0.5s ease;
}

.page-header h1 i {
    margin-right: 0.5rem;
}

.page-header p {
    font-size: 1.1rem;
    opacity: 0.9;
    animation: fadeInUp 0.5s ease 0.2s both;
}

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Cards */
.checkout-card, .confirmation-card {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.checkout-card:hover, .confirmation-card:hover {
    box-shadow: var(--shadow-lg);
}

.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-200);
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-title i {
    color: var(--primary-color);
    font-size: 1.5rem;
}

/* Form Styles */
.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-700);
    font-size: 0.875rem;
}

.form-label i {
    margin-right: 0.5rem;
    color: var(--primary-color);
}

.form-control, .form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    font-size: 1rem;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Payment Methods */
.payment-methods, .delivery-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.payment-method, .delivery-method {
    position: relative;
}

.payment-method input[type="radio"],
.delivery-method input[type="radio"] {
    display: none;
}

.payment-method label,
.delivery-method label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.5rem;
    background: white;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius-lg);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    text-align: center;
}

.payment-method input[type="radio"]:checked + label,
.delivery-method input[type="radio"]:checked + label {
    border-color: var(--primary-color);
    background: var(--primary-light);
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.payment-method i,
.delivery-method i {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 0.75rem;
}

.payment-method span,
.delivery-method span {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.payment-method small,
.delivery-method small {
    color: var(--gray-600);
    font-size: 0.75rem;
}

/* Order Summary */
.order-summary-card {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    position: sticky;
    top: 100px;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}

.order-summary-card h4 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.order-summary-card h4 i {
    color: var(--primary-color);
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding: 0.5rem 0;
    color: var(--gray-700);
}

.summary-item.total {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 2px solid var(--gray-200);
}

.product-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: var(--border-radius);
    margin-right: 1rem;
    border: 1px solid var(--gray-200);
}

.product-info {
    flex: 1;
}

.product-name {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.product-quantity {
    color: var(--gray-600);
    font-size: 0.8rem;
}

.product-price {
    font-weight: 600;
    color: var(--primary-color);
}

/* Button Confirm */
.btn-confirm {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    border: none;
    border-radius: var(--border-radius);
    font-size: 1.125rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 1rem;
}

.btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Alert */
.alert {
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.alert-success {
    background: var(--success-light);
    color: #2f855a;
    border-left: 4px solid var(--success-color);
}

.alert-error {
    background: var(--error-light);
    color: #c53030;
    border-left: 4px solid var(--error-color);
}

.alert-info {
    background: var(--info-light);
    color: #2b6cb0;
    border-left: 4px solid var(--info-color);
}

/* Security Info */
.security-info {
    margin-top: 1.5rem;
    padding: 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius);
}

.security-info p {
    margin-bottom: 0.5rem;
    font-size: 0.8rem;
    color: var(--gray-600);
}

.security-info p:last-child {
    margin-bottom: 0;
}

.security-info i {
    color: var(--primary-color);
    margin-right: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header h1 {
        font-size: 1.75rem;
    }
    
    .checkout-card, .confirmation-card {
        padding: 1.5rem;
    }
    
    .payment-methods, .delivery-methods {
        grid-template-columns: 1fr;
    }
    
    .order-summary-card {
        position: static;
        margin-top: 1rem;
    }
}

@media (max-width: 480px) {
    .page-header {
        padding: 2rem 0;
    }
    
    .checkout-card, .confirmation-card {
        padding: 1rem;
    }
    
    .card-title {
        font-size: 1.1rem;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-check-circle"></i> Finaliser votre commande</h1>
        <p><?php echo count($content); ?> article(s) dans votre panier</p>
    </div>
</div>

<div class="container mb-5">
    <?php displayFlashMessage(); ?>

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="" id="commandeForm">
                <!-- Informations personnelles -->
                <div class="checkout-card">
                    <h4 class="card-title">
                        <i class="fas fa-user-circle"></i> Informations personnelles
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" name="nom" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_info['nom'] ?? ''); ?>" 
                                   placeholder="Votre nom" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prénom *</label>
                            <input type="text" name="prenom" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_info['prenom'] ?? ''); ?>" 
                                   placeholder="Votre prénom" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" 
                                   placeholder="exemple@email.com" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Téléphone *</label>
                            <input type="tel" name="telephone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_info['telephone'] ?? ''); ?>" 
                                   placeholder="6XXXXXXXX" required>
                        </div>
                    </div>
                </div>
                
                <!-- Adresse de livraison -->
                <div class="checkout-card">
                    <h4 class="card-title">
                        <i class="fas fa-map-marker-alt"></i> Adresse de livraison
                    </h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Adresse complète *</label>
                        <input type="text" name="adresse" class="form-control" 
                               value="<?php echo htmlspecialchars($user_info['adresse'] ?? ''); ?>" 
                               placeholder="Numéro, rue, quartier, point de repère" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ville *</label>
                            <input type="text" name="ville" class="form-control" 
                                   value="<?php echo defined('DEFAULT_CITY') ? DEFAULT_CITY : 'Douala'; ?>" 
                                   placeholder="Ville" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Code postal</label>
                            <input type="text" name="code_postal" class="form-control" 
                                   placeholder="Ex: BP 1234">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Pays *</label>
                            <input type="text" name="pays" class="form-control" value="Cameroun" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Mode de livraison -->
                <div class="checkout-card">
                    <h4 class="card-title">
                        <i class="fas fa-truck"></i> Mode de livraison
                    </h4>
                    
                    <div class="delivery-methods">
                        <div class="delivery-method">
                            <input type="radio" name="mode_livraison" id="livraison_standard" value="standard" checked>
                            <label for="livraison_standard">
                                <i class="fas fa-truck"></i>
                                <span>Livraison Standard</span>
                                <small>3-5 jours ouvrés</small>
                                <small class="text-primary fw-bold"><?php echo number_format(defined('SHIPPING_FEE') ? SHIPPING_FEE : 5000, 0, ',', ' '); ?> FCFA</small>
                            </label>
                        </div>
                        
                        <div class="delivery-method">
                            <input type="radio" name="mode_livraison" id="livraison_express" value="express">
                            <label for="livraison_express">
                                <i class="fas fa-rocket"></i>
                                <span>Livraison Express</span>
                                <small>24-48 heures</small>
                                <small class="text-primary fw-bold"><?php echo number_format(defined('EXPRESS_SHIPPING_FEE') ? EXPRESS_SHIPPING_FEE : 10000, 0, ',', ' '); ?> FCFA</small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Mode de paiement -->
                <div class="checkout-card">
                    <h4 class="card-title">
                        <i class="fas fa-credit-card"></i> Mode de paiement
                    </h4>
                    
                    <div class="payment-methods">
                        <div class="payment-method">
                            <input type="radio" name="mode_paiement" id="paiement_mobile" value="mobile_money" checked>
                            <label for="paiement_mobile">
                                <i class="fas fa-mobile-alt"></i>
                                <span>Mobile Money</span>
                                <small>Orange Money, MTN Money</small>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" name="mode_paiement" id="paiement_carte" value="carte_bancaire">
                            <label for="paiement_carte">
                                <i class="fas fa-credit-card"></i>
                                <span>Carte bancaire</span>
                                <small>Visa, Mastercard</small>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" name="mode_paiement" id="paiement_livraison" value="espece">
                            <label for="paiement_livraison">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>À la livraison</span>
                                <small>Paiement en espèces</small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Notes supplémentaires -->
                <div class="checkout-card">
                    <h4 class="card-title">
                        <i class="fas fa-sticky-note"></i> Notes supplémentaires
                    </h4>
                    
                    <textarea name="notes" class="form-control" rows="4" 
                              placeholder="Instructions de livraison, précisions sur l'adresse, horaire de livraison souhaité..."></textarea>
                </div>
                
                <button type="submit" name="confirmer_commande" class="btn-confirm" onclick="return validateForm()">
                    <i class="fas fa-check-circle"></i> Confirmer la commande
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>
        
        <!-- Résumé de la commande -->
        <div class="col-lg-4">
            <div class="order-summary-card">
                <h4><i class="fas fa-shopping-bag"></i> Récapitulatif</h4>
                
                <?php foreach ($content as $item): ?>
                    <div class="summary-item">
                        <div class="d-flex align-items-center">
                            <img src="uploads/<?php echo htmlspecialchars($item['image'] ?? 'default.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 class="product-thumb">
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="product-quantity">Quantité: <?php echo $item['quantity']; ?></div>
                            </div>
                        </div>
                        <span class="product-price"><?php echo number_format(($item['final_price'] ?? $item['price'] ?? 0) * $item['quantity'], 0, ',', ' '); ?> FCFA</span>
                    </div>
                <?php endforeach; ?>
                
                <div class="summary-item">
                    <span>Sous-total</span>
                    <span class="fw-bold"><?php echo $totals['subtotal_formatted']; ?></span>
                </div>
                
                <div class="summary-item" id="shipping-cost">
                    <span>Livraison <span class="text-muted">(Standard)</span></span>
                    <span class="fw-bold"><?php echo $totals['shipping_formatted']; ?></span>
                </div>
                
                <div class="summary-item total">
                    <span>Total TTC</span>
                    <span id="total-amount"><?php echo $totals['total_formatted']; ?></span>
                </div>
                
                <div class="security-info">
                    <p><i class="fas fa-lock"></i> Vos informations sont sécurisées et cryptées</p>
                    <p><i class="fas fa-shield-alt"></i> Paiement 100% sécurisé</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Mettre à jour le résumé quand on change le mode de livraison
document.querySelectorAll('input[name="mode_livraison"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const shippingCost = document.getElementById('shipping-cost');
        const totalAmount = document.getElementById('total-amount');
        const subtotal = <?php echo $totals['subtotal']; ?>;
        
        if (this.value === 'express') {
            const expressFee = <?php echo defined('EXPRESS_SHIPPING_FEE') ? EXPRESS_SHIPPING_FEE : 10000; ?>;
            shippingCost.innerHTML = '<span>Livraison <span class="text-muted">(Express)</span></span><span class="fw-bold">' + formatPrice(expressFee) + '</span>';
            totalAmount.textContent = formatPrice(subtotal + expressFee);
        } else {
            const standardFee = <?php echo defined('SHIPPING_FEE') ? SHIPPING_FEE : 5000; ?>;
            shippingCost.innerHTML = '<span>Livraison <span class="text-muted">(Standard)</span></span><span class="fw-bold">' + formatPrice(standardFee) + '</span>';
            totalAmount.textContent = formatPrice(subtotal + standardFee);
        }
    });
});

// Formater le prix
function formatPrice(price) {
    return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
}

// Valider le formulaire
function validateForm() {
    const nom = document.querySelector('input[name="nom"]').value.trim();
    const prenom = document.querySelector('input[name="prenom"]').value.trim();
    const email = document.querySelector('input[name="email"]').value.trim();
    const telephone = document.querySelector('input[name="telephone"]').value.trim();
    const adresse = document.querySelector('input[name="adresse"]').value.trim();
    const ville = document.querySelector('input[name="ville"]').value.trim();
    
    if (!nom || !prenom || !email || !telephone || !adresse || !ville) {
        showAlert('Veuillez remplir tous les champs obligatoires', 'error');
        return false;
    }
    
    if (!validateEmail(email)) {
        showAlert('Veuillez entrer une adresse email valide', 'error');
        return false;
    }
    
    if (!validatePhone(telephone)) {
        showAlert('Numéro de téléphone invalide. Format attendu: 6XXXXXXXX ou 2XXXXXXXX', 'error');
        return false;
    }
    
    return confirm('Confirmez-vous votre commande ?');
}

// Valider email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Valider téléphone camerounais
function validatePhone(phone) {
    const re = /^(6|2|3)[0-9]{8}$/;
    phone = phone.replace(/\s/g, '').replace('+237', '');
    return re.test(phone);
}

// Afficher une alerte personnalisée
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type;
    alertDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
    
    const container = document.querySelector('.container');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>

<?php include 'includes/footer.php'; ?>