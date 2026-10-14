<?php
// chat/profil.php - Profil utilisateur pour le chat
require_once __DIR__ . '/../includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ../connexion.php');
    exit;
}

$user_id = getUserId();
$page_title = "Mon Profil Chat - " . SITE_NAME;

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Récupérer les statistiques de chat
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_messages,
        COUNT(DISTINCT id_expediteur) + COUNT(DISTINCT id_destinataire) - 1 as total_conversations,
        MAX(date_envoi) as dernier_message
    FROM message 
    WHERE id_expediteur = ? OR id_destinataire = ?
");
$stmt->execute([$user_id, $user_id]);
$chat_stats = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<style>
:root {
    --primary-color: #6c5ce7;
    --secondary-color: #a8a4e6;
    --dark-color: #2d3436;
    --light-color: #f5f6fa;
    --success-color: #00b894;
    --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.chat-profile-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 20px;
}

.profile-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px -10px rgba(0,0,0,0.2);
}

.profile-header {
    background: var(--gradient);
    padding: 3rem 2rem;
    text-align: center;
    color: white;
    position: relative;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 1.5rem;
    border: 4px solid white;
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #2d3d48, #1e2c34);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: bold;
}

.profile-name {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.profile-email {
    opacity: 0.9;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.profile-badge {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.5rem 1.5rem;
    background: rgba(255,255,255,0.2);
    border-radius: 30px;
    font-size: 0.9rem;
    backdrop-filter: blur(5px);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    padding: 2rem;
    background: #f8fafc;
}

.stat-item {
    text-align: center;
    padding: 1.5rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.stat-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 1rem;
    background: var(--gradient);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 0.3rem;
}

.stat-label {
    color: #64748b;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.profile-body {
    padding: 2rem;
}

.info-group {
    margin-bottom: 1.5rem;
}

.info-label {
    font-size: 0.9rem;
    color: #64748b;
    margin-bottom: 0.3rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.info-value {
    font-size: 1.1rem;
    color: var(--dark-color);
    font-weight: 500;
    padding: 0.8rem;
    background: #f8fafc;
    border-radius: 10px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0.3rem 1rem;
    border-radius: 30px;
    font-size: 0.9rem;
    font-weight: 500;
}

.status-online {
    background: #d1fae5;
    color: #065f46;
}

.status-offline {
    background: #fee2e2;
    color: #991b1b;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.status-online .status-dot {
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16,185,129,0.2);
    animation: pulse 2s infinite;
}

.status-offline .status-dot {
    background: #ef4444;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
    70% { box-shadow: 0 0 0 10px rgba(16,185,129,0); }
    100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
}

.profile-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn {
    flex: 1;
    padding: 1rem;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: var(--gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px -5px rgba(108,92,231,0.4);
}

.btn-outline {
    background: white;
    color: var(--dark-color);
    border: 1px solid #e2e8f0;
}

.btn-outline:hover {
    background: #f8fafc;
    border-color: var(--primary-color);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-actions {
        flex-direction: column;
    }
}
</style>

<div class="chat-profile-container">
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($user['photo_profil'])): ?>
                    <img src="../<?php echo htmlspecialchars($user['photo_profil']); ?>" alt="Avatar">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?php echo strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-name">
                <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>
            </div>
            
            <div class="profile-email">
                <i class="fas fa-envelope"></i>
                <?php echo htmlspecialchars($user['email']); ?>
            </div>
            
            <?php
            // Vérifier le statut en ligne
            $stmt = $pdo->prepare("SELECT est_en_ligne FROM utilisateur_connexion WHERE id_utilisateur = ?");
            $stmt->execute([$user_id]);
            $connexion = $stmt->fetch();
            $is_online = $connexion && $connexion['est_en_ligne'];
            ?>
            
            <div class="profile-badge">
                <span class="status-badge <?php echo $is_online ? 'status-online' : 'status-offline'; ?>">
                    <span class="status-dot"></span>
                    <?php echo $is_online ? 'En ligne' : 'Hors ligne'; ?>
                </span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-value"><?php echo $chat_stats['total_messages'] ?? 0; ?></div>
                <div class="stat-label">Messages</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $chat_stats['total_conversations'] ?? 0; ?></div>
                <div class="stat-label">Conversations</div>
            </div>
            
            <div class="stat-item">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value">
                    <?php 
                    if ($chat_stats['dernier_message']) {
                        $diff = time() - strtotime($chat_stats['dernier_message']);
                        if ($diff < 3600) {
                            echo floor($diff / 60) . ' min';
                        } elseif ($diff < 86400) {
                            echo floor($diff / 3600) . ' h';
                        } else {
                            echo floor($diff / 86400) . ' j';
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </div>
                <div class="stat-label">Dernière activité</div>
            </div>
        </div>
        
        <div class="profile-body">
            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle" style="color: var(--primary-color);"></i>
                Informations personnelles
            </h3>
            
            <div class="info-group">
                <div class="info-label">
                    <i class="fas fa-user"></i>
                    Nom complet
                </div>
                <div class="info-value">
                    <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>
                </div>
            </div>
            
            <div class="info-group">
                <div class="info-label">
                    <i class="fas fa-phone"></i>
                    Téléphone
                </div>
                <div class="info-value">
                    <?php echo !empty($user['telephone']) ? htmlspecialchars($user['telephone']) : 'Non renseigné'; ?>
                </div>
            </div>
            
            <div class="info-group">
                <div class="info-label">
                    <i class="fas fa-map-marker-alt"></i>
                    Localisation
                </div>
                <div class="info-value">
                    <?php 
                    if (!empty($user['ville'])) {
                        echo htmlspecialchars($user['ville']);
                        if (!empty($user['pays'])) echo ', ' . htmlspecialchars($user['pays']);
                    } else {
                        echo 'Non renseignée';
                    }
                    ?>
                </div>
            </div>
            
            <div class="info-group">
                <div class="info-label">
                    <i class="fas fa-calendar"></i>
                    Membre depuis
                </div>
                <div class="info-value">
                    <?php echo date('d F Y', strtotime($user['date_inscription'])); ?>
                </div>
            </div>
            
            <div class="profile-actions">
                <a href="chat.php" class="btn btn-primary">
                    <i class="fas fa-comment"></i>
                    Retour au chat
                </a>
                <a href="../pages/profile.php" class="btn btn-outline">
                    <i class="fas fa-user-cog"></i>
                    Profil complet
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>