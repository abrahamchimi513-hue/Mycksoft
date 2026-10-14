<?php
// commande_confirmation.php
require_once 'includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setFlashMessage('Veuillez vous connecter pour accéder à cette page', 'error');
    redirect('connexion.php');
}

// Vérifier si l'ID de commande est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Aucune commande spécifiée', 'error');
    redirect('index.php');
}

$id_commande = (int)$_GET['id'];
$user_id = getUserId();

// Initialiser les variables pour éviter les erreurs "undefined"
$commande = null;
$lignes_commande = [];
$livraison = null;
$paiement = null;

// Variable pour suivre le statut de confirmation
$confirmation_status = 'success';
$confirmation_message = '';
$confirmation_details = [];

// Récupérer les informations de la commande
try {
    // Vérifier que la commande appartient bien à l'utilisateur connecté
    $stmt = $pdo->prepare("
        SELECT c.*, u.nom, u.prenom, u.email, u.telephone
        FROM commande c
        JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
        WHERE c.id_commande = ? AND c.id_utilisateur = ?
    ");
    $stmt->execute([$id_commande, $user_id]);
    $commande = $stmt->fetch();

    if (!$commande) {
        $confirmation_status = 'error';
        $confirmation_message = 'Commande introuvable. Veuillez vérifier le numéro de commande.';
        $confirmation_details = [
            'code' => 'NOT_FOUND',
            'description' => 'La commande demandée n\'existe pas ou ne vous appartient pas.'
        ];
    } else {
        // Vérifier le statut de la commande
        switch($commande['statut']) {
            case 'en attente':
                $confirmation_status = 'pending';
                $confirmation_message = 'Votre commande a été enregistrée mais est en attente de confirmation.';
                $confirmation_details = [
                    'code' => 'PENDING',
                    'description' => 'Nous traitons actuellement votre commande. Vous recevrez une confirmation sous peu.',
                    'next_steps' => 'Veuillez procéder au paiement si ce n\'est pas déjà fait.'
                ];
                break;
            case 'confirmée':
            case 'confirmee':
                $confirmation_status = 'success';
                $confirmation_message = 'Félicitations ! Votre commande a été confirmée avec succès.';
                $confirmation_details = [
                    'code' => 'CONFIRMED',
                    'description' => 'Tous les articles sont disponibles et votre commande est validée.',
                    'next_steps' => 'Nous préparons votre commande pour expédition.'
                ];
                break;
            case 'livrée':
            case 'livree':
                $confirmation_status = 'success';
                $confirmation_message = 'Votre commande a été livrée avec succès !';
                $confirmation_details = [
                    'code' => 'DELIVERED',
                    'description' => 'Votre commande a été marquée comme livrée.',
                    'next_steps' => 'Nous espérons que vous êtes satisfait de vos achats.'
                ];
                break;
            case 'annulée':
            case 'annulee':
                $confirmation_status = 'error';
                $confirmation_message = 'Votre commande a été annulée.';
                $confirmation_details = [
                    'code' => 'CANCELLED',
                    'description' => 'Cette commande a été annulée et ne peut plus être traitée.',
                    'next_steps' => 'Si vous n\'êtes pas à l\'origine de cette annulation, contactez notre service client.'
                ];
                break;
            case 'échec':
            case 'echec':
            case 'failed':
                $confirmation_status = 'error';
                $confirmation_message = 'La confirmation de votre commande a échoué.';
                $confirmation_details = [
                    'code' => 'FAILED',
                    'description' => 'Un problème est survenu lors du traitement de votre commande.',
                    'next_steps' => 'Veuillez réessayer ou contacter notre service client.'
                ];
                break;
            default:
                $confirmation_status = 'info';
                $confirmation_message = 'Statut de commande : ' . ucfirst($commande['statut']);
                $confirmation_details = [
                    'code' => 'UNKNOWN',
                    'description' => 'Le statut actuel de votre commande est : ' . $commande['statut'],
                    'next_steps' => 'Contactez notre service client pour plus d\'informations.'
                ];
        }

        // Récupérer les lignes de la commande - CORRECTION ICI
        // Vérifions d'abord quelles colonnes existent dans la table produit
        try {
            // Requête sans le champ image qui semble ne pas exister
            $stmt = $pdo->prepare("
                SELECT lc.*, p.nom_produit
                FROM ligne_commande lc
                JOIN produit p ON lc.id_produit = p.id_produit
                WHERE lc.id_commande = ?
            ");
            $stmt->execute([$id_commande]);
            $lignes_commande = $stmt->fetchAll();
            
            // Si la requête réussit, on ajoute une image par défaut pour chaque ligne
            foreach ($lignes_commande as &$ligne) {
                $ligne['image'] = 'default.jpg'; // Image par défaut
            }
        } catch (Exception $e) {
            // Si la première requête échoue, essayons sans JOIN
            error_log("Erreur requête avec JOIN: " . $e->getMessage());
            
            $stmt = $pdo->prepare("SELECT * FROM ligne_commande WHERE id_commande = ?");
            $stmt->execute([$id_commande]);
            $lignes_commande = $stmt->fetchAll();
            
            // Récupérer les noms des produits séparément
            foreach ($lignes_commande as &$ligne) {
                $stmt_prod = $pdo->prepare("SELECT nom_produit FROM produit WHERE id_produit = ?");
                $stmt_prod->execute([$ligne['id_produit']]);
                $produit = $stmt_prod->fetch();
                $ligne['nom_produit'] = $produit ? $produit['nom_produit'] : 'Produit inconnu';
                $ligne['image'] = 'default.jpg';
            }
        }

        // Récupérer les informations de livraison
        $stmt = $pdo->prepare("SELECT * FROM livraison WHERE id_commande = ?");
        $stmt->execute([$id_commande]);
        $livraison = $stmt->fetch();

        // Récupérer les informations de paiement
        $stmt = $pdo->prepare("SELECT * FROM paiement WHERE id_commande = ?");
        $stmt->execute([$id_commande]);
        $paiement = $stmt->fetch();

        // Vérifier également le statut du paiement
        if ($paiement) {
            if ($paiement['statut_paiement'] === 'échec' || $paiement['statut_paiement'] === 'echec' || $paiement['statut_paiement'] === 'failed') {
                $confirmation_status = 'error';
                $confirmation_message = 'Échec du paiement';
                $confirmation_details = [
                    'code' => 'PAYMENT_FAILED',
                    'description' => 'Le paiement de votre commande a échoué.',
                    'next_steps' => 'Veuillez vérifier vos informations de paiement et réessayer.'
                ];
            } elseif ($paiement['statut_paiement'] === 'en attente' && $commande['statut'] === 'en attente') {
                $confirmation_status = 'pending';
                $confirmation_message = 'En attente de paiement';
                $confirmation_details = [
                    'code' => 'AWAITING_PAYMENT',
                    'description' => 'Nous attendons la confirmation de votre paiement.',
                    'next_steps' => 'Si vous avez déjà effectué le paiement, veuillez patienter quelques instants.'
                ];
            }
        }
    }
} catch (Exception $e) {
    error_log("Erreur confirmation commande: " . $e->getMessage());
    $confirmation_status = 'error';
    $confirmation_message = 'Erreur technique';
    $confirmation_details = [
        'code' => 'TECHNICAL_ERROR',
        'description' => 'Une erreur technique est survenue lors du chargement de votre commande.',
        'next_steps' => 'Veuillez réessayer plus tard ou contacter notre support technique.',
        'error_details' => $e->getMessage()
    ];
}

$page_title = "Confirmation de commande - " . SITE_NAME;
$header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_show_products_link = true;

include 'includes/header.php';
?>

<!-- [Tout le CSS reste identique] -->

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1><i class="fas fa-check-circle"></i> Confirmation de commande</h1>
        <?php if ($commande && isset($commande['numero_commande'])): ?>
        <div class="confirmation-badge <?php echo $confirmation_status; ?>">
            <i class="fas fa-<?php 
                echo $confirmation_status === 'success' ? 'check-circle' : 
                    ($confirmation_status === 'error' ? 'exclamation-circle' : 
                    ($confirmation_status === 'pending' ? 'clock' : 'info-circle')); 
            ?>"></i>
            N° <?php echo htmlspecialchars($commande['numero_commande']); ?>
        </div>
        <?php endif; ?>
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

    <!-- Section : STATUT DE CONFIRMATION -->
    <div class="status-card <?php echo $confirmation_status; ?>">
        <div class="status-icon">
            <i class="fas fa-<?php 
                echo $confirmation_status === 'success' ? 'check-circle' : 
                    ($confirmation_status === 'error' ? 'times-circle' : 
                    ($confirmation_status === 'pending' ? 'clock' : 'info-circle')); 
            ?>"></i>
        </div>
        
        <h2 class="status-title">
            <?php echo $confirmation_message ?: 'Statut de la commande'; ?>
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
            
            <?php if (isset($confirmation_details['error_details']) && $confirmation_status === 'error'): ?>
            <p class="text-danger mt-2">
                <i class="fas fa-exclamation-triangle"></i>
                <small>Détail technique : <?php echo htmlspecialchars($confirmation_details['error_details']); ?></small>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Actions spécifiques selon le statut -->
        <div class="status-actions">
            <?php if ($confirmation_status === 'error'): ?>
                <?php if ($commande): ?>
                <a href="commande.php" class="status-action-btn primary">
                    <i class="fas fa-redo"></i> Réessayer la commande
                </a>
                <?php endif; ?>
                <a href="contact.php" class="status-action-btn secondary">
                    <i class="fas fa-headset"></i> Contacter le support
                </a>
            <?php elseif ($confirmation_status === 'pending'): ?>
                <?php if ($paiement && $paiement['mode_paiement'] === 'mobile_money'): ?>
                <button onclick="window.print()" class="status-action-btn primary">
                    <i class="fas fa-print"></i> Imprimer les instructions
                </button>
                <?php endif; ?>
                <a href="produits.php" class="status-action-btn secondary">
                    <i class="fas fa-shopping-bag"></i> Continuer mes achats
                </a>
            <?php elseif ($confirmation_status === 'success'): ?>
                <a href="compte.php?page=commandes" class="status-action-btn primary">
                    <i class="fas fa-history"></i> Voir mes commandes
                </a>
                <a href="produits.php" class="status-action-btn secondary">
                    <i class="fas fa-shopping-bag"></i> Continuer mes achats
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($commande): ?>
    <!-- Progress Steps -->
    <div class="progress-steps">
        <div class="step <?php echo in_array($commande['statut'], ['confirmée', 'confirmee', 'livrée', 'livree']) ? 'completed' : 
            ($confirmation_status === 'error' ? 'failed' : 'active'); ?>">
            <div class="step-icon"><i class="fas fa-check"></i></div>
            <div class="step-label">Commande validée</div>
        </div>
        <div class="step <?php 
            if ($paiement && $paiement['statut_paiement'] === 'paye') {
                echo 'completed';
            } elseif ($confirmation_status === 'error' && $paiement && $paiement['statut_paiement'] === 'échec') {
                echo 'failed';
            } else {
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

    <!-- Instructions de paiement si nécessaire -->
    <?php if ($paiement && $paiement['mode_paiement'] === 'mobile_money' && $paiement['statut_paiement'] === 'en attente' && $confirmation_status !== 'error'): ?>
    <div class="confirmation-card">
        <h4 class="card-title">
            <i class="fas fa-mobile-alt"></i> Instructions de paiement Mobile Money
        </h4>
        
        <div class="payment-instructions">
            <h5><i class="fas fa-info-circle"></i> Comment procéder au paiement ?</h5>
            <ol>
                <li>Composez le *150# sur votre téléphone</li>
                <li>Choisissez l'option "Transfert d'argent"</li>
                <li>Entrez le numéro : <strong>6XXXXXXXX</strong></li>
                <li>Entrez le montant : <strong><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</strong></li>
                <li>Entrez votre code secret</li>
                <li>Confirmez la transaction</li>
                <li>Notez le numéro de transaction et envoyez-le par WhatsApp au <strong>6XXXXXXXX</strong></li>
            </ol>
            <p class="text-muted mt-3 mb-0">
                <i class="fas fa-clock"></i> Votre commande sera traitée dès réception du paiement.
            </p>
        </div>
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
                    <span class="statut-badge statut-<?php 
                        $statut_clean = str_replace(['é', 'è', 'ê'], 'e', $commande['statut']);
                        $statut_clean = str_replace([' '], '-', $statut_clean);
                        echo $statut_clean; 
                    ?>">
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
                                <!-- CORRECTION ICI : Utilisation d'une image par défaut -->
                                <img src="uploads/<?php echo isset($ligne['image']) ? htmlspecialchars($ligne['image']) : 'default.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($ligne['nom_produit'] ?? 'Produit'); ?>" 
                                     class="product-img me-3"
                                     onerror="this.src='uploads/default.jpg'">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($ligne['nom_produit'] ?? 'Produit'); ?></div>
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
                        <td class="text-end fw-bold" style="font-size: 1.2rem; color: #667eea;">
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
                            <span class="statut-badge statut-<?php 
                                $statut_paiement = str_replace(['é', 'è', 'ê'], 'e', $paiement['statut_paiement']);
                                $statut_paiement = str_replace(' ', '-', $statut_paiement);
                                echo $statut_paiement === 'paye' ? 'confirmee' : 'en-attente';
                            ?>">
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

    <!-- Notes de commande si présentes -->
    <?php if (!empty($commande['notes_commande'])): ?>
    <div class="confirmation-card">
        <h4 class="card-title">
            <i class="fas fa-sticky-note"></i> Notes de commande
        </h4>
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($commande['notes_commande'])); ?></p>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="actions">
        <a href="index.php" class="btn-action btn-outline">
            <i class="fas fa-home"></i> Retour à l'accueil
        </a>
        <a href="produits.php" class="btn-action btn-outline">
            <i class="fas fa-shopping-bag"></i> Continuer mes achats
        </a>
        <?php if ($paiement && $paiement['statut_paiement'] === 'en attente' && $confirmation_status !== 'error'): ?>
        <a href="#" class="btn-action btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimer les instructions
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Rafraîchir automatiquement le statut toutes les 30 secondes (seulement si en attente)
<?php if (isset($paiement) && $paiement && $paiement['statut_paiement'] === 'en attente' && $confirmation_status !== 'error'): ?>
setTimeout(function() {
    location.reload();
}, 30000);
<?php endif; ?>

// Afficher une notification si le paiement est en attente
<?php if (isset($paiement) && $paiement && $paiement['statut_paiement'] === 'en attente' && $confirmation_status !== 'error'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showNotification('Votre paiement est en attente. Veuillez suivre les instructions ci-dessus.', 'info');
});

function showNotification(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type;
    alertDiv.innerHTML = '<i class="fas fa-info-circle"></i> ' + message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    alertDiv.style.animation = 'slideIn 0.3s ease';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            alertDiv.remove();
        }, 300);
    }, 5000);
}
<?php endif; ?>

