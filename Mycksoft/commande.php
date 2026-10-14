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
/* Styles avec la même couleur de fond que l'index */
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.page-header {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    padding: 40px 0;
    margin-bottom: 40px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.page-header h1 {
    color: white;
    font-size: 2.5rem;
    font-weight: 600;
    margin-bottom: 10px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.page-header h1 i {
    margin-right: 15px;
    color: rgba(255, 255, 255, 0.9);
}

/* Styles des cards */
.checkout-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 30px;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

.checkout-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 30px 50px rgba(0,0,0,0.15);
}

.card-title {
    font-size: 1.4rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
}

.card-title i {
    color: #667eea;
    margin-right: 12px;
    font-size: 1.6rem;
}

.form-label {
    font-weight: 500;
    color: #4a5568;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.form-control, .form-select {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 16px;
    transition: all 0.3s ease;
    font-size: 0.95rem;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.payment-methods, .delivery-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 15px;
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
    padding: 25px 20px;
    background: linear-gradient(135deg, #f7fafc, #edf2f7);
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    height: 100%;
}

.payment-method input[type="radio"]:checked + label,
.delivery-method input[type="radio"]:checked + label {
    border-color: #667eea;
    background: linear-gradient(135deg, #f0f5ff, #e6edff);
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(102, 126, 234, 0.2);
}

.payment-method i,
.delivery-method i {
    font-size: 2.5rem;
    color: #667eea;
    margin-bottom: 15px;
}

.payment-method span,
.delivery-method span {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 5px;
    font-size: 1.1rem;
}

.payment-method small,
.delivery-method small {
    color: #718096;
    font-size: 0.85rem;
}

.order-summary-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    padding: 25px;
    position: sticky;
    top: 100px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

.order-summary-card h4 {
    font-size: 1.3rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
}

.order-summary-card h4 i {
    color: #667eea;
    margin-right: 10px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding: 8px 0;
    color: #4a5568;
    font-size: 0.95rem;
}

.summary-item.total {
    font-size: 1.3rem;
    font-weight: 700;
    color: #667eea;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px solid #e2e8f0;
}

.btn-confirm {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 16px 30px;
    font-size: 1.2rem;
    font-weight: 600;
    border-radius: 50px;
    width: 100%;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-confirm:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
}

.btn-confirm i {
    font-size: 1.3rem;
}

.product-thumb {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 12px;
    margin-right: 15px;
    border: 2px solid #e2e8f0;
}

.product-info {
    flex: 1;
}

.product-name {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 4px;
}

.product-quantity {
    color: #718096;
    font-size: 0.9rem;
}

.product-price {
    font-weight: 600;
    color: #667eea;
}

/* Alert messages */
.alert {
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 20px;
    border: none;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
    color: #22543d;
}

.alert-error {
    background: linear-gradient(135deg, #fed7d7, #feb2b2);
    color: #742a2a;
}

@media (max-width: 768px) {
    .payment-methods, .delivery-methods {
        grid-template-columns: 1fr;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .checkout-card {
        padding: 20px;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-check-circle"></i> Finaliser votre commande</h1>
        <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
            <?php echo count($content); ?> article(s) dans votre panier
        </p>
    </div>
</div>

<div class="container mb-5">
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?>">
            <i class="fas fa-<?php echo $_SESSION['flash']['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php 
                echo $_SESSION['flash']['message'];
                unset($_SESSION['flash']);
            ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="" id="commandeForm">
                <!-- Informations personnelles - Card -->
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
                
                <!-- Adresse de livraison - Card -->
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
                
                <!-- Mode de livraison - Card -->
                <!-- <div class="checkout-card">
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
                                <small class="text-primary fw-bold"><?php echo number_format(defined('SHIPPING_FEE') ? SHIPPING_FEE : 1, 0, ',', ' '); ?> FCFA</small>
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
                </div> -->
                
                <!-- Mode de paiement - Card -->
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
                
                <!-- Notes supplémentaires - Card -->
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
        
        <!-- Résumé de la commande - Card -->
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
                
                <div class="mt-3 p-3 bg-light rounded-3">
                    <p class="text-muted small mb-0">
                        <i class="fas fa-lock me-2"></i>
                        Vos informations sont sécurisées et cryptées
                    </p>
                    <p class="text-muted small mb-0 mt-2">
                        <i class="fas fa-shield-alt me-2"></i>
                        Paiement 100% sécurisé
                    </p>
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
createOrderNotification($pdo, $order_id, $user_id);
</script>

<?php include 'includes/footer.php'; ?>
