<?php
// paiement.php - Page de formulaire de paiement avec Campay
require_once 'includes/config.php';
require_once 'includes/campay_config.php';
require_once 'includes/mail_functions.php';

// Vérifier si l'utilisateur est connecté - Affichage d'un message au lieu de redirection
if (!isLoggedIn()) {
    $page_title = "Accès restreint - " . SITE_NAME;
    include 'includes/header.php';
    ?>
    <div style="max-width: 500px; margin: 100px auto; padding: 40px; background: white; border-radius: 20px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
        <i class="fas fa-lock" style="font-size: 64px; color: #ef4444; margin-bottom: 20px;"></i>
        <h1 style="color: #1e293b; margin-bottom: 10px;">Accès restreint</h1>
        <p style="color: #64748b; margin-bottom: 30px;">Vous devez être connecté pour accéder à la page de paiement.</p>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="login.php" style="background: linear-gradient(135deg, #4361ee, #3a56d4); color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 10px;">
                <i class="fas fa-sign-in-alt"></i>
                Se connecter
            </a>
            <a href="index.php" style="background: transparent; color: #64748b; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 500; transition: all 0.3s; display: inline-flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0;">
                <i class="fas fa-home"></i>
                Retour à l'accueil
            </a>
        </div>
    </div>
    <?php
    include 'includes/footer.php';
    exit;
}

$user_id = getUserId();
$page_title = "Paiement - " . SITE_NAME;

// Récupérer l'ID de la commande
$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;

