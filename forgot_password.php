<?php
// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure la configuration
require_once 'includes/config.php';

$page_title = "Mot de passe oublié";

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Initialisation des variables
$error = '';
$success = '';
$email = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (empty($email)) {
        $error = "Veuillez saisir votre adresse email";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format d'email invalide";
    } else {
        try {
            // Vérifier si l'email existe dans la base de données
            $query = "SELECT id_utilisateur, nom, prenom, email FROM utilisateur WHERE email = :email AND est_actif = 1";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Générer un token unique
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Supprimer les anciens tokens pour cet utilisateur
                $deleteQuery = "DELETE FROM password_resets WHERE id_utilisateur = :id_utilisateur";
                $deleteStmt = $pdo->prepare($deleteQuery);
                $deleteStmt->execute(['id_utilisateur' => $user['id_utilisateur']]);
                
                // Insérer le nouveau token
                $insertQuery = "INSERT INTO password_resets (id_utilisateur, token, expiry, date_creation) 
                               VALUES (:id_utilisateur, :token, :expiry, NOW())";
                $insertStmt = $pdo->prepare($insertQuery);
                $insertStmt->execute([
                    'id_utilisateur' => $user['id_utilisateur'],
                    'token' => $token,
                    'expiry' => $expiry
                ]);
                
                // Envoyer l'email (à configurer avec vos paramètres SMTP)
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/Mycksoft/reset-password.php?token=" . $token;
                
                // Ici, vous devrez configurer l'envoi d'email
                // Pour l'instant, on affiche juste le lien (à retirer en production)
                $success = "Un email de réinitialisation a été envoyé à l'adresse : " . htmlspecialchars($email);
                
                // Note: Pour l'envoi d'email, vous pouvez utiliser la fonction mail() de PHP
                // ou une bibliothèque comme PHPMailer
                
                /*
                // Exemple avec mail() (à configurer)
                $to = $email;
                $subject = "Réinitialisation de votre mot de passe";
                $message = "
                <html>
                <head>
                    <title>Réinitialisation de mot de passe</title>
                </head>
                <body>
                    <h2>Bonjour " . $user['prenom'] . " " . $user['nom'] . ",</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
                    <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
                    <p><a href='" . $resetLink . "'>Réinitialiser mon mot de passe</a></p>
                    <p>Ce lien est valable pendant 1 heure.</p>
                    <p>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
                    <p>Cordialement,<br>L'équipe Mycksoft</p>
                </body>
                </html>
                ";
                
                // Pour envoyer un email HTML
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= 'From: noreply@mycksoft.com' . "\r\n";
                
                if (mail($to, $subject, $message, $headers)) {
                    $success = "Un email de réinitialisation a été envoyé à votre adresse.";
                } else {
                    $error = "Erreur lors de l'envoi de l'email. Veuillez réessayer.";
                }
                */
                
            } else {
                // Pour des raisons de sécurité, on affiche le même message même si l'email n'existe pas
                $success = "Si cette adresse email existe dans notre base de données, vous recevrez un email de réinitialisation.";
            }
        } catch (PDOException $e) {
            error_log("Erreur forgot password : " . $e->getMessage());
            $error = "Une erreur technique est survenue. Veuillez réessayer plus tard.";
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <i class="fas fa-key"></i>
            <h2>Mot de passe oublié</h2>
            <p>Saisissez votre email pour réinitialiser votre mot de passe</p>
        </div>
        
        <!-- Message d'erreur -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Message de succès -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form method="POST" action="" class="auth-form">
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i>
                    Adresse email
                </label>
                <div class="input-group">
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($email); ?>" 
                           placeholder="votre@email.com"
                           required
                           autofocus>
                </div>
                <small class="form-text">
                    Vous recevrez un email avec les instructions pour réinitialiser votre mot de passe.
                </small>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i>
                Envoyer
            </button>
        </form>

        <div class="auth-footer">
            <p><a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Retour à la connexion
            </a></p>
        </div>
    </div>
</div>

<style>
.auth-container {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.auth-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 450px;
    padding: 2.5rem;
    animation: slideIn 0.5s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-header i {
    font-size: 4rem;
    color: #667eea;
    margin-bottom: 1rem;
}

.auth-header h2 {
    color: #333;
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.auth-header p {
    color: #666;
    font-size: 1rem;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.95rem;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #333;
    font-weight: 500;
    font-size: 0.95rem;
}

.form-group label i {
    color: #667eea;
    margin-right: 0.5rem;
}

.input-group {
    position: relative;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e1e1e1;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-text {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: #666;
}

.btn-submit {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.auth-footer {
    text-align: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 2px solid #f1f1f1;
}

.back-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.back-link:hover {
    color: #764ba2;
    transform: translateX(-5px);
}

@media (max-width: 480px) {
    .auth-card {
        padding: 1.5rem;
    }
    
    .auth-header h2 {
        font-size: 1.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>