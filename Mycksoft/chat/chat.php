<?php
// chat.php
require_once __DIR__ . '/../includes/config.php';

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Générer un token CSRF si nécessaire
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fonction pour journaliser les erreurs
function logError($message, $context = []) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if (!empty($context)) {
        $log .= " - Contexte: " . json_encode($context);
    }
    error_log($log);
}

// Vérifier et créer les tables si elles n'existent pas
try {
    $pdo->query("SELECT 1 FROM message LIMIT 1");
} catch (PDOException $e) {
    $sql = "CREATE TABLE IF NOT EXISTS message (
        id_message INT AUTO_INCREMENT PRIMARY KEY,
        id_expediteur INT NOT NULL,
        id_destinataire INT NOT NULL,
        message TEXT NOT NULL,
        type_message ENUM('text', 'image', 'file') DEFAULT 'text',
        date_envoi DATETIME NOT NULL,
        lu TINYINT(1) DEFAULT 0,
        FOREIGN KEY (id_expediteur) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE,
        FOREIGN KEY (id_destinataire) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE,
        INDEX idx_conversation (id_expediteur, id_destinataire),
        INDEX idx_date (date_envoi)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    logError("Table 'message' créée");
}

// Créer la table de suivi des connexions
try {
    $pdo->query("SELECT 1 FROM utilisateur_connexion LIMIT 1");
} catch (PDOException $e) {
    $sql = "CREATE TABLE IF NOT EXISTS utilisateur_connexion (
        id_utilisateur INT PRIMARY KEY,
        derniere_activite DATETIME NOT NULL,
        est_en_ligne BOOLEAN DEFAULT FALSE,
        est_entrain_ecrire BOOLEAN DEFAULT FALSE,
        entrain_ecrire_a INT DEFAULT NULL,
        FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    logError("Table 'utilisateur_connexion' créée");
}

// Créer le dossier uploads
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    logError("Dossier 'uploads' créé");
}

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    setFlashMessage('Veuillez vous connecter pour accéder au chat', 'error');
    redirect('connexion.php?redirect=chat.php');
}

$user_id = getUserId();