// Vérifier que la commande appartient bien à l'utilisateur
$stmt = $pdo->prepare("
    SELECT c.*, u.nom, u.prenom, u.email, u.telephone 
    FROM commande c
    JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
    WHERE c.id_commande = ? AND c.id_utilisateur = ?
");
$stmt->execute([$commande_id, $user_id]);
$commande = $stmt->fetch();

if (!$commande) {
    $_SESSION['error'] = "Commande non trouvée.";
    header('Location: orders.php');
    exit;
}

// Vérifier que la commande peut être payée
$statuts_payables = ['en attente', 'confirmee'];
if (!in_array($commande['statut'], $statuts_payables)) {
    $_SESSION['error'] = "Cette commande ne peut pas être payée (statut: " . $commande['statut'] . ").";
    header('Location: orders.php');
    exit;
}

// Vérifier si la commande est déjà payée
if ($commande['statut'] === 'payee') {
    $_SESSION['error'] = "Cette commande a déjà été payée.";
    header('Location: orders.php');
    exit;
}

// Récupérer les articles de la commande
$articles = [];
try {
    $stmt_articles = $pdo->prepare("SELECT lc.*, p.nom_produit, p.marque, p.modele, p.image FROM ligne_commande lc JOIN produit p ON lc.id_produit = p.id_produit WHERE lc.id_commande = ?");
    $stmt_articles->execute([$commande_id]);
    $articles = $stmt_articles->fetchAll();
} catch (Exception $e) {
    error_log("Erreur récupération articles commande: " . $e->getMessage());
}

// Vérifier si des articles ont été trouvés
if (empty($articles)) {
    $_SESSION['error'] = "Aucun article trouvé pour cette commande.";
    header('Location: orders.php');
    exit;
}

// Récupérer les informations de paiement existantes
$stmt_paiement = $pdo->prepare("SELECT * FROM paiement WHERE id_commande = ?");
$stmt_paiement->execute([$commande_id]);
$paiement_existant = $stmt_paiement->fetch();

// Générer un token CSRF pour la sécurité
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Variable pour stocker l'ID de facture après paiement
$facture_id_apres_paiement = null;

// Traitement de la confirmation de paiement (callback Campay)
if (isset($_GET['campay_success']) && isset($_GET['reference']) && isset($_GET['commande_id'])) {
    $reference_campay = $_GET['reference'];
    $commande_id = intval($_GET['commande_id']);
    
    // Vérifier le statut du paiement auprès de Campay
    $statut = verifierStatutPaiementCampay($reference_campay);
    
    if ($statut['success'] && $statut['statut'] === 'SUCCESSFUL') {
        // Paiement réussi, mettre à jour la base de données
        try {
            $pdo->beginTransaction();
            
            // Mettre à jour la commande
            $update = $pdo->prepare("
                UPDATE commande 
                SET statut = 'payee', 
                    mode_paiement = 'mobile_money', 
                    reference_paiement = ?,
                    date_paiement = NOW() 
                WHERE id_commande = ? AND id_utilisateur = ?
            ");
            $update->execute([$reference_campay, $commande_id, $user_id]);
            
            // Préparer les détails du paiement
            $details_paiement = json_encode([
                'mode' => 'campay',
                'reference_campay' => $reference_campay,
                'date' => date('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'campay_data' => $statut['data']
            ]);
            
            // Mettre à jour ou insérer dans la table paiement
            if ($paiement_existant) {
                $update_paiement = $pdo->prepare("
                    UPDATE paiement 
                    SET mode_paiement = 'mobile_money',
                        montant = ?,
                        reference_paiement = ?,
                        statut_paiement = 'paye',
                        date_paiement = NOW(),
                        details_paiement = ?
                    WHERE id_commande = ?
                ");
                $update_paiement->execute([
                    $commande['montant_total'],
                    $reference_campay,
                    $details_paiement,
                    $commande_id
                ]);
            } else {
                $insert_paiement = $pdo->prepare("
                    INSERT INTO paiement (id_commande, mode_paiement, montant, reference_paiement, statut_paiement, date_paiement, details_paiement)
                    VALUES (?, 'mobile_money', ?, ?, 'paye', NOW(), ?)
                ");
                $insert_paiement->execute([
                    $commande_id,
                    $commande['montant_total'],
                    $reference_campay,
                    $details_paiement
                ]);
            }
            
            $facture_id_apres_paiement = $commande_id;
            $_SESSION['derniere_facture_id'] = $facture_id_apres_paiement;
            $_SESSION['derniere_facture_numero'] = $commande['numero_commande'];

            $pdo->commit();

            $paiement_info = [
                'reference_paiement' => $reference_campay,
                'date_paiement' => date('Y-m-d H:i:s'),
                'mode_paiement' => 'mobile_money',
                'montant' => $commande['montant_total'],
                'statut_paiement' => 'paye',
            ];
            $envoi_email = envoyerEmailConfirmationPaiement($commande, $paiement_info);
            if (!$envoi_email) {
                error_log("Erreur envoi email confirmation paiement pour la commande #{$commande_id}");
            }
            
            // Message de succès et redirection vers la facture
            $_SESSION['success'] = "Paiement effectué avec succès !";
            header('Location: facture.php?id=' . $commande_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur confirmation Campay: " . $e->getMessage());
            $_SESSION['error'] = "Erreur lors de la confirmation du paiement. Veuillez contacter le support.";
            header('Location: orders.php');
            exit;
        }
    } else {
        $_SESSION['error'] = "Le paiement a échoué ou a été annulé.";
        header('Location: paiement.php?commande_id=' . $commande_id);
        exit;
    }
}

// Traitement du formulaire de paiement avec Campay
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payer_avec_campay'])) {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Erreur de sécurité. Veuillez réessayer.";
        header('Location: paiement.php?commande_id=' . $commande_id);
        exit;
    }
    
    $mode_paiement = $_POST['mode_paiement'] ?? '';
    $telephone = trim($_POST['telephone'] ?? '');
    $errors = [];
    
    // Validation
    if (empty($mode_paiement)) {
        $errors[] = "Veuillez choisir un mode de paiement.";
    } elseif (!in_array($mode_paiement, ['orange', 'mtn'])) {
        $errors[] = "Mode de paiement invalide.";
    }
    
    if (empty($telephone)) {
        $errors[] = "Veuillez entrer votre numéro de téléphone.";
    } else {
        $telephone = preg_replace('/[^0-9]/', '', $telephone);
        if (strlen($telephone) < 9 || strlen($telephone) > 12) {
            $errors[] = "Numéro de téléphone invalide.";
        }
    }
    
    // Vérifier que la commande n'a pas été modifiée
    $check_statut = $pdo->prepare("SELECT statut FROM commande WHERE id_commande = ?");
    $check_statut->execute([$commande_id]);
    $statut_actuel = $check_statut->fetchColumn();
    
    if (!in_array($statut_actuel, $statuts_payables)) {
        $errors[] = "Le statut de la commande a changé. Veuillez rafraîchir la page.";
    }
    
    if (empty($errors)) {
        // Générer une référence externe unique
        $reference_externe = 'CMD_' . $commande_id . '_' . time() . '_' . uniqid();
        
        // Description du paiement
        $description = 'Paiement commande #' . $commande['numero_commande'] . ' - ' . SITE_NAME;
        
        // Formater le téléphone pour Campay
        $telephone_formate = $telephone;
        if (strlen($telephone_formate) === 9) {
            $telephone_formate = '237' . $telephone_formate;
        }
        
        // Initier le paiement via Campay
        $resultat = initierPaiementCampay(
            $commande['montant_total'],
            $telephone_formate,
            $description,
            $reference_externe
        );
        
        if ($resultat['success']) {
            // Stocker les informations dans la session
            $_SESSION['campay_reference'] = $resultat['reference'];
            $_SESSION['campay_commande_id'] = $commande_id;
            $_SESSION['campay_external_ref'] = $reference_externe;
            
            // Mettre à jour la table paiement avec le statut en attente
            try {
                $details_paiement = json_encode([
                    'mode' => $mode_paiement,
                    'telephone' => $telephone,
                    'reference_campay' => $resultat['reference'],
                    'external_reference' => $reference_externe,
                    'date_initiation' => date('Y-m-d H:i:s'),
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                if ($paiement_existant) {
                    $update_paiement = $pdo->prepare("
                        UPDATE paiement 
                        SET mode_paiement = 'mobile_money',
                            montant = ?,
                            reference_paiement = ?,
                            statut_paiement = 'en attente',
                            details_paiement = ?
                        WHERE id_commande = ?
                    ");
                    $update_paiement->execute([
                        $commande['montant_total'],
                        $resultat['reference'],
                        $details_paiement,
                        $commande_id
                    ]);
                } else {
                    $insert_paiement = $pdo->prepare("
                        INSERT INTO paiement (id_commande, mode_paiement, montant, reference_paiement, statut_paiement, details_paiement)
                        VALUES (?, 'mobile_money', ?, ?, 'en attente', ?)
                    ");
                    $insert_paiement->execute([
                        $commande_id,
                        $commande['montant_total'],
                        $resultat['reference'],
                        $details_paiement
                    ]);
                }
            } catch (Exception $e) {
                error_log("Erreur enregistrement paiement: " . $e->getMessage());
            }
            
            // Rediriger vers la page d'attente
            header('Location: paiement_attente.php?reference=' . $resultat['reference'] . '&commande_id=' . $commande_id);
            exit;
        } else {
            $errors[] = "Erreur de paiement Campay: " . $resultat['message'];
        }
    }
}

include 'includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

:root {
    --primary: #4361ee;
    --primary-dark: #3a56d4;
    --primary-light: #eef2ff;
    --secondary: #64748b;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --orange: #ff8c00;
    --orange-dark: #e67e00;
    --orange-light: #fff4e6;
    --mtn: #fcd116;
    --mtn-dark: #e6bc14;
    --mtn-light: #fef9e7;
    --campay: #6366f1;
    --campay-dark: #4f52e0;
    --campay-light: #eef2ff;
    --dark: #1e293b;
    --gray: #64748b;
    --light: #f8fafc;
    --border: #e2e8f0;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
    --gradient-primary: linear-gradient(135deg, #4361ee, #3a56d4);
    --gradient-orange: linear-gradient(135deg, #ff8c00, #ff6b00);
    --gradient-mtn: linear-gradient(135deg, #fcd116, #f9a826);
    --gradient-campay: linear-gradient(135deg, #6366f1, #8b5cf6);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #667eea0d 0%, #764ba20d 100%), #f8fafc;
    color: var(--dark);
    min-height: 100vh;
}

/* Container principal */
.paiement-container {
    max-width: 1280px;
    margin: 40px auto;
    padding: 0 24px;
}

.paiement-wrapper {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 32px;
}

/* ===== COLONNE PRINCIPALE ===== */
.paiement-main {
    background: white;
    border-radius: 32px;
    box-shadow: var(--shadow-xl);
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
    animation: slideInLeft 0.6s ease-out;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.paiement-main:hover {
    box-shadow: 0 30px 60px -15px rgba(67, 97, 238, 0.3);
}

/* En-tête */
.paiement-header {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: white;
    padding: 40px 32px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.paiement-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 30s linear infinite;
}

.paiement-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--orange), var(--mtn), var(--campay));
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.paiement-header i {
    font-size: 64px;
    margin-bottom: 16px;
    animation: float 3s ease-in-out infinite;
    background: rgba(255,255,255,0.1);
    padding: 20px;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.paiement-header h1 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}

.paiement-header p {
    font-size: 16px;
    opacity: 0.9;
    margin-bottom: 16px;
}

/* Badge Campay */
.campay-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 12px 24px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 500;
    border: 1px solid rgba(255,255,255,0.2);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.campay-badge i {
    font-size: 18px;
    margin: 0;
    padding: 0;
    background: none;
    box-shadow: none;
    animation: none;
}

/* Corps du formulaire */
.paiement-body {
    padding: 40px 32px;
}

/* Messages d'erreur */
.error-message {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    padding: 20px 24px;
    border-radius: 16px;
    margin-bottom: 32px;
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 15px;
    border-left: 4px solid var(--danger);
    animation: shake 0.5s ease;
    box-shadow: var(--shadow-md);
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-5px); }
    40%, 80% { transform: translateX(5px); }
}

.error-message i {
    font-size: 24px;
    background: rgba(185, 28, 28, 0.1);
    padding: 12px;
    border-radius: 50%;
}

/* Info Campay */
.campay-info {
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    border: 2px solid var(--campay);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 32px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: var(--shadow-md);
    transition: all 0.3s;
}

.campay-info:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.campay-info i {
    font-size: 48px;
    color: var(--campay);
    background: white;
    padding: 16px;
    border-radius: 50%;
    box-shadow: var(--shadow);
}

.campay-info h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.campay-info p {
    color: var(--gray);
    font-size: 14px;
    line-height: 1.5;
}

/* Titre de section */
.section-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: var(--primary);
    background: var(--primary-light);
    padding: 8px;
    border-radius: 12px;
    font-size: 16px;
}

/* Modes de paiement */
.modes-paiement {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

.mode-paiement {
    border: 2px solid var(--border);
    border-radius: 20px;
    padding: 28px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    background: white;
    position: relative;
    overflow: hidden;
}

.mode-paiement::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.8);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.mode-paiement:active::before {
    width: 300px;
    height: 300px;
}

.mode-paiement:hover {
    transform: translateY(-8px) scale(1.02);
    border-color: transparent;
    box-shadow: var(--shadow-lg);
}

#mode-orange:hover,
#mode-orange.selected {
    border-color: var(--orange);
    background: var(--orange-light);
    box-shadow: 0 20px 30px -10px rgba(255, 140, 0, 0.3);
}

#mode-mtn:hover,
#mode-mtn.selected {
    border-color: var(--mtn);
    background: var(--mtn-light);
    box-shadow: 0 20px 30px -10px rgba(252, 209, 22, 0.3);
}

.mode-paiement.selected {
    transform: translateY(-5px);
    border-width: 3px;
}

.mode-paiement i {
    font-size: 48px;
    margin-bottom: 16px;
    transition: transform 0.3s;
}

.mode-paiement:hover i {
    transform: scale(1.1);
}

#mode-orange i { 
    color: var(--orange);
    filter: drop-shadow(0 10px 8px rgba(255, 140, 0, 0.2));
}

#mode-mtn i { 
    color: var(--mtn);
    filter: drop-shadow(0 10px 8px rgba(252, 209, 22, 0.2));
}

.mode-paiement h3 {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--dark);
}

.mode-paiement p {
    font-size: 13px;
    color: var(--gray);
    line-height: 1.4;
}

/* Formulaire */
.form-group {
    margin-bottom: 28px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: var(--dark);
    font-size: 15px;
}

.form-group label i {
    margin-right: 8px;
    color: var(--primary);
}

.form-control {
    width: 100%;
    padding: 16px 20px;
    border: 2px solid var(--border);
    border-radius: 16px;
    font-size: 16px;
    transition: all 0.3s;
    background: white;
    font-family: 'Inter', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--campay);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

.form-control.is-invalid {
    border-color: var(--danger);
    background: #fff5f5;
}

.form-control::placeholder {
    color: #cbd5e1;
    font-size: 15px;
}

/* Information supplémentaire */
.info-box {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    padding: 20px;
    border-radius: 16px;
    font-size: 14px;
    border: 1px solid #7dd3fc;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.info-box i {
    font-size: 24px;
    color: #0284c7;
    background: white;
    padding: 12px;
    border-radius: 50%;
}

/* Bouton de paiement */
.btn-payer {
    width: 100%;
    padding: 20px;
    background: var(--gradient-campay);
    color: white;
    border: none;
    border-radius: 20px;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.4s;
    margin-top: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}

.btn-payer::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-payer:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 40px -10px rgba(99, 102, 241, 0.5);
}

.btn-payer:hover::before {
    width: 300px;
    height: 300px;
}

.btn-payer i {
    font-size: 20px;
    transition: transform 0.3s;
}

.btn-payer:hover i {
    transform: translateX(5px);
}

.btn-payer:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Logo Campay */
.campay-footer {
    text-align: center;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 2px dashed var(--border);
}

.campay-footer img {
    height: 35px;
    opacity: 0.8;
    transition: opacity 0.3s;
}

.campay-footer img:hover {
    opacity: 1;
}

.campay-footer p {
    color: var(--gray);
    font-size: 13px;
    margin-top: 12px;
}

/* Bouton retour */
.btn-retour {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--gray);
    text-decoration: none;
    font-weight: 500;
    margin-top: 24px;
    padding: 12px 20px;
    border-radius: 50px;
    transition: all 0.3s;
    background: white;
    border: 1px solid var(--border);
}

.btn-retour:hover {
    background: var(--light);
    color: var(--campay);
    transform: translateX(-5px);
    border-color: var(--campay);
}

/* ===== COLONNE RÉCAPITULATIF ===== */
.paiement-recap {
    background: white;
    border-radius: 32px;
    padding: 32px;
    box-shadow: var(--shadow-xl);
    position: sticky;
    top: 20px;
    height: fit-content;
    animation: slideInRight 0.6s ease-out;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.recap-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 2px solid var(--border);
}

.recap-header i {
    font-size: 32px;
    color: var(--campay);
    background: var(--campay-light);
    padding: 16px;
    border-radius: 50%;
}

.recap-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.recap-header p {
    color: var(--gray);
    font-size: 14px;
}

/* Informations commande */
.commande-info {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-radius: 24px;
    padding: 24px;
    margin-bottom: 32px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px dashed var(--border);
}

.info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 0;
}

