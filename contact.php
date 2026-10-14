<?php
// contact.php - Page de contact
require_once 'includes/config.php';

$page_title = "Contactez-nous - " . SITE_NAME;
$header_show_products_link = true;

// Récupérer les catégories pour le header
$categories = $pdo->query("SELECT * FROM categorie ORDER BY nom_categorie")->fetchAll();
$header_categories = $categories;

// Traitement du formulaire de contact
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($nom) || empty($email) || empty($sujet) || empty($message)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } elseif (strlen($message) < 10) {
        $error = 'Le message doit contenir au moins 10 caractères.';
    } else {
        // Sauvegarder le message dans la base de données (si vous avez une table)
        try {
            // Option 1: Sauvegarder en base de données
            // $stmt = $pdo->prepare("INSERT INTO contacts (nom, email, telephone, sujet, message, date_envoi) VALUES (?, ?, ?, ?, ?, NOW())");
            // $stmt->execute([$nom, $email, $telephone, $sujet, $message]);
            
            // Option 2: Envoyer un email (à configurer selon votre serveur)
            $to = "contact@mycksoft.com"; // Remplacez par votre email
            $headers = "From: " . $email . "\r\n";
            $headers .= "Reply-To: " . $email . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $email_content = "
            <html>
            <head>
                <title>Nouveau message de contact</title>
            </head>
            <body>
                <h2>Nouveau message de contact - Mycksoft</h2>
                <p><strong>Nom:</strong> {$nom}</p>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Téléphone:</strong> " . ($telephone ?: 'Non fourni') . "</p>
                <p><strong>Sujet:</strong> {$sujet}</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br($message) . "</p>
                <hr>
                <p><em>Message envoyé depuis le formulaire de contact du site Mycksoft</em></p>
            </body>
            </html>
            ";
            
            // mail($to, "Contact Mycksoft: " . $sujet, $email_content, $headers); // Décommentez pour activer l'envoi d'email
            
            $success = 'Votre message a été envoyé avec succès ! Notre équipe vous répondra dans les plus brefs délais.';
            
            // Redirection pour éviter le renvoi du formulaire
            // header('Location: contact.php?success=1');
            // exit;
            
        } catch (Exception $e) {
            $error = 'Une erreur est survenue. Veuillez réessayer plus tard.';
        }
    }
}

include 'includes/header.php';
?>

<!-- Hero Section de la page contact -->
<section style="background: linear-gradient(135deg, #2d3d48 0%, #005c46 100%); color: white; padding: 60px 0; text-align: center;">
    <div class="container">
        <h1 style="font-size: 42px; margin-bottom: 15px;">Contactez Mycksoft</h1>
        <p style="font-size: 18px; max-width: 600px; margin: 0 auto; opacity: 0.9;">
            Votre partenaire technologique de confiance à Douala - Akwa
        </p>
    </div>
</section>