// Mettre à jour la connexion de l'utilisateur
try {
    $stmt = $pdo->prepare("
        INSERT INTO utilisateur_connexion (id_utilisateur, derniere_activite, est_en_ligne)
        VALUES (?, NOW(), TRUE)
        ON DUPLICATE KEY UPDATE derniere_activite = NOW(), est_en_ligne = TRUE
    ");
    $stmt->execute([$user_id]);
    
    // Nettoyer les utilisateurs inactifs (plus de 5 minutes)
    $pdo->exec("UPDATE utilisateur_connexion SET est_en_ligne = FALSE WHERE derniere_activite < NOW() - INTERVAL 5 MINUTE");
} catch (PDOException $e) {
    logError("Erreur mise à jour connexion", ['user_id' => $user_id, 'error' => $e->getMessage()]);
}

// Récupérer les informations de l'utilisateur courant
$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

if (!$current_user) {
    logError("Utilisateur non trouvé", ['user_id' => $user_id]);
    setFlashMessage('Utilisateur non trouvé', 'error');
    redirect('connexion.php');
}

// Vérifier si l'utilisateur est administrateur
$is_admin = false;
if (isset($current_user['role'])) {
    $is_admin = ($current_user['role'] === 'admin');
} elseif (isset($current_user['is_admin'])) {
    $is_admin = ($current_user['is_admin'] == 1);
}

// Récupérer les conversations de l'utilisateur
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        u.id_utilisateur,
        u.nom,
        u.prenom,
        u.email,
        u.photo_profil,
        (
            SELECT message 
            FROM message 
            WHERE (id_expediteur = u.id_utilisateur AND id_destinataire = ?)
               OR (id_expediteur = ? AND id_destinataire = u.id_utilisateur)
            ORDER BY date_envoi DESC 
            LIMIT 1
        ) as dernier_message,
        (
            SELECT date_envoi 
            FROM message 
            WHERE (id_expediteur = u.id_utilisateur AND id_destinataire = ?)
               OR (id_expediteur = ? AND id_destinataire = u.id_utilisateur)
            ORDER BY date_envoi DESC 
            LIMIT 1
        ) as date_dernier_message,
        (
            SELECT COUNT(*) 
            FROM message 
            WHERE id_expediteur = u.id_utilisateur 
            AND id_destinataire = ? 
            AND lu = 0
        ) as non_lus,
        uc.est_en_ligne,
        uc.est_entrain_ecrire
    FROM utilisateur u
    LEFT JOIN utilisateur_connexion uc ON u.id_utilisateur = uc.id_utilisateur
    WHERE u.id_utilisateur IN (
        SELECT DISTINCT id_expediteur FROM message WHERE id_destinataire = ?
        UNION
        SELECT DISTINCT id_destinataire FROM message WHERE id_expediteur = ?
    )
    AND u.id_utilisateur != ?
    ORDER BY date_dernier_message DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$conversations = $stmt->fetchAll();

// Récupérer les utilisateurs disponibles
$stmt = $pdo->prepare("
    SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.photo_profil, u.role,
           uc.est_en_ligne
    FROM utilisateur u
    LEFT JOIN utilisateur_connexion uc ON u.id_utilisateur = uc.id_utilisateur
    WHERE u.id_utilisateur != ?
    ORDER BY uc.est_en_ligne DESC, u.nom, u.prenom
");
$stmt->execute([$user_id]);
$available_users = $stmt->fetchAll();

// Traitement de l'indicateur "en train d'écrire"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['typing'])) {
    header('Content-Type: application/json');
    
    $destinataire_id = intval($_POST['destinataire_id']);
    $est_entrain_ecrire = filter_var($_POST['est_entrain_ecrire'], FILTER_VALIDATE_BOOLEAN);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE utilisateur_connexion 
            SET est_entrain_ecrire = ?, entrain_ecrire_a = ?
            WHERE id_utilisateur = ?
        ");
        $stmt->execute([$est_entrain_ecrire, $destinataire_id, $user_id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        logError("Erreur indicateur typing", ['user_id' => $user_id, 'error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Traitement de l'envoi de message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_msg = 'Token CSRF invalide';
        logError("Tentative de CSRF", ['user_id' => $user_id, 'ip' => $_SERVER['REMOTE_ADDR']]);
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => $error_msg]);
            exit;
        }
        
        setFlashMessage($error_msg, 'error');
        redirect('chat.php');
    }
    
    $destinataire_id = intval($_POST['destinataire_id']);
    $message = trim($_POST['message'] ?? '');
    $type_message = $_POST['type_message'] ?? 'text';
    
    // Validation du message
    $message = strip_tags($message);
    $message = substr($message, 0, 2000);
    
    if (empty($message) && empty($_FILES['fichier'])) {
        $error_msg = 'Message vide';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => $error_msg]);
            exit;
        }
        setFlashMessage($error_msg, 'error');
        redirect('chat.php?user=' . $destinataire_id);
    }
    
    // Vérifier que le destinataire existe
    $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$destinataire_id]);
    if (!$stmt->fetch()) {
        $error_msg = 'Destinataire invalide';
        logError("Destinataire invalide", ['destinataire_id' => $destinataire_id, 'user_id' => $user_id]);
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => $error_msg]);
            exit;
        }
        setFlashMessage($error_msg, 'error');
        redirect('chat.php');
    }
    
    try {
        // Gestion de l'upload de fichier
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
            $fichier = $_FILES['fichier'];
            $types_autorises = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'text/plain' => 'txt'
            ];
            $taille_max = 10 * 1024 * 1024; // 10MB
            
            if (!array_key_exists($fichier['type'], $types_autorises)) {
                throw new Exception('Type de fichier non autorisé');
            }
            
            if ($fichier['size'] > $taille_max) {
                throw new Exception('Fichier trop volumineux (max 10MB)');
            }
            
            // Générer un nom unique
            $extension = $types_autorises[$fichier['type']];
            $nom_fichier = uniqid() . '_' . date('Ymd_His') . '.' . $extension;
            $chemin_fichier = 'uploads/' . $nom_fichier;
            
            if (move_uploaded_file($fichier['tmp_name'], $upload_dir . $nom_fichier)) {
                $type_message = strpos($fichier['type'], 'image/') === 0 ? 'image' : 'file';
                $message_contenu = $chemin_fichier;
                logError("Fichier uploadé", ['nom' => $nom_fichier, 'type' => $type_message]);
            } else {
                throw new Exception('Erreur lors de l\'upload du fichier');
            }
        } else {
            $message_contenu = $message;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO message (id_expediteur, id_destinataire, message, type_message, date_envoi, lu)
            VALUES (?, ?, ?, ?, NOW(), 0)
        ");
        $stmt->execute([$user_id, $destinataire_id, $message_contenu, $type_message]);
        
        logError("Message envoyé", ['de' => $user_id, 'à' => $destinataire_id, 'type' => $type_message]);
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true, 'message' => 'Message envoyé']);
            exit;
        }
        
        setFlashMessage('Message envoyé avec succès', 'success');
        redirect('chat.php?user=' . $destinataire_id);
        
    } catch (Exception $e) {
        logError("Erreur envoi message", ['user_id' => $user_id, 'error' => $e->getMessage()]);
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        
        setFlashMessage('Erreur : ' . $e->getMessage(), 'error');
        redirect('chat.php?user=' . $destinataire_id);
    }
}

// Récupérer les messages d'une conversation spécifique
$conversation_user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
$messages = [];
$interlocuteur = null;
$messages_par_page = 50;

