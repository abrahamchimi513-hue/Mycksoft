<?php
// commande_confirmation.php
require_once 'includes/config.php';
require_once 'includes/mail_functions.php';
require_once 'includes/campay_config.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setFlashMessage('Veuillez vous connecter pour accéder à cette page', 'error');
    redirect('connexion.php');
}

// ========== RÉCUPÉRATION AMÉLIORÉE DE L'ID DE COMMANDE ==========
$id_commande = 0;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_commande = (int)$_GET['id'];
} elseif (isset($_GET['commande_id']) && !empty($_GET['commande_id'])) {
    $id_commande = (int)$_GET['commande_id'];
} elseif (isset($_POST['commande_id']) && !empty($_POST['commande_id'])) {
    $id_commande = (int)$_POST['commande_id'];
} elseif (isset($_SESSION['campay_commande_id']) && !empty($_SESSION['campay_commande_id'])) {
    $id_commande = (int)$_SESSION['campay_commande_id'];
}

if ($id_commande <= 0) {
    setFlashMessage('Aucune commande spécifiée', 'error');
    redirect('index.php');
}

$user_id = getUserId();

// Récupérer les informations de la commande
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.nom, u.prenom, u.email, u.telephone
        FROM commande c
        JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
        WHERE c.id_commande = ? AND c.id_utilisateur = ?
    ");
    $stmt->execute([$id_commande, $user_id]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        setFlashMessage('Commande introuvable', 'error');
        redirect('index.php');
    }
} catch (Exception $e) {
    error_log("Erreur récupération commande: " . $e->getMessage());
    setFlashMessage('Erreur lors de la récupération de la commande', 'error');
    redirect('index.php');
}