<!-- Section Contact -->
<section style="padding: 80px 0;">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px;">
            <!-- Informations de contact -->
            <div>
                <h2 style="font-size: 28px; color: #333; margin-bottom: 30px;">Parlons de votre projet</h2>
                
                <div style="margin-bottom: 40px;">
                    <p style="color: #666; line-height: 1.8; margin-bottom: 20px;">
                        Que vous ayez besoin d'aide pour choisir un produit, une question sur votre commande, 
                        ou simplement envie d'en savoir plus sur nos services, notre équipe est à votre écoute.
                    </p>
                </div>
                
                <!-- Coordonnées avec les vraies informations -->
                <div style="margin-bottom: 40px;">
                    <h3 style="font-size: 20px; color: #333; margin-bottom: 20px;">Nos coordonnées</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                            <div style="width: 40px; height: 40px; background: #2d3d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 5px 0; color: #333;">Adresse</h4>
                                <p style="color: #666; margin: 0;">Immeuble TECNO, Akwa</p>
                                <p style="color: #666; margin: 5px 0 0;">Douala - Cameroun</p>
                                <p style="color: #666; margin: 5px 0 0;"><small>Face au Cinéma Abbia</small></p>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                            <div style="width: 40px; height: 40px; background: #2d3d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 5px 0; color: #333;">Téléphone / WhatsApp</h4>
                                <p style="color: #666; margin: 0;">(+237) 697 176 943</p>
                                <p style="color: #666; margin: 5px 0 0;">(+237) 645 306 079</p>
                                <span style="background: #25D366; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block; margin-top: 5px;">
                                    <i class="fab fa-whatsapp"></i> WhatsApp disponible
                                </span>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                            <div style="width: 40px; height: 40px; background: #2d3d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 5px 0; color: #333;">Email</h4>
                                <p style="color: #666; margin: 0;">contact@mycksoft.com</p>
                                <p style="color: #666; margin: 5px 0 0;">support@mycksoft.com</p>
                                <p style="color: #666; margin: 5px 0 0;">www.mycksoft.com</p>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                            <div style="width: 40px; height: 40px; background: #2d3d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 5px 0; color: #333;">Horaires d'ouverture</h4>
                                <p style="color: #666; margin: 0;">Lundi - Vendredi: 8h30 - 16h30</p>
                                <p style="color: #666; margin: 5px 0 0;">Samedi: 9h - 16h</p>
                                <p style="color: #666; margin: 5px 0 0;">Dimanche: Fermé</p>
                                <p style="color: #2d3d48; margin: 5px 0 0; font-size: 13px;">
                                    <i class="fas fa-motorcycle"></i> Livraison disponible 7j/7
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Réseaux sociaux -->
                <div>
                    <h3 style="font-size: 20px; color: #333; margin-bottom: 20px;">Suivez-nous</h3>
                    <div style="display: flex; gap: 15px;">
                        <a href="#" style="width: 45px; height: 45px; background: #2d3d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: transform 0.3s;"
                           onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" style="width: 45px; height: 45px; background: #2d3d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: transform 0.3s;"
                           onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" style="width: 45px; height: 45px; background: #2d3d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: transform 0.3s;"
                           onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" style="width: 45px; height: 45px; background: #2d3d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: transform 0.3s;"
                           onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" style="width: 45px; height: 45px; background: #25D366; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: transform 0.3s;"
                           onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Formulaire de contact -->
            <div>
                <div style="background: white; border-radius: 15px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <h3 style="font-size: 24px; color: #333; margin-bottom: 30px;">Envoyez-nous un message</h3>
                    
                    <?php if ($success): ?>
                        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                    Nom complet <span style="color: #e74c3c;">*</span>
                                </label>
                                <input type="text" name="nom" required 
                                       value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                            </div>
                            
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                    Téléphone
                                </label>
                                <input type="tel" name="telephone" 
                                       value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>"
                                       placeholder="6XX XXX XXX"
                                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                Email <span style="color: #e74c3c;">*</span>
                            </label>
                            <input type="email" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                Sujet <span style="color: #e74c3c;">*</span>
                            </label>
                            <select name="sujet" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                                <option value="">Sélectionnez un sujet</option>
                                <option value="question-produit" <?php echo ($_POST['sujet'] ?? '') == 'question-produit' ? 'selected' : ''; ?>>Question sur un produit</option>
                                <option value="commande" <?php echo ($_POST['sujet'] ?? '') == 'commande' ? 'selected' : ''; ?>>Suivi de commande</option>
                                <option value="livraison" <?php echo ($_POST['sujet'] ?? '') == 'livraison' ? 'selected' : ''; ?>>Livraison à Douala/Akwa</option>
                                <option value="paiement" <?php echo ($_POST['sujet'] ?? '') == 'paiement' ? 'selected' : ''; ?>>Paiement</option>
                                <option value="retour" <?php echo ($_POST['sujet'] ?? '') == 'retour' ? 'selected' : ''; ?>>Retour / Remboursement</option>
                                <option value="partenariat" <?php echo ($_POST['sujet'] ?? '') == 'partenariat' ? 'selected' : ''; ?>>Partenariat</option>
                                <option value="autre" <?php echo ($_POST['sujet'] ?? '') == 'autre' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 25px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                Message <span style="color: #e74c3c;">*</span>
                            </label>
                            <textarea name="message" rows="6" required minlength="10"
                                      placeholder="Décrivez votre demande en quelques mots..."
                                      style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            <div style="text-align: right; color: #666; font-size: 14px; margin-top: 5px;">
                                <span id="charCount">0</span>/500 caractères minimum
                            </div>
                        </div>
                        
                        <button type="submit" 
                                style="width: 100%; background: linear-gradient(135deg, #2d3d48, #005c46); color: white; padding: 15px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fas fa-paper-plane"></i>
                            Envoyer le message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section Carte avec localisation exacte -->
