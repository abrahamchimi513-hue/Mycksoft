<?php
require_once 'includes/config.php';

$page_title = "Inscription - Mycksoft";

// Vérifier si l'utilisateur est déjà connecté
if (isLoggedIn()) {
    redirect('index.php');
}

// Traitement du formulaire d'inscription
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $telephone = trim($_POST['telephone']);
    $adresse = trim($_POST['adresse']);

    // Validation des champs
    if (empty($nom)) {
        $errors[] = "Le nom est obligatoire";
    }

    if (empty($prenom)) {
        $errors[] = "Le prénom est obligatoire";
    }

    if (empty($email)) {
        $errors[] = "L'email est obligatoire";
    } elseif (!validateEmail($email)) {
        $errors[] = "Format d'email invalide";
    } else {
        // Vérifier si l'email existe déjà
        try {
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Cet email est déjà utilisé";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur de vérification de l'email";
        }
    }

    if (empty($password)) {
        $errors[] = "Le mot de passe est obligatoire";
    } elseif (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    } else {
        $passwordStrength = validatePasswordStrength($password);
        if ($passwordStrength !== true) {
            $errors[] = $passwordStrength;
        }
    }

    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }

    if (empty($telephone)) {
        $errors[] = "Le numéro de téléphone est obligatoire";
    } elseif (!validatePhone($telephone)) {
        $errors[] = "Format de numéro de téléphone invalide";
    } else {
        $telephone = normalizePhone($telephone);
    }

    if (empty($adresse)) {
        $errors[] = "L'adresse est obligatoire";
    }

    // Si aucune erreur, procéder à l'inscription
    if (empty($errors)) {
        try {
            // Hasher le mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Préparer et exécuter la requête d'insertion
            $stmt = $pdo->prepare("
                INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, adresse, role) 
                VALUES (?, ?, ?, ?, ?, ?, 'client')
            ");
            
            $success = $stmt->execute([$nom, $prenom, $email, $hashed_password, $telephone, $adresse]);
            
            if ($success) {
                // Récupérer l'ID du nouvel utilisateur
                $user_id = $pdo->lastInsertId();
                
                // Créer un panier pour le nouvel utilisateur
                try {
                    $stmt_panier = $pdo->prepare("INSERT INTO panier (id_utilisateur) VALUES (?)");
                    $stmt_panier->execute([$user_id]);
                } catch (PDOException $e) {
                    // Le panier existe peut-être déjà, on continue
                    error_log("Erreur création panier: " . $e->getMessage());
                }
                
                // Connecter automatiquement l'utilisateur
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['prenom'] = $prenom;
                $_SESSION['nom'] = $nom;
                $_SESSION['role'] = 'client';
                $_SESSION['LAST_ACTIVITY'] = time();
                
                setFlashMessage("Inscription réussie ! Bienvenue $prenom", 'success');
                
                redirect('index.php');
            }
        } catch (PDOException $e) {
            // Analyser l'erreur pour donner un message plus précis
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errors[] = "Cette adresse email est déjà utilisée";
            } else {
                error_log("Erreur inscription: " . $e->getMessage());
                $errors[] = "Erreur lors de l'inscription. Veuillez réessayer.";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Créer un compte</h1>
                <p>Rejoignez Mycksoft et découvrez nos produits</p>
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

            <form method="POST" action="" class="auth-form" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="prenom" class="form-label">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" 
                               required placeholder="Votre prénom">
                        <div class="form-icon">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nom" class="form-label">Nom *</label>
                        <input type="text" id="nom" name="nom" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" 
                               required placeholder="Votre nom">
                        <div class="form-icon">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           required placeholder="votre@email.com">
                    <div class="form-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="form-label">Mot de passe *</label>
                        <input type="password" id="password" name="password" class="form-input" 
                               required placeholder="Minimum 8 caractères">
                        <div class="form-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            Minimum 8 caractères, majuscule, minuscule, chiffre et caractère spécial
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmation *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               required placeholder="Confirmez votre mot de passe">
                        <div class="form-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="telephone" class="form-label">Téléphone *</label>
                    <input type="tel" id="telephone" name="telephone" class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" 
                           required placeholder="+237 6XX XX XX XX">
                    <div class="form-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        Format: +237 6XX XX XX XX ou 6XX XX XX XX
                    </div>
                </div>

                <div class="form-group">
                    <label for="adresse" class="form-label">Adresse *</label>
                    <textarea id="adresse" name="adresse" class="form-input form-textarea" 
                              required placeholder="Votre adresse complète"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                    <div class="form-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="submitBtn">
                        <i class="fas fa-user-plus"></i>
                        Créer mon compte
                    </button>
                </div>
            </form>

            <div class="auth-footer">
                <p>Vous avez déjà un compte ? 
                    <a href="login.php" class="auth-link">
                        <i class="fas fa-sign-in-alt"></i>
                        Se connecter
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
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
    max-width: 500px;
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
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
}

.alert-error {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
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
    color: #b91c1c;
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
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

.form-textarea {
    resize: vertical;
    min-height: 100px;
    padding-top: 1rem;
    padding-bottom: 1rem;
    line-height: 1.5;
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

.form-textarea + .form-icon {
    top: 1.5rem;
    transform: none;
}

.form-hint {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: var(--secondary);
}

.form-hint i {
    font-size: 0.75rem;
}

.form-actions {
    margin-top: 2rem;
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

/* Styles pour la validation */
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

/* Animation de chargement */
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
    
    .form-row {
        grid-template-columns: 1fr;
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Validation en temps réel
    const inputs = form.querySelectorAll('input[required], textarea[required]');
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
    
    // Validation des mots de passe
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    passwordInput.addEventListener('input', function() {
        validatePasswordStrength(this);
        if (confirmPasswordInput.value) {
            validatePasswordMatch();
        }
    });
    
    confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    
    // Validation du téléphone
    const telephoneInput = document.getElementById('telephone');
    telephoneInput.addEventListener('blur', function() {
        validatePhone(this);
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
        
        if (!validatePasswordStrength(passwordInput)) {
            isValid = false;
        }
        
        if (!validatePasswordMatch()) {
            isValid = false;
        }
        
        if (!validatePhone(telephoneInput)) {
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            // Afficher un message d'erreur général
            const firstError = form.querySelector('.field-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                showNotification('Veuillez corriger les erreurs dans le formulaire', 'error');
            }
        } else {
            // Afficher l'indicateur de chargement
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Création du compte...';
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
    
    function validatePasswordStrength(field) {
        const value = field.value;
        let isValid = true;
        let errorMessage = '';
        
        if (!value) {
            errorMessage = 'Le mot de passe est obligatoire';
            isValid = false;
        } else if (value.length < 8) {
            errorMessage = 'Minimum 8 caractères';
            isValid = false;
        } else if (!/[A-Z]/.test(value)) {
            errorMessage = 'Doit contenir une majuscule';
            isValid = false;
        } else if (!/[a-z]/.test(value)) {
            errorMessage = 'Doit contenir une minuscule';
            isValid = false;
        } else if (!/[0-9]/.test(value)) {
            errorMessage = 'Doit contenir un chiffre';
            isValid = false;
        } else if (!/[^A-Za-z0-9]/.test(value)) {
            errorMessage = 'Doit contenir un caractère spécial';
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
    
    function validatePasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        let isValid = true;
        let errorMessage = '';
        
        if (password && confirmPassword && password !== confirmPassword) {
            errorMessage = 'Les mots de passe ne correspondent pas';
            isValid = false;
            showFieldError(confirmPasswordInput, errorMessage);
        } else if (confirmPassword) {
            clearFieldError(confirmPasswordInput);
            showFieldSuccess(confirmPasswordInput);
        }
        
        return isValid;
    }
    
    function validatePhone(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        if (!value) {
            errorMessage = 'Le téléphone est obligatoire';
            isValid = false;
        } else if (!isValidPhone(value)) {
            errorMessage = 'Format de téléphone invalide';
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
    
    function isValidPhone(phone) {
        // Formats acceptés: +237 6XX XX XX XX, 237 6XX XX XX XX, 6XX XX XX XX
        const phoneRegex = /^(\+?237|0)?[2368]\d{8}$/;
        return phoneRegex.test(phone.replace(/\s/g, ''));
    }
    
    function showNotification(message, type = 'info') {
        // Créer une notification
        const notification = document.createElement('div');
        notification.className = `custom-notification ${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 100px;
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
        
        // Supprimer après 3 secondes
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
});
</script>
<?php include 'includes/footer.php'; ?>
<?php
// register.php - Inscription client
require_once 'includes/config.php';

$page_title = "Inscription - Mycksoft";
$message = '';
$message_type = '';

// Traitement de l'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscription'])) {
    try {
        // Récupération des données
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $telephone = trim($_POST['telephone']);
        $adresse = trim($_POST['adresse']);
        $mot_de_passe = $_POST['mot_de_passe'];
        $confirmation_mdp = $_POST['confirmation_mdp'];
        
        // Validation
        $errors = [];
        
        if (empty($nom)) $errors[] = "Le nom est obligatoire";
        if (empty($prenom)) $errors[] = "Le prénom est obligatoire";
        if (empty($email)) $errors[] = "L'email est obligatoire";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
        if (empty($mot_de_passe)) $errors[] = "Le mot de passe est obligatoire";
        if (strlen($mot_de_passe) < 6) $errors[] = "Le mot de passe doit faire au moins 6 caractères";
        if ($mot_de_passe !== $confirmation_mdp) $errors[] = "Les mots de passe ne correspondent pas";
        
        // Vérifier si l'email existe déjà
        $stmt_check = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
        $stmt_check->execute([$email]);
        if ($stmt_check->fetch()) {
            $errors[] = "Cet email est déjà utilisé";
        }
        
        if (empty($errors)) {
            // Hash du mot de passe
            $hash_mot_de_passe = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            
            // Insertion dans la base
            $stmt = $pdo->prepare("
                INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, adresse, role, date_inscription, est_actif)
                VALUES (?, ?, ?, ?, ?, ?, 'client', NOW(), 1)
            ");
            
            $stmt->execute([$nom, $prenom, $email, $hash_mot_de_passe, $telephone, $adresse]);
            
            $user_id = $pdo->lastInsertId();
            
            // Créer un panier pour l'utilisateur
            $stmt_panier = $pdo->prepare("INSERT INTO panier (id_utilisateur, date_creation) VALUES (?, NOW())");
            $stmt_panier->execute([$user_id]);
            
            // Connecter automatiquement l'utilisateur
            $_SESSION['id_utilisateur'] = $user_id;
            $_SESSION['nom'] = $nom;
            $_SESSION['prenom'] = $prenom;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'client';
            
            $message = "Inscription réussie ! Bienvenue sur Mycksoft";
            $message_type = "success";
            
            // Redirection après 3 secondes
            header("refresh:3;url=compte.php");
            
        } else {
            $message = implode("<br>", $errors);
            $message_type = "error";
        }
        
    } catch (Exception $e) {
        $message = "Erreur lors de l'inscription : " . $e->getMessage();
        $message_type = "error";
    }
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header simplifié */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
        }
        
        .logo i {
            color: var(--red-cm);
        }
        
        .logo span {
            color: var(--yellow-cm);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }
        
        /* Card d'inscription */
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--green-dark) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .register-header p {
            opacity: 0.9;
        }
        
        .register-body {
            padding: 40px;
        }
        
        /* Messages */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(45, 61, 72, 0.1);
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        
        .btn:hover {
            background: var(--green-dark);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* Footer */
        .footer {
            background: var(--dark);
            color: white;
            padding: 30px 0;
            text-align: center;
        }
        
        .copyright {
            color: #aaa;
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .register-card {
                max-width: 100%;
            }
            
            .register-header,
            .register-body {
                padding: 25px;
            }
        }
        
        @media (max-width: 480px) {
            .register-header h1 {
                font-size: 24px;
            }
            
            .register-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-laptop"></i>
                Myck<span>soft</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="register-card">
            <div class="register-header">
                <h1><i class="fas fa-user-plus"></i> Créer un compte</h1>
                <p>Rejoignez notre communauté de clients satisfaits</p>
            </div>
            
            <div class="register-body">
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nom"><i class="fas fa-user"></i> Nom *</label>
                        <input type="text" id="nom" name="nom" class="form-control" required 
                               value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="prenom"><i class="fas fa-user"></i> Prénom *</label>
                        <input type="text" id="prenom" name="prenom" class="form-control" required
                               value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" id="email" name="email" class="form-control" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone"><i class="fas fa-phone"></i> Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" class="form-control" 
                               placeholder="+237 6XX XX XX XX"
                               value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="adresse"><i class="fas fa-map-marker-alt"></i> Adresse</label>
                        <textarea id="adresse" name="adresse" class="form-control" rows="3" 
                                  placeholder="Votre adresse complète"><?php echo isset($_POST['adresse']) ? htmlspecialchars($_POST['adresse']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="mot_de_passe"><i class="fas fa-lock"></i> Mot de passe *</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control" required 
                               oninput="checkPasswordStrength(this.value)">
                        <div class="password-strength" id="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmation_mdp"><i class="fas fa-lock"></i> Confirmer le mot de passe *</label>
                        <input type="password" id="confirmation_mdp" name="confirmation_mdp" class="form-control" required
                               oninput="checkPasswordMatch()">
                        <div class="password-strength" id="password-match"></div>
                    </div>
                    
                    <button type="submit" name="inscription" class="btn">
                        <i class="fas fa-user-plus"></i> S'inscrire
                    </button>
                </form>
                
                <div class="login-link">
                    Déjà inscrit ? <a href="login.php">Connectez-vous ici</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <div class="container">
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> Mycksoft. Tous droits réservés.
            </div>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength(password) {
            const strengthElement = document.getElementById('password-strength');
            let strength = 0;
            let message = '';
            let color = '#666';
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    message = 'Faible';
                    color = '#ef4444';
                    break;
                case 2:
                case 3:
                    message = 'Moyen';
                    color = '#f59e0b';
                    break;
                case 4:
                    message = 'Fort';
                    color = '#10b981';
                    break;
                case 5:
                    message = 'Très fort';
                    color = '#10b981';
                    break;
            }
            
            strengthElement.innerHTML = `Sécurité : <strong style="color: ${color}">${message}</strong>`;
            strengthElement.style.color = color;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('mot_de_passe').value;
            const confirm = document.getElementById('confirmation_mdp').value;
            const matchElement = document.getElementById('password-match');
            
            if (confirm.length === 0) {
                matchElement.innerHTML = '';
                return;
            }
            
            if (password === confirm) {
                matchElement.innerHTML = '<span style="color: #10b981"><i class="fas fa-check"></i> Les mots de passe correspondent</span>';
            } else {
                matchElement.innerHTML = '<span style="color: #ef4444"><i class="fas fa-times"></i> Les mots de passe ne correspondent pas</span>';
            }
        }
        
        // Validation en temps réel
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input[required], textarea[required]');
            
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.style.borderColor = '#ef4444';
                    } else {
                        this.style.borderColor = '#10b981';
                    }
                });
                
                input.addEventListener('input', function() {
                    if (this.value.trim()) {
                        this.style.borderColor = '#ddd';
                    }
                });
            });
        });
    </script>
</body>
</html>