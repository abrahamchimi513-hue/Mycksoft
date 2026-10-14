<?php
require_once 'includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setFlashMessage('Veuillez vous connecter', 'error');
    redirect('login.php');
}

// Récupérer l'ID de commande
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    setFlashMessage('Commande non trouvée', 'error');
    redirect('orders.php');
}

// Récupérer les détails de la commande
$order_query = "SELECT c.*, p.statut_paiement, p.mode_paiement 
                FROM commande c
                LEFT JOIN paiement p ON c.id_commande = p.id_commande
                WHERE c.id_commande = ? AND c.id_utilisateur = ?";
$stmt = $db->prepare($order_query);
$stmt->execute([$order_id, getUserId()]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('Commande non trouvée', 'error');
    redirect('orders.php');
}

// Récupérer les articles de la commande
$items_query = "SELECT lc.*, p.nom_produit, p.image_url 
                FROM ligne_commande lc
                INNER JOIN v_produits_complets p ON lc.id_produit = p.id_produit
                WHERE lc.id_commande = ?";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll();

$page_title = "Confirmation de commande";
include 'includes/header.php';
?>

<div class="container" style="min-height: 70vh; padding: 2rem 0;">
    <div style="max-width: 800px; margin: 0 auto; text-align: center;">
        <!-- Icône de succès -->
        <div style="margin-bottom: 2rem;">
            <div style="width: 80px; height: 80px; background: #10b981; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 2rem;">
                <i class="fas fa-check"></i>
            </div>
        </div>
        
        <h1 style="margin-bottom: 1rem; color: #10b981;">Commande confirmée !</h1>
        <p style="font-size: 1.1rem; color: #666; margin-bottom: 2rem;">
            Merci pour votre commande. Nous vous enverrons un email de confirmation dès que votre commande sera expédiée.
        </p>
        
        <!-- Récapitulatif -->
        <div style="background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 2rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div>
                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">Numéro de commande</div>
                    <div style="font-weight: bold; font-size: 1.2rem;"><?php echo htmlspecialchars($order['numero_commande']); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">Date</div>
                    <div style="font-weight: bold;"><?php echo date('d/m/Y H:i', strtotime($order['date_commande'])); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">Statut</div>
                    <div style="font-weight: bold; color: #f59e0b;"><?php echo htmlspecialchars($order['statut']); ?></div>
                </div>
                
                <div>
                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">Paiement</div>
                    <div style="font-weight: bold; color: #10b981;"><?php echo htmlspecialchars($order['statut_paiement']); ?></div>
                </div>
            </div>
            
            <div style="border-top: 1px solid #eee; padding-top: 1.5rem;">
                <h3 style="margin-bottom: 1.5rem; text-align: left;">Détails de la commande</h3>
                
                <?php foreach ($order_items as $item): ?>
                    <div style="display: flex; align-items: center; padding: 1rem 0; border-bottom: 1px solid #eee;">
                        <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'assets/images/default-product.jpg'); ?>" 
                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 1rem;">
                        
                        <div style="flex: 1; text-align: left;">
                            <div style="font-weight: 500;"><?php echo htmlspecialchars($item['nom_produit']); ?></div>
                            <div style="color: #666; font-size: 0.9rem;">
                                Qté: <?php echo $item['quantite']; ?>
                            </div>
                        </div>
                        
                        <div style="font-weight: bold;">
                            <?php echo formatPrice($item['prix_unitaire'] * $item['quantite']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align: right; margin-top: 1.5rem;">
                    <div style="margin-bottom: 0.5rem;">
                        <span style="margin-right: 2rem;">Sous-total:</span>
                        <span style="font-weight: bold;"><?php echo formatPrice($order['sous_total']); ?></span>
                    </div>
                    
                    <div style="margin-bottom: 0.5rem;">
                        <span style="margin-right: 2rem;">Livraison:</span>
                        <span style="font-weight: bold;"><?php echo formatPrice($order['frais_livraison']); ?></span>
                    </div>
                    
                    <div style="border-top: 2px solid #ddd; padding-top: 1rem; margin-top: 1rem;">
                        <span style="margin-right: 2rem; font-size: 1.1rem;">Total:</span>
                        <span style="font-weight: bold; font-size: 1.3rem; color: #007bff;">
                            <?php echo formatPrice($order['montant_total']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Instructions de paiement -->
        <?php if ($order['mode_paiement'] === 'mobile_money' && $order['statut_paiement'] === 'en attente'): ?>
            <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="color: #0369a1; margin-bottom: 1rem;">
                    <i class="fas fa-mobile-alt"></i> Instructions pour Mobile Money
                </h3>
                <p style="color: #0369a1; margin-bottom: 1rem;">
                    Veuillez effectuer le paiement Mobile Money au numéro suivant:
                </p>
                <div style="background: white; border-radius: 4px; padding: 1rem; margin-bottom: 1rem;">
                    <div style="font-weight: bold; font-size: 1.2rem; margin-bottom: 0.5rem;">
                        MTN: +237 670 000 000
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">
                        Montant: <?php echo formatPrice($order['montant_total']); ?><br>
                        Référence: <?php echo $order['numero_commande']; ?>
                    </div>
                </div>
                <p style="color: #0369a1; font-size: 0.9rem;">
                    Votre commande sera traitée une fois le paiement confirmé.
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Actions -->
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="orders.php" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                <i class="fas fa-list"></i> Voir mes commandes
            </a>
            
            <a href="index.php" class="btn btn-outline" style="padding: 0.75rem 1.5rem;">
                <i class="fas fa-shopping-bag"></i> Continuer mes achats
            </a>
            
            <button onclick="window.print()" class="btn btn-secondary" style="padding: 0.75rem 1.5rem;">
                <i class="fas fa-print"></i> Imprimer
            </button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?><?php
// order_confirmation.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Le reste de votre code order_confirmation.php...