<section style="padding: 0 0 80px;">
    <div class="container">
        <div style="border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3979.397014648563!2d9.69988931426583!3d4.048483647011288!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1061121c5b8b8b8b%3A0x8b8b8b8b8b8b8b8b!2sAkwa%2C%20Douala%2C%20Cameroun!5e0!3m2!1sfr!2scm!4v1234567890" 
                width="100%" 
                height="450" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy">
            </iframe>
        </div>
        <p style="text-align: center; margin-top: 10px; color: #666;">
            <i class="fas fa-map-pin"></i> Immeuble TECNO, Akwa - Douala (Face au Cinéma Abbia)
        </p>
    </div>
</section>

<!-- Section FAQ rapide -->
<section style="padding: 60px 0; background: #f8f9fa;">
    <div class="container">
        <div style="text-align: center; margin-bottom: 50px;">
            <h2 style="font-size: 32px; color: #333; margin-bottom: 15px;">Questions fréquentes</h2>
            <p style="color: #666;">Avant de nous contacter, consultez notre FAQ</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
            <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <i class="fas fa-truck" style="font-size: 30px; color: #2d3d48; margin-bottom: 15px;"></i>
                <h3 style="margin-bottom: 10px;">Livraison à Douala/Akwa ?</h3>
                <p style="color: #666;">Livraison express en 2-4h dans Douala. Gratuite à partir de 50 000 FCFA.</p>
            </div>
            
            <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <i class="fas fa-store" style="font-size: 30px; color: #2d3d48; margin-bottom: 15px;"></i>
                <h3 style="margin-bottom: 10px;">Retrait en magasin ?</h3>
                <p style="color: #666;">Possible à l'Immeuble TECNO, Akwa. Disponible immédiatement après commande.</p>
            </div>
            
            <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <i class="fas fa-credit-card" style="font-size: 30px; color: #2d3d48; margin-bottom: 15px;"></i>
                <h3 style="margin-bottom: 10px;">Paiement sur place ?</h3>
                <p style="color: #666;">Paiement en espèces accepté à notre boutique d'Akwa.</p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="faq.php" style="color: #2d3d48; font-weight: 600; text-decoration: none;">
                Voir toutes les questions <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- Section visite guidée -->
<section style="padding: 60px 0;">
    <div class="container">
        <div style="background: linear-gradient(135deg, #2d3d48, #005c46); border-radius: 15px; padding: 50px; color: white; text-align: center;">
            <i class="fas fa-map-signs" style="font-size: 50px; margin-bottom: 20px;"></i>
            <h2 style="font-size: 28px; margin-bottom: 15px;">Comment nous trouver ?</h2>
            <p style="max-width: 600px; margin: 0 auto 30px; opacity: 0.9;">
                Nous sommes situés à l'Immeuble TECNO à Akwa, un emplacement central et facilement accessible.
                Repérez l'enseigne Mycksoft au rez-de-chaussée.
            </p>
            <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-taxi"></i>
                    <span>Taxi : Arrêt "Akwa"</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-bus"></i>
                    <span>Bus : Ligne 5, 12, 20</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-car"></i>
                    <span>Parking disponible</span>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Compteur de caractères
const messageTextarea = document.querySelector('textarea[name="message"]');
const charCount = document.getElementById('charCount');

if (messageTextarea && charCount) {
    messageTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length;
        
        if (length >= 500) {
            charCount.style.color = '#e74c3c';
        } else if (length >= 400) {
            charCount.style.color = '#f39c12';
        } else {
            charCount.style.color = '#666';
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>