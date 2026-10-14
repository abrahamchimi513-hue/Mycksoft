<?php
// api/get_category.php
require_once '../includes/config.php';

// Vérifier l'authentification
if (!isLoggedIn() || !isAdmin()) {
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
    $stmt = $pdo->prepare("SELECT * FROM categorie WHERE id_categorie = ?");
    $stmt->execute([$id]);
    $categorie = $stmt->fetch();
    
    if ($categorie) {
        echo json_encode([
            'success' => true,
            'id_categorie' => $categorie['id_categorie'],
            'nom_categorie' => $categorie['nom_categorie'],
            'description' => $categorie['description'],
            'image_categorie' => $categorie['image_categorie'],
            'id_categorie_parent' => $categorie['id_categorie_parent']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Catégorie non trouvée']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}