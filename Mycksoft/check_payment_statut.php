<?php
// check_payment_status.php - Vérification AJAX du statut de paiement
require_once 'includes/config.php';
require_once 'includes/campay_config.php';

header('Content-Type: application/json');

if (!isset($_GET['reference']) || !isset($_GET['commande_id'])) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$reference = $_GET['reference'];
$commande_id = intval($_GET['commande_id']);

// Vérifier le statut via Campay
$statut = verifierStatutPaiement($reference);

if ($statut['success'] && $statut['status'] === 'SUCCESSFUL') {
    // Mettre à jour la commande si elle n'est pas déjà payée
    $check = $pdo->prepare("SELECT statut FROM commande WHERE id_commande = ?");
    $check->execute([$commande_id]);
    $commande = $check->fetch();
    
    if ($commande && $commande['statut'] !== 'payee') {
        $update = $pdo->prepare("
            UPDATE commande 
            SET statut = 'payee', 
                mode_paiement = 'campay', 
                reference_paiement = ?,
                date_paiement = NOW() 
            WHERE id_commande = ?
        ");
        $update->execute([$reference, $commande_id]);
    }
    
    echo json_encode(['success' => true, 'status' => 'SUCCESSFUL', 'commande_id' => $commande_id]);
} else {
    echo json_encode(['success' => false, 'status' => $statut['status'] ?? 'PENDING', 'message' => $statut['message'] ?? 'En attente']);
}
?>