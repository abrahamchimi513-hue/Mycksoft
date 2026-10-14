<?php
// includes/config.php

// Démarrer la session UNE SEULE FOIS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Activer les logs d'erreur
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

// Créer le dossier logs s'il n'existe pas
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// Charger l'autoload de Composer (pour PHPMailer)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
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
define('DEFAULT_CITY', 'Douala');
define('SHIPPING_FEE', 0);
define('FREE_SHIPPING_THRESHOLD', 50000);
define('EXPRESS_SHIPPING_FEE', 0);
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 3600);

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

// Configuration SMTP pour les emails (Gmail)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'abrahamchimi513@gmail.com');
define('SMTP_PASS', 'qilwxotvgbrdqwk');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'abrahamchimi513@gmail.com');
define('SMTP_FROM_NAME', SITE_NAME);
define('SMTP_REPLY_TO', 'support@mycksoft.cm');
define('SMTP_DEBUG', 0);

// Mode d'envoi d'email (SMTP ou MAIL)
define('EMAIL_MODE', 'MAIL'); // Utilise mail() native de PHP

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
    
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    
} catch(PDOException $e) {
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    die("Une erreur est survenue. Veuillez réessayer plus tard.");
}

date_default_timezone_set(DEFAULT_TIMEZONE);
$GLOBALS['pdo'] = $pdo;

// ============================================================================
// FONCTIONS UTILITAIRES
// ============================================================================

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !isSessionExpired();
}

function isSessionExpired() {
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return true;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
    return false;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isModerator() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'moderateur';
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function formatPrice($price) {
    return number_format($price, 0, '', ' ') . ' ' . CURRENCY;
}

function formatPriceWithSymbol($price) {
    return number_format($price, 0, '', ' ') . ' ' . CURRENCY_SYMBOL;
}

function getUser($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    
    return $message ? ['message' => $message, 'type' => $type] : null;
}

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

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (preg_match('/^(\\+?237|0)?[2368]\\d{8}$/', $phone)) {
        return true;
    }
    return false;
}

function normalizePhone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (preg_match('/^0/', $phone)) {
        $phone = '+237' . substr($phone, 1);
    }
    if (preg_match('/^237/', $phone) && !preg_match('/^\\+237/', $phone)) {
        $phone = '+' . $phone;
    }
    return $phone;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

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

function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9]+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

function getCartCount($user_id = null) {
    global $pdo;
    
    if ($user_id) {
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
        return isset($_SESSION['panier']) ? array_sum(array_column($_SESSION['panier'], 'quantity')) : 0;
    }
}

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

function checkLoginAttempts($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT login_attempts, last_attempt FROM login_attempts WHERE email = ?");
    $stmt->execute([$email]);
    $attempt = $stmt->fetch();
    
    if ($attempt) {
        $time_diff = time() - strtotime($attempt['last_attempt']);
        if ($attempt['login_attempts'] >= MAX_LOGIN_ATTEMPTS && $time_diff < 1800) {
            return false;
        }
    }
    
    return true;
}

