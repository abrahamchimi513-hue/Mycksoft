<?php
// admin_categories.php
require_once '../includes/config.php';

// Vérifier si l'utilisateur est connecté ET administrateur
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Gestion des Catégories - Mycksoft";

// Traitement des actions
$message = '';
$message_type = '';

// Ajouter une catégorie
if (isset($_POST['ajouter_categorie'])) {
    try {
        $nom = trim($_POST['nom_categorie']);
        $description = trim($_POST['description']);
        $id_categorie_parent = !empty($_POST['id_categorie_parent']) ? intval($_POST['id_categorie_parent']) : null;
        
        // Validation
        if (empty($nom)) {
            throw new Exception('Le nom de la catégorie est requis');
        }
        
        // Vérifier si la catégorie existe déjà
        $stmt_check = $pdo->prepare("SELECT id_categorie FROM categorie WHERE nom_categorie = ?");
        $stmt_check->execute([$nom]);
        if ($stmt_check->fetch()) {
            throw new Exception('Une catégorie avec ce nom existe déjà');
        }
        
        // Gestion de l'image
        $image_categorie = null;
        if (!empty($_FILES['image_categorie']['name']) && $_FILES['image_categorie']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Mycksoft/uploads/categories/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $tmp_name = $_FILES['image_categorie']['tmp_name'];
            $extension = pathinfo($_FILES['image_categorie']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array(strtolower($extension), $allowed_extensions)) {
                throw new Exception('Format d\'image non supporté');
            }
            
            $filename = 'categorie_' . time() . '_' . uniqid() . '.' . $extension;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($tmp_name, $destination)) {
                $image_categorie = 'uploads/categories/' . $filename;
            }
        }
        
        // Insertion
        $stmt = $pdo->prepare("
            INSERT INTO categorie (nom_categorie, description, image_categorie, id_categorie_parent, date_creation)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$nom, $description, $image_categorie, $id_categorie_parent]);
        
        $message = "Catégorie ajoutée avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Modifier une catégorie
