<?php
// notifications.php - Version corrigée
require_once 'includes/config.php';
// require_once 'classes/Notification.php'; // DÉCOMMENTÉ et corrigé

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Initialisation de la classe Notification
// $notification = new Notification($pdo, getUserId());
$user_id = getUserId();

// Traitement des actions
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification->markAsRead($_GET['mark_read']);
    header("Location: notifications.php");
    exit;
}

if (isset($_GET['mark_all_read'])) {
    $notification->markAllAsRead();
    header("Location: notifications.php");
    exit;
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification->delete($_GET['delete']);
    header("Location: notifications.php");
    exit;
}

// Récupération des données
// $notifications = $notification->getUserNotifications($user_id, 50);
// $unread_count = $notification->countUnread($user_id);

// Calcul des notifications du jour
$today_count = 0;
// foreach ($notifications as $n) {
    // if (date('Y-m-d') == date('Y-m-d', strtotime($n['date_creation']))) {
        // $today_count++;
    // }
// }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes notifications - Mycksoft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f6f9; }
        .header {
            background: #2d3d48; color: white; padding: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .logo { font-size: 24px; font-weight: bold; }
        .logo i { margin-right: 10px; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .card {
            background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px; overflow: hidden;
        }
        .card-header {
            padding: 20px; border-bottom: 1px solid #eee;
            display: flex; justify-content: space-between; align-items: center;
        }
        .btn {
            padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
            font-size: 14px; transition: all 0.3s;
        }
        .btn-primary { background: #2d3d48; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ce1126; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .notification-item {
            display: flex; align-items: flex-start; gap: 15px; padding: 20px;
            border-bottom: 1px solid #eee; transition: all 0.3s;
        }
        .notification-item:hover { background: #f9f9f9; }
        .notification-item.unread { background: #e3f2fd; }
        .notification-icon {
            width: 50px; height: 50px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 20px;
        }
        .icon-success { background: #10b981; }
        .icon-danger { background: #ce1126; }
        .icon-warning { background: #f59e0b; }
        .icon-info { background: #2d3d48; }
        .notification-content { flex: 1; }
        .notification-title {
            font-size: 18px; font-weight: 600; color: #333;
            display: flex; align-items: center; gap: 10px;
        }
        .badge {
            background: #ce1126; color: white; padding: 3px 10px;
            border-radius: 20px; font-size: 12px;
        }
        .notification-message {
            color: #666; margin: 10px 0; line-height: 1.6;
        }
        .notification-time {
            color: #999; font-size: 13px; display: flex; align-items: center; gap: 5px;
        }
        .notification-actions { display: flex; gap: 10px; }
        .empty-state {
            text-align: center; padding: 60px; color: #999;
        }
        .empty-state i { font-size: 80px; margin-bottom: 20px; }
        .stats {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white; padding: 20px; border-radius: 10px;
            text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-number { font-size: 32px; font-weight: bold; color: #2d3d48; }
        .stat-label { color: #666; margin-top: 5px; }
        .back-link {
            display: inline-block; margin-bottom: 20px; color: #2d3d48;
            text-decoration: none; font-size: 16px;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <i class="fas fa-bell"></i> Mycksoft - Centre de notifications
        </div>
        <div>
            <a href="index.php" class="btn btn-primary" style="background: #1e2c34;">
                <i class="fas fa-home"></i> Accueil
            </a>
            <a href="logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </div>
    
    <div class="container">
        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        
        <div class="stats">
            <div class="stat-card">
                <!-- <div class="stat-number"><?php echo count($notifications); ?></div> -->
                <div class="stat-label">Total notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #f59e0b;"><?php echo $unread_count; ?></div>
                <div class="stat-label">Non lues</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #10b981;">
                    <?php echo $today_count; ?>
                </div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Liste des notifications</h2>
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="btn btn-success" onclick="return confirm('Marquer toutes les notifications comme lues ?')">
                        <i class="fas fa-check-double"></i> Tout marquer comme lu
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="card-body" style="padding: 0;">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>Aucune notification</h3>
                        <p>Vous n'avez pas encore de notifications.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): 
                        $unread = $n['est_lue'] == 0;
                        
                        // Déterminer l'icône selon le type
                        $icon_class = 'fa-bell';
                        $icon_bg = 'icon-info';
                        
                        switch($n['type']) {
                            case 'success':
                                $icon_class = 'fa-check-circle';
                                $icon_bg = 'icon-success';
                                break;
                            case 'danger':
                                $icon_class = 'fa-exclamation-triangle';
                                $icon_bg = 'icon-danger';
                                break;
                            case 'warning':
                                $icon_class = 'fa-exclamation-circle';
                                $icon_bg = 'icon-warning';
                                break;
                            case 'info':
                                $icon_class = 'fa-info-circle';
                                $icon_bg = 'icon-info';
                                break;
                            case 'system':
                                $icon_class = 'fa-cog';
                                $icon_bg = 'icon-info';
                                break;
                        }
                    ?>
                        <div class="notification-item <?php echo $unread ? 'unread' : ''; ?>">
                            <div class="notification-icon <?php echo $icon_bg; ?>">
                                <i class="fas <?php echo $icon_class; ?>"></i>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($n['titre']); ?>
                                    <?php if ($unread): ?>
                                        <span class="badge">Nouveau</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="notification-message">
                                    <?php echo nl2br(htmlspecialchars($n['message'])); ?>
                                </div>
                                
                                <div class="notification-time">
                                    <i class="far fa-clock"></i>
                                    <?php echo date('d/m/Y à H:i', strtotime($n['date_creation'])); ?>
                                </div>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if ($unread): ?>
                                    <a href="?mark_read=<?php echo $n['id_notification']; ?>" class="btn btn-success" style="padding: 8px 15px;">
                                        <i class="fas fa-check"></i> Marquer lu
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($n['lien'])): ?>
                                    <a href="<?php echo htmlspecialchars($n['lien']); ?>" class="btn btn-primary" style="padding: 8px 15px;">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                <?php endif; ?>
                                
                                <a href="?delete=<?php echo $n['id_notification']; ?>" class="btn btn-danger" style="padding: 8px 15px;" 
                                   onclick="return confirm('Supprimer cette notification ?')">
                                    <i class="fas fa-trash"></i> Supprimer
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>