if ($conversation_user_id > 0) {
    // Vérifier que l'interlocuteur existe
    $stmt = $pdo->prepare("SELECT id_utilisateur, nom, prenom, email, photo_profil FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$conversation_user_id]);
    $interlocuteur = $stmt->fetch();
    
    if ($interlocuteur) {
        // Marquer les messages comme lus
        $stmt = $pdo->prepare("
            UPDATE message 
            SET lu = 1 
            WHERE id_expediteur = ? AND id_destinataire = ? AND lu = 0
        ");
        $stmt->execute([$conversation_user_id, $user_id]);
        
        // Pagination des messages
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $messages_par_page;
        
        // Récupérer les messages
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   u.nom as expediteur_nom, 
                   u.prenom as expediteur_prenom, 
                   u.photo_profil as expediteur_photo
            FROM message m
            JOIN utilisateur u ON m.id_expediteur = u.id_utilisateur
            WHERE (m.id_expediteur = ? AND m.id_destinataire = ?)
               OR (m.id_expediteur = ? AND m.id_destinataire = ?)
            ORDER BY m.date_envoi ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $conversation_user_id, $conversation_user_id, $user_id, $messages_par_page, $offset]);
        $messages = $stmt->fetchAll();
        
        // Récupérer l'état de connexion de l'interlocuteur
        $stmt = $pdo->prepare("SELECT est_en_ligne, est_entrain_ecrire FROM utilisateur_connexion WHERE id_utilisateur = ?");
        $stmt->execute([$conversation_user_id]);
        $interlocuteur_connexion = $stmt->fetch();
        $interlocuteur['est_en_ligne'] = $interlocuteur_connexion['est_en_ligne'] ?? false;
        $interlocuteur['est_entrain_ecrire'] = $interlocuteur_connexion['est_entrain_ecrire'] ?? false;
    }
}

// Récupérer les messages des clients pour l'admin
$admin_messages = [];
if ($is_admin) {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               u.nom as expediteur_nom, 
               u.prenom as expediteur_prenom, 
               u.email as expediteur_email,
               u.photo_profil as expediteur_photo
        FROM message m
        JOIN utilisateur u ON m.id_expediteur = u.id_utilisateur
        WHERE m.id_destinataire = ? 
        ORDER BY m.date_envoi DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $admin_messages = $stmt->fetchAll();
}

// API pour les compteurs non lus
if (isset($_GET['action']) && $_GET['action'] === 'get_unread_counts') {
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->prepare("
            SELECT id_expediteur, COUNT(*) as non_lus
            FROM message
            WHERE id_destinataire = ? AND lu = 0
            GROUP BY id_expediteur
        ");
        $stmt->execute([$user_id]);
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        echo json_encode($results);
    } catch (Exception $e) {
        logError("Erreur get_unread_counts", ['user_id' => $user_id, 'error' => $e->getMessage()]);
        echo json_encode([]);
    }
    exit;
}

$page_title = "Chat - " . SITE_NAME;
$header_categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_show_products_link = true;
?>

<!-- Interface de Chat Moderne -->
<style>
:root {
    --primary-color: #6c5ce7;
    --secondary-color: #a8a4e6;
    --dark-color: #2d3436;
    --light-color: #f5f6fa;
    --success-color: #00b894;
    --warning-color: #fdcb6e;
    --danger-color: #d63031;
    --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: var(--light-color);
}

/* Header agrandi et amélioré */
.chat-header {
    background: var(--gradient);
    padding: 1.5rem 3rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    position: relative;
    z-index: 1000;
    min-height: 100px;
    border-bottom: 3px solid rgba(255,255,255,0.1);
}

.chat-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
    pointer-events: none;
}

.chat-header::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--primary-color));
    animation: gradientShift 3s ease infinite;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.logo {
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    z-index: 2;
}

.logo i {
    font-size: 2.5rem;
    color: white;
    animation: bounce 2s infinite;
    filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
}

.logo span {
    font-size: 2rem;
    font-weight: bold;
    color: white;
    text-shadow: 3px 3px 6px rgba(0,0,0,0.3);
    letter-spacing: 1px;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

.header-nav {
    display: flex;
    gap: 1.5rem;
    background: rgba(255,255,255,0.15);
    padding: 0.8rem 1.5rem;
    border-radius: 60px;
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255,255,255,0.2);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    position: relative;
    z-index: 2;
}

.nav-item {
    color: white;
    text-decoration: none;
    padding: 0.8rem 1.5rem;
    border-radius: 40px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
    font-size: 1.1rem;
    font-weight: 500;
    position: relative;
    cursor: pointer;
}

.nav-item i {
    font-size: 1.3rem;
    transition: transform 0.3s ease;
}

.nav-item:hover i {
    transform: scale(1.2);
}

.nav-item:hover, .nav-item.active {
    background: white;
    color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}

.nav-item .badge {
    background: var(--danger-color);
    color: white;
    border-radius: 50px;
    padding: 4px 10px;
    font-size: 0.8rem;
    font-weight: bold;
    margin-left: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.user-status {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255,255,255,0.15);
    padding: 0.8rem 1.8rem;
    border-radius: 50px;
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255,255,255,0.2);
    position: relative;
    z-index: 2;
    cursor: pointer;
    transition: all 0.3s ease;
}

.user-status:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

.online-indicator {
    width: 14px;
    height: 14px;
    background-color: var(--success-color);
    border-radius: 50%;
    box-shadow: 0 0 0 3px rgba(0,184,148,0.3);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(0,184,148,0.7); }
    70% { box-shadow: 0 0 0 10px rgba(0,184,148,0); }
    100% { box-shadow: 0 0 0 0 rgba(0,184,148,0); }
}

.user-status span {
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
}

