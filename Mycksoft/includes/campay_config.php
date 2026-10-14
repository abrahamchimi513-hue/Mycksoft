<?php
// includes/campay_config.php - Configuration Campay

// Configuration de Campay (à remplacer par vos vraies informations)
define('CAMPAY_APP_USERNAME', 'fZ6vQfZCjecWthWUntKvQfr7fe_W-DYEEj6_xVEUGBlYk572CobrKOhzHooiHK1Iy00BS-1tfWWkwk_9EOyp1A');
define('CAMPAY_APP_PASSWORD', 'Gftkr9s4QcFuRTstyld_Yxhi7TXG6imjE9w3OUvxEyp5ismRTeBGTVLctOSb8t12RZ5NT-R9Ioy_M7s1jOUkhQ');
define('CAMPAY_API_URL', 'https://demo.campay.net/api'); // Utilisez https://campay.net/api pour la production

// Fonction pour obtenir le token d'accès Campay
function getCampayToken() {
    $ch = curl_init(CAMPAY_API_URL . '/token/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'username' => CAMPAY_APP_USERNAME,
        'password' => CAMPAY_APP_PASSWORD
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['token'] ?? null;
    }
    
    return null;
}

// Fonction pour initier un paiement
function initierPaiementCampay($montant, $telephone, $description, $reference_externe) {
    $token = getCampayToken();
    
    if (!$token) {
        return ['success' => false, 'message' => 'Impossible d\'obtenir le token d\'authentification'];
    }
    
    $ch = curl_init(CAMPAY_API_URL . '/collect/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'amount' => $montant,
        'from' => $telephone,
        'description' => $description,
        'external_reference' => $reference_externe
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 201 || $http_code === 200) {
        return [
            'success' => true,
            'reference' => $result['reference'] ?? null,
            'status' => $result['status'] ?? 'PENDING',
            'message' => 'Paiement initié avec succès'
        ];
    } else {
        return [
            'success' => false,
            'message' => $result['message'] ?? 'Erreur lors du paiement',
            'details' => $result
        ];
    }
}

// Fonction pour vérifier le statut d'un paiement
function verifierStatutPaiement($reference) {
    $token = getCampayToken();
    
    if (!$token) {
        return ['success' => false, 'message' => 'Impossible d\'obtenir le token d\'authentification'];
    }
    
    $ch = curl_init(CAMPAY_API_URL . '/transaction/' . $reference . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Token ' . $token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200) {
        return [
            'success' => true,
            'status' => $result['status'] ?? 'UNKNOWN',
            'amount' => $result['amount'] ?? 0,
            'reference' => $result['reference'] ?? $reference,
            'external_reference' => $result['external_reference'] ?? null
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Impossible de vérifier le statut du paiement'
        ];
    }
}
?>