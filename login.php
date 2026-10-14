<?php
require_once 'includes/config.php';

$page_title = "Connexion - Mycksoft";

// Vérifier si l'utilisateur est déjà connecté
if (isLoggedIn()) {
    redirectByRole();
    exit;
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
        try {
            $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE email = ? AND est_actif = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['mot_de_passe'])) {
                $_SESSION['user_id'] = $user['id_utilisateur'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['LAST_ACTIVITY'] = time();
                
                logActivity($user['id_utilisateur'], 'connexion', 'Connexion au site');
                
                $welcome_message = "Connexion réussie ! Bienvenue " . $user['prenom'];
                
                if ($user['role'] === 'admin') {
                    $welcome_message .= " (Administrateur)";
                } elseif ($user['role'] === 'moderateur') {
                    $welcome_message .= " (Modérateur)";
                }
                
                setFlashMessage($welcome_message, 'success');
                redirectByRole();
                exit;
            } else {
                $errors[] = "Email ou mot de passe incorrect";
                
                if ($user) {
                    recordLoginAttempt($email, false);
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur connexion: " . $e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<style>
/* Variables CSS */
:root {
    --primary: #007a5e;
    --primary-dark: #005c46;
    --primary-light: #e6f4f0;
    --secondary: #6c757d;
    --dark: #1a202c;
    --green-light: #d1fae5;
}

.auth-page {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--green-light) 0%, white 50%, var(--green-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.auth-container {
    width: 100%;
    max-width: 450px;
}

.auth-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 122, 94, 0.15);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.auth-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 80px rgba(0, 122, 94, 0.2);
}

.auth-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 2.5rem 2rem;
    text-align: center;
}

.auth-header h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.auth-header p {
    opacity: 0.9;
    font-size: 1.1rem;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    margin: 2rem;
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

.alert-content {
    flex: 1;
}

.alert-content strong {
    display: block;
    margin-bottom: 0.5rem;
}

.alert-content ul {
    margin: 0;
    padding-left: 1.25rem;
}

.alert-content li {
    margin-bottom: 0.25rem;
}

.auth-form {
    padding: 2rem;
}

.form-group {
    position: relative;
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.95rem;
}

.form-input {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(0, 122, 94, 0.1);
}

.form-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary);
    transition: color 0.3s ease;
}

.form-input:focus + .form-icon {
    color: var(--primary);
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

.form-actions {
    margin-top: 1rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 122, 94, 0.3);
}

.btn-lg {
    padding: 1rem 1.5rem;
    font-size: 1.1rem;
}

.btn-block {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.auth-footer {
    padding: 1.5rem 2rem 2rem;
    text-align: center;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.auth-footer p {
    color: var(--secondary);
    margin: 0;
}

.auth-link {
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: color 0.3s ease;
}

.auth-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.field-error {
    border-color: #dc2626 !important;
    background-color: #fef2f2 !important;
}

.field-error:focus {
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
}

.field-success {
    border-color: #10b981 !important;
    background-color: #f0fdf4 !important;
}

.field-success:focus {
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
}

.field-error-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: #dc2626;
    font-weight: 500;
}

.field-error-message i {
    font-size: 0.75rem;
}

.btn-loading {
    position: relative;
    color: transparent !important;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-right-color: transparent;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

/* Responsive */
@media (max-width: 768px) {
    .auth-page {
        padding: 1rem;
    }
    
    .auth-header {
        padding: 2rem 1.5rem;
    }
    
    .auth-header h1 {
        font-size: 1.75rem;
    }
    
    .auth-form {
        padding: 1.5rem;
    }
    
    .form-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .alert {
        margin: 1.5rem;
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .auth-container {
        max-width: 100%;
    }
    
    .auth-header {
        padding: 1.5rem 1rem;
    }
    
    .auth-form {
        padding: 1rem;
    }
    
    .form-input {
        padding: 0.875rem 0.875rem 0.875rem 2.5rem;
    }
    
    .form-icon {
        left: 0.875rem;
    }
}
</style>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Connexion</h1>
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
            
            <?php if (isset($_GET['redirected']) && $_GET['redirected'] == '1'): ?>
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
            
            <form method="POST" action="" class="auth-form" id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           required placeholder="votre@email.com">
                    <div class="form-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe *</label>
                    <input type="password" id="password" name="password" class="form-input" 
                           required placeholder="Votre mot de passe">
                    <div class="form-icon">
                        <i class="fas fa-lock"></i>
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
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="submitBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        Se connecter
                    </button>
                </div>
            </form>
            
            <div class="auth-footer">
                <p>Vous n'avez pas de compte ? 
                    <a href="register.php" class="auth-link">
                        <i class="fas fa-user-plus"></i>
                        Créer un compte
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Validation en temps réel
    const inputs = form.querySelectorAll('input[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            clearFieldError(this);
        });
    });
    
    // Validation spécifique pour l'email
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('blur', function() {
        validateEmail(this);
    });
    
    // Validation du formulaire avant soumission
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        inputs.forEach(input => {
            if (!validateField(input)) {
                isValid = false;
            }
        });
        
        if (!validateEmail(emailInput)) {
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            const firstError = form.querySelector('.field-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                showNotification('Veuillez corriger les erreurs dans le formulaire', 'error');
            }
        } else {
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Connexion en cours...';
        }
    });
    
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        if (!value) {
            errorMessage = 'Ce champ est obligatoire';
            isValid = false;
        }
        
        if (!isValid) {
            showFieldError(field, errorMessage);
        } else {
            clearFieldError(field);
            showFieldSuccess(field);
        }
        
        return isValid;
    }
    
    function validateEmail(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        if (!value) {
            errorMessage = 'L\'email est obligatoire';
            isValid = false;
        } else if (!isValidEmail(value)) {
            errorMessage = 'Format d\'email invalide';
            isValid = false;
        }
        
        if (!isValid) {
            showFieldError(field, errorMessage);
        } else {
            clearFieldError(field);
            showFieldSuccess(field);
        }
        
        return isValid;
    }
    
    function showFieldError(field, message) {
        clearFieldError(field);
        field.classList.add('field-error');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error-message';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        field.parentNode.appendChild(errorDiv);
    }
    
    function clearFieldError(field) {
        field.classList.remove('field-error');
        const existingError = field.parentNode.querySelector('.field-error-message');
        if (existingError) {
            existingError.remove();
        }
        field.classList.remove('field-success');
    }
    
    function showFieldSuccess(field) {
        field.classList.add('field-success');
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `custom-notification ${type}`;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease;
            max-width: 300px;
        `;
        
        if (type === 'success') {
            notification.style.background = '#10b981';
        } else if (type === 'error') {
            notification.style.background = '#ef4444';
        } else {
            notification.style.background = '#3b82f6';
        }
        
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        if (type === 'error') icon = 'fa-exclamation-circle';
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
});
</script>

<?php include 'includes/footer.php'; ?>