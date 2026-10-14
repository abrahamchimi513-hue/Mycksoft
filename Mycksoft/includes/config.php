<?php
// includes/config.php

// Démarrer la session UNE SEULE FOIS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuration du site
define('SITE_NAME', 'Mycksoft - Informatique Cameroun');
define('SITE_URL', 'http://localhost/Mycksoft');
define('SITE_DOMAIN', 'mycksoft.cm');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/Mycksoft/uploads/');
define('ASSETS_PATH', SITE_URL . '/assets');
define('CURRENCY', 'FCFA');
define('CURRENCY_SYMBOL', 'FCFA');
define('DEFAULT_LANGUAGE', 'fr');
define('DEFAULT_TIMEZONE', 'Africa/Douala');

// Paramètres de l'application
define('ITEMS_PER_PAGE', 12);
define('MAX_CART_ITEMS', 20);
define('DEFAULT_CITY', 'Douala'); // Ville par défaut
define('SHIPPING_FEE', 0); // 5000 FCFA pour la livraison standard
define('FREE_SHIPPING_THRESHOLD', 50000); // Livraison gratuite à partir de 50 000 FCFA
define('EXPRESS_SHIPPING_FEE', 0); // Frais livraison express
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 3600); // 1 heure en secondes

// Configuration des chemins
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PAGES_PATH', ROOT_PATH . '/pages');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('ASSETS_URL', SITE_URL . '/assets');
define('CSS_URL', ASSETS_URL . '/css');
define('JS_URL', ASSETS_URL . '/js');
define('IMAGES_URL', ASSETS_URL . '/images');
define('UPLOADS_URL', SITE_URL . '/uploads');

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    // Désactiver le mode ONLY_FULL_GROUP_BY
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    
} catch(PDOException $e) {
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    die("Une erreur est survenue. Veuillez réessayer plus tard.");
}

// Définir le fuseau horaire
date_default_timezone_set(DEFAULT_TIMEZONE);

// Rendre $pdo disponible globalement
$GLOBALS['pdo'] = $pdo;

// ============================================================================
// FONCTIONS UTILITAIRES
// ============================================================================

/**
 * Rediriger vers une URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !isSessionExpired();
}

/**
 * Vérifier si la session a expiré
 */
function isSessionExpired() {
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
        // Session expirée
        session_unset();
        session_destroy();
        return true;
    }
    $_SESSION['LAST_ACTIVITY'] = time(); // Mettre à jour le temps d'activité
    return false;
}

/**
 * Vérifier si l'utilisateur est administrateur
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Vérifier si l'utilisateur est modérateur
 */
function isModerator() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'moderateur';
}

/**
 * Obtenir l'ID de l'utilisateur connecté
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Formater le prix en FCFA
 */
function formatPrice($price) {
    return number_format($price, 0, '', ' ') . ' ' . CURRENCY;
}

/**
 * Formater le prix avec symbole
 */
function formatPriceWithSymbol($price) {
    return number_format($price, 0, '', ' ') . ' ' . CURRENCY_SYMBOL;
}

/**
 * Obtenir les données de l'utilisateur
 */
function getUser($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Créer un message flash
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Obtenir le message flash
 */
function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    
    return $message ? ['message' => $message, 'type' => $type] : null;
}

/**
 * Afficher un message flash
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $class = '';
        switch ($flash['type']) {
            case 'success': $class = 'alert-success'; break;
            case 'error': $class = 'alert-danger'; break;
            case 'warning': $class = 'alert-warning'; break;
            default: $class = 'alert-info'; break;
        }
        echo '<div class="alert ' . $class . ' alert-dismissible fade show flash-message" role="alert">';
        echo htmlspecialchars($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

/**
 * Sanitizer les données d'entrée
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valider un email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Valider un numéro de téléphone camerounais
 */
function validatePhone($phone) {
    // Formats acceptés: +237 6XX XX XX XX, 237 6XX XX XX XX, 6XX XX XX XX
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Vérifier si c'est un numéro camerounais
    if (preg_match('/^(\\+?237|0)?[2368]\\d{8}$/', $phone)) {
        return true;
    }
    
    return false;
}

/**
 * Normaliser un numéro de téléphone
 */
function normalizePhone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Si le numéro commence par 0, remplacer par +237
    if (preg_match('/^0/', $phone)) {
        $phone = '+237' . substr($phone, 1);
    }
    
    // Si le numéro commence par 237 sans +, ajouter +
    if (preg_match('/^237/', $phone) && !preg_match('/^\\+237/', $phone)) {
        $phone = '+' . $phone;
    }
    
    return $phone;
}

