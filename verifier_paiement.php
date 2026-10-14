<?php
// verifier_paiement.php - API pour vérifier le statut d'un paiement Campay
require_once 'includes/config.php';
require_once 'includes/campay_config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$reference = isset($_GET['reference']) ? $_GET['reference'] : '';
$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;

if (!$reference || !$commande_id) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

// Vérifier que la commande appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT id_commande FROM commande WHERE id_commande = ? AND id_utilisateur = ?");
$stmt->execute([$commande_id, getUserId()]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
    exit;
}

// Vérifier d'abord dans la base de données si le paiement a été enregistré
$stmt = $pdo->prepare("SELECT statut_paiement FROM paiement WHERE id_commande = ? AND reference_paiement = ?");
$stmt->execute([$commande_id, $reference]);
$paiement = $stmt->fetch();

if ($paiement && $paiement['statut_paiement'] === 'paye') {
    echo json_encode([
        'success' => true,
        'statut' => 'SUCCESSFUL',
        'source' => 'database'
    ]);
    exit;
}

// Sinon, vérifier auprès de Campay
$resultat = verifierStatutPaiementCampay($reference);

if ($resultat['success']) {
    echo json_encode([
        'success' => true,
        'statut' => $resultat['statut'],
        'source' => 'campay'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $resultat['message']
    ]);
}
?>