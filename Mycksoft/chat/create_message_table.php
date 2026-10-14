<?php
// create_message_table.php
require_once 'includes/config.php';

try {
    // Requête pour créer la table message
    $sql = "CREATE TABLE IF NOT EXISTS message (
        id_message INT AUTO_INCREMENT PRIMARY KEY,
        id_expediteur INT NOT NULL,
        id_destinataire INT NOT NULL,
        message TEXT NOT NULL,
        type_message ENUM('text', 'image', 'file') DEFAULT 'text',
        date_envoi DATETIME NOT NULL,
        lu TINYINT(1) DEFAULT 0,
        FOREIGN KEY (id_expediteur) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE,
        FOREIGN KEY (id_destinataire) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE,
        INDEX idx_conversation (id_expediteur, id_destinataire),
        INDEX idx_date (date_envoi)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "✅ Table 'message' créée avec succès !";
    
    // Vérifier que la table a bien été créée
    $check = $pdo->query("SHOW TABLES LIKE 'message'");
    if ($check->rowCount() > 0) {
        echo "<br>✅ La table 'message' est maintenant disponible.";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>