// Afficher une notification en cas d'erreur
<?php if ($confirmation_status === 'error'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showNotification('<?php echo addslashes($confirmation_message); ?>', 'error');
});

function showNotification(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type;
    alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    alertDiv.style.animation = 'slideIn 0.3s ease';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            alertDiv.remove();
        }, 300);
    }, 8000);
}
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
<style>
/* Variables de couleur */
:root {
    --primary-color: #667eea;
    --primary-dark: #5a67d8;
    --success-color: #48bb78;
    --success-light: #c6f6d5;
    --error-color: #f56565;
    --error-light: #fed7d7;
    --warning-color: #ecc94b;
    --warning-light: #fefcbf;
    --pending-color: #ed8936;
    --pending-light: #feebc8;
    --info-color: #4299e1;
    --info-light: #bee3f8;
    --gray-100: #f7fafc;
    --gray-200: #edf2f7;
    --gray-300: #e2e8f0;
    --gray-400: #cbd5e0;
    --gray-500: #a0aec0;
    --gray-600: #718096;
    --gray-700: #4a5568;
    --gray-800: #2d3748;
    --gray-900: #1a202c;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --border-radius: 0.5rem;
    --border-radius-sm: 0.25rem;
    --border-radius-lg: 0.75rem;
    --border-radius-xl: 1rem;
    --transition: all 0.3s ease;
}