/**
 * Générer un token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifier un token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Obtenir l'adresse IP du client
 */
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Créer un slug à partir d'une chaîne
 */
function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9]+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

/**
 * Obtenir le nombre d'articles dans le panier
 */
function getCartCount($user_id = null) {
    global $pdo;
    
    if ($user_id) {
        // Utilisateur connecté
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(lp.quantite), 0) as total 
                FROM panier p 
                LEFT JOIN ligne_panier lp ON p.id_panier = lp.id_panier 
                WHERE p.id_utilisateur = ?
            ");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Erreur récupération panier: " . $e->getMessage());
            return 0;
        }
    } else {
        // Utilisateur non connecté (session)
        return isset($_SESSION['panier']) ? array_sum(array_column($_SESSION['panier'], 'quantity')) : 0;
    }
}

/**
 * Fonction pour récupérer le nombre de messages non lus
 */
function getUnreadChatCount($pdo, $userId) {
    // Vérifier si la table conversation existe
    try {
        // Vérifier si l'utilisateur existe
        $stmt = $pdo->prepare("SELECT role FROM utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return 0;
        }
        
        $isAdmin = $user && $user['role'] === 'admin';
        
        if ($isAdmin) {
            // Pour les admins: compter tous les messages non lus de tous les clients
            $sql = "SELECT COUNT(*) as unread_count
                    FROM message_chat m
                    JOIN conversation c ON m.id_conversation = c.id_conversation
                    WHERE m.est_lu = 0 AND m.id_expediteur != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
        } else {
            // Pour les clients: compter leurs messages non lus
            $sql = "SELECT COUNT(*) as unread_count
                    FROM message_chat m
                    JOIN conversation c ON m.id_conversation = c.id_conversation
                    WHERE c.id_client = ? AND m.est_lu = 0 AND m.id_expediteur != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $userId]);
        }
        
        $result = $stmt->fetch();
        return $result['unread_count'] ?? 0;
    } catch (Exception $e) {
        // Si les tables n'existent pas encore, retourner 0
        error_log("Erreur dans getUnreadChatCount: " . $e->getMessage());
        return 0;
    }
}

/**
 * Vérifier les permissions d'accès
 */
function requireLogin($required_role = null) {
    if (!isLoggedIn()) {
        setFlashMessage('Veuillez vous connecter pour accéder à cette page', 'error');
        redirect('login.php');
    }
    
    if ($required_role && $_SESSION['role'] !== $required_role) {
        setFlashMessage('Accès non autorisé', 'error');
        redirect('index.php');
    }
}

/**
 * Vérifier les tentatives de connexion
 */
function checkLoginAttempts($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT login_attempts, last_attempt FROM login_attempts WHERE email = ?");
    $stmt->execute([$email]);
    $attempt = $stmt->fetch();
    
    if ($attempt) {
        // Vérifier si le blocage a expiré (30 minutes)
        $time_diff = time() - strtotime($attempt['last_attempt']);
        if ($attempt['login_attempts'] >= MAX_LOGIN_ATTEMPTS && $time_diff < 1800) {
            return false; // Trop de tentatives
        }
    }
    
    return true;
}

/**
 * Enregistrer une tentative de connexion
 */
function recordLoginAttempt($email, $success) {
    global $pdo;
    
    if ($success) {
        // Réinitialiser les tentatives
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->execute([$email]);
    } else {
        // Incrémenter les tentatives
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (email, login_attempts, last_attempt) 
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE 
            login_attempts = login_attempts + 1, 
            last_attempt = NOW()
        ");
        $stmt->execute([$email]);
    }
}

/**
 * Générer un mot de passe sécurisé
 */
function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Valider la force d'un mot de passe
 */
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return 'Le mot de passe doit contenir au moins 8 caractères';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Le mot de passe doit contenir au moins une majuscule';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return 'Le mot de passe doit contenir au moins une minuscule';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return 'Le mot de passe doit contenir au moins un chiffre';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Le mot de passe doit contenir au moins un caractère spécial';
    }
    
    return true;
}

/**
 * Envoyer un email
 */