// ========== TRAITEMENT DU CALLBACK CAMPAY ==========
if (isset($_GET['campay_success']) && isset($_GET['reference'])) {
    $reference_campay = $_GET['reference'];
    $commande_id_callback = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : $id_commande;
    
    if ($commande_id_callback == $id_commande) {
        $statut = verifierStatutPaiementCampay($reference_campay);
        
        if ($statut['success'] && $statut['statut'] === 'SUCCESSFUL') {
            try {
                $pdo->beginTransaction();
                
                // Mettre à jour la commande
                $update = $pdo->prepare("
                    UPDATE commande 
                    SET statut = 'payee', 
                        mode_paiement = 'mobile_money', 
                        reference_paiement = ?,
                        date_paiement = NOW() 
                    WHERE id_commande = ? AND id_utilisateur = ?
                ");
                $update->execute([$reference_campay, $id_commande, $user_id]);
                
                // Mettre à jour le paiement
                $details_paiement = json_encode([
                    'mode' => 'campay',
                    'reference_campay' => $reference_campay,
                    'date' => date('Y-m-d H:i:s'),
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                $stmt_paiement = $pdo->prepare("SELECT * FROM paiement WHERE id_commande = ?");
                $stmt_paiement->execute([$id_commande]);
                
                if ($stmt_paiement->fetch()) {
                    $update_paiement = $pdo->prepare("
                        UPDATE paiement 
                        SET mode_paiement = 'mobile_money',
                            montant = ?,
                            reference_paiement = ?,
                            statut_paiement = 'paye',
                            date_paiement = NOW(),
                            details_paiement = ?
                        WHERE id_commande = ?
                    ");
                    $update_paiement->execute([
                        $commande['montant_total'],
                        $reference_campay,
                        $details_paiement,
                        $id_commande
                    ]);
                } else {
                    $insert_paiement = $pdo->prepare("
                        INSERT INTO paiement (id_commande, mode_paiement, montant, reference_paiement, statut_paiement, date_paiement, details_paiement)
                        VALUES (?, 'mobile_money', ?, ?, 'paye', NOW(), ?)
                    ");
                    $insert_paiement->execute([
                        $id_commande,
                        $commande['montant_total'],
                        $reference_campay,
                        $details_paiement
                    ]);
                }
                
                $pdo->commit();
                
                // ========== ENVOI DE L'EMAIL DE CONFIRMATION ==========
                $email_envoye = envoyerEmailConfirmationPaiement($commande, [
                    'mode_paiement' => 'Mobile Money (Campay)',
                    'reference_paiement' => $reference_campay,
                    'date_paiement' => date('Y-m-d H:i:s'),
                    'montant' => $commande['montant_total']
                ]);
                
                if ($email_envoye) {
                    setFlashMessage("Paiement effectué avec succès ! Un email de confirmation vous a été envoyé.", 'success');
                } else {
                    setFlashMessage("Paiement effectué avec succès ! (Email non envoyé - vérifiez votre configuration)", 'warning');
                }
                // =====================================================
                
                redirect('commande_confirmation.php?id=' . $id_commande);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Erreur confirmation Campay: " . $e->getMessage());
                setFlashMessage("Erreur lors de la confirmation du paiement.", 'error');
                redirect('commande_confirmation.php?id=' . $id_commande);
            }
        } else {
            setFlashMessage("Le paiement a échoué ou a été annulé.", 'error');
            redirect('commande_confirmation.php?id=' . $id_commande);
        }
    }
}

// ========== TRAITEMENT DU FORMULAIRE DE PAIEMENT ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payer_avec_campay'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setFlashMessage("Erreur de sécurité. Veuillez réessayer.", 'error');
        redirect('commande_confirmation.php?id=' . $id_commande);
    }
    
    $mode_paiement = $_POST['mode_paiement'] ?? '';
    $telephone = trim($_POST['telephone'] ?? '');
    $errors = [];
    
    if (empty($mode_paiement)) {
        $errors[] = "Veuillez choisir un mode de paiement.";
    }
    
    if (empty($telephone)) {
        $errors[] = "Veuillez entrer votre numéro de téléphone.";
    } else {
        $telephone = preg_replace('/[^0-9]/', '', $telephone);
        if (strlen($telephone) < 9) {
            $errors[] = "Numéro de téléphone invalide.";
        }
    }
    
    if (empty($errors)) {
        $reference_externe = 'CMD_' . $id_commande . '_' . time() . '_' . uniqid();
        $description = 'Paiement commande #' . $commande['numero_commande'] . ' - ' . SITE_NAME;
        
        $resultat = initierPaiementCampay(
            $commande['montant_total'],
            $telephone,
            $description,
            $reference_externe
        );
        
        if ($resultat['success']) {
            $_SESSION['campay_reference'] = $resultat['reference'];
            $_SESSION['campay_commande_id'] = $id_commande;
            
            setFlashMessage("Veuillez confirmer le paiement sur votre téléphone.", 'info');
            redirect('commande_confirmation.php?id=' . $id_commande . '&attente_paiement=1');
        } else {
            setFlashMessage("Erreur de paiement: " . $resultat['message'], 'error');
            redirect('commande_confirmation.php?id=' . $id_commande);
        }
    } else {
        setFlashMessage(implode('<br>', $errors), 'error');
        redirect('commande_confirmation.php?id=' . $id_commande);
    }
}

// ========== RÉCUPÉRATION DES INFORMATIONS COMPLÉMENTAIRES ==========
$lignes_commande = [];
$livraison = null;
$paiement = null;

try {
    $stmt = $pdo->prepare("
        SELECT lc.*, p.nom_produit, p.marque, p.modele
        FROM ligne_commande lc
        JOIN produit p ON lc.id_produit = p.id_produit
        WHERE lc.id_commande = ?
    ");
    $stmt->execute([$id_commande]);
    $lignes_commande = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur récupération lignes: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("SELECT * FROM livraison WHERE id_commande = ?");
    $stmt->execute([$id_commande]);
    $livraison = $stmt->fetch();
} catch (Exception $e) {
    error_log("Erreur récupération livraison: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("SELECT * FROM paiement WHERE id_commande = ?");
    $stmt->execute([$id_commande]);
    $paiement = $stmt->fetch();
} catch (Exception $e) {
    error_log("Erreur récupération paiement: " . $e->getMessage());
}

// Déterminer le statut de confirmation
$confirmation_status = 'pending';
$confirmation_message = 'Votre commande est en cours de traitement.';
$confirmation_details = [
    'code' => 'PENDING',
    'description' => 'Nous traitons actuellement votre commande.',
    'next_steps' => 'Vous recevrez une confirmation sous peu.'
];

if ($paiement && $paiement['statut_paiement'] === 'paye') {
    $confirmation_status = 'success';
    $confirmation_message = 'Votre paiement a été confirmé avec succès !';
    $confirmation_details = [
        'code' => 'PAYMENT_CONFIRMED',
        'description' => 'Votre paiement a été confirmé.',
        'next_steps' => 'Votre commande est en cours de préparation.'
    ];
} elseif ($commande['statut'] === 'payee') {
    $confirmation_status = 'success';
    $confirmation_message = 'Commande payée et confirmée !';
    $confirmation_details = [
        'code' => 'CONFIRMED',
        'description' => 'Votre commande a été confirmée.',
        'next_steps' => 'Nous préparons votre commande pour expédition.'
    ];
}

$page_title = "Confirmation de commande - " . SITE_NAME;
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-check-circle"></i> Confirmation de commande</h1>
        <?php if ($commande && isset($commande['numero_commande'])): ?>
        <div class="confirmation-badge <?php echo $confirmation_status; ?>">
            <i class="fas fa-<?php 
                echo $confirmation_status === 'success' ? 'check-circle' : 
                    ($confirmation_status === 'error' ? 'exclamation-circle' : 'clock'); 
            ?>"></i>
            N° <?php echo htmlspecialchars($commande['numero_commande']); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container mb-5">
    <!-- Affichage des messages flash -->
    <?php displayFlashMessage(); ?>

    <?php if ($commande): ?>
    <!-- Status Card -->
    <div class="status-card <?php echo $confirmation_status; ?>">
        <div class="status-icon">
            <i class="fas fa-<?php 
                echo $confirmation_status === 'success' ? 'check-circle' : 
                    ($confirmation_status === 'error' ? 'times-circle' : 'clock'); 
            ?>"></i>
        </div>
        
        <h2 class="status-title">
            <?php echo $confirmation_message; ?>
        </h2>
        
        <?php if (!empty($confirmation_details)): ?>
        <div class="status-details">
            <?php if (isset($confirmation_details['code'])): ?>
            <p>
                <i class="fas fa-code"></i>
                <strong>Code :</strong> 
                <span class="code-badge"><?php echo htmlspecialchars($confirmation_details['code']); ?></span>
            </p>
            <?php endif; ?>
            
            <?php if (isset($confirmation_details['description'])): ?>
            <p>
                <i class="fas fa-info-circle"></i>
                <strong>Description :</strong> 
                <?php echo htmlspecialchars($confirmation_details['description']); ?>
            </p>
            <?php endif; ?>
            
            <?php if (isset($confirmation_details['next_steps'])): ?>
            <p>
                <i class="fas fa-arrow-right"></i>
                <strong>Prochaines étapes :</strong> 
                <?php echo htmlspecialchars($confirmation_details['next_steps']); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="status-actions">
            <?php if ($confirmation_status === 'error'): ?>
                <a href="contact.php" class="status-action-btn secondary">
                    <i class="fas fa-headset"></i> Contacter le support
                </a>
            <?php elseif ($confirmation_status === 'pending' && (!$paiement || $paiement['statut_paiement'] !== 'paye')): ?>
                <a href="produits.php" class="status-action-btn secondary">
                    <i class="fas fa-shopping-bag"></i> Continuer mes achats
                </a>
            <?php elseif ($confirmation_status === 'success'): ?>
                <a href="javascript:window.print()" class="status-action-btn secondary">
                    <i class="fas fa-print"></i> Imprimer la facture
                </a>
                <a href="compte.php?page=commandes" class="status-action-btn primary">
                    <i class="fas fa-history"></i> Voir mes commandes
                </a>
                <a href="produits.php" class="status-action-btn secondary">
                    <i class="fas fa-shopping-bag"></i> Continuer mes achats
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progress Steps -->
    <div class="progress-steps">
        <div class="step <?php echo in_array($commande['statut'], ['payee', 'confirmée', 'confirmee', 'livrée', 'livree']) ? 'completed' : 'active'; ?>">
            <div class="step-icon"><i class="fas fa-check"></i></div>
            <div class="step-label">Commande validée</div>
        </div>
        <div class="step <?php 
            if ($paiement && $paiement['statut_paiement'] === 'paye') {
                echo 'completed';
            } elseif ($paiement && $paiement['statut_paiement'] === 'en attente') {
                echo 'active';
            }
        ?>">
            <div class="step-icon"><i class="fas fa-credit-card"></i></div>
            <div class="step-label">Paiement</div>
        </div>
        <div class="step">
            <div class="step-icon"><i class="fas fa-box"></i></div>
            <div class="step-label">Préparation</div>
        </div>
        <div class="step">
            <div class="step-icon"><i class="fas fa-truck"></i></div>
            <div class="step-label">Livraison</div>
        </div>
        <div class="step">
            <div class="step-icon"><i class="fas fa-home"></i></div>
            <div class="step-label">Réception</div>
        </div>
    </div>

    <!-- SECTION PAIEMENT CAMPAY (uniquement si commande non payée) -->
    <?php if ((!$paiement || $paiement['statut_paiement'] !== 'paye') && $commande['statut'] !== 'payee'): ?>
    <div class="payment-section">
        <div class="payment-title">
            <i class="fas fa-mobile-alt"></i>
            <span>Paiement sécurisé par Campay</span>
        </div>
        <div class="campay-badge">
            <i class="fas fa-shield-alt"></i>
            <span>Transactions 100% sécurisées</span>
        </div>
        
        <form method="POST" action="" id="paiementForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="payer_avec_campay" value="1">
            <input type="hidden" name="commande_id" value="<?php echo $id_commande; ?>">
            
            <div class="modes-paiement">
                <div class="mode-paiement" onclick="selectMode('orange')" id="mode-orange">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Orange Money</h3>
                    <p>Paiement rapide</p>
                </div>
                
                <div class="mode-paiement" onclick="selectMode('mtn')" id="mode-mtn">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>MTN Mobile Money</h3>
                    <p>Paiement simple</p>
                </div>
            </div>
            
            <input type="hidden" name="mode_paiement" id="mode_paiement" value="">
            
            <div class="form-group">
                <label for="telephone">
                    <i class="fas fa-phone-alt"></i>
                    Numéro de téléphone Mobile Money *
                </label>
                <input type="tel" class="form-control" id="telephone" name="telephone" 
                       placeholder="6XXXXXXXX" 
                       value="<?php echo htmlspecialchars($commande['telephone'] ?? ''); ?>"
                       maxlength="12" required>
                <small style="color: #718096; font-size: 0.7rem; margin-top: 0.25rem; display: block;">
                    <i class="fas fa-info-circle"></i> Exemple : 691234567
                </small>
            </div>
            
            <div class="info-box">
                <i class="fas fa-bell"></i>
                <div>
                    <strong>Comment ça marche ?</strong><br>
                    <span>Vous recevrez une notification sur votre téléphone → Ouvrez votre application Mobile Money → Entrez votre code PIN pour confirmer</span>
                </div>
            </div>
            
            <button type="submit" class="btn-payer" id="btnPayer">
                <span>Payer <?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                <i class="fas fa-arrow-right"></i>
                <div class="loader"></div>
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Résumé de la commande -->
    <div class="confirmation-card">
        <h4 class="card-title">
            <i class="fas fa-shopping-bag"></i> Détails de la commande
        </h4>
        
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="info-label">Numéro de commande</div>
                <div class="info-value"><?php echo htmlspecialchars($commande['numero_commande']); ?></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="info-label">Date</div>
                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="info-label">Statut</div>
                <div class="info-value">
                    <span class="statut-badge statut-<?php echo str_replace(['é', 'è', 'ê', ' '], ['e', 'e', 'e', '-'], $commande['statut']); ?>">
                        <?php echo ucfirst($commande['statut']); ?>
                    </span>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="info-label">Total</div>
                <div class="info-value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</div>
            </div>
        </div>

        <?php if (!empty($lignes_commande)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th class="text-center">Quantité</th>
                        <th class="text-end">Prix unitaire</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes_commande as $ligne): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="uploads/<?php echo isset($ligne['image']) ? htmlspecialchars($ligne['image']) : 'default.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($ligne['nom_produit'] ?? 'Produit'); ?>" 
                                     class="product-img me-3"
                                     onerror="this.src='uploads/default.jpg'">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($ligne['nom_produit'] ?? 'Produit'); ?></div>
                                    <?php if (!empty($ligne['marque']) || !empty($ligne['modele'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($ligne['marque'] . ' ' . $ligne['modele']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-center"><?php echo $ligne['quantite']; ?></td>
                        <td class="text-end"><?php echo number_format($ligne['prix_unitaire'], 0, ',', ' '); ?> FCFA</td>
                        <td class="text-end fw-bold"><?php echo number_format($ligne['prix_unitaire'] * $ligne['quantite'], 0, ',', ' '); ?> FCFA</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Sous-total :</td>
                        <td class="text-end"><?php echo number_format($commande['sous_total'], 0, ',', ' '); ?> FCFA</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Frais de livraison :</td>
                        <td class="text-end"><?php echo number_format($commande['frais_livraison'], 0, ',', ' '); ?> FCFA</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end fw-bold" style="font-size: 1.2rem;">Total TTC :</td>
                        <td class="text-end fw-bold" style="font-size: 1.2rem; color: var(--primary-color);">
                            <?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted">Aucun détail de produit disponible pour cette commande.</p>
        <?php endif; ?>
    </div>

    <div class="row">
        <!-- Adresse de livraison -->
        <div class="col-md-6">
            <div class="confirmation-card h-100">
                <h4 class="card-title">
                    <i class="fas fa-map-marker-alt"></i> Adresse de livraison
                </h4>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nom complet</div>
                        <div class="info-value"><?php echo htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Téléphone</div>
                        <div class="info-value"><?php echo htmlspecialchars($commande['telephone']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Adresse</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($commande['adresse_livraison'])); ?></div>
                    </div>
                    <?php if ($livraison): ?>
                    <div class="info-item">
                        <div class="info-label">Mode de livraison</div>
                        <div class="info-value"><?php echo htmlspecialchars($livraison['transporteur']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Paiement -->
        <div class="col-md-6">
            <div class="confirmation-card h-100">
                <h4 class="card-title">
                    <i class="fas fa-credit-card"></i> Informations de paiement
                </h4>
                
                <?php if ($paiement): ?>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Mode de paiement</div>
                        <div class="info-value">
                            <?php 
                            switch($paiement['mode_paiement']) {
                                case 'mobile_money':
                                    echo 'Mobile Money';
                                    break;
                                case 'carte_bancaire':
                                    echo 'Carte bancaire';
                                    break;
                                case 'espece':
                                    echo 'Paiement à la livraison';
                                    break;
                                default:
                                    echo $paiement['mode_paiement'];
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Statut du paiement</div>
                        <div class="info-value">
                            <span class="statut-badge statut-<?php echo $paiement['statut_paiement'] === 'paye' ? 'payee' : 'en-attente'; ?>">
                                <?php echo ucfirst($paiement['statut_paiement']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Montant</div>
                        <div class="info-value"><?php echo number_format($paiement['montant'], 0, ',', ' '); ?> FCFA</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date du paiement</div>
                        <div class="info-value">
                            <?php 
                            if ($paiement['date_paiement']) {
                                echo date('d/m/Y H:i', strtotime($paiement['date_paiement']));
                            } else {
                                echo 'En attente';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted">Aucune information de paiement disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="actions">
        <a href="index.php" class="btn-action btn-outline">
            <i class="fas fa-home"></i> Retour à l'accueil
        </a>
        <a href="produits.php" class="btn-action btn-outline">
            <i class="fas fa-shopping-bag"></i> Continuer mes achats
        </a>
    </div>
    <?php else: ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo $confirmation_message ?: 'Commande introuvable.'; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal d'attente de paiement -->
<div id="waitingModal" class="waiting-modal" style="display: none;">
    <div class="waiting-content">
        <div class="waiting-spinner"></div>
        <h2>Paiement en cours</h2>
        <p style="color: var(--gray-600);">Une demande de paiement a été envoyée à votre téléphone.</p>
        <p style="font-weight: 600; margin-top: 1rem;">Montant à confirmer :</p>
        <p style="font-size: 1.75rem; font-weight: 800; color: var(--primary-color);">
            <?php echo number_format($commande['montant_total'] ?? 0, 0, ',', ' '); ?> FCFA
        </p>
        <div class="reference" id="campayReference"></div>
        
        <div style="background: var(--primary-light); padding: 0.75rem; border-radius: var(--border-radius); text-align: left; margin: 1rem 0;">
            <p style="font-weight: 600; margin-bottom: 0.5rem; font-size: 0.875rem;"><i class="fas fa-clock"></i> En attente de confirmation...</p>
            <p style="font-size: 0.75rem; color: var(--gray-600);">1. Vérifiez les notifications sur votre téléphone</p>
            <p style="font-size: 0.75rem; color: var(--gray-600);">2. Entrez votre code PIN pour autoriser le paiement</p>
            <p style="font-size: 0.75rem; color: var(--gray-600);">3. Patientez pendant la confirmation</p>
        </div>
        
        <p style="font-size: 0.7rem; color: var(--gray-500);">
            <i class="fas fa-sync-alt fa-spin"></i> Cette page se mettra à jour automatiquement
        </p>
        
        <button onclick="window.location.reload()" class="btn-refresh">
            <i class="fas fa-sync-alt"></i>
            Rafraîchir
        </button>
    </div>
</div>

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

.confirmation-badge {
    display: inline-block;
    padding: 0.5rem 1.5rem;
    border-radius: 2rem;
    font-size: 1.1rem;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    animation: fadeInUp 0.5s ease 0.2s both;
}

.confirmation-badge.success {
    background: rgba(72, 187, 120, 0.2);
    border-color: var(--success-color);
}

.confirmation-badge.error {
    background: rgba(245, 101, 101, 0.2);
    border-color: var(--error-color);
}

.confirmation-badge.pending {
    background: rgba(237, 137, 54, 0.2);
    border-color: var(--pending-color);
}

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.status-card {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    text-align: center;
    position: relative;
    overflow: hidden;
    border: 2px solid transparent;
    transition: var(--transition);
}

.status-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-xl);
}

.status-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.status-card.success::before {
    background: linear-gradient(90deg, var(--success-color), #9ae6b4);
}

.status-card.error::before {
    background: linear-gradient(90deg, var(--error-color), #fc8181);
}

.status-card.pending::before {
    background: linear-gradient(90deg, var(--pending-color), #fbd38d);
}

.status-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
    from { transform: scale(0); }
    to { transform: scale(1); }
}

.status-icon.success {
    background: linear-gradient(135deg, var(--success-light), #f0fff4);
    color: var(--success-color);
    box-shadow: 0 0 0 10px rgba(72, 187, 120, 0.1);
}

.status-icon.error {
    background: linear-gradient(135deg, var(--error-light), #fff5f5);
    color: var(--error-color);
    box-shadow: 0 0 0 10px rgba(245, 101, 101, 0.1);
}

.status-icon.pending {
    background: linear-gradient(135deg, var(--pending-light), #fffbeb);
    color: var(--pending-color);
    box-shadow: 0 0 0 10px rgba(237, 137, 54, 0.1);
}

.status-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--gray-800);
}

.status-details {
    max-width: 600px;
    margin: 0 auto;
    padding: 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius);
}

.status-details p {
    margin-bottom: 0.5rem;
    color: var(--gray-700);
}

.code-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: var(--gray-200);
    border-radius: 2rem;
    font-family: monospace;
    font-size: 0.875rem;
}

.status-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.status-action-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 2rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 2px solid transparent;
}

.status-action-btn.primary {
    background: var(--primary-color);
    color: white;
}

.status-action-btn.primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    color: white;
    text-decoration: none;
}

.status-action-btn.secondary {
    background: transparent;
    border-color: var(--gray-300);
    color: var(--gray-700);
}

.status-action-btn.secondary:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-2px);
    text-decoration: none;
}

.progress-steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: 3rem;
    padding: 0 1rem;
    position: relative;
}

.progress-steps::before {
    content: '';
    position: absolute;
    top: 1.5rem;
    left: 2rem;
    right: 2rem;
    height: 2px;
    background: var(--gray-300);
    z-index: 1;
}

.step {
    flex: 1;
    text-align: center;
    position: relative;
    z-index: 2;
    background: white;
    padding: 0 0.5rem;
}

.step-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: white;
    border: 2px solid var(--gray-300);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-size: 1.25rem;
    color: var(--gray-500);
    transition: var(--transition);
}

.step.completed .step-icon {
    background: var(--success-color);
    border-color: var(--success-color);
    color: white;
    animation: bounce 0.5s ease;
}

.step.active .step-icon {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: white;
}

.step-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    font-weight: 500;
}

.step.completed .step-label {
    color: var(--success-color);
}

.step.active .step-label {
    color: var(--primary-color);
    font-weight: 600;
}

@keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.confirmation-card {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.confirmation-card:hover {
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

.payment-section {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    border: 2px solid var(--primary-color);
    position: relative;
    overflow: hidden;
}

.payment-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
}

.payment-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--gray-800);
}

.payment-title i {
    color: var(--primary-color);
    font-size: 2rem;
    background: var(--primary-light);
    padding: 0.75rem;
    border-radius: 50%;
}

.campay-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary-light);
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.875rem;
    color: var(--primary-color);
    margin-bottom: 1.5rem;
    border: 1px solid var(--primary-color);
}

.modes-paiement {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.mode-paiement {
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    background: white;
    position: relative;
}

.mode-paiement:hover {
    transform: translateY(-5px);
    border-color: transparent;
    box-shadow: var(--shadow-lg);
}

#mode-orange:hover,
#mode-orange.selected {
    border-color: var(--orange);
    background: var(--orange-light);
}

#mode-mtn:hover,
#mode-mtn.selected {
    border-color: var(--mtn);
    background: var(--mtn-light);
}

.mode-paiement.selected {
    transform: translateY(-5px);
    border-width: 2px;
}

.mode-paiement i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    transition: transform 0.3s;
    display: block;
}

