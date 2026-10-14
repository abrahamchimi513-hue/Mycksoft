<?php
// config.php
session_start();

// Configuration de la base de données
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');

// Chemins
define('UPLOAD_DIR', 'uploads/chat/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt']);

// Connexion PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Fonction pour vérifier l'authentification
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    return $_SESSION['user_id'];
}

// Fonction pour vérifier les permissions admin
function isAdmin($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT role FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user && $user['role'] === 'admin';
}

// Fonction pour formater la date
function formatDate($date) {
    $dateTime = new DateTime($date);
    $now = new DateTime();
    $interval = $now->diff($dateTime);
    
    if ($interval->days == 0) {
        return "Aujourd'hui à " . $dateTime->format('H:i');
    } elseif ($interval->days == 1) {
        return "Hier à " . $dateTime->format('H:i');
    } elseif ($interval->days < 7) {
        return $dateTime->format('l') . " à " . $dateTime->format('H:i');
    } else {
        return $dateTime->format('d/m/Y à H:i');
    }
}
// config.php (version complète)
session_start();

// ... (votre configuration existante)

// Après la connexion PDO, ajoutez:
if(isset($_SESSION['user_id'])) {
    try {
        $unreadCount = getUnreadChatCount($pdo, $_SESSION['user_id']);
    } catch(Exception $e) {
        $unreadCount = 0;
        error_log("Erreur compteur chat: " . $e->getMessage());
    }
} else {
    $unreadCount = 0;
}

// Fonctions existantes...
?>