function sendEmail($to, $subject, $message, $headers = []) {
    $default_headers = [
        'From: ' . SITE_NAME . ' <no-reply@' . SITE_DOMAIN . '>',
        'Reply-To: support@' . SITE_DOMAIN,
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Logger une activité
 */
function logActivity($user_id, $action, $details = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO historique (id_utilisateur, action, date_action, details) 
        VALUES (?, ?, NOW(), ?)
    ");
    
    $stmt->execute([$user_id, $action, $details]);
}

/**
 * Obtenir les statistiques du site
 */
function getSiteStats() {
    global $pdo;
    
    $stats = [];
    
    // Nombre de produits
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produit WHERE est_actif = 1");
    $stats['products'] = $stmt->fetchColumn();
    
    // Nombre d'utilisateurs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM utilisateur WHERE role = 'client'");
    $stats['customers'] = $stmt->fetchColumn();
    
    // Nombre de commandes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM commande");
    $stats['orders'] = $stmt->fetchColumn();
    
    // Chiffre d'affaires
    $stmt = $pdo->query("SELECT COALESCE(SUM(montant_total), 0) as total FROM commande WHERE statut IN ('livree', 'expediee')");
    $stats['revenue'] = $stmt->fetchColumn();
    // Commandes aujourd'hui
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM commande WHERE DATE(date_commande) = CURDATE()");
    $stats['today_orders'] = $stmt->fetchColumn();
    
    // Avis en attente
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM avis WHERE est_approuve = 0");
    $stats['pending_reviews'] = $stmt->fetchColumn();
    
    return $stats;
}

/**
 * Ajouter un produit au panier
 */
function addToCart($product_id, $quantity = 1) {
    global $pdo;
    
    if (!isLoggedIn()) {
        return ['success' => false, 'message' => 'Veuillez vous connecter pour ajouter au panier'];
    }
    
    $user_id = getUserId();
    
    try {
        // Vérifier si le produit existe et est en stock
        $stmt = $pdo->prepare("SELECT stock, prix, prix_promotion FROM produit WHERE id_produit = ? AND est_actif = 1");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => false, 'message' => 'Produit non disponible'];
        }
        
        if ($product['stock'] < $quantity) {
            return ['success' => false, 'message' => 'Stock insuffisant'];
        }
        
        // Vérifier si l'utilisateur a déjà un panier
        $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $panier = $stmt->fetch();
        
        if (!$panier) {
            // Créer un nouveau panier
            $stmt = $pdo->prepare("INSERT INTO panier (id_utilisateur, date_creation) VALUES (?, NOW())");
            $stmt->execute([$user_id]);
            $panier_id = $pdo->lastInsertId();
        } else {
            $panier_id = $panier['id_panier'];
        }
        
        // Calculer le prix (promotionnel si disponible)
        $prix = $product['prix_promotion'] ?? $product['prix'];
        
        // Vérifier si le produit est déjà dans le panier
        $stmt = $pdo->prepare("SELECT * FROM ligne_panier WHERE id_panier = ? AND id_produit = ?");
        $stmt->execute([$panier_id, $product_id]);
        $existing_item = $stmt->fetch();
        
        if ($existing_item) {
            // Mettre à jour la quantité
            $new_quantity = $existing_item['quantite'] + $quantity;
            $stmt = $pdo->prepare("UPDATE ligne_panier SET quantite = ? WHERE id_ligne_panier = ?");
            $stmt->execute([$new_quantity, $existing_item['id_ligne_panier']]);
        } else {
            // Ajouter nouveau produit au panier
            $stmt = $pdo->prepare("
                INSERT INTO ligne_panier (id_panier, id_produit, quantite, prix_unitaire, date_ajout) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$panier_id, $product_id, $quantity, $prix]);
        }
        
        // Mettre à jour la date de modification du panier
        $stmt = $pdo->prepare("UPDATE panier SET date_modification = NOW() WHERE id_panier = ?");
        $stmt->execute([$panier_id]);
        
        return ['success' => true, 'cart_count' => getCartCount($user_id)];
        
    } catch (PDOException $e) {
        error_log("Erreur ajout panier: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur technique'];
    }
}

/**
 * Obtenir les données du panier
 */
