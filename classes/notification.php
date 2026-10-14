<?php
// classes/Notification.php
class Notification {
    private $pdo;
    private $user_id;
    
    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
    }
    
    // public function getUserNotifications($user_id, $limit = 50) {
        // $stmt = $this->pdo->prepare("
            // SELECT * FROM notifications 
            // WHERE id_utilisateur = ? 
            // ORDER BY date_creation DESC 
            // LIMIT ?
        // ");
        // $stmt->execute([$user_id, $limit]);
        // return $stmt->fetchAll();
    // }
    
    public function countUnread($user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE id_utilisateur = ? AND est_lue = 0
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }
    
    public function markAsRead($id) {
        $stmt = $this->pdo->prepare("
            UPDATE notifications SET est_lue = 1 
            WHERE id_notification = ? AND id_utilisateur = ?
        ");
        return $stmt->execute([$id, $this->user_id]);
    }
    
    public function markAllAsRead() {
        $stmt = $this->pdo->prepare("
            UPDATE notifications SET est_lue = 1 
            WHERE id_utilisateur = ? AND est_lue = 0
        ");
        return $stmt->execute([$this->user_id]);
    }
    
    public function delete($id) {
        $stmt = $this->pdo->prepare("
            DELETE FROM notifications 
            WHERE id_notification = ? AND id_utilisateur = ?
        ");
        return $stmt->execute([$id, $this->user_id]);
    }
    
    // ========== NOUVELLES MÉTHODES POUR ENVOYER DES NOTIFICATIONS ==========
    
    /**
     * Envoyer une notification à un utilisateur spécifique
     */
    public function sendToUser($user_id, $titre, $message, $type = 'info', $lien = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (id_utilisateur, titre, message, type, lien, date_creation, est_lue)
            VALUES (?, ?, ?, ?, ?, NOW(), 0)
        ");
        return $stmt->execute([$user_id, $titre, $message, $type, $lien]);
    }
    
    /**
     * Envoyer une notification à tous les clients (rôle = client)
     */
    public function sendToClients($titre, $message, $type = 'info', $lien = null) {
        $stmt = $this->pdo->prepare("
            SELECT id_utilisateur FROM utilisateur WHERE role = 'client' AND est_actif = 1
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        $success = true;
        foreach ($users as $user) {
            $result = $this->sendToUser($user['id_utilisateur'], $titre, $message, $type, $lien);
            if (!$result) $success = false;
        }
        return $success;
    }
    
    /**
     * Envoyer une notification à tous les utilisateurs actifs
     */
    public function sendToAllUsers($titre, $message, $type = 'info', $lien = null) {
        $stmt = $this->pdo->prepare("
            SELECT id_utilisateur FROM utilisateur WHERE est_actif = 1
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        $success = true;
        foreach ($users as $user) {
            $result = $this->sendToUser($user['id_utilisateur'], $titre, $message, $type, $lien);
            if (!$result) $success = false;
        }
        return $success;
    }
}
?>