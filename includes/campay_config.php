<?php
// includes/campay_config.php - Configuration de l'API Campay

// Configuration Campay (à remplacer par vos identifiants réels)
define('CAMPAY_APP_USERNAME', 'fZ6vQfZCjecWthWUntKvQfr7fe_W-DYEEj6_xVEUGBlYk572CobrKOhzHooiHK1Iy00BS-1tfWWkwk_9EOyp1A');
define('CAMPAY_APP_PASSWORD', 'Gftkr9s4QcFuRTstyld_Yxhi7TXG6imjE9w3OUvxEyp5ismRTeBGTVLctOSb8t12RZ5NT-R9Ioy_M7s1jOUkhQ');
define('CAMPAY_API_URL', 'https://demo.campay.net/api'); // Utiliser 'https://campay.net/api' en production

/**
 * Fonction pour obtenir le token d'accès Campay
 */
function getCampayToken() {
    $ch = curl_init(CAMPAY_API_URL . '/token/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'username' => CAMPAY_APP_USERNAME,
        'password' => CAMPAY_APP_PASSWORD
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // À mettre true en production

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Campay Token CURL Error: " . $curlError);
        return [
            'success' => false,
            'message' => 'Erreur de connexion à Campay: ' . $curlError
        ];
    }

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'token' => $data['token'],
            'expires' => time() + 86400 // 24 heures
        ];
    }

    error_log("Campay Token HTTP Error: " . $httpCode . " Response: " . $response);
    return [
        'success' => false,
        'message' => 'Erreur d\'authentification Campay (Code: ' . $httpCode . ')'
    ];
}

/**
 * Fonction pour initier un paiement Campay
 */
function initierPaiementCampay($montant, $telephone, $description, $reference_externe) {
    // Obtenir le token
    $tokenData = getCampayToken();
    if (!$tokenData['success']) {
        return [
            'success' => false,
            'message' => 'Impossible d\'obtenir le token d\'authentification: ' . $tokenData['message']
        ];
    }

    // Nettoyer le numéro de téléphone
    $telephone = preg_replace('/[^0-9]/', '', $telephone);
    if (strlen($telephone) === 9) {
        $telephone = '237' . $telephone;
    }

    // Préparer la requête de paiement
    $ch = curl_init(CAMPAY_API_URL . '/collect/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $tokenData['token'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'amount' => $montant,
        'currency' => 'XAF',
        'from' => $telephone,
        'description' => $description,
        'external_reference' => $reference_externe
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // À mettre true en production

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Journaliser pour débogage
    error_log("Campay API Response: " . $response . " HTTP Code: " . $httpCode);

    if ($curlError) {
        return [
            'success' => false,
            'message' => 'Erreur de communication avec Campay: ' . $curlError
        ];
    }

    if ($httpCode === 201 || $httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'reference' => $data['reference'] ?? $data['transaction_reference'] ?? null,
            'message' => 'Paiement initié avec succès',
            'data' => $data
        ];
    }

    $data = json_decode($response, true);
    return [
        'success' => false,
        'message' => $data['message'] ?? 'Erreur lors de l\'initiation du paiement (Code: ' . $httpCode . ')'
    ];
}

/**
 * Fonction pour vérifier le statut d'un paiement
 */
function verifierStatutPaiementCampay($reference) {
    // Obtenir le token
    $tokenData = getCampayToken();
    if (!$tokenData['success']) {
        return [
            'success' => false,
            'message' => 'Impossible d\'obtenir le token d\'authentification'
        ];
    }

    $ch = curl_init(CAMPAY_API_URL . '/transaction/' . $reference . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $tokenData['token'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'statut' => $data['status'] ?? 'unknown',
            'data' => $data
        ];
    }

    return [
        'success' => false,
        'message' => 'Impossible de vérifier le statut du paiement'
    ];
}