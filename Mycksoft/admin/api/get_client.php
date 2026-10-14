<?php
// api/get_client_details.php
require_once '../includes/config.php';

// Vérifier l'authentification admin
session_start();
if (!isset($_SESSION['id_utilisateur']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Vérifier l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

$id = intval($_GET['id']);

try {
    // Récupérer les détails du client
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM commande c WHERE c.id_utilisateur = u.id_utilisateur) as nb_commandes,
               (SELECT SUM(montant_total) FROM commande c WHERE c.id_utilisateur = u.id_utilisateur AND c.statut IN ('livree', 'expediee')) as montant_total_commandes
        FROM utilisateur u 
        WHERE u.id_utilisateur = ?
    ");
    $stmt->execute([$id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Client non trouvé']);
        exit;
    }
    
    // Formater la date d'inscription
    if ($client['date_inscription']) {
        $client['date_inscription'] = date('d/m/Y H:i', strtotime($client['date_inscription']));
    }
    
    // Récupérer les commandes du client
    $stmt_orders = $pdo->prepare("
        SELECT numero_commande, date_commande, montant_total, statut 
        FROM commande 
        WHERE id_utilisateur = ? 
        ORDER BY date_commande DESC 
        LIMIT 10
    ");
    $stmt_orders->execute([$id]);
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les dates des commandes
    foreach ($orders as &$order) {
        if ($order['date_commande']) {
            $order['date_commande'] = date('d/m/Y H:i', strtotime($order['date_commande']));
        }
        $order['montant_total'] = floatval($order['montant_total']);
    }
    
    echo json_encode([
        'success' => true,
        'client' => $client,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>