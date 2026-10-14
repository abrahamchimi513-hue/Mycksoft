<?php
// admin/delete_product.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Vérifier que l'utilisateur est admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé. Vous n\'êtes pas administrateur.']);
    exit;
}

// Vérifier la méthode de la requête
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer l'ID du produit
$product_id = $_POST['product_id'] ?? $_GET['product_id'] ?? null;

if (!$product_id || !is_numeric($product_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
    exit;
}

try {
    // Vérifier que le produit existe
    $stmt = $pdo->prepare("SELECT id_produit, image FROM produits WHERE id_produit = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
        exit;
    }
    
    // Supprimer les images du produit
    if (!empty($product['image'])) {
        $images = explode(',', $product['image']);
        foreach ($images as $image) {
            $image = trim($image);
            if (!empty($image)) {
                $image_path = UPLOAD_PATH . 'produits/' . $image;
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
        }
    }
    
    // Supprimer les avis du produit
    $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // Supprimer les articles du panier contenant ce produit
    $stmt = $pdo->prepare("DELETE FROM panier WHERE id_produit = ?");
    $stmt->execute([$product_id]);
    
    // Supprimer les articles des commandes (garder les données pour historique)
    // On met juste à jour le statut plutôt que de supprimer
    $stmt = $pdo->prepare("UPDATE commande_articles SET deleted = 1 WHERE id_produit = ?");
    $stmt->execute([$product_id]);
    
    // Supprimer le produit de la table produits
    $stmt = $pdo->prepare("DELETE FROM produits WHERE id_produit = ?");
    $stmt->execute([$product_id]);
    
    // Logger l'action
    $admin_id = $_SESSION['user_id'] ?? 'unknown';
    error_log("Produit supprimé: ID=$product_id par Admin=$admin_id");
    
    // Retourner une réponse JSON
    echo json_encode([
        'success' => true,
        'message' => 'Produit supprimé avec succès'
    ]);
    
    // Si c'est une requête GET (pas AJAX), rediriger vers la liste des produits
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['ajax'])) {
        header('Location: ' . SITE_URL . '/admin/dashboard_admin.php?section=produits&deleted=1');
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Erreur lors de la suppression du produit: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la suppression du produit'
    ]);
    exit;
} catch (Exception $e) {
    error_log("Erreur générale: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue'
    ]);
    exit;
}
?>
