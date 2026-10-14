<?php
// checkout.php

// Inclure les fichiers nécessaires
require_once 'includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    setFlashMessage('Veuillez vous connecter pour passer commande', 'error');
    header('Location: login.php');
    exit();
}

$page_title = "Finaliser la commande";

// Récupérer l'ID utilisateur
$user_id = $_SESSION['user_id'];

// Utiliser les fonctions de config.php
// Récupérer le panier de l'utilisateur
$cart_items = getCartData($user_id);

if (empty($cart_items)) {
    setFlashMessage('Votre panier est vide', 'error');
    header('Location: cart.php');
    exit();
}

// Vérifier le stock avant de procéder
foreach ($cart_items as $item) {
    if ($item['quantite'] > $item['stock']) {
        setFlashMessage("Stock insuffisant pour {$item['nom_produit']} (Stock disponible: {$item['stock']})", 'error');
        header('Location: cart.php');
        exit();
    }
}

// Récupérer les informations de l'utilisateur
$user = getUser($user_id);
if (!$user) {
    setFlashMessage('Utilisateur non trouvé', 'error');
    header('Location: login.php');
    exit();
}

// Variables par défaut
$address = $user['adresse'] ?? '';
$phone = $user['telephone'] ?? '+237';
$city = 'Douala';
$postal_code = '';
$country = 'Cameroun';
$shipping_method = 'standard';
$payment_method = 'mobile_money';
$order_notes = '';

// Calculer les totaux
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['prix_unitaire'] * $item['quantite'];
}

$shipping_fee = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FEE;
$total = $subtotal + $shipping_fee;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? 'Douala');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $shipping_method = $_POST['shipping_method'] ?? 'standard';
    $payment_method = $_POST['payment_method'] ?? 'mobile_money';
    $order_notes = trim($_POST['order_notes'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($address)) {
        $errors[] = "L'adresse de livraison est requise";
    }
    
    if (empty($phone) || !validatePhone($phone)) {
        $errors[] = "Un numéro de téléphone valide est requis (ex: +237612345678 ou 6XXXXXXXX)";
    }
    
    if (empty($city)) {
        $errors[] = "La ville est requise";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Normaliser le téléphone
            $phone = normalizePhone($phone);
            
            // 1. Créer la commande
            $order_query = "INSERT INTO commande (
                id_utilisateur, 
                sous_total, 
                frais_livraison, 
                montant_total, 
                adresse_facturation, 
                adresse_livraison, 
                notes_commande,
                statut
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'en attente')";
            
            $billing_address = $address . "\n" . $city . "\n" . $country;
            $shipping_address = $address . "\n" . $city . "\n" . $country;
            
            $stmt = $pdo->prepare($order_query);
            $stmt->execute([
                $user_id,
                $subtotal,
                $shipping_fee,
                $total,
                $billing_address,
                $shipping_address,
                $order_notes
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // Récupérer le numéro de commande généré par le trigger
            $order_num_query = "SELECT numero_commande FROM commande WHERE id_commande = ?";
            $order_num_stmt = $pdo->prepare($order_num_query);
            $order_num_stmt->execute([$order_id]);
            $order_number = $order_num_stmt->fetchColumn();
            
            // 2. Ajouter les lignes de commande
            $line_query = "INSERT INTO ligne_commande (id_commande, id_produit, quantite, prix_unitaire) 
                           SELECT ?, lp.id_produit, lp.quantite, p.prix
                           FROM ligne_panier lp
                           INNER JOIN panier pa ON lp.id_panier = pa.id_panier
                           INNER JOIN produit p ON lp.id_produit = p.id_produit
                           WHERE pa.id_utilisateur = ?";
            
            $line_stmt = $pdo->prepare($line_query);
            $line_stmt->execute([$order_id, $user_id]);
            
            // 3. Créer l'entrée de livraison
            $transporteur = ($shipping_method === 'standard') ? 'Expresso' : 'DHL';
            $shipping_query = "INSERT INTO livraison (
                id_commande,
                adresse_livraison,
                ville,
                code_postal,
                pays,
                telephone_livraison,
                transporteur,
                statut_livraison
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'en preparation')";
            
            $shipping_stmt = $pdo->prepare($shipping_query);
            $shipping_stmt->execute([
                $order_id,
                $address,
                $city,
                $postal_code,
                $country,
                $phone,
                $transporteur
            ]);
            
            // 4. Créer le paiement
            $payment_query = "INSERT INTO paiement (
                id_commande,
                mode_paiement,
                montant,
                statut_paiement,
                details_paiement
            ) VALUES (?, ?, ?, 'en attente', ?)";
            
            $payment_details = json_encode([
                'methode' => $payment_method,
                'telephone' => $phone,
                'date_commande' => date('Y-m-d H:i:s'),
                'reference' => $order_number
            ], JSON_UNESCAPED_UNICODE);
            
            $payment_stmt = $pdo->prepare($payment_query);
            $payment_stmt->execute([
                $order_id,
                $payment_method,
                $total,
                $payment_details
            ]);
            
            // 5. Vider le panier
            $delete_cart_query = "DELETE lp FROM ligne_panier lp
                                 INNER JOIN panier p ON lp.id_panier = p.id_panier
                                 WHERE p.id_utilisateur = ?";
            $delete_stmt = $pdo->prepare($delete_cart_query);
            $delete_stmt->execute([$user_id]);
            
            // Mettre à jour la date de modification du panier
            $update_panier_query = "UPDATE panier SET date_modification = NOW() WHERE id_utilisateur = ?";
            $update_stmt = $pdo->prepare($update_panier_query);
            $update_stmt->execute([$user_id]);
            
            $pdo->commit();
            
            // Envoyer un email de confirmation (optionnel)
            sendEmail($user['email'], 
                     "Confirmation de commande #" . $order_number,
                     "Bonjour {$user['prenom']},<br><br>Votre commande #{$order_number} a été enregistrée avec succès.<br>Montant total : " . formatPrice($total) . ".<br><br>Cordialement,<br>L'équipe MyckSoft");
            
            // Rediriger vers la confirmation
            setFlashMessage('Commande passée avec succès! Numéro de commande: ' . $order_number, 'success');
            header("Location: order_confirmation.php?id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur de commande: " . $e->getMessage());
            setFlashMessage('Erreur lors du passage de commande: ' . $e->getMessage(), 'error');
        }
    } else {
        foreach ($errors as $error) {
            setFlashMessage($error, 'error');
        }
    }
}