.user-status i {
    color: white;
    font-size: 1rem;
    transition: transform 0.3s ease;
}

.user-status:hover i {
    transform: rotate(180deg);
}

/* Interface principale */
.chat-interface {
    max-width: 1600px;
    margin: 2rem auto;
    background: white;
    border-radius: 30px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(0,0,0,0.15);
}

.main-chat-area {
    display: grid;
    grid-template-columns: 350px 1fr;
    min-height: 700px;
}

/* Section recherche améliorée */
.search-section, .admin-section {
    background: white;
    border-radius: 15px;
    margin: 1.5rem;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    display: none;
    border: 1px solid rgba(0,0,0,0.05);
}

.search-header, .admin-header {
    margin-bottom: 1.5rem;
}

.search-header h3, .admin-header h3 {
    color: var(--primary-color);
    margin-bottom: 1rem;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-input {
    width: 100%;
    padding: 1rem 1.2rem;
    border: 2px solid #e0e0e0;
    border-radius: 15px;
    font-size: 1rem;
    transition: all 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(108,92,231,0.1);
}

/* Reste du CSS identique à partir d'ici */
.search-results {
    max-height: 450px;
    overflow-y: auto;
}

.user-result {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s;
    position: relative;
    border-radius: 12px;
}

.user-result:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 1rem;
    position: relative;
    border: 2px solid var(--primary-color);
}

.user-avatar img {
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
    font-weight: bold;
    font-size: 1.2rem;
}

.user-info {
    flex: 1;
}

.user-info h4 {
    font-size: 1rem;
    margin-bottom: 0.3rem;
    color: var(--dark-color);
}

.user-info p {
    font-size: 0.85rem;
    color: #666;
}

.online-badge {
    display: inline-block;
    background: var(--success-color);
    color: white;
    font-size: 0.7rem;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    margin-top: 0.3rem;
    font-weight: 500;
}

.btn-start-chat {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s;
    box-shadow: 0 4px 10px rgba(108,92,231,0.3);
}

.btn-start-chat:hover {
    transform: scale(1.15) rotate(10deg);
}

/* Section Admin */
.admin-messages {
    max-height: 550px;
    overflow-y: auto;
}

.admin-message-item {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 1.2rem;
    margin-bottom: 1rem;
    border-left: 5px solid var(--primary-color);
    transition: all 0.3s;
}

.admin-message-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
}

.message-sender {
    display: flex;
    align-items: center;
    margin-bottom: 0.8rem;
}

.sender-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 0.8rem;
    border: 2px solid var(--primary-color);
}

.sender-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder.small {
    width: 40px;
    height: 40px;
    font-size: 1rem;
}

.sender-info h4 {
    font-size: 1rem;
    margin-bottom: 0.2rem;
    color: var(--dark-color);
}

.sender-info p {
    font-size: 0.8rem;
    color: #666;
}

.message-content {
    margin-bottom: 0.8rem;
}

.message-content p {
    font-size: 0.95rem;
    line-height: 1.5;
}

.message-time {
    font-size: 0.75rem;
    color: #999;
}

.message-actions {
    text-align: right;
}

.btn-reply {
    background: var(--gradient);
    color: white;
    padding: 0.5rem 1.2rem;
    border-radius: 25px;
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    box-shadow: 0 4px 10px rgba(108,92,231,0.3);
}

.btn-reply:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(108,92,231,0.4);
}

/* Zone de conversations améliorée */
.conversations-section {
    background: #fafafa;
    border-right: 2px solid #f0f0f0;
}

