<?php
// test_mail.php - Test d'envoi d'email simple
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test d'envoi d'email</h1>";

$to = "abrahamchimi513@gmail.com"; // Votre email
$subject = "Test Mycksoft - " . date('H:i:s');
$message = "<h1>Test réussi !</h1><p>Email envoyé le " . date('Y-m-d H:i:s') . "</p>";
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: Mycksoft <abrahamchimi513@gmail.com>\r\n";

echo "<p>Envoi à: " . $to . "</p>";

$result = mail($to, $subject, $message, $headers);

if ($result) {
    echo "<p style='color:green;font-weight:bold'>✅ Email envoyé avec succès !</p>";
} else {
    echo "<p style='color:red;font-weight:bold'>❌ Échec de l'envoi</p>";
    echo "<p>Vérifiez la configuration SMTP dans php.ini</p>";
}

// Afficher la configuration mail
echo "<h3>Configuration mail() de PHP:</h3>";
echo "<pre>";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "sendmail_from: " . ini_get('sendmail_from') . "\n";
echo "</pre>";
?>