.mode-paiement:hover i {
    transform: scale(1.1);
}

#mode-orange i { color: var(--orange); }
#mode-mtn i { color: var(--mtn); }

.mode-paiement h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: var(--gray-800);
}

.mode-paiement p {
    font-size: 0.75rem;
    color: var(--gray-600);
    margin: 0;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-700);
    font-size: 0.875rem;
}

.form-group label i {
    margin-right: 0.5rem;
    color: var(--primary-color);
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--gray-300);
    border-radius: var(--border-radius);
    font-size: 1rem;
    transition: var(--transition);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-control.is-invalid {
    border-color: var(--error-color);
    background: var(--error-light);
}

.info-box {
    background: linear-gradient(135deg, var(--primary-light), white);
    padding: 1rem;
    border-radius: var(--border-radius);
    font-size: 0.875rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--primary-color);
}

.info-box i {
    font-size: 1.5rem;
    color: var(--primary-color);
}

.btn-payer {
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
    position: relative;
    overflow: hidden;
}

.btn-payer::before {
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

.btn-payer:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-payer:hover::before {
    width: 300px;
    height: 300px;
}

.btn-payer:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.btn-payer .loader {
    display: none;
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.waiting-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(8px);
    z-index: 9999;
    animation: fadeIn 0.3s;
}

.waiting-content {
    background: white;
    max-width: 450px;
    margin: 100px auto;
    padding: 2rem;
    border-radius: var(--border-radius-lg);
    text-align: center;
    animation: slideDown 0.5s ease;
    box-shadow: var(--shadow-xl);
}

.waiting-spinner {
    width: 70px;
    height: 70px;
    border: 5px solid var(--gray-200);
    border-top: 5px solid var(--primary-color);
    border-right: 5px solid var(--orange);
    border-bottom: 5px solid var(--mtn);
    border-radius: 50%;
    animation: spin 1.5s linear infinite;
    margin: 0 auto 1.5rem;
}

.waiting-content h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--gray-800);
}