function getCartData($user_id = null) {
    global $pdo;
    
    if (!$user_id && !isLoggedIn()) {
        return [];
    }
    
    $user_id = $user_id ?: getUserId();
    
    $stmt = $pdo->prepare("
        SELECT 
            lp.*,
            p.nom_produit,
            p.prix as prix_actuel,
            p.stock,
            p.id_categorie,
            (SELECT ip.url_image 
             FROM image_produit ip 
             WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 
             LIMIT 1) as image_url
        FROM ligne_panier lp
        JOIN produit p ON lp.id_produit = p.id_produit
        JOIN panier pa ON lp.id_panier = pa.id_panier
        WHERE pa.id_utilisateur = ?
        ORDER BY lp.date_ajout DESC
    ");
    
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Supprimer un produit du panier
 */
function removeFromCart($line_id) {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_id = getUserId();
    
    // Vérifier que la ligne appartient bien au panier de l'utilisateur
    $stmt = $pdo->prepare("
        DELETE lp FROM ligne_panier lp
        JOIN panier p ON lp.id_panier = p.id_panier
        WHERE lp.id_ligne_panier = ? AND p.id_utilisateur = ?
    ");
    
    return $stmt->execute([$line_id, $user_id]);
}

/**
 * Mettre à jour la quantité dans le panier
 */
function updateCartQuantity($line_id, $quantity) {
    global $pdo;
    
    if (!isLoggedIn() || $quantity < 1) {
        return false;
    }
    
    $user_id = getUserId();
    
    // Vérifier que la ligne appartient bien au panier de l'utilisateur et que le stock est disponible
    $stmt = $pdo->prepare("
        SELECT p.stock FROM ligne_panier lp
        JOIN produit p ON lp.id_produit = p.id_produit
        JOIN panier pa ON lp.id_panier = pa.id_panier
        WHERE lp.id_ligne_panier = ? AND pa.id_utilisateur = ?
    ");
    
    $stmt->execute([$line_id, $user_id]);
    $stock = $stmt->fetchColumn();
    
    if ($stock < $quantity) {
        return false;
    }
    
    $stmt = $pdo->prepare("
        UPDATE ligne_panier lp
        JOIN panier p ON lp.id_panier = p.id_panier
        SET lp.quantite = ?
        WHERE lp.id_ligne_panier = ? AND p.id_utilisateur = ?
    ");
    
    return $stmt->execute([$quantity, $line_id, $user_id]);
}

/**
 * Vider complètement le panier de l'utilisateur
 */
function emptyCart() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_id = getUserId();
    
    try {
        // Récupérer l'id du panier de l'utilisateur
        $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $panier = $stmt->fetch();
        
        if ($panier) {
            // Supprimer toutes les lignes du panier
            $stmt = $pdo->prepare("DELETE FROM ligne_panier WHERE id_panier = ?");
            return $stmt->execute([$panier['id_panier']]);
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erreur emptyCart: " . $e->getMessage());
        return false;
    }
}

// ============================================================================
// INITIALISATION DE LA SESSION
// ============================================================================

// Vérifier et mettre à jour l'activité de session
if (isset($_SESSION['LAST_ACTIVITY'])) {
    if (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT) {
        // Session expirée
        session_unset();
        session_destroy();
        session_start();
        setFlashMessage('Votre session a expiré. Veuillez vous reconnecter.', 'warning');
    }
    $_SESSION['LAST_ACTIVITY'] = time();
} else {
    $_SESSION['LAST_ACTIVITY'] = time();
}

// Générer un token CSRF si nécessaire
if (!isset($_SESSION['csrf_token'])) {
    generateCSRFToken();
}

// Initialiser le panier si non existant
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

/**
 * Rediriger l'utilisateur selon son rôle
 */
function redirectByRole() {
    if (!isLoggedIn()) {
        return;
    }
    
    $role = $_SESSION['role'] ?? 'client';
    $redirect_url = '';
    
    switch ($role) {
        case 'admin':
            $redirect_url = 'admin/dashboard_admin.php';
            break;
            
        case 'moderateur':
            $redirect_url = 'moderator/dashboard_moderator.php';
            break;
            
        case 'client':
        default:
            // Vérifier s'il y a une URL de redirection dans la session
            if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
                $redirect_url = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
            } else {
                $redirect_url = 'index.php';
            }
            break;
    }
    
    redirect($redirect_url);
}

/**
 * Vérifier les permissions d'accès (à utiliser dans les pages protégées)
 */
function checkAccess($allowed_roles = []) {
    if (!isLoggedIn()) {
        // Stocker l'URL actuelle pour rediriger après connexion
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        setFlashMessage('Veuillez vous connecter pour accéder à cette page', 'error');
        redirect('login.php?redirected=1');
    }
    
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        setFlashMessage('Accès non autorisé. Vous n\'avez pas les permissions nécessaires.', 'error');
        
        // Rediriger selon le rôle
        redirectByRole();
    }
}

// Démarrer la bufferisation de sortie
ob_start();

// Inclure la classe Panier si elle existe
$panier_class_path = __DIR__ . '/../classes/Panier.php';
if (file_exists($panier_class_path)) {
    require_once $panier_class_path;
}

// Initialiser le panier pour toute la session si utilisateur connecté
if (!isset($cart) && isset($pdo) && class_exists('Panier')) {
    $cart = new Panier($pdo, getUserId());
}
?>
