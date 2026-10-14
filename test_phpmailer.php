<?php
// test_phpmailer.php - Diagnostic complet pour PHPMailer
// Inclure l'autoloader de Composer pour charger PHPMailer
require_once __DIR__ . '/vendor/autoload.php';

// Charger PHPMailer manuellement si l'autoloader ne fonctionne pas
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
}

require_once 'includes/config.php';
require_once 'includes/mail_functions.php';

// Utiliser les classes PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h1>🔍 Diagnostic complet des emails PHPMailer</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// 1. Vérifier la configuration
echo "<h2>📋 Configuration actuelle</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><td><strong>SMTP_HOST</strong></td><td>" . SMTP_HOST . "</td></tr>";
echo "<tr><td><strong>SMTP_USER</strong></td><td>" . SMTP_USER . "</td></tr>";
echo "<tr><td><strong>SMTP_PORT</strong></td><td>" . SMTP_PORT . "</td></tr>";
echo "<tr><td><strong>SMTP_FROM_EMAIL</strong></td><td>" . SMTP_FROM_EMAIL . "</td></tr>";
echo "<tr><td><strong>SMTP_FROM_NAME</strong></td><td>" . SMTP_FROM_NAME . "</td></tr>";
echo "</table>";

// 2. Vérifier si PHPMailer est chargé
echo "<h2>📦 Vérification PHPMailer</h2>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p class='success'>✅ PHPMailer est correctement chargé</p>";
} else {
    echo "<p class='error'>❌ PHPMailer n'est pas chargé</p>";
    exit;
}

// 3. Test de connexion SMTP
echo "<h2>🔗 Test de connexion SMTP</h2>";
$mail = new PHPMailer(true);

try {
    // Configuration de base
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->SMTPDebug = 2; // Debug détaillé

    // Options SSL
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // Test de connexion SMTP seulement
    if ($mail->smtpConnect()) {
        echo "<p class='success'>✅ Connexion SMTP réussie</p>";
        $mail->smtpClose();
    } else {
        echo "<p class='error'>❌ Échec de connexion SMTP</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur de connexion : " . $e->getMessage() . "</p>";
}

// 4. Test d'envoi d'email complet
echo "<h2>📧 Test d'envoi d'email</h2>";
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;

    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress('abrahamchimi513@gmail.com', 'Test Recipient');
    $mail->addReplyTo(SMTP_REPLY_TO, SMTP_FROM_NAME);

    $mail->isHTML(true);
    $mail->Subject = 'Test PHPMailer - Mycksoft - ' . date('H:i:s');
    $mail->Body = '<h1>🧪 Test PHPMailer</h1><p>Email envoyé le ' . date('Y-m-d H:i:s') . '</p><p>Si vous recevez cet email, la configuration fonctionne !</p>';
    $mail->AltBody = 'Test PHPMailer - ' . date('Y-m-d H:i:s');

    $mail->send();
    echo "<p class='success'>✅ Email envoyé avec succès ! Vérifiez votre boîte mail.</p>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur d'envoi : " . $mail->ErrorInfo . "</p>";

    // Diagnostic spécifique
    echo "<h3>🔧 Solutions recommandées :</h3>";
    echo "<div style='background:#fff3cd;padding:15px;border-radius:5px;margin:10px 0;'>";

    if (strpos($mail->ErrorInfo, 'authentication failed') !== false) {
        echo "<h4>Problème d'authentification Gmail :</h4>";
        echo "<ol>";
        echo "<li>Allez sur <a href='https://myaccount.google.com/security' target='_blank'>Google Account Security</a></li>";
        echo "<li>Activez la <strong>vérification en 2 étapes</strong></li>";
        echo "<li>Générez un <strong>mot de passe d'application</strong> : Sécurité → Mots de passe d'application</li>";
        echo "<li>Remplacez le mot de passe SMTP par ce nouveau mot de passe (16 caractères)</li>";
        echo "</ol>";
    }

    echo "<h4>Alternatives SMTP :</h4>";
    echo "<ul>";
    echo "<li><strong>SendGrid</strong> : Service SMTP gratuit pour les petits volumes</li>";
    echo "<li><strong>Mailgun</strong> : Alternative gratuite</li>";
    echo "<li><strong>Brevo (ex Sendinblue)</strong> : Interface française</li>";
    echo "<li><strong>OVH Mail</strong> : Si vous avez un hébergement OVH</li>";
    echo "</ul>";

    echo "<h4>Configuration alternative recommandée :</h4>";
    echo "<pre>
// Dans includes/config.php, remplacez par :
define('SMTP_HOST', 'smtp-relay.sendinblue.com'); // ou autre service
define('SMTP_USER', 'votre_email@domaine.com');
define('SMTP_PASS', 'votre_mot_de_passe_api');
define('SMTP_PORT', 587);
    </pre>";

    echo "</div>";
}

// 5. Test de la fonction envoyerEmailConfirmationPaiement
echo "<h2>🎯 Test de la fonction envoyerEmailConfirmationPaiement</h2>";