.waiting-content .reference {
    background: var(--gray-100);
    padding: 0.75rem;
    border-radius: var(--border-radius);
    font-family: monospace;
    font-size: 0.75rem;
    margin: 1rem 0;
    word-break: break-all;
}

.btn-refresh {
    margin-top: 1rem;
    padding: 0.625rem 1.25rem;
    background: var(--gray-100);
    border: 2px solid var(--gray-300);
    border-radius: 2rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-refresh:hover {
    background: var(--primary-light);
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.table-responsive {
    overflow-x: auto;
}

.table {
    width: 100%;
    margin-bottom: 0;
}

.table thead th {
    background: var(--gray-100);
    color: var(--gray-700);
    font-weight: 600;
    padding: 1rem;
    border-bottom: 2px solid var(--gray-300);
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
}

.table tfoot td {
    padding: 0.75rem 1rem;
}

.product-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-bottom: 0.25rem;
    font-weight: 500;
}

.info-value {
    font-size: 1rem;
    color: var(--gray-800);
    font-weight: 500;
}

.statut-badge {
    display: inline-block;
    padding: 0.375rem 1rem;
    border-radius: 2rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.statut-badge.statut-payee,
.statut-badge.statut-confirmee {
    background: var(--success-light);
    color: #2f855a;
    border: 1px solid var(--success-color);
}

.statut-badge.statut-en-attente {
    background: var(--pending-light);
    color: #c05621;
    border: 1px solid var(--pending-color);
}

.actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.btn-action {
    padding: 0.75rem 2rem;
    border-radius: 2rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 2px solid transparent;
}

.btn-action.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-action.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    text-decoration: none;
    color: white;
}

.btn-action.btn-outline {
    border: 2px solid var(--gray-300);
    background: white;
    color: var(--gray-700);
}

.btn-action.btn-outline:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-2px);
    text-decoration: none;
}

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

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-50px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .page-header h1 { font-size: 1.75rem; }
    .status-card { padding: 1.5rem; }
    .status-title { font-size: 1.25rem; }
    .modes-paiement { grid-template-columns: 1fr; }
    .progress-steps::before { left: 1rem; right: 1rem; }
    .step-icon { width: 2.5rem; height: 2.5rem; font-size: 1rem; }
    .step-label { font-size: 0.75rem; }
    .confirmation-card { padding: 1.5rem; }
    .info-grid { grid-template-columns: 1fr; }
    .actions { flex-direction: column; }
    .btn-action { width: 100%; justify-content: center; }
    .payment-section { padding: 1.5rem; }
    .payment-title { font-size: 1.25rem; }
    .waiting-content { margin: 50px 20px; padding: 1.5rem; }
}