// Inclure l'en-tête HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - MyckSoft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styles CSS de base */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-info { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .btn { display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 5px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; border: none; }
        .btn-outline { background: transparent; color: #007bff; border: 1px solid #007bff; }
        .btn-secondary { background: #6c757d; color: white; border: none; }
        
        /* Styles spécifiques pour le checkout */
        .checkout-container { min-height: 70vh; padding: 2rem 0; }
        .checkout-form { display: grid; grid-template-columns: 2fr 1fr; gap: 3rem; }
        .section-box { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .section-title { margin-bottom: 1.5rem; }
        .payment-option { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; margin-bottom: 1rem; }
        .payment-option:hover { border-color: #007bff; }
        .payment-option.selected { border-color: #007bff; background: #f0f9ff; }
        .payment-option input { transform: scale(1.2); }
        .cart-summary { position: sticky; top: 2rem; }
        .cart-item { display: flex; gap: 1rem; align-items: center; padding: 1rem 0; border-bottom: 1px solid #eee; }
        .cart-item-image { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; }
        .cart-item-details { flex: 1; }
        .cart-item-name { font-weight: 500; }
        .cart-item-qty { color: #666; font-size: 0.9rem; }
        .cart-item-price { font-weight: bold; }
        .totals { border-top: 1px solid #ddd; padding-top: 1rem; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .grand-total { border-top: 2px solid #ddd; margin-top: 1rem; padding-top: 1rem; font-weight: bold; font-size: 1.2rem; }
        .confirm-btn { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            padding: 1rem; 
            border-radius: 8px; 
            width: 100%; 
            font-weight: bold; 
            font-size: 1.1rem; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .confirm-btn:hover { opacity: 0.9; }
        .shipping-info { 
            font-size: 0.85rem; 
            color: #666; 
            margin-top: 0.5rem; 
            text-align: center; 
            padding: 0.5rem; 
            background: #fef3c7; 
            border-radius: 4px; 
        }
        .shipping-free { 
            font-size: 0.85rem; 
            color: #10b981; 
            margin-top: 0.5rem; 
            text-align: center; 
            padding: 0.5rem; 
            background: #d1fae5; 
            border-radius: 4px; 
        }
    </style>
</head>
<body>
    <div class="container checkout-container">
        <h1 style="margin-bottom: 2rem;">Finaliser la commande</h1>
        
        <?php 
        // Afficher les messages flash
        displayFlashMessage();
        ?>
        
        <form method="POST" action="" class="checkout-form">
            <!-- Section informations -->
            <div>
                <!-- Informations personnelles -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i> Informations personnelles
                    </h3>
                    
                    <div style="display: grid; gap: 1rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Nom</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['nom']); ?>" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;" readonly>
                            </div>
                            
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Prénom</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['prenom']); ?>" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;" readonly>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Adresse de livraison -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-truck"></i> Adresse de livraison
                    </h3>
                    
                    <div style="display: grid; gap: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                Adresse complète <span style="color: #ef4444;">*</span>
                            </label>
                            <textarea name="address" required 
                                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; min-height: 100px;"
                                      placeholder="Ex: Rue 1234, Quartier, Arrondissement"><?php echo htmlspecialchars($address); ?></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                    Ville <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="city" required 
                                        style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="Douala" <?php echo $city === 'Douala' ? 'selected' : ''; ?>>Douala</option>
                                    <option value="Yaoundé" <?php echo $city === 'Yaoundé' ? 'selected' : ''; ?>>Yaoundé</option>
                                    <option value="Bafoussam" <?php echo $city === 'Bafoussam' ? 'selected' : ''; ?>>Bafoussam</option>
                                    <option value="Garoua" <?php echo $city === 'Garoua' ? 'selected' : ''; ?>>Garoua</option>
                                    <option value="Maroua" <?php echo $city === 'Maroua' ? 'selected' : ''; ?>>Maroua</option>
                                    <option value="Autre" <?php echo $city === 'Autre' ? 'selected' : ''; ?>>Autre ville</option>
                                </select>
                            </div>
                            
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                    Code postal
                                </label>
                                <input type="text" name="postal_code" value="<?php echo htmlspecialchars($postal_code); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;"
                                       placeholder="Ex: 00237">
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                Téléphone <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="tel" name="phone" required value="<?php echo htmlspecialchars($phone); ?>"
                                   style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;"
                                   placeholder="Ex: +237612345678">
                            <small style="color: #666;">Format: +237 ou 6XXXXXXXX</small>
                        </div>
                    </div>
                </div>
                
                <!-- Mode de livraison -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-shipping-fast"></i> Mode de livraison
                    </h3>
                    
                    <div>
                        <label class="payment-option <?php echo $shipping_method === 'standard' ? 'selected' : ''; ?>">
                            <input type="radio" name="shipping_method" value="standard" checked 
                                   style="transform: scale(1.2);">
                            <div style="flex: 1;">
                                <div style="font-weight: bold;">Livraison standard</div>
                                <div style="color: #666;">5-7 jours ouvrables</div>
                            </div>
                            <div style="font-weight: bold; color: #007bff;">
                                <?php echo $shipping_fee === 0 ? 'GRATUIT' : formatPrice(SHIPPING_FEE); ?>
                            </div>
                        </label>
                        
                        <label class="payment-option <?php echo $shipping_method === 'express' ? 'selected' : ''; ?>">
                            <input type="radio" name="shipping_method" value="express" 
                                   style="transform: scale(1.2);">
                            <div style="flex: 1;">
                                <div style="font-weight: bold;">Livraison express</div>
                                <div style="color: #666;">2-3 jours ouvrables</div>
                            </div>
                            <div style="font-weight: bold; color: #007bff;">
                                <?php echo formatPrice(SHIPPING_FEE * 2); ?>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Mode de paiement -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-credit-card"></i> Mode de paiement
                    </h3>
                    
                    <div>
                        <label class="payment-option <?php echo $payment_method === 'mobile_money' ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" value="mobile_money" checked 
                                   style="transform: scale(1.2);">
                            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                <i class="fas fa-mobile-alt" style="font-size: 1.5rem; color: #007bff;"></i>
                                <div>
                                    <div style="font-weight: bold;">Mobile Money</div>
                                    <div style="color: #666;">MTN Mobile Money ou Orange Money</div>
                                </div>
                            </div>
                        </label>
                        
                        <label class="payment-option <?php echo $payment_method === 'carte_credit' ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" value="carte_credit" 
                                   style="transform: scale(1.2);">
                            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                <i class="fas fa-credit-card" style="font-size: 1.5rem; color: #007bff;"></i>
                                <div>
                                    <div style="font-weight: bold;">Carte de crédit</div>
                                    <div style="color: #666;">Visa, Mastercard</div>
                                </div>
                            </div>
                        </label>
                        
                        <label class="payment-option <?php echo $payment_method === 'espece' ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" value="espece" 
                                   style="transform: scale(1.2);">
                            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                <i class="fas fa-money-bill-wave" style="font-size: 1.5rem; color: #007bff;"></i>
                                <div>
                                    <div style="font-weight: bold;">Paiement en espèces</div>
                                    <div style="color: #666;">À la livraison</div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Notes de commande -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-sticky-note"></i> Notes de commande (optionnel)
                    </h3>
                    
                    <textarea name="order_notes" 
                              style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; min-height: 100px;"
                              placeholder="Instructions spéciales pour la livraison, remarques, etc."><?php echo htmlspecialchars($order_notes); ?></textarea>
                </div>
            </div>
            
            <!-- Récapitulatif -->
            <div>
                <div class="cart-summary">
                    <!-- Récapitulatif des articles -->
                    <div class="section-box">
                        <h3 class="section-title">Récapitulatif de la commande</h3>
                        
                        <div style="max-height: 300px; overflow-y: auto; margin-bottom: 1.5rem;">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item">
                                    <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'assets/images/default-product.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($item['nom_produit']); ?>"
                                         class="cart-item-image">
                                    
                                    <div class="cart-item-details">
                                        <div class="cart-item-name"><?php echo htmlspecialchars($item['nom_produit']); ?></div>
                                        <div class="cart-item-qty">
                                            Qté: <?php echo $item['quantite']; ?> × <?php echo formatPrice($item['prix_unitaire']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="cart-item-price">
                                        <?php echo formatPrice($item['prix_unitaire'] * $item['quantite']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Totaux -->
                        <div class="totals">
                            <div class="total-row">
                                <span>Sous-total</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            
                            <div class="total-row">
                                <span>Livraison</span>
                                <span id="shipping-display">
                                    <?php if ($shipping_fee === 0): ?>
                                        <span style="color: #10b981;">GRATUIT</span>
                                    <?php else: ?>
                                        <?php echo formatPrice($shipping_fee); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <?php if ($subtotal < FREE_SHIPPING_THRESHOLD): ?>
                                <div class="shipping-info">
                                    <i class="fas fa-info-circle"></i> Ajoutez <?php echo formatPrice(FREE_SHIPPING_THRESHOLD - $subtotal); ?> 
                                    pour la livraison gratuite!
                                </div>
                            <?php else: ?>
                                <div class="shipping-free">
                                    <i class="fas fa-check-circle"></i> Livraison gratuite!
                                </div>
                            <?php endif; ?>
                            
                            <div class="grand-total total-row">
                                <span>Total</span>
                                <span id="total-display"><?php echo formatPrice($total); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bouton de confirmation -->
                    <button type="submit" class="confirm-btn" onclick="return confirm('Confirmez-vous cette commande ?')">
                        <i class="fas fa-lock"></i> Confirmer la commande
                    </button>
                    
                    <p style="font-size: 0.85rem; color: #666; margin-top: 1rem; text-align: center;">
                        En cliquant sur "Confirmer la commande", vous acceptez nos 
                        <a href="terms.php" style="color: #007bff;">Conditions générales de vente</a>
                    </p>
                    
                    <a href="cart.php" style="display: block; text-align: center; margin-top: 1rem; color: #007bff; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Retour au panier
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
    // Mise à jour dynamique des frais de livraison
    document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Mettre à jour la sélection visuelle
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            this.closest('.payment-option').classList.add('selected');
            
            const subtotal = <?php echo $subtotal; ?>;
            const freeThreshold = <?php echo FREE_SHIPPING_THRESHOLD; ?>;
            const standardFee = <?php echo SHIPPING_FEE; ?>;
            const expressFee = <?php echo SHIPPING_FEE * 2; ?>;
            
            let shippingFee = 0;
            
            if (subtotal >= freeThreshold) {
                shippingFee = 0;
            } else {
                shippingFee = this.value === 'express' ? expressFee : standardFee;
            }
            
            const total = subtotal + shippingFee;
            
            document.getElementById('shipping-display').innerHTML = 
                shippingFee === 0 ? 
                '<span style="color: #10b981;">GRATUIT</span>' : 
                'FCFA ' + shippingFee.toLocaleString('fr-FR');
            
            document.getElementById('total-display').textContent = 
                'FCFA ' + total.toLocaleString('fr-FR');
                
            // Mettre à jour le message de livraison gratuite
            const shippingInfo = document.querySelector('.shipping-info, .shipping-free');
            if (subtotal >= freeThreshold) {
                if (shippingInfo) shippingInfo.remove();
                document.querySelector('.totals').insertAdjacentHTML('beforeend', 
                    '<div class="shipping-free"><i class="fas fa-check-circle"></i> Livraison gratuite!</div>');
            } else {
                if (shippingInfo) shippingInfo.remove();
                document.querySelector('.totals').insertAdjacentHTML('beforeend', 
                    '<div class="shipping-info"><i class="fas fa-info-circle"></i> Ajoutez FCFA ' + (freeThreshold - subtotal).toLocaleString('fr-FR') + ' pour la livraison gratuite!</div>');
            }
        });
    });
    
    // Mettre à jour la sélection visuelle pour le mode de paiement
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            this.closest('.payment-option').classList.add('selected');
        });
    });
    </script>
    
</body>
</html>
<?php
// checkout.php

// Inclure les fichiers nécessaires
require_once 'includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    setFlashMessage('Veuillez vous connecter pour passer commande', 'error');
    header('Location: login.php');
    exit();
}

$page_title = "Finaliser la commande";

// Récupérer l'ID utilisateur
$user_id = $_SESSION['user_id'];

// Utiliser les fonctions de config.php
// Récupérer le panier de l'utilisateur
$cart_items = getCartData($user_id);

if (empty($cart_items)) {
    setFlashMessage('Votre panier est vide', 'error');
    header('Location: cart.php');
    exit();
}

// Vérifier le stock avant de procéder
foreach ($cart_items as $item) {
    if ($item['quantite'] > $item['stock']) {
        setFlashMessage("Stock insuffisant pour {$item['nom_produit']} (Stock disponible: {$item['stock']})", 'error');
        header('Location: cart.php');
        exit();
    }
}

// Récupérer les informations de l'utilisateur
$user = getUser($user_id);
if (!$user) {
    setFlashMessage('Utilisateur non trouvé', 'error');
    header('Location: login.php');
    exit();
}

// Variables par défaut
$address = $user['adresse'] ?? '';
$phone = $user['telephone'] ?? '+237';
$city = 'Douala';
$postal_code = '';
$country = 'Cameroun';
$shipping_method = 'standard';
$payment_method = 'mobile_money';
$order_notes = '';

// Calculer les totaux
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['prix_unitaire'] * $item['quantite'];
}

