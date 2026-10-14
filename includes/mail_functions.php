<?php
// includes/mail_functions.php

// Charger la configuration (qui contient sendEmailSMTP)
require_once __DIR__ . '/config.php';

// Vérifier si PHPMailer est installé via Composer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Sinon, inclure manuellement PHPMailer
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Vérifier que les constantes SMTP sont définies
if (!defined('SMTP_HOST')) {
    // Configuration SMTP par défaut (à modifier selon votre serveur)
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_USER', 'abrahamchimi513@gmail.com');
    define('SMTP_PASS', 'qilwxotvjgbrdqwk');
    define('SMTP_PORT', 587);
    define('SMTP_FROM_EMAIL', 'noreply@mycksoft.cm');
    define('SMTP_FROM_NAME', 'Mycksoft Informatique');
    define('SMTP_REPLY_TO', 'contact@mycksoft.cm');
}

/**
 * Envoyer la facture par email
 */
function envoyerEmailFacture($commande, $lignes_commande, $livraison, $paiement) {
    // Vérifier les paramètres requis
    if (empty($commande) || empty($lignes_commande)) {
        error_log("Erreur: Paramètres manquants pour envoyerEmailFacture");
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Configuration supplémentaire pour éviter les erreurs
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $email_destinataire = isset($commande['email']) ? $commande['email'] : (isset($commande['email_client']) ? $commande['email_client'] : '');
        $nom_destinataire = (isset($commande['prenom']) ? $commande['prenom'] : '') . ' ' . (isset($commande['nom']) ? $commande['nom'] : 'Client');
        
        if (empty($email_destinataire)) {
            error_log("Erreur: Email du destinataire manquant");
            return false;
        }
        
        $mail->addAddress($email_destinataire, $nom_destinataire);
        $mail->addReplyTo(SMTP_REPLY_TO, SMTP_FROM_NAME);
        
        // Contenu
        $mail->isHTML(true);
        $numero_commande = isset($commande['numero_commande']) ? $commande['numero_commande'] : (isset($commande['id_commande']) ? $commande['id_commande'] : '00000');
        $mail->Subject = 'Facture de votre commande #' . $numero_commande . ' - ' . (defined('SITE_NAME') ? SITE_NAME : 'Mycksoft');
        
        // Générer la facture HTML
        $factureHTML = genererFactureHTML($commande, $lignes_commande, $livraison, $paiement);
        
        $mail->Body = $factureHTML;
        
        // Version texte alternative
        $montant_total = isset($commande['montant_total']) ? $commande['montant_total'] : 0;
        $prenom_client = isset($commande['prenom']) ? $commande['prenom'] : 'Client';
        $date_paiement = isset($paiement['date_paiement']) ? $paiement['date_paiement'] : date('Y-m-d H:i:s');
        
        $mail->AltBody = "Bonjour " . $prenom_client . ",\n\n";
        $mail->AltBody .= "Merci pour votre commande #" . $numero_commande . ".\n\n";
        $mail->AltBody .= "Montant total : " . number_format($montant_total, 0, ',', ' ') . " FCFA\n";
        $mail->AltBody .= "Date de paiement : " . date('d/m/Y H:i', strtotime($date_paiement)) . "\n\n";
        $mail->AltBody .= "Vous trouverez le détail de votre commande ci-dessous.\n\n";
        $mail->AltBody .= "Cordialement,\n" . (defined('SITE_NAME') ? SITE_NAME : 'Mycksoft');
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email facture: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envoyer la confirmation de paiement par email
 */
function envoyerEmailConfirmationPaiement($commande, $paiement) {
    error_log("envoyerEmailConfirmationPaiement: Début");
    
    // Vérifier les paramètres requis
    if (empty($commande) || empty($paiement)) {
        error_log("Erreur: Paramètres manquants pour envoyerEmailConfirmationPaiement");
        return false;
    }
    
    // Récupérer l'email du destinataire
    $email_destinataire = isset($commande['email']) ? $commande['email'] : (isset($commande['email_client']) ? $commande['email_client'] : '');
    
    if (empty($email_destinataire)) {
        error_log("Erreur: Email du destinataire manquant pour confirmation");
        return false;
    }
    
    // Préparer les données
    $prenom_client = isset($commande['prenom']) ? $commande['prenom'] : 'Client';
    $nom_client = isset($commande['nom']) ? $commande['nom'] : '';
    $numero_commande = isset($commande['numero_commande']) ? $commande['numero_commande'] : (isset($commande['id_commande']) ? $commande['id_commande'] : '00000');
    $montant_total = isset($commande['montant_total']) ? $commande['montant_total'] : 0;
    $date_paiement = isset($paiement['date_paiement']) ? $paiement['date_paiement'] : date('Y-m-d H:i:s');
    $reference_paiement = isset($paiement['reference_paiement']) ? $paiement['reference_paiement'] : 'N/A';
    $id_commande = isset($commande['id_commande']) ? $commande['id_commande'] : 0;
    
    $sujet = 'Confirmation de paiement - Commande #' . $numero_commande;
    
    // Construire le corps HTML
    $corps_html = "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .info-box { background: #f7fafc; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
            .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; transition: background 0.3s; }
            .button:hover { background: #5a67d8; }
            .footer { background: #f7fafc; padding: 20px; text-align: center; font-size: 12px; color: #718096; border-top: 1px solid #e2e8f0; }
            .success-icon { font-size: 48px; margin-bottom: 20px; }
            h1 { margin: 0; font-size: 24px; }
            h2 { color: #2d3748; margin-top: 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='success-icon'>✅</div>
                <h1>Paiement Confirmé !</h1>
            </div>
            <div class='content'>
                <h2>Bonjour " . htmlspecialchars($prenom_client) . ",</h2>
                <p>Nous avons bien reçu votre paiement pour la commande <strong>#" . htmlspecialchars($numero_commande) . "</strong>.</p>
                
                <div class='info-box'>
                    <p><strong>💵 Montant payé :</strong> " . number_format($montant_total, 0, ',', ' ') . " FCFA</p>
                    <p><strong>📅 Date du paiement :</strong> " . date('d/m/Y H:i', strtotime($date_paiement)) . "</p>
                    <p><strong>💳 Mode de paiement :</strong> Mobile Money</p>
                    <p><strong>🔑 Référence :</strong> " . htmlspecialchars($reference_paiement) . "</p>
                </div>
                
                <p>Votre commande est maintenant en cours de préparation. Vous recevrez un email dès son expédition.</p>
                
                <div style='text-align: center;'>
                    <a href='" . (defined('SITE_URL') ? SITE_URL : 'http://localhost/Mycksoft') . "/commande_confirmation.php?id=" . $id_commande . "' class='button'>📦 Voir ma commande</a>
                </div>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " " . (defined('SITE_NAME') ? SITE_NAME : 'Mycksoft') . ". Tous droits réservés.</p>
                <p>Questions ? Répondez à cet email.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Utiliser la fonction sendEmailSMTP() qui gère le basculement mail/SMTP
    error_log("envoyerEmailConfirmationPaiement: Appel de sendEmailSMTP()");
    $resultat = sendEmailSMTP($email_destinataire, $sujet, $corps_html, true);
    
    if ($resultat) {
        error_log("envoyerEmailConfirmationPaiement: Email envoyé avec succès à $email_destinataire");
    } else {
        error_log("envoyerEmailConfirmationPaiement: Échec d'envoi à $email_destinataire");
    }
    
    return $resultat;
}

/**
 * Fonction générique pour envoyer un email
 */
function envoyerEmail($destinataire, $sujet, $message_html, $message_texte = '') {
    if (empty($destinataire) || empty($sujet) || empty($message_html)) {
        return false;
    }
    
    return sendEmailSMTP($destinataire, $sujet, $message_html, true);
}

/**
 * Fonction temporaire pour générer la facture HTML (à remplacer par votre vrai template)
 */
function genererFactureHTML($commande, $lignes_commande, $livraison, $paiement) {
    $numero_commande = isset($commande['numero_commande']) ? $commande['numero_commande'] : (isset($commande['id_commande']) ? $commande['id_commande'] : '00000');
    $montant_total = isset($commande['montant_total']) ? $commande['montant_total'] : 0;
    $date_commande = isset($commande['date_commande']) ? $commande['date_commande'] : date('Y-m-d H:i:s');
    
    $html = "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; }
            .facture { max-width: 800px; margin: 0 auto; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; }
            .details { margin: 20px 0; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background: #f4f4f4; }
            .total { text-align: right; margin-top: 20px; font-size: 18px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='facture'>
            <div class='header'>
                <h2>" . (defined('SITE_NAME') ? SITE_NAME : 'Mycksoft') . "</h2>
                <p>Facture #" . htmlspecialchars($numero_commande) . "</p>
            </div>
            
            <div class='details'>
                <p><strong>Date :</strong> " . date('d/m/Y H:i', strtotime($date_commande)) . "</p>
                <p><strong>Client :</strong> " . htmlspecialchars(isset($commande['prenom']) ? $commande['prenom'] . ' ' . $commande['nom'] : 'Client') . "</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Quantité</th>
                        <th>Prix unitaire</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>";
    
    foreach ($lignes_commande as $ligne) {
        $nom_produit = isset($ligne['nom_produit']) ? $ligne['nom_produit'] : (isset($ligne['produit_nom']) ? $ligne['produit_nom'] : 'Produit');
        $quantite = isset($ligne['quantite']) ? $ligne['quantite'] : 1;
        $prix_unitaire = isset($ligne['prix_unitaire']) ? $ligne['prix_unitaire'] : 0;
        $total_ligne = $quantite * $prix_unitaire;
        
        $html .= "
                    <tr>
                        <td>" . htmlspecialchars($nom_produit) . "</td>
                        <td>" . $quantite . "</td>
                        <td>" . number_format($prix_unitaire, 0, ',', ' ') . " FCFA</td>
                        <td>" . number_format($total_ligne, 0, ',', ' ') . " FCFA</td>
                    </tr>";
    }
    
    $html .= "
                </tbody>
            </table>
            
            <div class='total'>
                <p>Total à payer : " . number_format($montant_total, 0, ',', ' ') . " FCFA</p>
            </div>
            
            <div class='footer' style='margin-top: 30px; font-size: 12px; color: #666;'>
                <p>Merci de votre confiance !</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}
?>