<?php
// profile.php - Page de profil utilisateur
require_once __DIR__ . '/../includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'profile.php';
    redirect('connexion.php');
}

$user_id = getUserId();
$page_title = "Mon Profil - " . SITE_NAME;
$message = '';
$message_type = '';

// Récupérer les catégories pour le header
$header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_show_products_link = true;

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    setFlashMessage('Utilisateur non trouvé', 'error');
    redirect('index.php');
}

// Récupérer les statistiques de l'utilisateur
$stats = [];

// Nombre de commandes
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM commande WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$stats['total_commandes'] = $stmt->fetch()['total'];

// Montant total dépensé
$stmt = $pdo->prepare("SELECT COALESCE(SUM(montant_total), 0) as total FROM commande WHERE id_utilisateur = ? AND statut = 'livree'");
$stmt->execute([$user_id]);
$stats['total_depense'] = $stmt->fetch()['total'];

// Nombre d'avis laissés
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM avis WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$stats['total_avis'] = $stmt->fetch()['total'];

// Dernière commande
$stmt = $pdo->prepare("SELECT * FROM commande WHERE id_utilisateur = ? ORDER BY date_commande DESC LIMIT 1");
$stmt->execute([$user_id]);
$stats['derniere_commande'] = $stmt->fetch();

// Récupérer les commandes récentes
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(lc.id_produit) as nb_produits
    FROM commande c
    LEFT JOIN ligne_commande lc ON c.id_commande = lc.id_commande
    WHERE c.id_utilisateur = ?
    GROUP BY c.id_commande
    ORDER BY c.date_commande DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$commandes_recentes = $stmt->fetchAll();

