<?php
// campay_callback.php - Point d'entrée pour les callbacks de Campay
require_once 'includes/config.php';
require_once 'includes/campay_config.php';

// Récupérer les données du callback
$input = json_decode(file_get_contents('php://input'), true);

// Journaliser le callback pour débogage
$log_file = 'campay_callback.log';
$log_entry = date('Y-m-d H:i:s') . " - Callback reçu: " . json_encode($input) . PHP_EOL;
file_put_contents($log_file, $log_entry, FILE_APPEND);

// Vérifier si c'est une confirmation de paiement
if (isset($input['status']) && $input['status'] === 'SUCCESSFUL') {
    
    $reference = $input['reference'] ?? '';
    $external_reference = $input['external_reference'] ?? '';
    $amount = $input['amount'] ?? 0;
    $telephone = $input['payer'] ?? '';
    
    // Extraire l'ID de commande de la référence externe
    // Format attendu: ORDER_123
    if (strpos($external_reference, 'ORDER_') === 0) {
        $commande_id = intval(str_replace('ORDER_', '', $external_reference));
        
        // Vérifier que la commande existe
        $check = $pdo->prepare("SELECT id_commande, montant_total FROM commande WHERE id_commande = ?");
        $check->execute([$commande_id]);
        $commande = $check->fetch();
        
        if ($commande) {
            // Vérifier que le montant correspond
            if (floatval($amount) == floatval($commande['montant_total'])) {
                // Mettre à jour la commande
                $update = $pdo->prepare("
                    UPDATE commande 
                    SET statut = 'payee', 
                        mode_paiement = 'campay', 
                        reference_paiement = ?,
                        date_paiement = NOW() 
                    WHERE id_commande = ?
                ");
                $update->execute([$reference, $commande_id]);
                
                // Réponse de succès
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Commande mise à jour']);
                
                // Journaliser
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - Commande #$commande_id mise à jour avec succès" . PHP_EOL, FILE_APPEND);
            } else {
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - ERREUR: Montant incorrect pour commande #$commande_id" . PHP_EOL, FILE_APPEND);
            }
        } else {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - ERREUR: Commande #$commande_id non trouvée" . PHP_EOL, FILE_APPEND);
        }
    }
} else {
    // Paiement échoué ou autre statut
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Statut non traité: " . ($input['status'] ?? 'INCONNU') . PHP_EOL, FILE_APPEND);
}

// Toujours retourner 200 pour accuser réception
http_response_code(200);
echo json_encode(['status' => 'received']);
?>