.conversations-header {
    padding: 1.5rem;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conversations-header h3 {
    font-size: 1.3rem;
    color: var(--dark-color);
    font-weight: 600;
}

.btn-new-chat {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: var(--gradient);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 1.2rem;
    box-shadow: 0 4px 10px rgba(108,92,231,0.3);
}

.btn-new-chat:hover {
    transform: rotate(180deg) scale(1.1);
}

.conversations-list {
    overflow-y: auto;
    max-height: 600px;
}

.conversation-item {
    display: flex;
    align-items: center;
    padding: 1.2rem;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s;
    position: relative;
}

.conversation-item:hover, .conversation-item.active {
    background: linear-gradient(90deg, #f0f2f5 0%, #ffffff 100%);
    transform: translateX(5px);
}

.conv-avatar {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 1rem;
    border: 3px solid var(--primary-color);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.conv-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.online-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 14px;
    height: 14px;
    background-color: var(--success-color);
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.conv-info {
    flex: 1;
}

.conv-info h4 {
    font-size: 1.1rem;
    margin-bottom: 0.3rem;
    color: var(--dark-color);
    font-weight: 600;
}

.conv-info p {
    font-size: 0.9rem;
    color: #666;
}

.typing-indicator {
    color: var(--primary-color);
    font-style: italic;
    font-size: 0.85rem;
    animation: pulse 1.5s infinite;
}

.conv-badge {
    background: var(--gradient);
    color: white;
    border-radius: 50px;
    padding: 4px 12px;
    font-size: 0.8rem;
    font-weight: bold;
    margin-left: 0.5rem;
    box-shadow: 0 4px 10px rgba(108,92,231,0.3);
}

/* Zone de chat active améliorée */
.active-chat {
    display: flex;
    flex-direction: column;
    background: #fff;
}

.active-chat .chat-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 2px solid #f0f0f0;
    background: white;
    position: static;
    min-height: auto;
    box-shadow: none;
}

.active-chat .chat-header::before,
.active-chat .chat-header::after {
    display: none;
}

.chat-user-info {
    display: flex;
    align-items: center;
    gap: 1.2rem;
}

.chat-user-avatar {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--primary-color);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.chat-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.chat-user-details h3 {
    font-size: 1.2rem;
    margin-bottom: 0.3rem;
    color: var(--dark-color);
    font-weight: 600;
}

.user-status-text {
    font-size: 0.9rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: 5px;
}

.user-status-text.online {
    color: var(--success-color);
}

.user-status-text.offline {
    color: #b2bec3;
}

.typing-small {
    color: var(--primary-color);
    font-size: 0.8rem;
    font-style: italic;
    animation: pulse 1.5s infinite;
}

.messages-area {
    flex: 1;
    padding: 1.5rem;
    overflow-y: auto;
    max-height: 500px;
    background: #f8fafd;
}

.message {
    display: flex;
    margin-bottom: 1.2rem;
    align-items: flex-end;
}

.message.sent {
    justify-content: flex-end;
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 0.8rem;
    align-self: flex-end;
    border: 2px solid var(--primary-color);
}

.message-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder.tiny {
    width: 40px;
    height: 40px;
    font-size: 0.9rem;
}

.message-bubble {
    max-width: 60%;
    padding: 1rem 1.2rem;
    border-radius: 20px;
    position: relative;
    font-size: 0.95rem;
    line-height: 1.5;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.message.sent .message-bubble {
    background: var(--gradient);
    color: white;
    border-bottom-right-radius: 5px;
}

.message.received .message-bubble {
    background: white;
    color: var(--dark-color);
    border-bottom-left-radius: 5px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.message-image {
    max-width: 250px;
    max-height: 250px;
    border-radius: 12px;
    cursor: pointer;
    transition: transform 0.3s;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.message-image:hover {
    transform: scale(1.1);
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0.6rem 1.2rem;
    background: var(--light-color);
    color: var(--dark-color);
    text-decoration: none;
    border-radius: 25px;
    transition: all 0.3s;
    font-size: 0.9rem;
    border: 1px solid #e0e0e0;
}

.file-link:hover {
    background: var(--gradient);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(108,92,231,0.3);
}

.message-time {
    font-size: 0.65rem;
    margin-top: 0.4rem;
    display: block;
    opacity: 0.7;
    text-align: right;
}

.message-input-area {
    padding: 1.2rem 1.5rem;
    border-top: 2px solid #f0f0f0;
    background: white;
}

.input-wrapper {
    display: flex;
    gap: 0.8rem;
    align-items: center;
    background: #f8fafd;
    border-radius: 30px;
    padding: 0.5rem;
    border: 2px solid #e0e0e0;
    transition: all 0.3s;
}

.input-wrapper:focus-within {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(108,92,231,0.1);
}

.input-wrapper textarea {
    flex: 1;
    padding: 0.8rem 1rem;
    border: none;
    background: transparent;
    resize: none;
    font-family: inherit;
    font-size: 0.95rem;
    max-height: 100px;
}

.input-wrapper textarea:focus {
    outline: none;
}

.input-actions {
    display: flex;
    gap: 0.5rem;
    margin-right: 0.5rem;
}

.btn-file, .input-wrapper button {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: var(--gradient);
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    font-size: 1.2rem;
    box-shadow: 0 4px 10px rgba(108,92,231,0.3);
}

.btn-file:hover, .input-wrapper button:hover {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 6px 15px rgba(108,92,231,0.4);
}

.btn-file {
    background: var(--secondary-color);
}

.file-preview {
    margin-top: 0.8rem;
    padding: 0.8rem 1rem;
    background: #f8fafd;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid #e0e0e0;
}

.file-preview span {
    font-size: 0.9rem;
    color: var(--dark-color);
    font-weight: 500;
}

.file-preview button {
    background: none;
    border: none;
    color: var(--danger-color);
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0 0.5rem;
}

.load-more {
    text-align: center;
    padding: 1.5rem;
}

.btn-load-more {
    background: none;
    border: 2px solid var(--primary-color);
    color: var(--primary-color);
    padding: 0.8rem 2rem;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.95rem;
    font-weight: 600;
}

.btn-load-more:hover {
    background: var(--gradient);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(108,92,231,0.3);
}

.no-chat-selected {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #999;
    text-align: center;
    padding: 2rem;
}

.no-chat-selected i {
    font-size: 5rem;
    margin-bottom: 1.5rem;
    color: var(--primary-color);
    opacity: 0.3;
}

.no-chat-selected h3 {
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
    color: var(--dark-color);
}

.no-chat-selected p {
    font-size: 1.1rem;
    color: #666;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.message {
    animation: fadeIn 0.3s ease;
}

/* Responsive */
@media (max-width: 768px) {
    .main-chat-area {
        grid-template-columns: 1fr;
    }
    
    .chat-header {
        flex-direction: column;
        gap: 1.5rem;
        padding: 1rem;
    }
    
    .header-nav {
        flex-wrap: wrap;
        justify-content: center;
        padding: 0.5rem;
    }
    
    .nav-item {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    
    .logo span {
        font-size: 1.5rem;
    }
}
</style>

<!-- Interface de Chat -->
<!-- Header avec navigation agrandi -->
<header class="chat-header">
    <div class="logo">
        <i class="fas fa-cat"></i>
        <span>ChatFlow</span>
    </div>
    <nav class="header-nav">
        <!-- Correction du lien Accueil -->
        <a href="../index.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Accueil</span>
        </a>
        <a href="chat.php" class="nav-item active">
            <i class="fas fa-comment"></i>
            <span>Messages</span>
            <span class="badge"><?php echo array_sum(array_column($conversations, 'non_lus')); ?></span>
        </a>
        <a href="#recherche" class="nav-item" id="search-tab">
            <i class="fas fa-search"></i>
            <span>Recherche</span>
        </a>
        <?php if ($is_admin): ?>
        <a href="#admin" class="nav-item" id="admin-tab">
            <i class="fas fa-crown"></i>
            <span>Admin</span>
        </a>
        <?php endif; ?>
        <a href="profil.php" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Profil</span>
        </a>
    </nav>
    <div class="user-status">
        <div class="online-indicator"></div>
        <span><?php echo htmlspecialchars($current_user['prenom'] . ' ' . $current_user['nom']); ?></span>
        <i class="fas fa-chevron-down"></i>
    </div>
</header>
        <div class="user-status">
            <div class="online-indicator"></div>
            <span><?php echo htmlspecialchars($current_user['prenom'] . ' ' . $current_user['nom']); ?></span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </header>

    <!-- Zone de recherche d'utilisateurs -->
    <div class="search-section" id="search-section">
        <div class="search-header">
            <h3><i class="fas fa-search"></i> Rechercher des utilisateurs</h3>
            <input type="text" class="search-input" placeholder="Nom, prénom ou email..." id="user-search">
        </div>
        <div class="search-results" id="search-results">
            <?php foreach ($available_users as $user): ?>
            <div class="user-result" data-user-id="<?php echo $user['id_utilisateur']; ?>">
                <div class="user-avatar">
                    <?php if (!empty($user['photo_profil'])): ?>
                        <img src="<?php echo htmlspecialchars($user['photo_profil']); ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')); ?></h4>
                    <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    <?php if ($user['est_en_ligne'] ?? false): ?>
                        <span class="online-badge">En ligne</span>
                    <?php endif; ?>
                </div>
                <a href="chat.php?user=<?php echo $user['id_utilisateur']; ?>" class="btn-start-chat">
                    <i class="fas fa-comment"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Section Admin -->
    <?php if ($is_admin): ?>
    <div class="admin-section" id="admin-section">
        <div class="admin-header">
            <h3><i class="fas fa-crown"></i> Messages reçus</h3>
        </div>
        <div class="admin-messages">
            <?php if (empty($admin_messages)): ?>
                <p class="no-messages">Aucun message pour le moment.</p>
            <?php else: ?>
                <?php foreach ($admin_messages as $admin_msg): ?>
                <div class="admin-message-item">
                    <div class="message-sender">
                        <div class="sender-avatar">
                            <?php if (!empty($admin_msg['expediteur_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($admin_msg['expediteur_photo']); ?>" alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-placeholder small">
                                    <?php echo strtoupper(substr($admin_msg['expediteur_prenom'] ?? '', 0, 1) . substr($admin_msg['expediteur_nom'] ?? '', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="sender-info">
                            <h4><?php echo htmlspecialchars(($admin_msg['expediteur_prenom'] ?? '') . ' ' . ($admin_msg['expediteur_nom'] ?? '')); ?></h4>
                            <p><?php echo htmlspecialchars($admin_msg['expediteur_email'] ?? ''); ?></p>
                        </div>
                    </div>
                    <div class="message-content">
                        <?php if ($admin_msg['type_message'] === 'image'): ?>
                            <img src="<?php echo htmlspecialchars($admin_msg['message']); ?>" alt="Image" class="message-image">
                        <?php elseif ($admin_msg['type_message'] === 'file'): ?>
                            <a href="<?php echo htmlspecialchars($admin_msg['message']); ?>" target="_blank" class="file-link">
                                <i class="fas fa-file"></i> Télécharger le fichier
                            </a>
                        <?php else: ?>
                            <p><?php echo nl2br(htmlspecialchars($admin_msg['message'])); ?></p>
                        <?php endif; ?>
                        <span class="message-time"><?php echo date('d/m/Y H:i', strtotime($admin_msg['date_envoi'])); ?></span>
                    </div>
                    <div class="message-actions">
                        <a href="chat.php?user=<?php echo $admin_msg['id_expediteur']; ?>" class="btn-reply">
                            <i class="fas fa-reply"></i> Répondre
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Zone principale du chat (identique à avant) -->
    <div class="main-chat-area">
        <!-- Section des conversations -->
        <div class="conversations-section">
            <div class="conversations-header">
                <h3>Conversations</h3>
                <button class="btn-new-chat" id="new-chat-btn">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="conversations-list">
                <?php if (empty($conversations)): ?>
                    <p class="no-conversations">Aucune conversation. Commencez à discuter !</p>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                    <a href="chat.php?user=<?php echo $conv['id_utilisateur']; ?>" class="conversation-item <?php echo ($conversation_user_id == $conv['id_utilisateur']) ? 'active' : ''; ?>" data-user-id="<?php echo $conv['id_utilisateur']; ?>">
                        <div class="conv-avatar">
                            <?php if (!empty($conv['photo_profil'])): ?>
                                <img src="<?php echo htmlspecialchars($conv['photo_profil']); ?>" alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($conv['prenom'] ?? '', 0, 1) . substr($conv['nom'] ?? '', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($conv['est_en_ligne'] ?? false): ?>
                                <span class="online-dot"></span>
                            <?php endif; ?>
                        </div>
                        <div class="conv-info">
                            <h4><?php echo htmlspecialchars(($conv['prenom'] ?? '') . ' ' . ($conv['nom'] ?? '')); ?></h4>
                            <p>
                                <?php if ($conv['est_entrain_ecrire'] ?? false): ?>
                                    <span class="typing-indicator">En train d'écrire...</span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars(substr($conv['dernier_message'] ?? 'Nouvelle conversation', 0, 30)) . '...'; ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if (($conv['non_lus'] ?? 0) > 0): ?>
                        <div class="conv-badge"><?php echo $conv['non_lus']; ?></div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Zone de chat active (identique à avant) -->
        <div class="active-chat">
            <?php if ($conversation_user_id > 0 && $interlocuteur): ?>
            <div class="chat-header">
                <div class="chat-user-info">
                    <div class="chat-user-avatar">
                        <?php if (!empty($interlocuteur['photo_profil'])): ?>
                            <img src="<?php echo htmlspecialchars($interlocuteur['photo_profil']); ?>" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr($interlocuteur['prenom'] ?? '', 0, 1) . substr($interlocuteur['nom'] ?? '', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="chat-user-details">
                        <h3><?php echo htmlspecialchars(($interlocuteur['prenom'] ?? '') . ' ' . ($interlocuteur['nom'] ?? '')); ?></h3>
                        <span class="user-status-text <?php echo ($interlocuteur['est_en_ligne'] ?? false) ? 'online' : 'offline'; ?>">
                            <?php echo ($interlocuteur['est_en_ligne'] ?? false) ? 'En ligne' : 'Hors ligne'; ?>
                            <?php if ($interlocuteur['est_entrain_ecrire'] ?? false): ?>
                                <span class="typing-small"> - en train d'écrire...</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="messages-area" id="messages-area">
                <?php foreach ($messages as $msg): ?>
                <div class="message <?php echo ($msg['id_expediteur'] == $user_id) ? 'sent' : 'received'; ?>">
                    <?php if ($msg['id_expediteur'] != $user_id): ?>
                    <div class="message-avatar">
                        <?php if (!empty($msg['expediteur_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($msg['expediteur_photo']); ?>" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder tiny">
                                <?php echo strtoupper(substr($msg['expediteur_prenom'] ?? '', 0, 1) . substr($msg['expediteur_nom'] ?? '', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="message-bubble">
                        <?php if ($msg['type_message'] === 'image'): ?>
                            <img src="<?php echo htmlspecialchars($msg['message']); ?>" alt="Image" class="message-image" onclick="window.open(this.src)">
                        <?php elseif ($msg['type_message'] === 'file'): ?>
                            <a href="<?php echo htmlspecialchars($msg['message']); ?>" target="_blank" class="file-link">
                                <i class="fas fa-file"></i> Télécharger le fichier
                            </a>
                        <?php else: ?>
                            <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                        <?php endif; ?>
                        <span class="message-time"><?php echo date('H:i', strtotime($msg['date_envoi'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($messages) >= $messages_par_page): ?>
                <div class="load-more">
                    <button class="btn-load-more" data-page="2">
                        <i class="fas fa-chevron-down"></i> Voir plus de messages
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="message-input-area">
                <form method="POST" action="chat.php?user=<?php echo $conversation_user_id; ?>" id="message-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="destinataire_id" value="<?php echo $conversation_user_id; ?>">
                    <input type="hidden" name="type_message" value="text" id="message-type">
                    
                    <div class="input-wrapper">
                        <textarea name="message" id="message-input" placeholder="Écrivez votre message..."></textarea>
                        
                        <div class="input-actions">
                            <label for="file-upload" class="btn-file">
                                <i class="fas fa-paperclip"></i>
                            </label>
                            <input type="file" id="file-upload" name="fichier" style="display: none;" accept="image/*,.pdf,.doc,.docx,.txt">
                            
                            <button type="submit" name="send_message" id="send-button">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div id="file-preview" class="file-preview" style="display: none;">
                        <span id="file-name"></span>
                        <button type="button" onclick="removeFile()"><i class="fas fa-times"></i></button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="no-chat-selected">
                <i class="fas fa-comments"></i>
                <h3>Sélectionnez une conversation</h3>
                <p>Ou commencez une nouvelle discussion avec un utilisateur</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Scripts (identiques à avant) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Variables globales
    let typingTimer;
    const typingInterval = 1000;
    const currentUserId = <?php echo $user_id; ?>;
    const conversationUserId = <?php echo $conversation_user_id ?: 0; ?>;
    
    // Gestion des onglets
    $('#search-tab').click(function(e) {
        e.preventDefault();
        $('#search-section').slideToggle(300);
    });

    <?php if ($is_admin): ?>
    $('#admin-tab').click(function(e) {
        e.preventDefault();
        $('#admin-section').slideToggle(300);
    });
    <?php endif; ?>

    // Recherche d'utilisateurs
    $('#user-search').on('keyup', function() {
        var searchText = $(this).val().toLowerCase();
        $('#search-results .user-result').each(function() {
            var userName = $(this).find('h4').text().toLowerCase();
            var userEmail = $(this).find('p').first().text().toLowerCase();
            if (userName.includes(searchText) || userEmail.includes(searchText)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Nouvelle conversation
    $('#new-chat-btn').click(function() {
        $('#search-section').slideToggle(300);
    });

    // Auto-scroll vers le bas des messages
    function scrollToBottom() {
        var messagesArea = $('#messages-area');
        if (messagesArea.length) {
            messagesArea.scrollTop(messagesArea[0].scrollHeight);
        }
    }
    scrollToBottom();

    // Gestion de l'upload de fichier
    $('#file-upload').change(function(e) {
        const file = e.target.files[0];
        if (file) {
            $('#file-name').text(file.name);
            $('#file-preview').show();
            $('#message-type').val(file.type.startsWith('image/') ? 'image' : 'file');
            $('#message-input').prop('required', false);
        } else {
            removeFile();
        }
    });

    window.removeFile = function() {
        $('#file-upload').val('');
        $('#file-preview').hide();
        $('#message-type').val('text');
        $('#message-input').prop('required', true);
    };

    // Indicateur "en train d'écrire"
    if (conversationUserId > 0) {
        $('#message-input').on('keyup', function() {
            clearTimeout(typingTimer);
            
            $.ajax({
                type: 'POST',
                url: window.location.href,
                data: {
                    typing: true,
                    destinataire_id: conversationUserId,
                    est_entrain_ecrire: true
                },
                dataType: 'json'
            });
            
            typingTimer = setTimeout(function() {
                $.ajax({
                    type: 'POST',
                    url: window.location.href,
                    data: {
                        typing: true,
                        destinataire_id: conversationUserId,
                        est_entrain_ecrire: false
                    },
                    dataType: 'json'
                });
            }, typingInterval);
        });
    }

    // Envoi de message avec AJAX
    $('#message-form').submit(function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('send_message', '1');
        
        $.ajax({
            type: 'POST',
            url: window.location.href,
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + response.error);
                }
            },
            error: function() {
                alert('Erreur lors de l\'envoi du message');
            }
        });
    });

    // Charger plus de messages
    $('.btn-load-more').click(function() {
        const page = $(this).data('page');
        $.ajax({
            url: window.location.href + '&page=' + page,
            type: 'GET',
            success: function(data) {
                const newMessages = $(data).find('#messages-area').html();
                $('#messages-area').prepend(newMessages);
                
                if ($(newMessages).find('.btn-load-more').length) {
                    $('.btn-load-more').data('page', page + 1);
                } else {
                    $('.load-more').remove();
                }
            }
        });
    });

    // Rafraîchissement automatique des messages
    <?php if ($conversation_user_id > 0): ?>
    setInterval(function() {
        $.ajax({
            url: window.location.href,
            type: 'GET', 
            success: function(data) {
                var newMessages = $(data).find('#messages-area').html();
                var currentMessages = $('#messages-area').html();
                
                if (newMessages && newMessages !== currentMessages) {
                    $('#messages-area').html(newMessages);
                    scrollToBottom();
                }
                
                var newStatus = $(data).find('.user-status-text').text();
                $('.user-status-text').text(newStatus);
            }
        });
        
        $.ajax({
            url: window.location.href + '?action=get_unread_counts',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                $('.conversation-item').each(function() {
                    const userId = $(this).data('user-id');
                    const badge = $(this).find('.conv-badge');
                    if (data[userId] > 0) {
                        if (badge.length) {
                            badge.text(data[userId]);
                        } else {
                            $(this).append('<div class="conv-badge">' + data[userId] + '</div>');
                        }
                    } else {
                        badge.remove();
                    }
                });
                
                const totalUnread = Object.values(data).reduce((a, b) => a + b, 0);
                $('.header-nav .badge').text(totalUnread);
            }
        });
    }, 5000);
    <?php endif; ?>

    // Empêcher l'envoi du formulaire si aucun message ni fichier
    $('#send-button').click(function(e) {
        const message = $('#message-input').val().trim();
        const file = $('#file-upload').val();
        
        if (!message && !file) {
            e.preventDefault();
            alert('Veuillez écrire un message ou sélectionner un fichier');
        }
    });
});
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>