.info-label {
    color: var(--gray);
    font-size: 14px;
    font-weight: 500;
}

.info-value {
    font-weight: 700;
    color: var(--dark);
}

.total-montant {
    font-size: 32px;
    font-weight: 800;
    background: var(--gradient-campay);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Liste des articles */
.articles-list {
    max-height: 350px;
    overflow-y: auto;
    margin-bottom: 24px;
    padding-right: 12px;
}

.articles-list::-webkit-scrollbar {
    width: 6px;
}

.articles-list::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.articles-list::-webkit-scrollbar-thumb {
    background: var(--campay);
    border-radius: 10px;
}

.article-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #f8fafc;
    border-radius: 16px;
    margin-bottom: 12px;
    transition: all 0.3s;
    border: 1px solid var(--border);
}

.article-item:hover {
    transform: translateX(5px);
    background: white;
    border-color: var(--campay);
    box-shadow: var(--shadow-md);
}

.article-image {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    overflow: hidden;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
}

.article-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.article-image i {
    font-size: 28px;
    color: var(--campay);
}

.article-details {
    flex: 1;
}

.article-name {
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
    font-size: 15px;
}

.article-meta {
    font-size: 13px;
    color: var(--gray);
}

.article-price {
    font-weight: 800;
    color: var(--primary);
    font-size: 16px;
    background: var(--primary-light);
    padding: 4px 12px;
    border-radius: 20px;
}

