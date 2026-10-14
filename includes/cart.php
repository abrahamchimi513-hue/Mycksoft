<?php
// api/cart.php
require_once '../includes/config.php';
require_once '../classes/Panier.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// FONCTION IMPORTANTE : Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    // L'utilisateur n'est pas connecté, on renvoie une erreur 401
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour ajouter des articles au panier',
        'redirect' => 'connexion.php?redirect=' . urlencode($_SERVER['HTTP_REFERER'] ?? 'produits.php')
    ]);
    exit;
}

// Si on arrive ici, l'utilisateur est connecté
$user_id = getUserId();
$cart = new Panier($pdo, $user_id);

// Récupérer l'action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
            exit;
        }
        
        $result = $cart->add($product_id, $quantity);
        echo json_encode($result);
        break;
        
    case 'update':
        $item_id = $_POST['item_id'] ?? '';
        $quantity = intval($_POST['quantity'] ?? 1);
        
        $result = $cart->update($item_id, $quantity);
        echo json_encode($result);
        break;
        
    case 'remove':
        $item_id = $_POST['item_id'] ?? '';
        
        $result = $cart->remove($item_id);
        echo json_encode($result);
        break;
        
    case 'clear':
        $result = $cart->clear();
        echo json_encode($result);
        break;
        
    case 'get_summary':
        echo json_encode($cart->getSummary());
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action non valide']);
}