function recordLoginAttempt($email, $success) {
    global $pdo;
    
    if ($success) {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->execute([$email]);
    } else {
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

function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

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

function logActivity($user_id, $action, $details = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO historique (id_utilisateur, action, date_action, details) 
        VALUES (?, ?, NOW(), ?)
    ");
    
    $stmt->execute([$user_id, $action, $details]);
}

function getSiteStats() {
    global $pdo;
    
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produit WHERE est_actif = 1");
    $stats['products'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM utilisateur WHERE role = 'client'");
    $stats['customers'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM commande");
    $stats['orders'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(montant_total), 0) as total FROM commande WHERE statut IN ('livree', 'expediee')");
    $stats['revenue'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM commande WHERE DATE(date_commande) = CURDATE()");
    $stats['today_orders'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM avis WHERE est_approuve = 0");
    $stats['pending_reviews'] = $stmt->fetchColumn();
    
    return $stats;
}

function addToCart($product_id, $quantity = 1) {
    global $pdo;
    
    if (!isLoggedIn()) {
        return ['success' => false, 'message' => 'Veuillez vous connecter pour ajouter au panier'];
    }
    
    $user_id = getUserId();
    
    try {
        $stmt = $pdo->prepare("SELECT stock, prix, prix_promotion FROM produit WHERE id_produit = ? AND est_actif = 1");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => false, 'message' => 'Produit non disponible'];
        }
        
        if ($product['stock'] < $quantity) {
            return ['success' => false, 'message' => 'Stock insuffisant'];
        }
        
        $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $panier = $stmt->fetch();
        
        if (!$panier) {
            $stmt = $pdo->prepare("INSERT INTO panier (id_utilisateur, date_creation) VALUES (?, NOW())");
            $stmt->execute([$user_id]);
            $panier_id = $pdo->lastInsertId();
        } else {
            $panier_id = $panier['id_panier'];
        }
        
        $prix = $product['prix_promotion'] ?? $product['prix'];
        
        $stmt = $pdo->prepare("SELECT * FROM ligne_panier WHERE id_panier = ? AND id_produit = ?");
        $stmt->execute([$panier_id, $product_id]);
        $existing_item = $stmt->fetch();
        
        if ($existing_item) {
            $new_quantity = $existing_item['quantite'] + $quantity;
            $stmt = $pdo->prepare("UPDATE ligne_panier SET quantite = ? WHERE id_ligne_panier = ?");
            $stmt->execute([$new_quantity, $existing_item['id_ligne_panier']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ligne_panier (id_panier, id_produit, quantite, prix_unitaire, date_ajout) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$panier_id, $product_id, $quantity, $prix]);
        }
        
        $stmt = $pdo->prepare("UPDATE panier SET date_modification = NOW() WHERE id_panier = ?");
        $stmt->execute([$panier_id]);
        
        return ['success' => true, 'cart_count' => getCartCount($user_id)];
        
    } catch (PDOException $e) {
        error_log("Erreur ajout panier: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur technique'];
    }
}

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

function removeFromCart($line_id) {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_id = getUserId();
    
    $stmt = $pdo->prepare("
        DELETE lp FROM ligne_panier lp
        JOIN panier p ON lp.id_panier = p.id_panier
        WHERE lp.id_ligne_panier = ? AND p.id_utilisateur = ?
    ");
    
    return $stmt->execute([$line_id, $user_id]);
}

function updateCartQuantity($line_id, $quantity) {
    global $pdo;
    
    if (!isLoggedIn() || $quantity < 1) {
        return false;
    }
    
    $user_id = getUserId();
    
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

function emptyCart() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_id = getUserId();
    
    try {
        $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $panier = $stmt->fetch();
        
        if ($panier) {
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
// FONCTIONS SUPPLÉMENTAIRES POUR LE PANIER
// ============================================================================

function calculateCartTotal($cartItems) {
    $subtotal = 0;
    $total = 0;
    $discount = 0;
    $shipping_fee = SHIPPING_FEE;
    
    if (empty($cartItems)) {
        return [
            'subtotal' => 0,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 0
        ];
    }
    
    foreach ($cartItems as $item) {
        $subtotal += $item['quantite'] * $item['prix_unitaire'];
    }
    
    if (isset($_SESSION['coupon_applied']) && $_SESSION['coupon_applied']) {
        $discount = calculateCouponDiscount($subtotal, $_SESSION['coupon_code'] ?? null);
    }
    
    $city = $_SESSION['shipping_city'] ?? DEFAULT_CITY;
    $shipping_type = $_SESSION['shipping_type'] ?? 'standard';
    
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT shipping_fee FROM shipping_cities WHERE city_name = ?");
        $stmt->execute([$city]);
        $city_fee = $stmt->fetchColumn();
        $shipping_fee = $city_fee !== false ? $city_fee : SHIPPING_FEE;
    } catch (Exception $e) {
        $shipping_fee = SHIPPING_FEE;
    }
    
    if ($shipping_type === 'express') {
        $shipping_fee += EXPRESS_SHIPPING_FEE;
    }
    
    if ($subtotal >= FREE_SHIPPING_THRESHOLD) {
        $shipping_fee = 0;
    }
    
    $total = $subtotal - $discount + $shipping_fee;
    
    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'shipping_fee' => $shipping_fee,
        'total' => $total > 0 ? $total : 0
    ];
}

function calculateCouponDiscount($subtotal, $coupon_code = null) {
    global $pdo;
    
    if (!$coupon_code) {
        $coupon_code = $_SESSION['coupon_code'] ?? null;
    }
    
    if (!$coupon_code) return 0;
    
    try {
        $stmt = $pdo->prepare("
            SELECT discount_percent, max_discount 
            FROM coupons 
            WHERE code = ? 
            AND is_active = 1 
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ");
        $stmt->execute([$coupon_code]);
        $coupon = $stmt->fetch();
        
        if ($coupon) {
            $discount_amount = ($subtotal * $coupon['discount_percent']) / 100;
            if ($coupon['max_discount'] && $discount_amount > $coupon['max_discount']) {
                $discount_amount = $coupon['max_discount'];
            }
            return $discount_amount;
        }
    } catch (Exception $e) {
        error_log("Erreur calcul coupon: " . $e->getMessage());
    }
    
    return 0;
}

function applyCoupon($coupon_code, $user_id = null) {
    global $pdo;
    
    if (empty($coupon_code)) {
        return ['success' => false, 'message' => 'Veuillez entrer un code promo'];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM coupons 
            WHERE code = ? 
            AND is_active = 1 
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ");
        $stmt->execute([$coupon_code]);
        $coupon = $stmt->fetch();
        
        if (!$coupon) {
            return ['success' => false, 'message' => 'Code promo invalide ou expiré'];
        }
        
        if ($coupon['max_uses'] > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?");
            $stmt->execute([$coupon['id_coupon']]);
            $used_count = $stmt->fetchColumn();
            
            if ($used_count >= $coupon['max_uses']) {
                return ['success' => false, 'message' => 'Ce code promo a atteint sa limite d\'utilisation'];
            }
        }
        
        if ($user_id && $coupon['per_user_limit'] > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ? AND user_id = ?");
            $stmt->execute([$coupon['id_coupon'], $user_id]);
            $user_used = $stmt->fetchColumn();
            
            if ($user_used >= $coupon['per_user_limit']) {
                return ['success' => false, 'message' => 'Vous avez déjà utilisé ce code promo'];
            }
        }
        
        $_SESSION['coupon_applied'] = true;
        $_SESSION['coupon_code'] = $coupon_code;
        $_SESSION['coupon_id'] = $coupon['id_coupon'];
        $_SESSION['coupon_discount'] = $coupon['discount_percent'];
        
        return ['success' => true, 'message' => 'Code promo appliqué avec succès'];
        
    } catch (Exception $e) {
        error_log("Erreur applyCoupon: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de l\'application du code'];
    }
}

function removeCoupon($user_id = null) {
    unset($_SESSION['coupon_applied']);
    unset($_SESSION['coupon_code']);
    unset($_SESSION['coupon_id']);
    unset($_SESSION['coupon_discount']);
    return true;
}

function getAppliedCoupon($user_id = null) {
    if (isset($_SESSION['coupon_applied']) && $_SESSION['coupon_applied']) {
        return [
            'code' => $_SESSION['coupon_code'] ?? null,
            'discount_percent' => $_SESSION['coupon_discount'] ?? 0
        ];
    }
    return null;
}

function updateShippingMethod($city, $shipping_type, $user_id = null) {
    global $pdo;
    
    if (empty($city)) {
        return ['success' => false, 'message' => 'Veuillez sélectionner une ville'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM shipping_cities WHERE city_name = ?");
        $stmt->execute([$city]);
        $city_exists = $stmt->fetch();
        
        if (!$city_exists) {
            return ['success' => false, 'message' => 'Ville non desservie'];
        }
        
        $_SESSION['shipping_city'] = $city;
        $_SESSION['shipping_type'] = $shipping_type;
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Erreur updateShippingMethod: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
    }
}

function getShippingCities() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT city_name, shipping_fee, estimated_days 
            FROM shipping_cities 
            WHERE is_active = 1 
            ORDER BY city_name
        ");
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur getShippingCities: " . $e->getMessage());
        return [];
    }
}

function calculateItemTotal($line_id, $cartItems) {
    foreach ($cartItems as $item) {
        if ($item['id_ligne_panier'] == $line_id) {
            return $item['quantite'] * $item['prix_unitaire'];
        }
    }
    return 0;
}

function getRecommendedProducts($cartItems) {
    global $pdo;
    
    try {
        if (empty($cartItems)) {
            $stmt = $pdo->query("
                SELECT DISTINCT p.*, 
                       (SELECT ip.url_image FROM image_produit ip WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 LIMIT 1) as image_url
                FROM produit p
                WHERE p.est_actif = 1 AND p.stock > 0
                ORDER BY p.date_creation DESC
                LIMIT 4
            ");
        } else {
            $categories = array_unique(array_column($cartItems, 'id_categorie'));
            if (empty($categories)) {
                return [];
            }
            
            $placeholders = str_repeat('?,', count($categories) - 1) . '?';
            
            $stmt = $pdo->prepare("
                SELECT DISTINCT p.*, 
                       (SELECT ip.url_image FROM image_produit ip WHERE ip.id_produit = p.id_produit AND ip.est_principale = 1 LIMIT 1) as image_url
                FROM produit p
                WHERE p.est_actif = 1 
                  AND p.stock > 0
                  AND p.id_categorie IN ($placeholders)
                  AND p.id_produit NOT IN (
                      SELECT lp.id_produit 
                      FROM ligne_panier lp
                      JOIN panier pa ON lp.id_panier = pa.id_panier
                      WHERE pa.id_utilisateur = ?
                  )
                ORDER BY RAND()
                LIMIT 4
            ");
            
            $params = array_merge($categories, [getUserId()]);
            $stmt->execute($params);
        }
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Erreur getRecommendedProducts: " . $e->getMessage());
        return [];
    }
}

// ============================================================================
// FONCTIONS EMAILS - VERSION CORRIGÉE
// ============================================================================

/**
 * Envoyer un email via SMTP avec PHPMailer
 */
function sendEmailSMTP($to, $subject, $message, $isHTML = true) {
    error_log("sendEmailSMTP: Début - Destinataire: $to - Mode: " . EMAIL_MODE);
    
    if (empty($to) || empty($subject) || empty($message)) {
        error_log("sendEmailSMTP: Paramètres manquants");
        return false;
    }
    
    // Si EMAIL_MODE est MAIL, utiliser directement mail()
    if (defined('EMAIL_MODE') && EMAIL_MODE === 'MAIL') {
        error_log("sendEmailSMTP: Utilisation du mode MAIL (mail() natif)");
        return sendMailFallback($to, $subject, $message, $isHTML);
    }
    
    // Vérifier que PHPMailer est disponible
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("sendEmailSMTP: PHPMailer non disponible - utilisation de mail()");
        return sendMailFallback($to, $subject, $message, $isHTML);
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Désactiver la vérification SSL (pour développement)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_REPLY_TO, SMTP_FROM_NAME);
        
        // Contenu
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        error_log("sendEmailSMTP: Tentative d'envoi...");
        $mail->send();
        error_log("sendEmailSMTP: Email envoyé avec succès à: " . $to);
        return true;
        
    } catch (Exception $e) {
        error_log("sendEmailSMTP: Erreur - " . $mail->ErrorInfo);
        return sendMailFallback($to, $subject, $message, $isHTML);
    }
}

/**
 * Fallback avec la fonction mail() native
 */
function sendMailFallback($to, $subject, $message, $isHTML = true) {
    error_log("sendMailFallback: Utilisation de mail() pour $to");
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_REPLY_TO . "\r\n";
    
    $result = mail($to, $subject, $message, $headers);
    error_log("sendMailFallback: Résultat = " . ($result ? "succès" : "échec"));
    return $result;
}

/**
 * Fonction de test pour vérifier l'envoi d'email
 */
function testEmailEnvoi($test_email = null) {
    if (!$test_email) {
        $test_email = SMTP_FROM_EMAIL;
    }

    $subject = "Test SMTP - " . SITE_NAME;
    $message = "
    <html>
    <body>
        <h1>Test d'envoi d'email</h1>
        <p>Ceci est un test de votre configuration SMTP.</p>
        <p>Heure: " . date('Y-m-d H:i:s') . "</p>
        <p>SMTP_HOST: " . SMTP_HOST . "</p>
        <p>SMTP_USER: " . SMTP_USER . "</p>
    </body>
    </html>";

    return sendEmailSMTP($test_email, $subject, $message, true);
}

// ============================================================================
// INITIALISATION DE LA SESSION
// ============================================================================

if (isset($_SESSION['LAST_ACTIVITY'])) {
    if (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
        setFlashMessage('Votre session a expiré. Veuillez vous reconnecter.', 'warning');
    }
    $_SESSION['LAST_ACTIVITY'] = time();
} else {
    $_SESSION['LAST_ACTIVITY'] = time();
}

if (!isset($_SESSION['csrf_token'])) {
    generateCSRFToken();
}

if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

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

function checkAccess($allowed_roles = []) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        setFlashMessage('Veuillez vous connecter pour accéder à cette page', 'error');
        redirect('login.php?redirected=1');
    }
    
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        setFlashMessage('Accès non autorisé. Vous n\'avez pas les permissions nécessaires.', 'error');
        redirectByRole();
    }
}

ob_start();

$panier_class_path = __DIR__ . '/../classes/Panier.php';
if (file_exists($panier_class_path)) {
    require_once $panier_class_path;
}

if (!isset($cart) && isset($pdo) && class_exists('Panier')) {
    $cart = new Panier($pdo, getUserId());
}

?>