/* Styles généraux */
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
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
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
    color: white;
}

.confirmation-badge.error {
    background: rgba(245, 101, 101, 0.2);
    border-color: var(--error-color);
    color: white;
}

.confirmation-badge.pending {
    background: rgba(237, 137, 54, 0.2);
    border-color: var(--pending-color);
    color: white;
}

.confirmation-badge i {
    margin-right: 0.5rem;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Status Card */
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

.status-card.info::before {
    background: linear-gradient(90deg, var(--info-color), #90cdf4);
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
    position: relative;
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

.status-icon.info {
    background: linear-gradient(135deg, var(--info-light), #ebf8ff);
    color: var(--info-color);
    box-shadow: 0 0 0 10px rgba(66, 153, 225, 0.1);
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
    font-size: 1rem;
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

/* Progress Steps */
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
    animation: pulse 2s infinite;
}

.step.failed .step-icon {
    border-color: var(--error-color);
    color: var(--error-color);
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

/* Confirmation Card */
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
    border-color: var(--gray-300);
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

/* Payment Instructions */
.payment-instructions {
    background: linear-gradient(135deg, var(--gray-100), white);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
}

.payment-instructions h5 {
    color: var(--gray-800);
    font-size: 1.1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.payment-instructions h5 i {
    color: var(--primary-color);
}

.payment-instructions ol {
    padding-left: 1.5rem;
    margin-bottom: 0;
}

.payment-instructions li {
    margin-bottom: 0.5rem;
    color: var(--gray-700);
}

.payment-instructions strong {
    color: var(--primary-color);
    background: var(--gray-100);
    padding: 0.125rem 0.375rem;
    border-radius: var(--border-radius-sm);
}

/* Table Styles */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table {
    width: 100%;
    margin-bottom: 0;
}

.table thead th {
    background: var(--gray-100);
    color: var(--gray-700);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.875rem;
    letter-spacing: 0.05em;
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
    border-top: 1px solid var(--gray-300);
}

.table tfoot tr:last-child td {
    border-top: 2px solid var(--gray-400);
}

.product-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.product-img:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow);
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-item {
    padding: 0.5rem;
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.info-item:hover {
    background: var(--gray-100);
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
    line-height: 1.5;
}

/* Statut Badge */
.statut-badge {
    display: inline-block;
    padding: 0.375rem 1rem;
    border-radius: 2rem;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: center;
    white-space: nowrap;
}

.statut-badge.statut-confirmee,
.statut-badge.statut-confirmée {
    background: var(--success-light);
    color: #2f855a;
    border: 1px solid var(--success-color);
}

.statut-badge.statut-en-attente,
.statut-badge.statut-en-attente-paiement {
    background: var(--pending-light);
    color: #c05621;
    border: 1px solid var(--pending-color);
}

.statut-badge.statut-annulee,
.statut-badge.statut-annulée {
    background: var(--error-light);
    color: #c53030;
    border: 1px solid var(--error-color);
}

.statut-badge.statut-paye {
    background: var(--success-light);
    color: #2f855a;
    border: 1px solid var(--success-color);
}

.statut-badge.statut-echec {
    background: var(--error-light);
    color: #c53030;
    border: 1px solid var(--error-color);
}

.statut-badge.statut-livree,
.statut-badge.statut-livrée {
    background: var(--success-light);
    color: #2f855a;
    border: 1px solid var(--success-color);
}

/* Actions */
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
    box-shadow: var(--shadow-lg);
    color: white;
    text-decoration: none;
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
    box-shadow: var(--shadow);
}

/* Alert Styles */
.alert {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    padding: 1rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-xl);
    animation: slideIn 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.alert-success {
    background: linear-gradient(135deg, var(--success-light), #f0fff4);
    border-left: 4px solid var(--success-color);
    color: #2f855a;
}

.alert-error {
    background: linear-gradient(135deg, var(--error-light), #fff5f5);
    border-left: 4px solid var(--error-color);
    color: #c53030;
}

.alert-info {
    background: linear-gradient(135deg, var(--info-light), #ebf8ff);
    border-left: 4px solid var(--info-color);
    color: #2b6cb0;
}

.alert i {
    font-size: 1.25rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header h1 {
        font-size: 2rem;
    }
    
    .status-card {
        padding: 1.5rem;
    }
    
    .status-title {
        font-size: 1.25rem;
    }
    
    .status-actions {
        flex-direction: column;
    }
    
    .status-action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .progress-steps::before {
        left: 1rem;
        right: 1rem;
    }
    
    .step-icon {
        width: 2.5rem;
        height: 2.5rem;
        font-size: 1rem;
    }
    
    .step-label {
        font-size: 0.75rem;
    }
    
    .confirmation-card {
        padding: 1.5rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .actions {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
    
    .table thead {
        display: none;
    }
    
    .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--border-radius);
        padding: 1rem;
    }
    
    .table tbody td {
        display: block;
        text-align: right;
        padding: 0.5rem;
        border: none;
    }
    
    .table tbody td::before {
        content: attr(data-label);
        float: left;
        font-weight: 600;
        color: var(--gray-600);
    }
    
    .product-img {
        width: 40px;
        height: 40px;
    }
}

/* Print Styles */
@media print {
    .page-header,
    .status-actions,
    .actions,
    .progress-steps,
    .alert {
        display: none !important;
    }
    
    .confirmation-card {
        box-shadow: none;
        border: 1px solid #ddd;
        break-inside: avoid;
    }
    
    .status-card {
        border: 2px solid #ddd;
        box-shadow: none;
    }
    
    .status-icon {
        background: none !important;
        box-shadow: none !important;
    }
}

/* Loading Animation */
.loading {
    display: inline-block;
    width: 1.5rem;
    height: 1.5rem;
    border: 3px solid var(--gray-300);
    border-top-color: var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>