/* Badge de sécurité */
.security-badge {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border-radius: 20px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 32px;
    border: 2px solid #86efac;
}

.security-badge i {
    font-size: 36px;
    color: var(--success);
    animation: pulse 2s infinite;
}

.security-badge h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 4px;
}

.security-badge p {
    font-size: 13px;
    color: var(--gray);
}

/* Modal d'attente */
.waiting-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    backdrop-filter: blur(10px);
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.waiting-content {
    background: white;
    max-width: 500px;
    margin: 100px auto;
    padding: 48px;
    border-radius: 40px;
    text-align: center;
    animation: slideDown 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: var(--shadow-2xl);
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.waiting-spinner {
    width: 100px;
    height: 100px;
    border: 6px solid #f1f5f9;
    border-top: 6px solid var(--campay);
    border-right: 6px solid var(--orange);
    border-bottom: 6px solid var(--mtn);
    border-radius: 50%;
    animation: spin 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
    margin: 0 auto 40px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.waiting-content h2 {
    font-size: 28px;
    font-weight: 800;
    color: var(--dark);
    margin-bottom: 16px;
    background: var(--gradient-campay);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.waiting-content .reference {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    padding: 16px 24px;
    border-radius: 16px;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 18px;
    color: var(--dark);
    margin: 24px 0;
    border: 2px dashed var(--campay);
}

.btn-refresh {
    margin-top: 24px;
    padding: 12px 32px;
    background: var(--light);
    border: 2px solid var(--border);
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-refresh:hover {
    background: var(--campay-light);
    border-color: var(--campay);
    color: var(--campay);
    transform: rotate(180deg);
}

/* Loader */
.loader {
    display: none;
    width: 24px;
    height: 24px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top: 3px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Responsive */
@media (max-width: 1024px) {
    .paiement-wrapper {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .paiement-recap {
        position: static;
    }
}

@media (max-width: 640px) {
    .paiement-container {
        padding: 0 16px;
        margin: 20px auto;
    }
    
    .paiement-header {
        padding: 32px 20px;
    }
    
    .paiement-header h1 {
        font-size: 24px;
    }
    
    .paiement-body {
        padding: 24px 20px;
    }
    
    .modes-paiement {
        grid-template-columns: 1fr;
    }
    
    .mode-paiement {
        padding: 20px;
    }
    
    .campay-info {
        flex-direction: column;
        text-align: center;
    }
    
    .btn-payer {
        font-size: 16px;
        padding: 16px;
    }
    
    .waiting-content {
        margin: 50px 20px;
        padding: 32px 20px;
    }
}

/* Animations supplémentaires */
@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

.shimmer {
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    background-size: 1000px 100%;
    animation: shimmer 2s infinite;
}

/* Tooltip personnalisé */
[data-tooltip] {
    position: relative;
    cursor: help;
}

[data-tooltip]:before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 8px 12px;
    background: var(--dark);
    color: white;
    font-size: 12px;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
    pointer-events: none;
}

[data-tooltip]:hover:before {
    opacity: 1;
    visibility: visible;
    bottom: 120%;
}
</style>

<div class="paiement-container">
    <div class="paiement-wrapper">
        <!-- Colonne principale : Formulaire de paiement -->
        <div class="paiement-main">
            <div class="paiement-header">
                <div class="shimmer"></div>
                <i class="fas fa-shield-alt"></i>
                <h1>Paiement sécurisé</h1>
                <p>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></p>
                <div class="campay-badge">
                    <i class="fas fa-check-circle"></i>
                    <span>Paiement 100% sécurisé par Campay</span>
                </div>
            </div>
            
            <div class="paiement-body">
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo $error; ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($paiement_existant && $paiement_existant['statut_paiement'] === 'en attente'): ?>
                <div style="background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; padding: 16px 20px; border-radius: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; border-left: 4px solid #2563eb;">
                    <i class="fas fa-clock" style="font-size: 24px;"></i>
                    <div>
                        <strong style="display: block; margin-bottom: 4px;">Paiement en attente</strong>
                        <span style="font-size: 14px;">Un paiement est déjà en cours pour cette commande. Veuillez patienter ou rafraîchir la page.</span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="campay-info">
                    <i class="fas fa-bolt"></i>
                    <div>
                        <h4>Paiement instantané avec Campay</h4>
                        <p>Vous allez recevoir une demande de paiement sur votre téléphone. Entrez votre code PIN pour confirmer en toute sécurité.</p>
                    </div>
                </div>
                
                <form method="POST" action="" id="paiementForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="payer_avec_campay" value="1">
                    
                    <div class="section-title">
                        <i class="fas fa-mobile-alt"></i>
                        Choisissez votre opérateur
                    </div>
                    
                    <div class="modes-paiement">
                        <div class="mode-paiement <?php echo (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'orange') ? 'selected' : ''; ?>" 
                             onclick="selectMode('orange')" 
                             id="mode-orange"
                             data-method="orange"
                             data-tooltip="Paiement via Orange Money">
                            <i class="fas fa-mobile-alt"></i>
                            <h3>Orange Money</h3>
                            <p>Paiement rapide et sécurisé</p>
                        </div>
                        
                        <div class="mode-paiement <?php echo (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'mtn') ? 'selected' : ''; ?>" 
                             onclick="selectMode('mtn')" 
                             id="mode-mtn"
                             data-method="mtn"
                             data-tooltip="Paiement via MTN Money">
                            <i class="fas fa-mobile-alt"></i>
                            <h3>MTN Mobile Money</h3>
                            <p>Paiement simple et fiable</p>
                        </div>
                    </div>
                    
                    <input type="hidden" name="mode_paiement" id="mode_paiement" value="<?php echo isset($_POST['mode_paiement']) ? htmlspecialchars($_POST['mode_paiement']) : ''; ?>">
                    
                    <div id="mobile-money-fields">
                        <div class="form-group">
                            <label for="telephone">
                                <i class="fas fa-phone-alt"></i>
                                Numéro de téléphone *
                            </label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                   placeholder="6XXXXXXXX" 
                                   value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : htmlspecialchars($commande['telephone'] ?? ''); ?>"
                                   maxlength="12" required
                                   data-tooltip="Entrez votre numéro à 9 chiffres">
                            <small style="color: #64748b; font-size: 13px; margin-top: 8px; display: block;">
                                <i class="fas fa-info-circle"></i> Exemple : 691234567
                            </small>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <i class="fas fa-bell"></i>
                        <div>
                            <strong style="display: block; margin-bottom: 5px;">Comment ça marche ?</strong>
                            <span style="font-size: 13px;">1. Vous recevrez une notification sur votre téléphone<br>2. Ouvrez votre application Mobile Money<br>3. Entrez votre code PIN pour confirmer</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-payer" id="btnPayer">
                        <span>Payer <?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                        <i class="fas fa-arrow-right"></i>
                        <div class="loader"></div>
                    </button>
                </form>
                
                <div class="campay-footer">
                    <img src="https://campay.net/assets/logo.png" alt="Campay" onerror="this.src='https://via.placeholder.com/120x30?text=Campay'">
                    <p><i class="fas fa-lock"></i> Transactions sécurisées - Agréé par le MINFI</p>
                </div>
                
                <a href="orders.php" class="btn-retour">
                    <i class="fas fa-arrow-left"></i>
                    Retour à mes commandes
                </a>
            </div>
        </div>
        
        <!-- Colonne latérale : Récapitulatif amélioré -->
        <div class="paiement-recap">
            <div class="recap-header">
                <i class="fas fa-shopping-bag"></i>
                <div>
                    <h2>Récapitulatif</h2>
                    <p>Détails de votre commande</p>
                </div>
            </div>
            
            <div class="commande-info">
                <div class="info-row">
                    <span class="info-label">Numéro de commande</span>
                    <span class="info-value">#<?php echo htmlspecialchars($commande['numero_commande']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Date</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Client</span>
                    <span class="info-value"><?php echo htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Articles</span>
                    <span class="info-value"><?php echo count($articles); ?> produit(s)</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Sous-total</span>
                    <span class="info-value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Livraison</span>
                    <span class="info-value" style="color: var(--success);">Gratuite</span>
                </div>
                
                <div class="info-row" style="border-bottom: none;">
                    <span class="info-label" style="font-size: 16px;">Total à payer</span>
                    <span class="total-montant"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA</span>
                </div>
            </div>
            
            <div class="section-title" style="margin-bottom: 16px;">
                <i class="fas fa-box"></i>
                Articles commandés
            </div>
            
            <div class="articles-list">
                <?php foreach ($articles as $article): ?>
                <div class="article-item">
                    <div class="article-image">
                        <?php if (!empty($article['image'])): ?>
                            <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['nom_produit']); ?>">
                        <?php else: ?>
                            <i class="fas fa-box"></i>
                        <?php endif; ?>
                    </div>
                    <div class="article-details">
                        <div class="article-name"><?php echo htmlspecialchars($article['nom_produit']); ?></div>
                        <div class="article-meta">
                            <?php echo htmlspecialchars($article['marque'] . ' ' . $article['modele']); ?> × <?php echo $article['quantite']; ?>
                        </div>
                    </div>
                    <div class="article-price">
                        <?php echo number_format($article['prix_unitaire'] * $article['quantite'], 0, ',', ' '); ?> FCFA
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <div>
                    <h4>🔒 Paiement 100% sécurisé</h4>
                    <p>Vos informations sont cryptées et protégées par Campay</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'attente de paiement amélioré -->
<div id="waitingModal" class="waiting-modal" style="display: none;">
    <div class="waiting-content">
        <div class="waiting-spinner"></div>
        <h2>Paiement en cours</h2>
        <p style="font-size: 16px; color: var(--gray); margin-bottom: 8px;">Une demande de paiement a été envoyée à votre téléphone.</p>
        <p style="font-size: 15px; font-weight: 600; color: var(--dark);">Montant à confirmer :</p>
        <p style="font-size: 36px; font-weight: 800; background: var(--gradient-campay); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; margin: 15px 0;">
            <?php echo number_format($commande['montant_total'], 0, ',', ' '); ?> FCFA
        </p>
        <div class="reference" id="campayReference"></div>
        
        <div style="background: #f0f9ff; padding: 20px; border-radius: 16px; text-align: left; margin: 20px 0;">
            <p style="font-weight: 600; margin-bottom: 10px;"><i class="fas fa-clock" style="color: #3b82f6;"></i> En attente de confirmation...</p>
            <p style="font-size: 14px; color: var(--gray);">1. Vérifiez les notifications sur votre téléphone</p>
            <p style="font-size: 14px; color: var(--gray);">2. Entrez votre code PIN pour autoriser le paiement</p>
            <p style="font-size: 14px; color: var(--gray);">3. Patientez pendant la confirmation</p>
        </div>
        
        <p style="font-size: 13px; color: #94a3b8; margin: 15px 0;">
            <i class="fas fa-sync-alt fa-spin"></i> Cette page se mettra à jour automatiquement
        </p>
        
        <button onclick="window.location.reload()" class="btn-refresh">
            <i class="fas fa-sync-alt"></i>
            Rafraîchir manuellement
        </button>
    </div>
</div>

<script>
// État du formulaire
let isSubmitting = false;

// Sélection du mode de paiement avec animation
function selectMode(mode) {
    document.getElementById('mode_paiement').value = mode;
    
    document.querySelectorAll('.mode-paiement').forEach(el => {
        el.classList.remove('selected');
        el.style.transform = 'scale(1)';
    });
    
    const selectedMode = document.getElementById('mode-' + mode);
    selectedMode.classList.add('selected');
    
    // Animation de sélection
    selectedMode.style.transform = 'scale(0.98)';
    setTimeout(() => {
        selectedMode.style.transform = 'scale(1.02)';
        setTimeout(() => {
            selectedMode.style.transform = 'scale(1)';
        }, 150);
    }, 150);
    
    // Feedback haptique (si disponible)
    if (window.navigator && window.navigator.vibrate) {
        window.navigator.vibrate(50);
    }
}

// Si un mode est déjà sélectionné
<?php if (isset($_POST['mode_paiement'])): ?>
    selectMode('<?php echo $_POST['mode_paiement']; ?>');
<?php endif; ?>

// Validation du formulaire
document.getElementById('paiementForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (isSubmitting) return;
    
    const mode = document.getElementById('mode_paiement').value;
    const telephone = document.getElementById('telephone').value.trim().replace(/\D/g, '');
    const btnPayer = document.getElementById('btnPayer');
    const btnText = btnPayer.querySelector('span');
    const loader = btnPayer.querySelector('.loader');
    
    // Réinitialiser les erreurs
    document.querySelectorAll('.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });
    
    let hasError = false;
    
    // Validation
    if (!mode) {
        // Animation d'erreur
        document.querySelector('.modes-paiement').style.animation = 'shake 0.5s ease';
        setTimeout(() => {
            document.querySelector('.modes-paiement').style.animation = '';
        }, 500);
        
        alert('Veuillez choisir un opérateur (Orange Money ou MTN Money)');
        document.querySelector('.modes-paiement').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    
    if (!telephone) {
        document.getElementById('telephone').classList.add('is-invalid');
        document.getElementById('telephone').focus();
        hasError = true;
    } else if (telephone.length < 9 || telephone.length > 12) {
        document.getElementById('telephone').classList.add('is-invalid');
        document.getElementById('telephone').focus();
        hasError = true;
        alert('Numéro de téléphone invalide (doit contenir 9 à 12 chiffres)');
    }
    
    if (hasError) return;
    
    // Afficher le loader avec animation
    isSubmitting = true;
    btnPayer.disabled = true;
    btnText.style.opacity = '0';
    loader.style.display = 'inline-block';
    
    // Animation de soumission
    btnPayer.style.transform = 'scale(0.98)';
    await new Promise(resolve => setTimeout(resolve, 150));
    btnPayer.style.transform = '';
    
    // Soumettre le formulaire
    this.submit();
});

// Validation en temps réel pour téléphone
document.getElementById('telephone')?.addEventListener('input', function(e) {
    let value = this.value.replace(/\D/g, '');
    if (value.length > 12) value = value.slice(0, 12);
    this.value = value;
    
    // Retirer la classe d'erreur si l'utilisateur corrige
    if (value.length >= 9 && value.length <= 12) {
        this.classList.remove('is-invalid');
    }
});

// Vérifier si on vient d'initier un paiement Campay
<?php if (isset($_SESSION['campay_reference'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Afficher le modal d'attente
    const modal = document.getElementById('waitingModal');
    const referenceSpan = document.getElementById('campayReference');
    referenceSpan.textContent = 'Référence: <?php echo $_SESSION['campay_reference']; ?>';
    modal.style.display = 'block';
    
    // Animation d'entrée
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.opacity = '1';
    }, 10);
    
    // Vérifier le statut toutes les 5 secondes
    const checkInterval = setInterval(function() {
        fetch('verifier_paiement.php?reference=<?php echo $_SESSION['campay_reference']; ?>&commande_id=<?php echo $_SESSION['campay_commande_id']; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.statut === 'SUCCESSFUL') {
                    clearInterval(checkInterval);
                    
                    // Animation de succès
                    const content = modal.querySelector('.waiting-content');
                    content.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        window.location.href = 'paiement.php?campay_success=1&reference=<?php echo $_SESSION['campay_reference']; ?>&commande_id=<?php echo $_SESSION['campay_commande_id']; ?>';
                    }, 300);
                }
            })
            .catch(error => console.error('Erreur:', error));
    }, 5000);
    
    // Nettoyer après 5 minutes
    setTimeout(function() {
        clearInterval(checkInterval);
        <?php 
        unset($_SESSION['campay_reference']);
        unset($_SESSION['campay_commande_id']);
        unset($_SESSION['campay_external_ref']);
        ?>
    }, 300000);
});
<?php endif; ?>