// Traitement de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token CSRF invalide";
        $message_type = "error";
    }
    
    elseif (isset($_POST['update_profile'])) {
        try {
            $nom = trim($_POST['nom']);
            $prenom = trim($_POST['prenom']);
            $email = trim($_POST['email']);
            $telephone = trim($_POST['telephone']);
            $adresse = trim($_POST['adresse']);
            $ville = trim($_POST['ville']);
            $code_postal = trim($_POST['code_postal']);
            $pays = trim($_POST['pays']);
            
            // Validation
            if (empty($nom) || empty($prenom) || empty($email)) {
                throw new Exception('Veuillez remplir tous les champs obligatoires');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email invalide');
            }
            
            // Vérifier si l'email existe déjà (sauf pour l'utilisateur actuel)
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ? AND id_utilisateur != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                throw new Exception('Cet email est déjà utilisé par un autre compte');
            }
            
            // Mise à jour
            $stmt = $pdo->prepare("
                UPDATE utilisateur 
                SET nom = ?, prenom = ?, email = ?, telephone = ?, 
                    adresse = ?, ville = ?, code_postal = ?, pays = ?,
                    date_modification = NOW()
                WHERE id_utilisateur = ?
            ");
            
            $stmt->execute([$nom, $prenom, $email, $telephone, $adresse, $ville, $code_postal, $pays, $user_id]);
            
            $message = "Profil mis à jour avec succès !";
            $message_type = "success";
            
            // Mettre à jour la session
            $_SESSION['nom'] = $nom;
            $_SESSION['prenom'] = $prenom;
            $_SESSION['email'] = $email;
            
            // Recharger les données
            $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (Exception $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    elseif (isset($_POST['change_password'])) {
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('Tous les champs sont obligatoires');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('Les mots de passe ne correspondent pas');
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('Le mot de passe doit contenir au moins 6 caractères');
            }
            
            // Vérifier l'ancien mot de passe
            if (!password_verify($current_password, $user['mot_de_passe'])) {
                throw new Exception('Mot de passe actuel incorrect');
            }
            
            // Mettre à jour
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE id_utilisateur = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $message = "Mot de passe modifié avec succès !";
            $message_type = "success";
            
        } catch (Exception $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    elseif (isset($_POST['update_avatar'])) {
        try {
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Veuillez sélectionner une image');
            }
            
            $file = $_FILES['avatar'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Format d\'image non autorisé (JPG, PNG, GIF, WEBP uniquement)');
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception('L\'image ne doit pas dépasser 2MB');
            }
            
            // Créer le dossier uploads s'il n'existe pas
            $upload_dir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Supprimer l'ancien avatar si existe
            if (!empty($user['photo_profil']) && file_exists(__DIR__ . '/../' . $user['photo_profil'])) {
                unlink(__DIR__ . '/../' . $user['photo_profil']);
            }
            
            // Générer un nom unique
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user_id . '_' . uniqid() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $avatar_url = 'uploads/avatars/' . $filename;
                
                $stmt = $pdo->prepare("UPDATE utilisateur SET photo_profil = ? WHERE id_utilisateur = ?");
                $stmt->execute([$avatar_url, $user_id]);
                
                $message = "Photo de profil mise à jour avec succès !";
                $message_type = "success";
                
                // Recharger les données
                $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                throw new Exception('Erreur lors de l\'upload de l\'image');
            }
            
        } catch (Exception $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    elseif (isset($_POST['delete_account'])) {
        try {
            $password = $_POST['password'];
            
            if (empty($password)) {
                throw new Exception('Veuillez entrer votre mot de passe');
            }
            
            // Vérifier le mot de passe
            if (!password_verify($password, $user['mot_de_passe'])) {
                throw new Exception('Mot de passe incorrect');
            }
            
            // Supprimer ou désactiver le compte
            $stmt = $pdo->prepare("UPDATE utilisateur SET est_actif = 0, date_suppression = NOW() WHERE id_utilisateur = ?");
            $stmt->execute([$user_id]);
            
            // Déconnecter l'utilisateur
            session_destroy();
            
            redirect('index.php?message=compte_desactive');
            
        } catch (Exception $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Générer token CSRF
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

include __DIR__ . '/../includes/header.php';
?>

<style>
:root {
    --primary-color: #2d3d48;
    --secondary-color: #3f37c9;
    --success-color: #10b981;
    --danger-color: #ce1126;
    --warning-color: #f59e0b;
    --info-color: #4895ef;
    --dark-color: #1e293b;
    --light-color: #f8fafc;
    --gradient: linear-gradient(135deg, #2d3d48 0%, #1e2c34 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f1f5f9;
    color: var(--dark-color);
}

/* Header du profil */
.profile-header {
    background: var(--gradient);
    padding: 3rem 2rem;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.profile-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.profile-header h1 i {
    font-size: 2.5rem;
    opacity: 0.9;
}

.profile-header p {
    font-size: 1.1rem;
    opacity: 0.9;
    position: relative;
}

/* Container principal */
.profile-container {
    max-width: 1400px;
    margin: -50px auto 2rem;
    padding: 0 20px;
    position: relative;
    z-index: 10;
}

/* Cartes de statistiques */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 30px -10px rgba(0,0,0,0.2);
}

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: var(--gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    box-shadow: 0 10px 15px -5px rgba(45,61,72,0.3);
}

.stat-info h3 {
    font-size: 28px;
    color: var(--dark-color);
    margin-bottom: 5px;
    font-weight: 700;
}

.stat-info p {
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
}

/* Grille principale */
.profile-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 25px;
}

/* Sidebar du profil */
.profile-sidebar {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    height: fit-content;
    position: sticky;
    top: 20px;
}

.avatar-section {
    text-align: center;
    margin-bottom: 25px;
}

.avatar-container {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto 15px;
}

.avatar-image {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid var(--primary-color);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
}

.avatar-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: var(--gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: bold;
}

.avatar-upload {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid white;
}

.avatar-upload:hover {
    background: var(--secondary-color);
    transform: scale(1.1);
}

.avatar-upload input {
    display: none;
}

.user-name {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 5px;
}

.user-email {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.user-badge {
    display: inline-block;
    padding: 5px 15px;
    background: var(--gradient);
    color: white;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 20px;
}

.profile-menu {
    list-style: none;
    margin-top: 20px;
}

.profile-menu li {
    margin-bottom: 5px;
}

.profile-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: #64748b;
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.3s;
    font-weight: 500;
}

.profile-menu a:hover,
.profile-menu a.active {
    background: var(--gradient);
    color: white;
    transform: translateX(5px);
}

.profile-menu a i {
    width: 20px;
    font-size: 16px;
}

/* Contenu principal */
.profile-content {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
}

.content-header h2 {
    font-size: 24px;
    color: var(--dark-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.content-header h2 i {
    color: var(--primary-color);
}

/* Formulaire */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark-color);
    font-size: 14px;
}

.form-group label i {
    margin-right: 8px;
    color: var(--primary-color);
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(45, 61, 72, 0.1);
}

.form-control[readonly] {
    background: #f1f5f9;
    cursor: not-allowed;
}

.form-full {
    grid-column: 1 / -1;
}

/* Commandes récentes */
.recent-orders {
    margin-top: 30px;
}

.recent-orders h3 {
    font-size: 18px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--dark-color);
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th {
    text-align: left;
    padding: 15px;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    font-size: 14px;
    font-weight: 600;
    color: var(--dark-color);
}

.orders-table td {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
}

.orders-table tr:hover {
    background: #f8fafc;
}

.order-status {
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.status-en-attente {
    background: #fef3c7;
    color: #92400e;
}

.status-confirmee {
    background: #dbeafe;
    color: #1e40af;
}

.status-expediee {
    background: #cffafe;
    color: #0891b2;
}

.status-livree {
    background: #d1fae5;
    color: #065f46;
}

.status-annulee {
    background: #fee2e2;
    color: #991b1b;
}

/* Boutons */
.btn {
    padding: 12px 25px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-primary {
    background: var(--gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px -5px rgba(45, 61, 72, 0.4);
}

.btn-outline {
    background: white;
    color: var(--dark-color);
    border: 1px solid #e2e8f0;
}

.btn-outline:hover {
    background: #f8fafc;
    border-color: var(--primary-color);
}

.btn-danger {
    background: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
    transform: translateY(-2px);
}

.btn-success {
    background: var(--success-color);
    color: white;
}

.btn-success:hover {
    background: #0d9488;
    transform: translateY(-2px);
}

.btn-block {
    width: 100%;
    justify-content: center;
}

.btn-sm {
    padding: 8px 15px;
    font-size: 12px;
}

/* Tabs */
.profile-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 15px;
}

.tab-btn {
    padding: 10px 20px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 8px;
    border-radius: 30px;
    transition: all 0.3s;
}

.tab-btn:hover {
    color: var(--primary-color);
    background: #f1f5f9;
}

.tab-btn.active {
    background: var(--gradient);
    color: white;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Alertes */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid var(--success-color);
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid var(--danger-color);
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border-left: 4px solid var(--warning-color);
}

.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border-left: 4px solid var(--info-color);
}

@keyframes slideIn {
    from {
        transform: translateX(-100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

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
    backdrop-filter: blur(5px);
    overflow-y: auto;
}

.modal-content {
    background: white;
    margin: 50px auto;
    padding: 30px;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
}

.modal-header h3 {
    font-size: 20px;
    color: var(--dark-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #64748b;
    transition: all 0.3s;
}

.modal-close:hover {
    color: var(--danger-color);
    transform: rotate(90deg);
}

.modal-footer {
    margin-top: 25px;
    padding-top: 15px;
    border-top: 2px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Responsive */
@media (max-width: 1024px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-sidebar {
        position: static;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .profile-header h1 {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-tabs {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        flex: 1;
        justify-content: center;
    }
    
    .orders-table {
        font-size: 13px;
    }
    
    .orders-table th,
    .orders-table td {
        padding: 10px;
    }
    
    .btn-sm {
        padding: 5px 10px;
    }
}

@media (max-width: 480px) {
    .orders-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<!-- Header du profil -->
<div class="profile-header">
    <h1>
        <i class="fas fa-user-circle"></i>
        Mon Profil
    </h1>
    <p>Gérez vos informations personnelles et vos préférences</p>
</div>

<!-- Container principal -->
<div class="profile-container">
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>">
        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $stats['total_commandes']; ?></h3>
                <p>Commandes totales</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['total_depense'], 0, ',', ' '); ?> FCFA</h3>
                <p>Total dépensé</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $stats['total_avis']; ?></h3>
                <p>Avis laissés</p>
            </div>
        </div>
        
        <?php if ($stats['derniere_commande']): ?>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo date('d/m', strtotime($stats['derniere_commande']['date_commande'])); ?></h3>
                <p>Dernière commande</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Grille principale -->
    <div class="profile-grid">
        <!-- Sidebar -->
        <div class="profile-sidebar">
            <div class="avatar-section">
                <div class="avatar-container">
                    <div class="avatar-image">
                        <?php if (!empty($user['photo_profil'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['photo_profil']); ?>" alt="Photo de profil">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <label for="avatarUpload" class="avatar-upload">
                            <i class="fas fa-camera"></i>
                            <input type="file" id="avatarUpload" name="avatar" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                        </label>
                        <input type="hidden" name="update_avatar" value="1">
                    </form>
                </div>
                
                <div class="user-name"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></div>
                <div class="user-email">
                    <i class="fas fa-envelope"></i>
                    <?php echo htmlspecialchars($user['email']); ?>
                </div>
                <div class="user-badge">
                    <i class="fas fa-shield-alt"></i>
                    Membre depuis <?php echo date('d/m/Y', strtotime($user['date_inscription'])); ?>
                </div>
            </div>
            
            <ul class="profile-menu">
                <li><a href="#info" class="active" onclick="showTab('info')"><i class="fas fa-user"></i> Informations personnelles</a></li>
                <li><a href="#password" onclick="showTab('password')"><i class="fas fa-lock"></i> Sécurité</a></li>
                <li><a href="#orders" onclick="showTab('orders')"><i class="fas fa-shopping-bag"></i> Mes commandes</a></li>
                <li><a href="#preferences" onclick="showTab('preferences')"><i class="fas fa-bell"></i> Préférences</a></li>
                <li><a href="#danger" onclick="showTab('danger')" style="color: var(--danger-color);"><i class="fas fa-exclamation-triangle"></i> Zone de danger</a></li>
            </ul>
        </div>
        
        <!-- Contenu principal -->
        <div class="profile-content">
            <!-- Tabs -->
            <div class="profile-tabs">
                <button class="tab-btn active" onclick="showTab('info')">
                    <i class="fas fa-user"></i> Informations
                </button>
                <button class="tab-btn" onclick="showTab('password')">
                    <i class="fas fa-lock"></i> Mot de passe
                </button>
                <button class="tab-btn" onclick="showTab('orders')">
                    <i class="fas fa-shopping-bag"></i> Commandes
                </button>
                <button class="tab-btn" onclick="showTab('preferences')">
                    <i class="fas fa-bell"></i> Préférences
                </button>
            </div>
            
            <!-- Tab: Informations personnelles -->
            <div id="tab-info" class="tab-pane active">
                <div class="content-header">
                    <h2><i class="fas fa-user-edit"></i> Informations personnelles</h2>
                </div>
                
                <form method="POST" onsubmit="return validateForm()">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nom *</label>
                            <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Prénom *</label>
                            <input type="text" name="prenom" class="form-control" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Téléphone</label>
                            <input type="tel" name="telephone" class="form-control" value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>" placeholder="+237 6XX XX XX XX">
                        </div>
                        
                        <div class="form-group form-full">
                            <label><i class="fas fa-map-marker-alt"></i> Adresse</label>
                            <input type="text" name="adresse" class="form-control" value="<?php echo htmlspecialchars($user['adresse'] ?? ''); ?>" placeholder="Votre adresse complète">
                        </div>
                        
                        <div class="form-group">
                            <label>Ville</label>
                            <input type="text" name="ville" class="form-control" value="<?php echo htmlspecialchars($user['ville'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Code postal</label>
                            <input type="text" name="code_postal" class="form-control" value="<?php echo htmlspecialchars($user['code_postal'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Pays</label>
                            <input type="text" name="pays" class="form-control" value="<?php echo htmlspecialchars($user['pays'] ?? 'Cameroun'); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Enregistrer les modifications
                    </button>
                </form>
            </div>
            
            <!-- Tab: Sécurité -->
            <div id="tab-password" class="tab-pane">
                <div class="content-header">
                    <h2><i class="fas fa-lock"></i> Sécurité du compte</h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Mot de passe actuel *</label>
                        <input type="password" name="current_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Nouveau mot de passe *</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small style="color: #64748b;">Minimum 6 caractères</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Confirmer le mot de passe *</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Modifier le mot de passe
                    </button>
                </form>
                
                <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 12px;">
                    <h3 style="margin-bottom: 15px; font-size: 16px;">Conseils de sécurité</h3>
                    <ul style="list-style: none;">
                        <li style="margin-bottom: 10px;"><i class="fas fa-check-circle" style="color: var(--success-color); margin-right: 10px;"></i> Utilisez un mot de passe unique</li>
                        <li style="margin-bottom: 10px;"><i class="fas fa-check-circle" style="color: var(--success-color); margin-right: 10px;"></i> Mélangez lettres, chiffres et symboles</li>
                        <li style="margin-bottom: 10px;"><i class="fas fa-check-circle" style="color: var(--success-color); margin-right: 10px;"></i> Ne partagez jamais votre mot de passe</li>
                    </ul>
                </div>
            </div>
            
            <!-- Tab: Commandes -->
            <div id="tab-orders" class="tab-pane">
                <div class="content-header">
                    <h2><i class="fas fa-shopping-bag"></i> Mes commandes récentes</h2>
                    <a href="orders.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-external-link-alt"></i>
                        Voir tout
                    </a>
                </div>
                
                <?php if (empty($commandes_recentes)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-box-open" style="font-size: 60px; color: #cbd5e1; margin-bottom: 15px;"></i>
                    <h3 style="color: #64748b;">Aucune commande pour le moment</h3>
                    <p style="margin-top: 10px;">Découvrez nos produits et passez votre première commande !</p>
                    <a href="index.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-store"></i>
                        Voir les produits
                    </a>
                </div>
                <?php else: ?>
                <div class="recent-orders">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>N° Commande</th>
                                <th>Date</th>
                                <th>Articles</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes_recentes as $cmd): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($cmd['numero_commande']); ?></strong></td>
                                <td><?php echo date('d/m/Y', strtotime($cmd['date_commande'])); ?></td>
                                <td><?php echo $cmd['nb_produits']; ?> produit(s)</td>
                                <td><strong><?php echo number_format($cmd['montant_total'], 0, ',', ' '); ?> FCFA</strong></td>
                                <td>
                                    <span class="order-status status-<?php echo $cmd['statut']; ?>">
                                        <?php 
                                        $labels = [
                                            'en attente' => '⏳ En attente',
                                            'confirmee' => '✅ Confirmée',
                                            'expediee' => '🚚 Expédiée',
                                            'livree' => '📦 Livrée',
                                            'annulee' => '❌ Annulée'
                                        ];
                                        echo $labels[$cmd['statut']] ?? ucfirst($cmd['statut']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="order-details.php?id=<?php echo $cmd['id_commande']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Préférences -->
            <div id="tab-preferences" class="tab-pane">
                <div class="content-header">
                    <h2><i class="fas fa-bell"></i> Préférences de notification</h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div style="margin-bottom: 25px;">
                        <h3 style="margin-bottom: 15px; font-size: 16px;">Notifications par email</h3>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="notif_commandes" checked style="width: 18px; height: 18px;">
                                <span>Confirmation de commande</span>
                            </label>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="notif_livraison" checked style="width: 18px; height: 18px;">
                                <span>Mise à jour de livraison</span>
                            </label>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="notif_promotions" style="width: 18px; height: 18px;">
                                <span>Offres promotionnelles</span>
                            </label>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="notif_newsletter" style="width: 18px; height: 18px;">
                                <span>Newsletter</span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_preferences" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Enregistrer les préférences
                    </button>
                </form>
            </div>
            
            <!-- Tab: Zone de danger -->
            <div id="tab-danger" class="tab-pane">
                <div class="content-header">
                    <h2 style="color: var(--danger-color);"><i class="fas fa-exclamation-triangle"></i> Zone de danger</h2>
                </div>
                
                <div style="background: #fee2e2; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <h3 style="color: #991b1b; margin-bottom: 10px;">⚠️ Actions irréversibles</h3>
                    <p style="color: #7f1d1d;">Les actions dans cette section sont définitives et ne peuvent pas être annulées.</p>
                </div>
                
                <div style="border: 1px solid #fee2e2; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px;">Désactiver mon compte</h4>
                    <p style="color: #64748b; margin-bottom: 15px;">Votre compte sera désactivé et vos données seront anonymisées.</p>
                    
                    <button class="btn btn-danger" onclick="openDeleteModal()">
                        <i class="fas fa-trash"></i>
                        Désactiver mon compte
                    </button>
                </div>
                
                <div style="border: 1px solid #fee2e2; border-radius: 12px; padding: 20px;">
                    <h4 style="margin-bottom: 10px;">Exporter mes données</h4>
                    <p style="color: #64748b; margin-bottom: 15px;">Téléchargez toutes vos données personnelles.</p>
                    
                    <a href="export-data.php" class="btn btn-outline">
                        <i class="fas fa-download"></i>
                        Exporter mes données
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation suppression -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i> Confirmation</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div style="margin-bottom: 20px;">
                <p style="margin-bottom: 15px;">Êtes-vous sûr de vouloir désactiver votre compte ? Cette action est irréversible.</p>
                
                <div class="form-group">
                    <label>Entrez votre mot de passe pour confirmer :</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Annuler</button>
                <button type="submit" name="delete_account" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Confirmer la désactivation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Gestion des tabs
function showTab(tabName) {
    // Cacher tous les tabs
    document.querySelectorAll('.tab-pane').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Désactiver tous les boutons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Afficher le tab sélectionné
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Activer le bouton correspondant
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.textContent.toLowerCase().includes(tabName)) {
            btn.classList.add('active');
        }
    });
    
    // Mettre à jour le menu latéral
    document.querySelectorAll('.profile-menu a').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + tabName) {
            link.classList.add('active');
        }
    });
}

// Validation du formulaire
function validateForm() {
    const email = document.querySelector('input[name="email"]').value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!emailRegex.test(email)) {
        alert('Veuillez entrer une adresse email valide');
        return false;
    }
    
    return true;
}

// Gestion de l'upload d'avatar
document.getElementById('avatarUpload')?.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const file = this.files[0];
        const fileSize = file.size / 1024 / 1024; // en MB
        const fileType = file.type;
        
        if (fileSize > 2) {
            alert('L\'image ne doit pas dépasser 2MB');
            this.value = '';
            return;
        }
        
        if (!fileType.startsWith('image/')) {
            alert('Veuillez sélectionner une image valide');
            this.value = '';
            return;
        }
        
        document.getElementById('avatarForm').submit();
    }
});

// Modal de suppression
function openDeleteModal() {
    document.getElementById('deleteModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Fermer le modal en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        closeDeleteModal();
    }
}

// Animation des statistiques
document.querySelectorAll('.stat-card').forEach((card, index) => {
    card.style.animation = `slideIn 0.3s ease ${index * 0.1}s both`;
});

// Confirmation avant actions importantes
document.querySelectorAll('.btn-danger').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
            e.preventDefault();
        }
    });
});

// Prévisualisation de l'image uploadée
document.getElementById('avatarUpload')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.avatar-image img, .avatar-placeholder');
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                // Remplacer le placeholder par une image
                const container = document.querySelector('.avatar-image');
                container.innerHTML = `<img src="${e.target.result}" alt="Photo de profil">`;
            }
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>