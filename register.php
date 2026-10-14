<?php
// register.php - Inscription client
require_once 'includes/config.php';

$page_title = "Inscription - Mycksoft";

// Vérifier si l'utilisateur est déjà connecté
if (isLoggedIn()) {
    redirect('index.php');
}

// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$form_data = [];

// Traitement de l'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscription'])) {
    
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['general'] = "Token de sécurité invalide. Veuillez réessayer.";
    } else {
        
        // Récupération et nettoyage des données
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $mot_de_passe = $_POST['mot_de_passe'] ?? '';
        $confirmation_mdp = $_POST['confirmation_mdp'] ?? '';
        
        // Sauvegarde des données du formulaire
        $form_data = compact('nom', 'prenom', 'email', 'telephone', 'adresse');
        
        // Validation des champs
        if (empty($nom)) {
            $errors['nom'] = "Le nom est obligatoire";
        } elseif (strlen($nom) < 2) {
            $errors['nom'] = "Le nom doit contenir au moins 2 caractères";
        }
        
        if (empty($prenom)) {
            $errors['prenom'] = "Le prénom est obligatoire";
        } elseif (strlen($prenom) < 2) {
            $errors['prenom'] = "Le prénom doit contenir au moins 2 caractères";
        }
        
        if (empty($email)) {
            $errors['email'] = "L'email est obligatoire";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Format d'email invalide";
        } else {
            // Vérifier si l'email existe déjà
            $stmt_check = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
            $stmt_check->execute([$email]);
            if ($stmt_check->fetch()) {
                $errors['email'] = "Cet email est déjà utilisé";
            }
        }
        
        // Validation téléphone (optionnel mais formaté)
        if (!empty($telephone)) {
            $telephone_clean = preg_replace('/[^0-9+]/', '', $telephone);
            if (!preg_match('/^(\+237|237|0)?[2368][0-9]{8}$/', $telephone_clean)) {
                $errors['telephone'] = "Format de téléphone invalide. Exemple: 6XXXXXXXX ou +2376XXXXXXXX";
            } else {
                // Normaliser le numéro
                $telephone = '+237' . ltrim(preg_replace('/^(\+237|237|0)/', '', $telephone_clean), '0');
            }
        }
        
        // Validation adresse
        if (!empty($adresse) && strlen($adresse) < 5) {
            $errors['adresse'] = "L'adresse est trop courte (minimum 5 caractères)";
        }
        
        // Validation mot de passe
        if (empty($mot_de_passe)) {
            $errors['mot_de_passe'] = "Le mot de passe est obligatoire";
        } elseif (strlen($mot_de_passe) < 8) {
            $errors['mot_de_passe'] = "Le mot de passe doit contenir au moins 8 caractères";
        } elseif (!preg_match('/[A-Z]/', $mot_de_passe)) {
            $errors['mot_de_passe'] = "Le mot de passe doit contenir au moins une majuscule";
        } elseif (!preg_match('/[a-z]/', $mot_de_passe)) {
            $errors['mot_de_passe'] = "Le mot de passe doit contenir au moins une minuscule";
        } elseif (!preg_match('/[0-9]/', $mot_de_passe)) {
            $errors['mot_de_passe'] = "Le mot de passe doit contenir au moins un chiffre";
        } elseif (!preg_match('/[^A-Za-z0-9]/', $mot_de_passe)) {
            $errors['mot_de_passe'] = "Le mot de passe doit contenir au moins un caractère spécial";
        }
        
        // Vérification de la confirmation
        if ($mot_de_passe !== $confirmation_mdp) {
            $errors['confirmation_mdp'] = "Les mots de passe ne correspondent pas";
        }
        
        // Validation des conditions générales
        if (!isset($_POST['cgv']) || $_POST['cgv'] !== 'on') {
            $errors['cgv'] = "Vous devez accepter les conditions générales";
        }
        
        // Si aucune erreur, procéder à l'inscription
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Hash du mot de passe
                $hash_mot_de_passe = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                
                // Insertion dans la base
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, adresse, role, date_inscription, est_actif)
                    VALUES (?, ?, ?, ?, ?, ?, 'client', NOW(), 1)
                ");
                
                $stmt->execute([$nom, $prenom, $email, $hash_mot_de_passe, $telephone ?: null, $adresse ?: null]);
                $user_id = $pdo->lastInsertId();
                
                // Créer un panier pour l'utilisateur
                $stmt_panier = $pdo->prepare("INSERT INTO panier (id_utilisateur, date_creation) VALUES (?, NOW())");
                $stmt_panier->execute([$user_id]);
                
                $pdo->commit();
                
                // Connecter automatiquement l'utilisateur
                $_SESSION['user_id'] = $user_id;
                $_SESSION['nom'] = $nom;
                $_SESSION['prenom'] = $prenom;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'client';
                $_SESSION['LAST_ACTIVITY'] = time();
                
                setFlashMessage("Inscription réussie ! Bienvenue $prenom sur Mycksoft", "success");
                
                // Redirection vers la page d'accueil
                redirect('index.php');
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Erreur inscription: " . $e->getMessage());
                
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $errors['email'] = "Cet email est déjà utilisé";
                } else {
                    $errors['general'] = "Erreur lors de l'inscription. Veuillez réessayer.";
                }
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
                <h1><i class="fas fa-user-plus"></i> Créer un compte</h1>
                <p>Rejoignez notre communauté de clients satisfaits</p>
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
                                <?php if (is_string($error)): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="prenom" class="form-label">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" class="form-input <?php echo isset($errors['prenom']) ? 'field-error' : ''; ?>" 
                               value="<?php echo htmlspecialchars($form_data['prenom'] ?? ''); ?>" 
                               required placeholder="Votre prénom">
                        <div class="form-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php if (isset($errors['prenom'])): ?>
                            <div class="field-error-message"><?php echo htmlspecialchars($errors['prenom']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="nom" class="form-label">Nom *</label>
                        <input type="text" id="nom" name="nom" class="form-input <?php echo isset($errors['nom']) ? 'field-error' : ''; ?>" 
                               value="<?php echo htmlspecialchars($form_data['nom'] ?? ''); ?>" 
                               required placeholder="Votre nom">
                        <div class="form-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php if (isset($errors['nom'])): ?>
                            <div class="field-error-message"><?php echo htmlspecialchars($errors['nom']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" id="email" name="email" class="form-input <?php echo isset($errors['email']) ? 'field-error' : ''; ?>" 
                           value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" 
                           required placeholder="votre@email.com">
                    <div class="form-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <div class="field-error-message"><?php echo htmlspecialchars($errors['email']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mot_de_passe" class="form-label">Mot de passe *</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-input <?php echo isset($errors['mot_de_passe']) ? 'field-error' : ''; ?>" 
                               required placeholder="Minimum 8 caractères">
                        <div class="form-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="form-hint" id="password-strength-hint">
                            <i class="fas fa-info-circle"></i>
                            Minimum 8 caractères, majuscule, minuscule, chiffre et caractère spécial
                        </div>
                        <div id="password-strength" class="password-strength"></div>
                        <?php if (isset($errors['mot_de_passe'])): ?>
                            <div class="field-error-message"><?php echo htmlspecialchars($errors['mot_de_passe']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmation_mdp" class="form-label">Confirmation *</label>
                        <input type="password" id="confirmation_mdp" name="confirmation_mdp" class="form-input <?php echo isset($errors['confirmation_mdp']) ? 'field-error' : ''; ?>" 
                               required placeholder="Confirmez votre mot de passe">
                        <div class="form-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div id="password-match" class="password-match"></div>
                        <?php if (isset($errors['confirmation_mdp'])): ?>
                            <div class="field-error-message"><?php echo htmlspecialchars($errors['confirmation_mdp']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="telephone" class="form-label">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" class="form-input <?php echo isset($errors['telephone']) ? 'field-error' : ''; ?>" 
                           value="<?php echo htmlspecialchars($form_data['telephone'] ?? ''); ?>" 
                           placeholder="6XXXXXXXX ou +2376XXXXXXXX">
                    <div class="form-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        Format: 6XXXXXXXX ou +2376XXXXXXXX
                    </div>
                    <?php if (isset($errors['telephone'])): ?>
                        <div class="field-error-message"><?php echo htmlspecialchars($errors['telephone']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="adresse" class="form-label">Adresse</label>
                    <textarea id="adresse" name="adresse" class="form-input form-textarea <?php echo isset($errors['adresse']) ? 'field-error' : ''; ?>" 
                              placeholder="Votre adresse complète (optionnel)"><?php echo htmlspecialchars($form_data['adresse'] ?? ''); ?></textarea>
                    <div class="form-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <?php if (isset($errors['adresse'])): ?>
                        <div class="field-error-message"><?php echo htmlspecialchars($errors['adresse']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="cgv" required <?php echo isset($_POST['cgv']) ? 'checked' : ''; ?>>
                        <span>J'accepte les <a href="cgv.php" target="_blank">conditions générales de vente</a> *</span>
                    </label>
                    <?php if (isset($errors['cgv'])): ?>
                        <div class="field-error-message"><?php echo htmlspecialchars($errors['cgv']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" name="inscription" class="btn btn-primary btn-lg btn-block" id="submitBtn">
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
    min-height: calc(100vh - 200px);
    background: linear-gradient(135deg, var(--green-light) 0%, white 50%, var(--green-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.auth-container {
    width: 100%;
    max-width: 600px;
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
    padding: 2rem;
    text-align: center;
}

.auth-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.auth-header p {
    opacity: 0.9;
    font-size: 1rem;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem;
    margin: 1.5rem;
    border-radius: 12px;
}

.alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
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
    font-size: 0.9rem;
}

.form-input {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 2.75rem;
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
}

.form-icon {
    position: absolute;
    left: 1rem;
    top: 2.5rem;
    color: var(--secondary);
    transition: color 0.3s ease;
}

.form-input:focus + .form-icon {
    color: var(--primary);
}

.form-hint {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--secondary);
}

.form-hint i {
    font-size: 0.7rem;
}

.password-strength, .password-match {
    margin-top: 0.5rem;
    font-size: 0.8rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--dark);
}

.checkbox-label input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-label a {
    color: var(--primary);
    text-decoration: none;
}

.checkbox-label a:hover {
    text-decoration: underline;
}

.form-actions {
    margin-top: 2rem;
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
    font-size: 0.8rem;
    color: #dc2626;
    font-weight: 500;
}

.field-error-message i {
    font-size: 0.7rem;
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
        padding: 1.5rem;
    }
    
    .auth-header h1 {
        font-size: 1.5rem;
    }
    
    .auth-form {
        padding: 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .alert {
        margin: 1rem;
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .auth-container {
        max-width: 100%;
    }
    
    .auth-header {
        padding: 1.25rem;
    }
    
    .auth-form {
        padding: 1rem;
    }
    
    .form-input {
        padding: 0.75rem 0.75rem 0.75rem 2.5rem;
        font-size: 0.9rem;
    }
    
    .form-icon {
        left: 0.875rem;
        top: 2.3rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Validation en temps réel
    const inputs = form.querySelectorAll('input[required], textarea');
    inputs.forEach(input => {
        if (input.type !== 'checkbox') {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        }
    });
    
    // Validation spécifique pour l'email
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            validateEmail(this);
        });
    }
    
    // Validation des mots de passe
    const passwordInput = document.getElementById('mot_de_passe');
    const confirmPasswordInput = document.getElementById('confirmation_mdp');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            validatePasswordStrength(this);
            if (confirmPasswordInput && confirmPasswordInput.value) {
                validatePasswordMatch();
            }
        });
    }
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    }
    
    // Validation du téléphone
    const telephoneInput = document.getElementById('telephone');
    if (telephoneInput) {
        telephoneInput.addEventListener('blur', function() {
            validatePhone(this);
        });
    }
    
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        if (field.hasAttribute('required') && !value) {
            errorMessage = 'Ce champ est obligatoire';
            isValid = false;
        } else if (field.id === 'nom' && value && value.length < 2) {
            errorMessage = 'Minimum 2 caractères';
            isValid = false;
        } else if (field.id === 'prenom' && value && value.length < 2) {
            errorMessage = 'Minimum 2 caractères';
            isValid = false;
        } else if (field.id === 'adresse' && value && value.length < 5) {
            errorMessage = 'Adresse trop courte (minimum 5 caractères)';
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
        const strengthElement = document.getElementById('password-strength');
        let isValid = true;
        let strength = 0;
        let message = '';
        let color = '#6c757d';
        
        if (!value) {
            if (strengthElement) strengthElement.innerHTML = '';
            return false;
        }
        
        if (value.length >= 8) strength++;
        if (/[A-Z]/.test(value)) strength++;
        if (/[a-z]/.test(value)) strength++;
        if (/[0-9]/.test(value)) strength++;
        if (/[^A-Za-z0-9]/.test(value)) strength++;
        
        switch(strength) {
            case 0:
            case 1:
                message = 'Très faible';
                color = '#dc2626';
                isValid = false;
                break;
            case 2:
                message = 'Faible';
                color = '#f59e0b';
                isValid = false;
                break;
            case 3:
                message = 'Moyen';
                color = '#f59e0b';
                isValid = true;
                break;
            case 4:
                message = 'Fort';
                color = '#10b981';
                isValid = true;
                break;
            case 5:
                message = 'Très fort';
                color = '#10b981';
                isValid = true;
                break;
        }
        
        if (strengthElement) {
            strengthElement.innerHTML = `<span style="color: ${color}"><i class="fas ${strength >= 4 ? 'fa-check-circle' : 'fa-shield-alt'}"></i> Sécurité : ${message}</span>`;
        }
        
        if (!isValid) {
            showFieldError(field, `Mot de passe ${message.toLowerCase()}. ${strength < 3 ? 'Minimum 8 caractères avec majuscule, minuscule, chiffre et caractère spécial.' : ''}`);
        } else {
            clearFieldError(field);
            showFieldSuccess(field);
        }
        
        return isValid;
    }
    
    function validatePasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const matchElement = document.getElementById('password-match');
        let isValid = true;
        
        if (!confirmPassword) {
            if (matchElement) matchElement.innerHTML = '';
            return true;
        }
        
        if (password !== confirmPassword) {
            if (matchElement) {
                matchElement.innerHTML = '<span style="color: #dc2626"><i class="fas fa-times-circle"></i> Les mots de passe ne correspondent pas</span>';
            }
            showFieldError(confirmPasswordInput, 'Les mots de passe ne correspondent pas');
            isValid = false;
        } else {
            if (matchElement) {
                matchElement.innerHTML = '<span style="color: #10b981"><i class="fas fa-check-circle"></i> Les mots de passe correspondent</span>';
            }
            clearFieldError(confirmPasswordInput);
            showFieldSuccess(confirmPasswordInput);
        }
        
        return isValid;
    }
    
    function validatePhone(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        if (value && !isValidPhone(value)) {
            errorMessage = 'Format de téléphone invalide. Exemple: 6XXXXXXXX ou +2376XXXXXXXX';
            isValid = false;
        }
        
        if (!isValid) {
            showFieldError(field, errorMessage);
        } else if (value) {
            clearFieldError(field);
            showFieldSuccess(field);
        } else {
            clearFieldError(field);
        }
        
        return isValid;
    }
    
    function showFieldError(field, message) {
        clearFieldError(field);
        field.classList.add('field-error');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error-message';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        
        const existingError = field.parentNode.querySelector('.field-error-message');
        if (!existingError) {
            field.parentNode.appendChild(errorDiv);
        }
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
        const phoneClean = phone.replace(/[^0-9+]/g, '');
        const phoneRegex = /^(\+237|237|0)?[2368][0-9]{8}$/;
        return phoneRegex.test(phoneClean);
    }
    
    // Validation du formulaire avant soumission
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        const requiredInputs = form.querySelectorAll('input[required], textarea[required]');
        requiredInputs.forEach(input => {
            if (input.type !== 'checkbox') {
                if (!validateField(input)) isValid = false;
            }
        });
        
        if (!validateEmail(emailInput)) isValid = false;
        if (!validatePasswordStrength(passwordInput)) isValid = false;
        if (!validatePasswordMatch()) isValid = false;
        if (telephoneInput && telephoneInput.value && !validatePhone(telephoneInput)) isValid = false;
        
        const cgvCheckbox = form.querySelector('input[name="cgv"]');
        if (!cgvCheckbox.checked) {
            showNotification('Vous devez accepter les conditions générales', 'error');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            const firstError = form.querySelector('.field-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else {
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création du compte...';
        }
    });
    
    function showNotification(message, type = 'info') {
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
            max-width: 350px;
        `;
        
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            info: '#3b82f6'
        };
        
        notification.style.background = colors[type] || colors.info;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas ${icons[type]}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="margin-left: auto; background: none; border: none; color: white; cursor: pointer; font-size: 18px;">
                    ×
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
});
</script>

<?php include 'includes/footer.php'; ?>