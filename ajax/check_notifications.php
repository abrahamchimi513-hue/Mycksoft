<?php
require_once '../includes/config.php';
require_once '../classes/Notification.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['has_new' => false, 'count' => 0]);
    exit;
}

$notification = new Notification($pdo, getUserId());
$count = $notification->countUnread();
$last_check = $_SESSION['last_notification_check'] ?? null;

// Vérifier s'il y a des nouvelles depuis le dernier check
$has_new = false;
if ($last_check) {
    $sql = "SELECT COUNT(*) FROM notifications 
            WHERE id_utilisateur = ? AND date_creation > ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([getUserId(), $last_check]);
    $has_new = $stmt->fetchColumn() > 0;
}

$_SESSION['last_notification_check'] = date('Y-m-d H:i:s');

echo json_encode([
    'has_new' => $has_new,
    'count' => $count
]);