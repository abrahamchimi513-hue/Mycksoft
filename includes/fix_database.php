<?php
// fix_database.php
require_once 'includes/config.php';

try {
    // Correction de la table utilisateur
    $pdo->exec("ALTER TABLE `utilisateur` MODIFY `id_utilisateur` int(11) NOT NULL AUTO_INCREMENT");
    
    // Vérification
    $stmt = $pdo->query("SHOW CREATE TABLE `utilisateur`");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Structure de la table utilisateur corrigée avec succès!<br>";
    echo "<pre>" . htmlspecialchars($result['Create Table']) . "</pre>";
    
} catch (PDOException $e) {
    echo "Erreur lors de la correction : " . $e->getMessage();
}
?>