// Simuler des données de commande
$commande_test = [
    'id_commande' => 999,
    'numero_commande' => 'TEST-001',
    'montant_total' => 50000,
    'nom' => 'Test',
    'prenom' => 'Client',
    'email' => 'abrahamchimi513@gmail.com',
    'telephone' => '000000000',
    'adresse' => 'Test Address',
    'date_paiement' => date('Y-m-d H:i:s')
];

$paiement_test = [
    'mode_paiement' => 'Mobile Money',
    'reference_paiement' => 'TEST-REF-123',
    'date_paiement' => date('Y-m-d H:i:s'),
    'montant' => 50000
];

$result = envoyerEmailConfirmationPaiement($commande_test, $paiement_test);

if ($result) {
    echo "<p class='success'>✅ Fonction envoyerEmailConfirmationPaiement : SUCCÈS</p>";
} else {
    echo "<p class='error'>❌ Fonction envoyerEmailConfirmationPaiement : ÉCHEC</p>";
}

echo "<hr>";
echo "<p><strong>📝 Note :</strong> Si les tests échouent, vérifiez les logs d'erreur dans le dossier logs/ ou les logs PHP.</p>";
?>

try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;

    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress('abrahamchimi513@gmail.com', 'Test Recipient');
    $mail->addReplyTo(SMTP_REPLY_TO, SMTP_FROM_NAME);

    $mail->isHTML(true);
    $mail->Subject = 'Test PHPMailer - Mycksoft - ' . date('H:i:s');
    $mail->Body = '<h1>🧪 Test PHPMailer</h1><p>Email envoyé le ' . date('Y-m-d H:i:s') . '</p><p>Si vous recevez cet email, la configuration fonctionne !</p>';
    $mail->AltBody = 'Test PHPMailer - ' . date('Y-m-d H:i:s');

    $mail->send();
    echo "<p class='success'>✅ Email envoyé avec succès ! Vérifiez votre boîte mail.</p>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur d'envoi : " . $mail->ErrorInfo . "</p>";

    // Diagnostic spécifique
    echo "<h3>🔧 Solutions recommandées :</h3>";
    echo "<div style='background:#fff3cd;padding:15px;border-radius:5px;margin:10px 0;'>";

    if (strpos($mail->ErrorInfo, 'authentication failed') !== false) {
        echo "<h4>Problème d'authentification Gmail :</h4>";
        echo "<ol>";
        echo "<li>Allez sur <a href='https://myaccount.google.com/security' target='_blank'>Google Account Security</a></li>";
        echo "<li>Activez la <strong>vérification en 2 étapes</strong></li>";
        echo "<li>Générez un <strong>mot de passe d'application</strong> : Sécurité → Mots de passe d'application</li>";
        echo "<li>Remplacez le mot de passe SMTP par ce nouveau mot de passe (16 caractères)</li>";
        echo "</ol>";
    }

    echo "<h4>Alternatives SMTP :</h4>";
    echo "<ul>";
    echo "<li><strong>SendGrid</strong> : Service SMTP gratuit pour les petits volumes</li>";
    echo "<li><strong>Mailgun</strong> : Alternative gratuite</li>";
    echo "<li><strong>Brevo (ex Sendinblue)</strong> : Interface française</li>";
    echo "<li><strong>OVH Mail</strong> : Si vous avez un hébergement OVH</li>";
    echo "</ul>";

    echo "<h4>Configuration alternative recommandée :</h4>";
    echo "<pre>
// Dans includes/config.php, remplacez par :
define('SMTP_HOST', 'smtp-relay.sendinblue.com'); // ou autre service
define('SMTP_USER', 'votre_email@domaine.com');
define('SMTP_PASS', 'votre_mot_de_passe_api');
define('SMTP_PORT', 587);
    </pre>";

    echo "</div>";
}

// 5. Test de la fonction envoyerEmailConfirmationPaiement
echo "<h2>🎯 Test de la fonction envoyerEmailConfirmationPaiement</h2>";

// Simuler des données de commande
$commande_test = [
    'id_commande' => 999,
    'numero_commande' => 'TEST-001',
    'montant_total' => 50000,
    'nom' => 'Test',
    'prenom' => 'Client',
    'email' => 'abrahamchimi513@gmail.com',
    'telephone' => '000000000',
    'adresse' => 'Test Address',
    'date_paiement' => date('Y-m-d H:i:s')
];

$paiement_test = [
    'mode_paiement' => 'Mobile Money',
    'reference_paiement' => 'TEST-REF-123',
    'date_paiement' => date('Y-m-d H:i:s'),
    'montant' => 50000
];

$result = envoyerEmailConfirmationPaiement($commande_test, $paiement_test);

if ($result) {
    echo "<p class='success'>✅ Fonction envoyerEmailConfirmationPaiement : SUCCÈS</p>";
} else {
    echo "<p class='error'>❌ Fonction envoyerEmailConfirmationPaiement : ÉCHEC</p>";
}

echo "<hr>";
echo "<p><strong>📝 Note :</strong> Si les tests échouent, vérifiez les logs d'erreur dans le dossier logs/ ou les logs PHP.</p>";
?>