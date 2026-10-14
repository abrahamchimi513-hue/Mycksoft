<?php
// create_admin.php
// Page sécurisée pour créer un administrateur via interface web
// À placer dans un dossier sécurisé ou à supprimer après utilisation

require_once '../includes/config.php';

$page_title = "Création administrateur";

// Vérifier que seul un super-admin peut accéder à cette page
// Vous pouvez ajouter une vérification supplémentaire ici
$secret_key = "MYCKSOFT_ADMIN_CREATE_2026";

if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    header("HTTP/1.0 403 Forbidden");
    die("Accès interdit");
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $telephone = trim($_POST['telephone']);
    $adresse = trim($_POST['adresse']);
    $role = $_POST['role']; // admin ou moderateur

    // Validation
    $errors = [];
    
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $errors[] = "Tous les champs obligatoires doivent être remplis";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }
    
    if (empty($errors)) {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $errors[] = "Cet email est déjà utilisé";
            } else {
                // Hasher le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insérer l'administrateur
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateur (
                        nom, prenom, email, mot_de_passe, telephone, adresse, role, date_inscription, est_actif
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)
                ");
                
                $stmt->execute([$nom, $prenom, $email, $hashed_password, $telephone, $adresse, $role]);
                
                $user_id = $pdo->lastInsertId();
                
                // Créer le panier
                $stmt_panier = $pdo->prepare("INSERT INTO panier (id_utilisateur) VALUES (?)");
                $stmt_panier->execute([$user_id]);
                
                $message = "Administrateur créé avec succès !";
                $message_type = "success";
                
                // Journaliser la création
                error_log("Administrateur créé: $email - Role: $role");
            }
        } catch (PDOException $e) {
            $message = "Erreur: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

include 'includes/header.php';
?>

<div class="container" style="max-width: 600px; margin: 50px auto;">
    <div class="card">
        <div class="card-header">
            <h2>Création d'un administrateur</h2>
            <p>Interface sécurisée de création de compte administrateur</p>
        </div>
        
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Nom *</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" class="form-control" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Mot de passe *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Confirmation *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone" class="form-control" value="+237">
                </div>
                
                <div class="mb-3">
                    <label>Adresse</label>
                    <textarea name="adresse" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="mb-3">
                    <label>Rôle *</label>
                    <select name="role" class="form-control" required>
                        <option value="admin">Administrateur</option>
                        <option value="moderateur">Modérateur</option>
                    </select>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Créer l'administrateur
                    </button>
                </div>
            </form>
        </div>
        
        <div class="card-footer text-muted">
            <small>Cette page est sécurisée par une clé secrète. Ne la partagez pas.</small>
        </div>
    </div>
</div>

<style>
.card {
    border: 1px solid #ddd;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.card-header {
    background: #2d3d48;
    color: white;
    padding: 20px;
    border-radius: 10px 10px 0 0;
}
.card-body {
    padding: 30px;
}
.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}
.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
</style>
<?php include 'includes/footer.php'; ?>