$shipping_fee = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FEE;
$total = $subtotal + $shipping_fee;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? 'Douala');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $shipping_method = $_POST['shipping_method'] ?? 'standard';
    $payment_method = $_POST['payment_method'] ?? 'mobile_money';
    $order_notes = trim($_POST['order_notes'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($address)) {
        $errors[] = "L'adresse de livraison est requise";
    }
    
    if (empty($phone) || !validatePhone($phone)) {
        $errors[] = "Un numéro de téléphone valide est requis (ex: +237612345678 ou 6XXXXXXXX)";
    }
    
    if (empty($city)) {
        $errors[] = "La ville est requise";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Normaliser le téléphone
            $phone = normalizePhone($phone);
            
            // ==== PARTIE MANQUANTE : GÉNÉRATION DU NUMÉRO DE COMMANDE ====
            // 1. Générer le numéro de commande UNIQUE
            $numero_commande = 'CMD-' . date('Y') . '-' . str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            // Vérifier que le numéro n'existe pas déjà (peu probable mais sécurisé)
            $check_num_query = "SELECT COUNT(*) FROM commande WHERE numero_commande = ?";
            $check_num_stmt = $pdo->prepare($check_num_query);
            $check_num_stmt->execute([$numero_commande]);
            
            if ($check_num_stmt->fetchColumn() > 0) {
                // Regénérer si collision (très rare)
                $numero_commande = 'CMD-' . date('Y') . '-' . str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            // Vérifier le stock une dernière fois avant transaction
            $check_stock_query = "SELECT p.id_produit, p.nom_produit, p.stock, lp.quantite 
                                  FROM ligne_panier lp
                                  INNER JOIN panier pa ON lp.id_panier = pa.id_panier
                                  INNER JOIN produit p ON lp.id_produit = p.id_produit
                                  WHERE pa.id_utilisateur = ?";
            $check_stmt = $pdo->prepare($check_stock_query);
            $check_stmt->execute([$user_id]);
            $stock_check = $check_stmt->fetchAll();
            
            foreach ($stock_check as $item) {
                if ($item['quantite'] > $item['stock']) {
                    throw new Exception("Stock insuffisant pour {$item['nom_produit']} (Stock disponible: {$item['stock']})");
                }
            }
            // ==== FIN DE LA PARTIE MANQUANTE ====
            
            // 1. Créer la commande AVEC le numéro généré
            $order_query = "INSERT INTO commande (
                numero_commande,  -- Champ ajouté
                id_utilisateur, 
                sous_total, 
                frais_livraison, 
                montant_total, 
                adresse_facturation, 
                adresse_livraison, 
                notes_commande,
                statut
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en attente')";
            
            $billing_address = $address . "\n" . $city . "\n" . $country;
            $shipping_address = $address . "\n" . $city . "\n" . $country;
            
            $stmt = $pdo->prepare($order_query);
            $stmt->execute([
                $numero_commande,  // Numéro généré
                $user_id,
                $subtotal,
                $shipping_fee,
                $total,
                $billing_address,
                $shipping_address,
                $order_notes
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // 2. Ajouter les lignes de commande
            $line_query = "INSERT INTO ligne_commande (id_commande, id_produit, quantite, prix_unitaire) 
                           SELECT ?, lp.id_produit, lp.quantite, p.prix
                           FROM ligne_panier lp
                           INNER JOIN panier pa ON lp.id_panier = pa.id_panier
                           INNER JOIN produit p ON lp.id_produit = p.id_produit
                           WHERE pa.id_utilisateur = ?";
            
            $line_stmt = $pdo->prepare($line_query);
            $line_stmt->execute([$order_id, $user_id]);
            
            // ==== PARTIE MANQUANTE : MISE À JOUR DU STOCK ====
            // Mettre à jour le stock manuellement (puisque le trigger est supprimé)
            $update_stock_query = "UPDATE produit p
                                   INNER JOIN ligne_panier lp ON p.id_produit = lp.id_produit
                                   INNER JOIN panier pa ON lp.id_panier = pa.id_panier
                                   SET p.stock = p.stock - lp.quantite,
                                       p.date_modification = NOW()
                                   WHERE pa.id_utilisateur = ?";
            $update_stmt = $pdo->prepare($update_stock_query);
            $update_stmt->execute([$user_id]);
            // ==== FIN DE LA PARTIE MANQUANTE ====
            
            // 3. Créer l'entrée de livraison
            $transporteur = ($shipping_method === 'standard') ? 'Expresso' : 'DHL';
            $shipping_query = "INSERT INTO livraison (
                id_commande,
                adresse_livraison,
                ville,
                code_postal,
                pays,
                telephone_livraison,
                transporteur,
                statut_livraison
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'en preparation')";
            
            $shipping_stmt = $pdo->prepare($shipping_query);
            $shipping_stmt->execute([
                $order_id,
                $address,
                $city,
                $postal_code,
                $country,
                $phone,
                $transporteur
            ]);
            
            // 4. Créer le paiement
            $payment_query = "INSERT INTO paiement (
                id_commande,
                mode_paiement,
                montant,
                statut_paiement,
                details_paiement
            ) VALUES (?, ?, ?, 'en attente', ?)";
            
            $payment_details = json_encode([
                'methode' => $payment_method,
                'telephone' => $phone,
                'date_commande' => date('Y-m-d H:i:s'),
                'reference' => $numero_commande  // Utiliser le numéro généré
            ], JSON_UNESCAPED_UNICODE);
            
            $payment_stmt = $pdo->prepare($payment_query);
            $payment_stmt->execute([
                $order_id,
                $payment_method,
                $total,
                $payment_details
            ]);
            
            // 5. Vider le panier
            $delete_cart_query = "DELETE lp FROM ligne_panier lp
                                 INNER JOIN panier p ON lp.id_panier = p.id_panier
                                 WHERE p.id_utilisateur = ?";
            $delete_stmt = $pdo->prepare($delete_cart_query);
            $delete_stmt->execute([$user_id]);
            
            // Mettre à jour la date de modification du panier
            $update_panier_query = "UPDATE panier SET date_modification = NOW() WHERE id_utilisateur = ?";
            $update_stmt = $pdo->prepare($update_panier_query);
            $update_stmt->execute([$user_id]);
            
            $pdo->commit();
            
            // Envoyer un email de confirmation (optionnel)
            sendEmail($user['email'], 
                     "Confirmation de commande #" . $numero_commande,
                     "Bonjour {$user['prenom']},<br><br>Votre commande #{$numero_commande} a été enregistrée avec succès.<br>Montant total : " . formatPrice($total) . ".<br><br>Cordialement,<br>L'équipe MyckSoft");
            
            // Rediriger vers la confirmation
            setFlashMessage('Commande passée avec succès! Numéro de commande: ' . $numero_commande, 'success');
            header("Location: order_confirmation.php?id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur de commande: " . $e->getMessage());
            setFlashMessage('Erreur lors du passage de commande: ' . $e->getMessage(), 'error');
        }
    } else {
        foreach ($errors as $error) {
            setFlashMessage($error, 'error');
        }
    }
}

