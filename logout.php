<?php
require_once 'includes/config.php';

// Détruire la session
session_destroy();

// Rediriger vers la page d'accueil
$_SESSION['flash_message'] = "Vous avez été déconnecté avec succès";
$_SESSION['flash_type'] = 'success';

redirect('index.php');
?>