@media print {
    .page-header, .status-actions, .actions, .progress-steps, .payment-section, .alert, .waiting-modal {
        display: none !important;
    }
    .confirmation-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; }
    .status-card { border: 2px solid #ddd; box-shadow: none; }
}
</style>

<script>
// Sélection du mode de paiement
function selectMode(mode) {
    document.getElementById('mode_paiement').value = mode;
    
    document.querySelectorAll('.mode-paiement').forEach(el => {
        el.classList.remove('selected');
    });
    
    const selectedMode = document.getElementById('mode-' + mode);
    if (selectedMode) {
        selectedMode.classList.add('selected');
        selectedMode.style.transform = 'scale(0.98)';
        setTimeout(() => {
            selectedMode.style.transform = '';
        }, 150);
    }
}

// Validation du formulaire
const paiementForm = document.getElementById('paiementForm');
if (paiementForm) {
    paiementForm.addEventListener('submit', function(e) {
        const mode = document.getElementById('mode_paiement').value;
        const telephone = document.getElementById('telephone').value.trim().replace(/\D/g, '');
        const btnPayer = document.getElementById('btnPayer');
        
        if (!mode) {
            e.preventDefault();
            alert('Veuillez choisir un opérateur (Orange Money ou MTN Money)');
            document.querySelector('.modes-paiement').scrollIntoView({ behavior: 'smooth' });
            return false;
        }
        
        if (!telephone || telephone.length < 9) {
            e.preventDefault();
            const telephoneField = document.getElementById('telephone');
            telephoneField.classList.add('is-invalid');
            alert('Numéro de téléphone invalide (9 chiffres minimum)');
            telephoneField.focus();
            return false;
        }
        
        if (btnPayer) {
            const btnText = btnPayer.querySelector('span');
            const loader = btnPayer.querySelector('.loader');
            btnPayer.disabled = true;
            if (btnText) btnText.style.opacity = '0';
            if (loader) loader.style.display = 'inline-block';
        }
        
        return true;
    });
}

