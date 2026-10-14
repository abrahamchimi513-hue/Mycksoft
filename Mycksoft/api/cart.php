<?php
// api/cart.php
require_once '../includes/config.php';
require_once '../classes/Panier.php';

// Définir le type de réponse
header('Content-Type: application/json');

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Initialiser le panier
$user_id = getUserId();
$cart = new Panier($pdo, $user_id);

// Récupérer l'action
$action = $_POST['action'] ?? '';

switch ($action) {
    
    case 'add':
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
            break;
        }
        
        $result = $cart->add($product_id, $quantity);
        echo json_encode($result);
        break;
        
    case 'update':
        $item_id = $_POST['item_id'] ?? '';
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if (empty($item_id)) {
            echo json_encode(['success' => false, 'message' => 'ID article invalide']);
            break;
        }
        
        $result = $cart->update($item_id, $quantity);
        echo json_encode($result);
        break;
        
    case 'remove':
        $item_id = $_POST['item_id'] ?? '';
        
        if (empty($item_id)) {
            echo json_encode(['success' => false, 'message' => 'ID article invalide']);
            break;
        }
        
        $result = $cart->remove($item_id);
        echo json_encode($result);
        break;
        
    case 'clear':
        $result = $cart->clear();
        echo json_encode($result);
        break;
        
    case 'get':
        $content = $cart->getContent();
        $totals = $cart->getTotals();
        
        echo json_encode([
            'success' => true,
            'items' => $content,
            'totals' => $totals,
            'is_empty' => $cart->isEmpty()
        ]);
        break;
        
    case 'count':
        echo json_encode([
            'success' => true,
            'count' => $cart->getTotalItems()
        ]);
        break;
        
    case 'summary':
        echo json_encode($cart->getSummary());
        break;
        
    case 'sync':
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
            break;
        }
        
        $result = $cart->syncSessionCart();
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Panier synchronisé' : 'Erreur de synchronisation'
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        break;
}
?>