// Animation au survol
document.querySelectorAll('.mode-paiement').forEach(el => {
    el.addEventListener('mouseenter', function() {
        if (!this.classList.contains('selected')) {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        }
    });
    
    el.addEventListener('mouseleave', function() {
        if (!this.classList.contains('selected')) {
            this.style.transform = 'translateY(0) scale(1)';
        }
    });
});

// Empêcher la soumission multiple avec le bouton retour
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});

// Confirmation avant de quitter avec style
let formModified = false;

document.querySelectorAll('#paiementForm input, #paiementForm .mode-paiement').forEach(el => {
    el.addEventListener('change', () => {
        formModified = true;
    });
    el.addEventListener('input', () => {
        formModified = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (formModified && !isSubmitting) {
        e.preventDefault();
        e.returnValue = 'Vous avez des modifications non enregistrées. Êtes-vous sûr de vouloir quitter ?';
    }
});

// Focus automatique sur le champ téléphone
window.addEventListener('load', function() {
    setTimeout(() => {
        document.getElementById('telephone').focus();
    }, 500);
});

// Afficher des tooltips personnalisés
document.querySelectorAll('[data-tooltip]').forEach(el => {
    el.addEventListener('mouseenter', function() {
        const tooltip = this.getAttribute('data-tooltip');
        // Gestion des tooltips (optionnel)
    });
});
</script>

<?php include 'includes/footer.php'; ?>