// Validation en temps réel
const telephoneField = document.getElementById('telephone');
if (telephoneField) {
    telephoneField.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 12) value = value.slice(0, 12);
        this.value = value;
        
        if (value.length >= 9) {
            this.classList.remove('is-invalid');
        }
    });
}

// Vérifier si on vient d'initier un paiement Campay
<?php if (isset($_SESSION['campay_reference']) && isset($_GET['attente_paiement'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('waitingModal');
    const referenceSpan = document.getElementById('campayReference');
    if (modal && referenceSpan) {
        referenceSpan.textContent = 'Référence: <?php echo $_SESSION['campay_reference']; ?>';
        modal.style.display = 'block';
        
        let checkCount = 0;
        const checkInterval = setInterval(function() {
            checkCount++;
            fetch('verifier_paiement.php?reference=<?php echo $_SESSION['campay_reference']; ?>&commande_id=<?php echo $_SESSION['campay_commande_id']; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.statut === 'SUCCESSFUL') {
                        clearInterval(checkInterval);
                        window.location.href = 'commande_confirmation.php?campay_success=1&reference=<?php echo $_SESSION['campay_reference']; ?>&commande_id=<?php echo $_SESSION['campay_commande_id']; ?>';
                    } else if (checkCount > 60) {
                        clearInterval(checkInterval);
                        modal.style.display = 'none';
                        alert('Le paiement prend plus de temps que prévu. Veuillez vérifier votre téléphone ou rafraîchir la page.');
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }, 5000);
    }
});
<?php 
unset($_SESSION['campay_reference']);
unset($_SESSION['campay_commande_id']);
endif; 
?>

// Animations
document.querySelectorAll('.mode-paiement').forEach(el => {
    el.addEventListener('mouseenter', function() {
        if (!this.classList.contains('selected')) {
            this.style.transform = 'translateY(-2px)';
        }
    });
    
    el.addEventListener('mouseleave', function() {
        if (!this.classList.contains('selected')) {
            this.style.transform = '';
        }
    });
});

// Focus automatique
window.addEventListener('load', function() {
    const telField = document.getElementById('telephone');
    if (telField && !telField.value) {
        setTimeout(() => telField.focus(), 500);
    }
});
</script>

<?php include 'includes/footer.php'; ?>