// Inclure l'en-tête HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - MyckSoft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styles CSS de base */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-info { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .btn { display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 5px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; border: none; }
        .btn-outline { background: transparent; color: #007bff; border: 1px solid #007bff; }
        .btn-secondary { background: #6c757d; color: white; border: none; }
        
        /* Styles spécifiques pour le checkout */
        .checkout-container { min-height: 70vh; padding: 2rem 0; }
        .checkout-form { display: grid; grid-template-columns: 2fr 1fr; gap: 3rem; }
        .section-box { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .section-title { margin-bottom: 1.5rem; }
        .payment-option { display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; margin-bottom: 1rem; }
        .payment-option:hover { border-color: #007bff; }
        .payment-option.selected { border-color: #007bff; background: #f0f9ff; }
        .payment-option input { transform: scale(1.2); }
        .cart-summary { position: sticky; top: 2rem; }
        .cart-item { display: flex; gap: 1rem; align-items: center; padding: 1rem 0; border-bottom: 1px solid #eee; }
        .cart-item-image { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; }
        .cart-item-details { flex: 1; }
        .cart-item-name { font-weight: 500; }
        .cart-item-qty { color: #666; font-size: 0.9rem; }
        .cart-item-price { font-weight: bold; }
        .totals { border-top: 1px solid #ddd; padding-top: 1rem; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .grand-total { border-top: 2px solid #ddd; margin-top: 1rem; padding-top: 1rem; font-weight: bold; font-size: 1.2rem; }
        .confirm-btn { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            padding: 1rem; 
            border-radius: 8px; 
            width: 100%; 
            font-weight: bold; 
            font-size: 1.1rem; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .confirm-btn:hover { opacity: 0.9; }
        .shipping-info { 
            font-size: 0.85rem; 
            color: #666; 
            margin-top: 0.5rem; 
            text-align: center; 
            padding: 0.5rem; 
            background: #fef3c7; 
            border-radius: 4px; 
        }
        .shipping-free { 
            font-size: 0.85rem; 
            color: #10b981; 
            margin-top: 0.5rem; 
            text-align: center; 
            padding: 0.5rem; 
            background: #d1fae5; 
            border-radius: 4px; 
        }
    </style>
</head>
<body>
    <div class="container checkout-container">
        <h1 style="margin-bottom: 2rem;">Finaliser la commande</h1>
        
        <?php 
        // Afficher les messages flash
        displayFlashMessage();
        ?>
        
        <form method="POST" action="" class="checkout-form">
            <!-- Section informations -->
            <div>
                <!-- Informations personnelles -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i> Informations personnelles
                    </h3>
                    
                    <div style="display: grid; gap: 1rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Nom</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['nom']); ?>" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;" readonly>
                            </div>
                            
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Prénom</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['prenom']); ?>" 
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;" readonly>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Adresse de livraison -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-truck"></i> Adresse de livraison
                    </h3>
                    
                    <div style="display: grid; gap: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                Adresse complète <span style="color: #ef4444;">*</span>
                            </label>
                            <textarea name="address" required 
                                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; min-height: 100px;"
                                      placeholder="Ex: Rue 1234, Quartier, Arrondissement"><?php echo htmlspecialchars($address); ?></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                    Ville <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="city" required 
                                        style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="Douala" <?php echo $city === 'Douala' ? 'selected' : ''; ?>>Douala</option>
                                    <option value="Yaoundé" <?php echo $city === 'Yaoundé' ? 'selected' : ''; ?>>Yaoundé</option>
                                    <option value="Bafoussam" <?php echo $city === 'Bafoussam' ? 'selected' : ''; ?>>Bafoussam</option>
                                    <option value="Garoua" <?php echo $city === 'Garoua' ? 'selected' : ''; ?>>Garoua</option>
                                    <option value="Maroua" <?php echo $city === 'Maroua' ? 'selected' : ''; ?>>Maroua</option>
                                    <option value="Autre" <?php echo $city === 'Autre' ? 'selected' : ''; ?>>Autre ville</option>
                                </select>
                            </div>
                            
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                    Code postal
                                </label>
                                <input type="text" name="postal_code" value="<?php echo htmlspecialchars($postal_code); ?>"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;"
                                       placeholder="Ex: 00237">
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                Téléphone <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="tel" name="phone" required value="<?php echo htmlspecialchars($phone); ?>"
                                   style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;"
                                   placeholder="Ex: +237612345678">
                            <small style="color: #666;">Format: +237 ou 6XXXXXXXX</small>
                        </div>
                    </div>
                </div>
                
                <!-- Mode de livraison -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-shipping-fast"></i> Mode de livraison
                    </h3>
                    
                    <div>
                        <label class="payment-option <?php echo $shipping_method === 'standard' ? 'selected' : ''; ?>">
                            <input type="radio" name="shipping_method" value="standard" checked 
                                   style="transform: scale(1.2);">
                            <div style="flex: 1;">
                                <div style="font-weight: bold;">Livraison standard</div>
                                <div style="color: #666;">5-7 jours ouvrables</div>
                            </div>
                            <div style="font-weight: bold; color: #007bff;">
                                <?php echo $shipping_fee === 0 ? 'GRATUIT' : formatPrice(SHIPPING_FEE); ?>
                            </div>
                        </label>
                        
                        <label class="payment-option <?php echo $shipping_method === 'express' ? 'selected' : ''; ?>">
                            <input type="radio" name="shipping_method" value="express" 
                                   style="transform: scale(1.2);">
                            <div style="flex: 1;">
                                <div style="font-weight: bold;">Livraison express</div>
                                <div style="color: #666;">2-3 jours ouvrables</div>
                            </div>
                            <div style="font-weight: bold; color: #007bff;">
                                <?php echo formatPrice(SHIPPING_FEE * 2); ?>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Mode de paiement -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-credit-card"></i> Mode de paiement
                    </h3>
                    
                    <div>
                        <label class="payment-option <?php echo $payment_method === 'mobile_money' ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" value="mobile_money" checked 
                                   style="transform: scale(1.2);">
                            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                <i class="fas fa-mobile-alt" style="font-size: 1.5rem; color: #007bff;"></i>
                                <div>
                                    <div style="font-weight: bold;">Mobile Money</div>
                                    <div style="color: #666;">MTN Mobile Money ou Orange Money</div>
                                </div>
                            </div>
                        </label>
                        
                        <label class="payment-option <?php echo $payment_method === 'carte_credit' ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" value="carte_credit" 
                                   style="transform: scale(1.2);">
                            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                <i class="fas fa-credit-card" style="font-size: 1.5rem; color: #007bff;"></i>
                                <div>
                                    <div style="font-weight: bold;">Carte de crédit</div>
                                    <div style="color: #666;">Visa, Mastercard</div>
                                </div>
                            </div>
                        </label>
                        
                        <label class="payment-option <?php echo $payment_method === 'espece' ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" value="espece" 
                                   style="transform: scale(1.2);">
                            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                                <i class="fas fa-money-bill-wave" style="font-size: 1.5rem; color: #007bff;"></i>
                                <div>
                                    <div style="font-weight: bold;">Paiement en espèces</div>
                                    <div style="color: #666;">À la livraison</div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Notes de commande -->
                <div class="section-box">
                    <h3 class="section-title">
                        <i class="fas fa-sticky-note"></i> Notes de commande (optionnel)
                    </h3>
                    
                    <textarea name="order_notes" 
                              style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; min-height: 100px;"
                              placeholder="Instructions spéciales pour la livraison, remarques, etc."><?php echo htmlspecialchars($order_notes); ?></textarea>
                </div>
            </div>
            
            <!-- Récapitulatif -->
            <div>
                <div class="cart-summary">
                    <!-- Récapitulatif des articles -->
                    <div class="section-box">
                        <h3 class="section-title">Récapitulatif de la commande</h3>
                        
                        <div style="max-height: 300px; overflow-y: auto; margin-bottom: 1.5rem;">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item">
                                    <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'assets/images/default-product.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($item['nom_produit']); ?>"
                                         class="cart-item-image">
                                    
                                    <div class="cart-item-details">
                                        <div class="cart-item-name"><?php echo htmlspecialchars($item['nom_produit']); ?></div>
                                        <div class="cart-item-qty">
                                            Qté: <?php echo $item['quantite']; ?> × <?php echo formatPrice($item['prix_unitaire']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="cart-item-price">
                                        <?php echo formatPrice($item['prix_unitaire'] * $item['quantite']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Totaux -->
                        <div class="totals">
                            <div class="total-row">
                                <span>Sous-total</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            
                            <div class="total-row">
                                <span>Livraison</span>
                                <span id="shipping-display">
                                    <?php if ($shipping_fee === 0): ?>
                                        <span style="color: #10b981;">GRATUIT</span>
                                    <?php else: ?>
                                        <?php echo formatPrice($shipping_fee); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <?php if ($subtotal < FREE_SHIPPING_THRESHOLD): ?>
                                <div class="shipping-info">
                                    <i class="fas fa-info-circle"></i> Ajoutez <?php echo formatPrice(FREE_SHIPPING_THRESHOLD - $subtotal); ?> 
                                    pour la livraison gratuite!
                                </div>
                            <?php else: ?>
                                <div class="shipping-free">
                                    <i class="fas fa-check-circle"></i> Livraison gratuite!
                                </div>
                            <?php endif; ?>
                            
                            <div class="grand-total total-row">
                                <span>Total</span>
                                <span id="total-display"><?php echo formatPrice($total); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bouton de confirmation -->
                    <button type="submit" class="confirm-btn" onclick="return confirm('Confirmez-vous cette commande ?')">
                        <i class="fas fa-lock"></i> Confirmer la commande
                    </button>
                    
                    <p style="font-size: 0.85rem; color: #666; margin-top: 1rem; text-align: center;">
                        En cliquant sur "Confirmer la commande", vous acceptez nos 
                        <a href="terms.php" style="color: #007bff;">Conditions générales de vente</a>
                    </p>
                    
                    <a href="cart.php" style="display: block; text-align: center; margin-top: 1rem; color: #007bff; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Retour au panier
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
    // Mise à jour dynamique des frais de livraison
    document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Mettre à jour la sélection visuelle
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            this.closest('.payment-option').classList.add('selected');
            
            const subtotal = <?php echo $subtotal; ?>;
            const freeThreshold = <?php echo FREE_SHIPPING_THRESHOLD; ?>;
            const standardFee = <?php echo SHIPPING_FEE; ?>;
            const expressFee = <?php echo SHIPPING_FEE * 2; ?>;
            
            let shippingFee = 0;
            
            if (subtotal >= freeThreshold) {
                shippingFee = 0;
            } else {
                shippingFee = this.value === 'express' ? expressFee : standardFee;
            }
            
            const total = subtotal + shippingFee;
            
            document.getElementById('shipping-display').innerHTML = 
                shippingFee === 0 ? 
                '<span style="color: #10b981;">GRATUIT</span>' : 
                'FCFA ' + shippingFee.toLocaleString('fr-FR');
            
            document.getElementById('total-display').textContent = 
                'FCFA ' + total.toLocaleString('fr-FR');
                
            // Mettre à jour le message de livraison gratuite
            const shippingInfo = document.querySelector('.shipping-info, .shipping-free');
            if (subtotal >= freeThreshold) {
                if (shippingInfo) shippingInfo.remove();
                document.querySelector('.totals').insertAdjacentHTML('beforeend', 
                    '<div class="shipping-free"><i class="fas fa-check-circle"></i> Livraison gratuite!</div>');
            } else {
                if (shippingInfo) shippingInfo.remove();
                document.querySelector('.totals').insertAdjacentHTML('beforeend', 
                    '<div class="shipping-info"><i class="fas fa-info-circle"></i> Ajoutez FCFA ' + (freeThreshold - subtotal).toLocaleString('fr-FR') + ' pour la livraison gratuite!</div>');
            }
        });
    });
    
    // Mettre à jour la sélection visuelle pour le mode de paiement
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            this.closest('.payment-option').classList.add('selected');
        });
    });
    </script>
    
</body>
</html>