<?php
require_once 'includes/config.php';

$page_title = "Connexion";

// Vérifier si l'utilisateur est déjà connecté
if (isLoggedIn()) {
    // Rediriger selon le rôle
    redirectByRole();
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validation
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "L'email est obligatoire";
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est obligatoire";
    }
    
    if (empty($errors)) {
        // Vérifier l'utilisateur dans la base de données
        try {
            $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE email = ? AND est_actif = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['mot_de_passe'])) {
                // Connexion réussie
                $_SESSION['user_id'] = $user['id_utilisateur'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['LAST_ACTIVITY'] = time();
                
                // Journaliser la connexion
                logActivity($user['id_utilisateur'], 'connexion', 'Connexion au site');
                
                // Message de bienvenue selon le rôle
                $welcome_message = "Connexion réussie ! Bienvenue " . $user['prenom'];
                
                if ($user['role'] === 'admin') {
                    $welcome_message .= " (Administrateur)";
                } elseif ($user['role'] === 'moderateur') {
                    $welcome_message .= " (Modérateur)";
                }
                
                setFlashMessage($welcome_message, 'success');
                
                // Redirection selon le rôle
                redirectByRole();
                
            } else {
                $errors[] = "Email ou mot de passe incorrect";
                
                // Enregistrer la tentative échouée
                if ($user) {
                    recordLoginAttempt($email, false);
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur connexion: " . $e->getMessage());
            // $errors[] = "Erreur lors de la connexion. Veuillez réessayer.";
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Connexion à votre compte</h2>
            <p>Accédez à votre espace personnel</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="alert-content">
                    <strong>Des erreurs sont survenues :</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <?php 
        // Afficher un message si l'utilisateur vient d'être redirigé à cause d'une permission
        if (isset($_GET['redirected']) && $_GET['redirected'] == '1'): 
        ?>
            <div class="alert alert-info">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="alert-content">
                    <strong>Accès restreint</strong>
                    <p>Veuillez vous connecter pour accéder à cette page</p>
                </div>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="auth-form">
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-with-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           placeholder="votre@email.com" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Votre mot de passe" required>
                </div>
            </div>
            
            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember" value="1">
                    <span>Se souvenir de moi</span>
                </label>
                <a href="forgot-password.php" class="forgot-password">
                    Mot de passe oublié ?
                </a>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <i class="fas fa-sign-in-alt"></i>
                Se connecter
            </button>
        </form>
        
        <div class="auth-footer">
            <p>Vous n'avez pas de compte ? 
                <a href="register.php" class="auth-link">
                    <i class="fas fa-user-plus"></i>
                    Créer un compte
                </a>
            </p>
            
            <div class="role-info">
                <div class="role-item">
                    <i class="fas fa-user-shield text-primary"></i>
                    <span>Administrateurs : Accès au dashboard admin</span>
                </div>
                <div class="role-item">
                    <i class="fas fa-user-tie text-info"></i>
                    <span>Modérateurs : Gestion des contenus</span>
                </div>
                <div class="role-item">
                    <i class="fas fa-user text-success"></i>
                    <span>Clients : Accès à votre espace personnel</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.auth-container {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--green-light) 0%, white 50%, var(--green-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.auth-card {
    background: white;
    padding: 3rem;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 122, 94, 0.15);
    width: 100%;
    max-width: 450px;
}

.auth-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.auth-header h2 {
    color: var(--primary);
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.auth-header p {
    color: var(--secondary);
    font-size: 1.1rem;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-radius: 12px;
}

.alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
}

.alert-info {
    background: #dbeafe;
    border: 1px solid #bfdbfe;
    color: #1e40af;
}

.alert-icon {
    font-size: 1.25rem;
    margin-top: 0.125rem;
}

.alert-content strong {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.alert-content ul {
    margin: 0;
    padding-left: 1.25rem;
}

.alert-content li {
    margin-bottom: 0.25rem;
}

.auth-form {
    margin-bottom: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.95rem;
}

.input-with-icon {
    position: relative;
}

.input-with-icon i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary);
    font-size: 1rem;
}

.input-with-icon .form-control {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.input-with-icon .form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(0, 122, 94, 0.1);
}

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    color: var(--dark);
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.forgot-password {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.forgot-password:hover {
    text-decoration: underline;
}

.btn-block {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
}

.auth-footer {
    text-align: center;
    padding-top: 2rem;
    border-top: 1px solid #e2e8f0;
}

.auth-footer p {
    color: var(--secondary);
    margin-bottom: 1.5rem;
}

.auth-link {
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.auth-link:hover {
    text-decoration: underline;
}

.role-info {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1rem;
    text-align: left;
}

.role-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.role-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.role-item i {
    font-size: 1.25rem;
}

.role-item span {
    color: var(--dark);
    font-size: 0.9rem;
}

.text-primary { color: var(--primary); }
.text-info { color: var(--info); }
.text-success { color: var(--success); }

/* Responsive */
@media (max-width: 768px) {
    .auth-container {
        padding: 1rem;
    }
    
    .auth-card {
        padding: 2rem 1.5rem;
    }
    
    .auth-header h2 {
        font-size: 1.75rem;
    }
    
    .form-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .auth-card {
        padding: 1.5rem 1rem;
    }
    
    .auth-header h2 {
        font-size: 1.5rem;
    }
    
    .input-with-icon .form-control {
        padding: 0.875rem 0.875rem 0.875rem 2.5rem;
    }
    
    .input-with-icon i {
        left: 0.875rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.auth-form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    
    // Validation en temps réel
    emailInput.addEventListener('blur', validateEmail);
    passwordInput.addEventListener('blur', validatePassword);
    
    // Validation du formulaire
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        if (!validateEmail()) {
            isValid = false;
        }
        
        if (!validatePassword()) {
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
    
    function validateEmail() {
        const value = emailInput.value.trim();
        let isValid = true;
        
        if (!value) {
            showFieldError(emailInput, 'L\'email est obligatoire');
            isValid = false;
        } else if (!isValidEmail(value)) {
            showFieldError(emailInput, 'Format d\'email invalide');
            isValid = false;
        } else {
            clearFieldError(emailInput);
        }
        
        return isValid;
    }
    
    function validatePassword() {
        const value = passwordInput.value;
        let isValid = true;
        
        if (!value) {
            showFieldError(passwordInput, 'Le mot de passe est obligatoire');
            isValid = false;
        } else {
            clearFieldError(passwordInput);
        }
        
        return isValid;
    }
    
    function showFieldError(field, message) {
        clearFieldError(field);
        field.style.borderColor = '#dc2626';
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error-message';
        errorDiv.style.cssText = 'display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; font-size: 0.85rem; color: #dc2626; font-weight: 500;';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle" style="font-size: 0.75rem;"></i> ${message}`;
        
        field.parentNode.parentNode.appendChild(errorDiv);
    }
    
    function clearFieldError(field) {
        field.style.borderColor = '';
        const existingError = field.parentNode.parentNode.querySelector('.field-error-message');
        if (existingError) {
            existingError.remove();
        }
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