if (isset($_POST['modifier_categorie'])) {
    try {
        $id_categorie = intval($_POST['id_categorie']);
        $nom = trim($_POST['nom_categorie']);
        $description = trim($_POST['description']);
        $id_categorie_parent = !empty($_POST['id_categorie_parent']) ? intval($_POST['id_categorie_parent']) : null;
        
        if (empty($nom)) {
            throw new Exception('Le nom de la catégorie est requis');
        }
        
        // Vérifier si une autre catégorie a déjà ce nom
        $stmt_check = $pdo->prepare("SELECT id_categorie FROM categorie WHERE nom_categorie = ? AND id_categorie != ?");
        $stmt_check->execute([$nom, $id_categorie]);
        if ($stmt_check->fetch()) {
            throw new Exception('Une catégorie avec ce nom existe déjà');
        }
        
        // Gestion de l'image
        $image_update = '';
        $params = [$nom, $description, $id_categorie_parent, $id_categorie];
        
        if (!empty($_FILES['image_categorie']['name']) && $_FILES['image_categorie']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Mycksoft/uploads/categories/';
            $tmp_name = $_FILES['image_categorie']['tmp_name'];
            $extension = pathinfo($_FILES['image_categorie']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array(strtolower($extension), $allowed_extensions)) {
                throw new Exception('Format d\'image non supporté');
            }
            
            $filename = 'categorie_' . time() . '_' . uniqid() . '.' . $extension;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($tmp_name, $destination)) {
                $image_categorie = 'uploads/categories/' . $filename;
                $image_update = ', image_categorie = ?';
                array_splice($params, 2, 0, $image_categorie);
            }
        }
        
        // Mise à jour
        $sql = "UPDATE categorie SET nom_categorie = ?, description = ?, id_categorie_parent = ?" . $image_update . " WHERE id_categorie = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $message = "Catégorie modifiée avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Supprimer une catégorie
if (isset($_GET['supprimer_categorie'])) {
    try {
        $id_categorie = intval($_GET['supprimer_categorie']);
        
        // Vérifier si la catégorie a des produits
        $stmt_check = $pdo->prepare("SELECT COUNT(*) as nb_produits FROM produit WHERE id_categorie = ?");
        $stmt_check->execute([$id_categorie]);
        $result = $stmt_check->fetch();
        
        if ($result['nb_produits'] > 0) {
            // Si elle a des produits, on peut choisir de déplacer les produits vers une autre catégorie
            // Ou afficher un message d'erreur
            throw new Exception('Cette catégorie contient des produits. Vous ne pouvez pas la supprimer.');
        }
        
        // Vérifier si c'est une catégorie parente
        $stmt_check = $pdo->prepare("SELECT COUNT(*) as nb_enfants FROM categorie WHERE id_categorie_parent = ?");
        $stmt_check->execute([$id_categorie]);
        $result = $stmt_check->fetch();
        
        if ($result['nb_enfants'] > 0) {
            // Mettre à jour les sous-catégories pour les rendre orphelines
            $stmt_update = $pdo->prepare("UPDATE categorie SET id_categorie_parent = NULL WHERE id_categorie_parent = ?");
            $stmt_update->execute([$id_categorie]);
        }
        
        // Suppression
        $stmt = $pdo->prepare("DELETE FROM categorie WHERE id_categorie = ?");
        $stmt->execute([$id_categorie]);
        
        $message = "Catégorie supprimée avec succès !";
        $message_type = "success";
        
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = "error";
    }
}

// Récupérer les catégories
try {
    // Catégories principales (sans parent)
    $stmt_categories = $pdo->query("
        SELECT c1.*, 
               (SELECT COUNT(*) FROM produit p WHERE p.id_categorie = c1.id_categorie) as nb_produits,
               (SELECT COUNT(*) FROM categorie c2 WHERE c2.id_categorie_parent = c1.id_categorie) as nb_sous_categories
        FROM categorie c1 
        WHERE c1.id_categorie_parent IS NULL 
        ORDER BY c1.nom_categorie
    ");
    $categories_principales = $stmt_categories->fetchAll();
    
    // Toutes les catégories pour les select
    $stmt_all_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie");
    $all_categories = $stmt_all_categories->fetchAll();
    
    // Statistiques
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM categorie) as total_categories,
            (SELECT COUNT(*) FROM categorie WHERE id_categorie_parent IS NOT NULL) as sous_categories,
            (SELECT COUNT(DISTINCT id_categorie) FROM produit) as categories_utilisees
    ")->fetch();
    
} catch (Exception $e) {
    $message = "Erreur lors du chargement des catégories : " . $e->getMessage();
    $message_type = "error";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Utiliser le même style que le dashboard */
        :root {
            --green-cm: #2d3d48;
            --red-cm: #ce1126;
            --yellow-cm: #fcd116;
            --green-dark: #005c46;
            --primary: var(--green-cm);
            --secondary: #3f37c9;
            --success: #10b981;
            --danger: var(--red-cm);
            --warning: #f59e0b;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar identique au dashboard */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
        }
        
        .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #eee;
            background: var(--primary);
            color: white;
        }
        
        .logo h2 {
            font-size: 22px;
            margin-bottom: 5px;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .nav-item:hover, .nav-item.active {
            background: #f0f7ff;
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .nav-item i {
            width: 25px;
            margin-right: 10px;
            font-size: 18px;
        }
        
        .user-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .header h1 {
            color: var(--dark);
            font-size: 28px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--green-dark);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            z-index: 1000;
            min-width: 300px;
            animation: slideIn 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .notification-success { background: var(--success); }
        .notification-error { background: var(--danger); }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.categories { background: var(--primary); }
        .stat-icon.subcategories { background: var(--success); }
        .stat-icon.products { background: var(--warning); }
        
        /* Catégories List */
        .categories-list {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .category-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }
        
        .category-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 15px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .category-image i {
            font-size: 24px;
            color: #666;
        }
        
        .category-info {
            flex: 1;
        }
        
        .category-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .category-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .category-stats {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #888;
        }
        
        .category-actions {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s;
        }
        
        .action-btn-edit { background: var(--info); }
        .action-btn-delete { background: var(--danger); }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .logo h2, .nav-item span, .user-info .user-details {
                display: none;
            }
            
            .nav-item {
                justify-content: center;
                padding: 15px;
            }
            
            .nav-item i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .category-item {
                flex-direction: column;
                text-align: center;
            }
            
            .category-image {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .category-actions {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>Mycksoft</h2>
            <p>Administration</p>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard_admin.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="#" class="nav-item active">
                <i class="fas fa-tags"></i>
                <span>Catégories</span>
            </a>
            <a href="admin_avis.php" class="nav-item">
                <i class="fas fa-star"></i>
                <span>Avis clients</span>
            </a>
            <a href="dashboard_admin.php#products" class="nav-item">
                <i class="fas fa-box"></i>
                <span>Produits</span>
            </a>
            <a href="dashboard_admin.php#orders" class="nav-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
            </a>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                <div class="user-role">Administrateur</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-tags"></i> Gestion des Catégories</h1>
            <button class="btn btn-primary" onclick="openModal('modalAjouter')">
                <i class="fas fa-plus"></i>
                Nouvelle catégorie
            </button>
        </div>
        
        <!-- Notifications -->
        <?php if ($message): ?>
            <div class="notification notification-<?php echo $message_type; ?>" id="notification">
                <span><?php echo htmlspecialchars($message); ?></span>
                <button class="notification-close" onclick="closeNotification()">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon categories">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_categories']; ?></h3>
                    <p>Catégories totales</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon subcategories">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['sous_categories']; ?></h3>
                    <p>Sous-catégories</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon products">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['categories_utilisees']; ?></h3>
                    <p>Catégories utilisées</p>
                </div>
            </div>
        </div>
        
        <!-- Liste des catégories -->
        <div class="categories-list">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-list"></i> Liste des catégories</h2>
            </div>
            
            <?php if (empty($categories_principales)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-tags" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                    <p style="color: #666;">Aucune catégorie n'a été créée</p>
                </div>
            <?php else: ?>
                <?php foreach ($categories_principales as $categorie): ?>
                    <div class="category-item">
                        <div class="category-image">
                            <?php if ($categorie['image_categorie']): ?>
                                <img src="../<?php echo htmlspecialchars($categorie['image_categorie']); ?>" alt="<?php echo htmlspecialchars($categorie['nom_categorie']); ?>">
                            <?php else: ?>
                                <i class="fas fa-folder"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="category-info">
                            <div class="category-name"><?php echo htmlspecialchars($categorie['nom_categorie']); ?></div>
                            <?php if ($categorie['description']): ?>
                                <div class="category-description"><?php echo htmlspecialchars(substr($categorie['description'], 0, 100)); ?><?php echo strlen($categorie['description']) > 100 ? '...' : ''; ?></div>
                            <?php endif; ?>
                            <div class="category-stats">
                                <span><i class="fas fa-box"></i> <?php echo $categorie['nb_produits']; ?> produits</span>
                                <span><i class="fas fa-sitemap"></i> <?php echo $categorie['nb_sous_categories']; ?> sous-catégories</span>
                            </div>
                        </div>
                        
                        <div class="category-actions">
                            <button class="action-btn action-btn-edit" onclick="editCategory(<?php echo $categorie['id_categorie']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?supprimer_categorie=<?php echo $categorie['id_categorie']; ?>" 
                               class="action-btn action-btn-delete"
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ? Cette action est irréversible.')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Sous-catégories -->
                    <?php 
                    $stmt_sous = $pdo->prepare("
                        SELECT * FROM categorie 
                        WHERE id_categorie_parent = ? 
                        ORDER BY nom_categorie
                    ");
                    $stmt_sous->execute([$categorie['id_categorie']]);
                    $sous_categories = $stmt_sous->fetchAll();
                    
                    if (!empty($sous_categories)): ?>
                        <div style="margin-left: 30px; margin-top: -5px; margin-bottom: 15px;">
                            <?php foreach ($sous_categories as $sous): ?>
                                <div class="category-item" style="background: #f9f9f9; border: 1px solid #e0e0e0;">
                                    <div class="category-image" style="width: 50px; height: 50px;">
                                        <?php if ($sous['image_categorie']): ?>
                                            <img src="../<?php echo htmlspecialchars($sous['image_categorie']); ?>" alt="<?php echo htmlspecialchars($sous['nom_categorie']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-folder"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="category-info">
                                        <div class="category-name" style="font-size: 14px;"><?php echo htmlspecialchars($sous['nom_categorie']); ?></div>
                                        <div class="category-stats">
                                            <span><i class="fas fa-box"></i> 
                                                <?php 
                                                $stmt_count = $pdo->prepare("SELECT COUNT(*) as nb FROM produit WHERE id_categorie = ?");
                                                $stmt_count->execute([$sous['id_categorie']]);
                                                $count = $stmt_count->fetch();
                                                echo $count['nb']; 
                                                ?> produits
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="category-actions">
                                        <button class="action-btn action-btn-edit" style="width: 30px; height: 30px; font-size: 12px;" onclick="editCategory(<?php echo $sous['id_categorie']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?supprimer_categorie=<?php echo $sous['id_categorie']; ?>" 
                                           class="action-btn action-btn-delete" style="width: 30px; height: 30px; font-size: 12px;"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette sous-catégorie ?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Ajouter Catégorie -->
    <div id="modalAjouter" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajouter une catégorie</h2>
                <button class="modal-close" onclick="closeModal('modalAjouter')">&times;</button>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nom de la catégorie *</label>
                    <input type="text" name="nom_categorie" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Catégorie parente (optionnel)</label>
                    <select name="id_categorie_parent" class="form-control">
                        <option value="">Aucune (catégorie principale)</option>
                        <?php foreach ($all_categories as $cat): ?>
                            <?php if (!$cat['id_categorie_parent']): ?>
                                <option value="<?php echo $cat['id_categorie']; ?>">
                                    <?php echo htmlspecialchars($cat['nom_categorie']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Image de la catégorie</label>
                    <input type="file" name="image_categorie" class="form-control" accept="image/*">
                    <small style="color: #666;">Formats acceptés: JPG, PNG, GIF, WebP. Taille max: 2MB</small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('modalAjouter')">Annuler</button>
                    <button type="submit" name="ajouter_categorie" class="btn btn-primary">Créer la catégorie</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Modifier Catégorie -->
    <div id="modalModifier" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Modifier la catégorie</h2>
                <button class="modal-close" onclick="closeModal('modalModifier')">&times;</button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="formModifier">
                <input type="hidden" name="id_categorie" id="edit_id_categorie">
                
                <div class="form-group">
                    <label>Nom de la catégorie *</label>
                    <input type="text" name="nom_categorie" id="edit_nom_categorie" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Catégorie parente (optionnel)</label>
                    <select name="id_categorie_parent" id="edit_id_categorie_parent" class="form-control">
                        <option value="">Aucune (catégorie principale)</option>
                        <?php foreach ($all_categories as $cat): ?>
                            <?php if (!$cat['id_categorie_parent']): ?>
                                <option value="<?php echo $cat['id_categorie']; ?>">
                                    <?php echo htmlspecialchars($cat['nom_categorie']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Image actuelle</label>
                    <div id="current_image" style="margin-bottom: 10px;"></div>
                    
                    <label>Changer l'image</label>
                    <input type="file" name="image_categorie" class="form-control" accept="image/*">
                    <small style="color: #666;">Laisser vide pour conserver l'image actuelle</small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('modalModifier')">Annuler</button>
                    <button type="submit" name="modifier_categorie" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Gestion des modals
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Notification
        function closeNotification() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }
        
        setTimeout(() => {
            closeNotification();
        }, 5000);
        
        // Édition de catégorie
        function editCategory(id) {
            // Charger les données de la catégorie via AJAX
            fetch(`../api/get_category.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_id_categorie').value = data.id_categorie;
                        document.getElementById('edit_nom_categorie').value = data.nom_categorie;
                        document.getElementById('edit_description').value = data.description || '';
                        document.getElementById('edit_id_categorie_parent').value = data.id_categorie_parent || '';
                        
                        // Afficher l'image actuelle
                        const currentImage = document.getElementById('current_image');
                        if (data.image_categorie) {
                            currentImage.innerHTML = `
                                <img src="../${data.image_categorie}" alt="${data.nom_categorie}" style="max-width: 200px; max-height: 100px; border-radius: 5px;">
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">Image actuelle</div>
                            `;
                        } else {
                            currentImage.innerHTML = '<div style="color: #999;">Aucune image</div>';
                        }
                        
                        openModal('modalModifier');
                    } else {
                        alert('Erreur lors du chargement de la catégorie');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors du chargement de la catégorie');
                });
        }
    </script>
</body>
</html>