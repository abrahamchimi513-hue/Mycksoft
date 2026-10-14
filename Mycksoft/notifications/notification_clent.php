<?php
// notifications_client.php
require_once '../includes/config.php';
require_once '../classes/Notification.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

//$notification = new Notification($pdo);
//$user_id = getUserId();

// Marquer comme lu (si appelé depuis un bouton)
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification->markAsRead($_GET['mark_read'], $user_id);
    header("Location: notifications_client.php");
    exit;
}

// Marquer tout comme lu
if (isset($_GET['mark_all_read'])) {
    $notification->markAllAsRead($user_id);
    header("Location: notifications_client.php");
    exit;
}

$unread_count = $notification->countUnread($user_id);
$notifications = $notification->getUserNotifications($user_id, 50); // Récupère les 50 dernières
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes notifications - Mycksoft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #2d3d48;
            --primary-dark: #1e2c34;
            --success: #10b981;
            --danger: #ce1126;
            --warning: #f59e0b;
            --info: #4895ef;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 1rem;
        }

        body {
            background: var(--gray-100);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border: 1px solid var(--gray-200);
        }

        .header h1 {
            color: var(--gray-800);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header h1 i {
            color: var(--primary);
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Boutons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: white;
            border: 2px solid var(--gray-200);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #0d9488;
            transform: translateY(-1px);
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary { background: var(--primary); }
        .stat-icon.success { background: var(--success); }
        .stat-icon.warning { background: var(--warning); }

        .stat-content h3 {
            font-size: 1.8rem;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .stat-content p {
            color: var(--gray-500);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filtres */
        .filters {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1.25rem;
            border-radius: 2rem;
            border: 2px solid var(--gray-200);
            background: white;
            color: var(--gray-600);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Liste des notifications */
        .notification-list {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            transition: all 0.2s;
            position: relative;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: var(--gray-50);
        }

        .notification-item.unread {
            background: #f0f9ff;
            border-left: 4px solid var(--primary);
        }

        .notification-item.unread:hover {
            background: #e6f7ff;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .notification-icon.success { background: linear-gradient(135deg, var(--success), #0d9488); }
        .notification-icon.danger { background: linear-gradient(135deg, var(--danger), #b91c1c); }
        .notification-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }
        .notification-icon.info { background: linear-gradient(135deg, var(--info), #2563eb); }
        .notification-icon.system { background: linear-gradient(135deg, #6b7280, #4b5563); }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }

        .notification-title {
            font-weight: 700;
            color: var(--gray-800);
            font-size: 1.1rem;
        }

        .notification-badge {
            background: var(--danger);
            color: white;
            padding: 0.2rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .notification-message {
            color: var(--gray-600);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 0.75rem;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .notification-time {
            color: var(--gray-400);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .notification-type {
            color: var(--gray-500);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            text-transform: capitalize;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .action-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-1px);
        }

        .action-btn.danger:hover {
            background: var(--danger);
            border-color: var(--danger);
        }

        /* État vide */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--gray-700);
            margin-bottom: 0.75rem;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: var(--gray-500);
            max-width: 400px;
            margin: 0 auto;
        }

        /* Toast de notification */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 4px solid var(--primary);
            animation: slideIn 0.3s ease;
            z-index: 1000;
        }

        .toast.success { border-left-color: var(--success); }
        .toast.error { border-left-color: var(--danger); }
        .toast.warning { border-left-color: var(--warning); }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Loader */
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 1rem; }
            
            .notification-item {
                flex-direction: column;
            }
            
            .notification-actions {
                align-self: flex-end;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .header-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-bell"></i>
                Mes notifications
                <?php if ($unread_count > 0): ?>
                    <span style="background: var(--danger); color: white; padding: 0.25rem 0.75rem; border-radius: 2rem; font-size: 0.9rem; margin-left: 0.5rem;">
                        <?php echo $unread_count; ?> nouvelle(s)
                    </span>
                <?php endif; ?>
            </h1>
            
            <div class="header-actions">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="btn btn-success" onclick="return confirm('Marquer toutes les notifications comme lues ?')">
                        <i class="fas fa-check-double"></i>
                        Tout marquer comme lu
                    </a>
                <?php endif; ?>
                
                <a href="javascript:history.back()" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Retour
                </a>
            </div>
        </div>

        <!-- Statistiques -->
        <?php if (!empty($notifications)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($notifications); ?></h3>
                    <p>Total</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $unread_count; ?></h3>
                    <p>Non lues</p>
                </div>
            </div>
            
            <?php
            // Compter les notifications du jour
            $today_count = 0;
            foreach ($notifications as $n) {
                if (date('Y-m-d') === date('Y-m-d', strtotime($n['date_notification']))) {
                    $today_count++;
                }
            }
            ?>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $today_count; ?></h3>
                    <p>Aujourd'hui</p>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters">
            <button class="filter-btn active" onclick="filterNotifications('all')">
                <i class="fas fa-list"></i>
                Toutes
            </button>
            <button class="filter-btn" onclick="filterNotifications('unread')">
                <i class="fas fa-envelope"></i>
                Non lues (<?php echo $unread_count; ?>)
            </button>
            <button class="filter-btn" onclick="filterNotifications('today')">
                <i class="fas fa-calendar-day"></i>
                Aujourd'hui
            </button>
        </div>
        <?php endif; ?>

        <!-- Liste des notifications -->
        <div class="notification-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>Aucune notification</h3>
                    <p>Vous n'avez pas encore de notifications. Les communications de l'administrateur apparaîtront ici.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): 
                    $is_unread = $notif['est_lue'] == 0;
                    $icon_class = [
                        'success' => 'fa-check-circle',
                        'danger' => 'fa-exclamation-triangle',
                        'warning' => 'fa-exclamation-circle',
                        'info' => 'fa-info-circle',
                        'system' => 'fa-cog'
                    ][$notif['type']] ?? 'fa-bell';
                    
                    $bg_class = [
                        'success' => 'success',
                        'danger' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        'system' => 'system'
                    ][$notif['type']] ?? 'info';
                    
                    $type_label = [
                        'success' => 'Succès',
                        'danger' => 'Urgent',
                        'warning' => 'Avertissement',
                        'info' => 'Information',
                        'system' => 'Système'
                    ][$notif['type']] ?? 'Information';
                ?>
                    <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>" 
                         data-id="<?php echo $notif['id_notification']; ?>"
                         data-status="<?php echo $is_unread ? 'unread' : 'read'; ?>"
                         data-date="<?php echo date('Y-m-d', strtotime($notif['date_notification'])); ?>">
                        
                        <!-- Icône -->
                        <div class="notification-icon <?php echo $bg_class; ?>">
                            <i class="fas <?php echo $icon_class; ?>"></i>
                        </div>
                        
                        <!-- Contenu -->
                        <div class="notification-content">
                            <div class="notification-header">
                                <span class="notification-title">
                                    <?php echo htmlspecialchars($notif['titre']); ?>
                                </span>
                                <?php if ($is_unread): ?>
                                    <span class="notification-badge">Nouveau</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notification-message">
                                <?php echo nl2br(htmlspecialchars($notif['message'])); ?>
                            </div>
                            
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <i class="far fa-clock"></i>
                                    <?php 
                                    $date = new DateTime($notif['date_notification']);
                                    $now = new DateTime();
                                    $diff = $now->diff($date);
                                    
                                    if ($diff->days == 0) {
                                        if ($diff->h == 0) {
                                            echo $diff->i > 0 ? "Il y a {$diff->i} minutes" : "À l'instant";
                                        } else {
                                            echo "Il y a {$diff->h} heures";
                                        }
                                    } elseif ($diff->days == 1) {
                                        echo "Hier à " . $date->format('H:i');
                                    } else {
                                        echo $date->format('d/m/Y H:i');
                                    }
                                    ?>
                                </span>
                                
                                <span class="notification-type">
                                    <i class="fas fa-tag"></i>
                                    <?php echo $type_label; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="notification-actions">
                            <?php if ($is_unread): ?>
                                <a href="?mark_read=<?php echo $notif['id_notification']; ?>" 
                                   class="action-btn" 
                                   title="Marquer comme lu"
                                   onclick="event.preventDefault(); markAsRead(<?php echo $notif['id_notification']; ?>);">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($notif['lien'])): ?>
                                <a href="<?php echo htmlspecialchars($notif['lien']); ?>" 
                                   class="action-btn" 
                                   title="Voir le détail">
                                    <i class="fas fa-eye"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Zone pour les toasts -->
    <div id="toastContainer"></div>

    <script>
        // Configuration
        const AJAX_URL = '../ajax/';
        
        // Fonction pour marquer comme lu
        function markAsRead(notificationId) {
            showLoader();
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', AJAX_URL + 'mark_notification_read.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                hideLoader();
                if (xhr.status >= 200 && xhr.status < 400) {
                    showToast('Notification marquée comme lue', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showToast('Erreur lors du marquage', 'error');
                }
            };
            
            xhr.onerror = function() {
                hideLoader();
                showToast('Erreur de connexion', 'error');
            };
            
            xhr.send('notification_id=' + notificationId);
        }
        
        // Fonction pour filtrer les notifications
        function filterNotifications(filter) {
            const items = document.querySelectorAll('.notification-item');
            const today = new Date().toISOString().split('T')[0];
            
            // Mettre à jour les boutons actifs
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filtrer
            items.forEach(item => {
                const status = item.dataset.status;
                const date = item.dataset.date;
                let show = true;
                
                switch(filter) {
                    case 'unread':
                        show = status === 'unread';
                        break;
                    case 'today':
                        show = date === today;
                        break;
                    default:
                        show = true;
                }
                
                item.style.display = show ? 'flex' : 'none';
            });
        }
        
        // Fonction pour afficher un toast
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            if (type === 'warning') icon = 'fa-exclamation-triangle';
            
            toast.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideIn 0.3s reverse';
                setTimeout(() => {
                    if (toast.parentNode) toast.remove();
                }, 280);
            }, 3000);
        }
        
        // Loader
        function showLoader() {
            const loader = document.createElement('div');
            loader.id = 'globalLoader';
            loader.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255,255,255,0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            `;
            loader.innerHTML = '<div class="loader" style="width: 50px; height: 50px;"></div>';
            document.body.appendChild(loader);
        }
        
        function hideLoader() {
            const loader = document.getElementById('globalLoader');
            if (loader) loader.remove();
        }
        
        // Vérifier les nouvelles notifications toutes les 30 secondes
        function checkNewNotifications() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', AJAX_URL + 'check_new_notifications.php', true);
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.has_new && response.count > 0) {
                        showToast(`${response.count} nouvelle(s) notification(s)`, 'info');
                        // Recharger après un délai
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    }
                }
            };
            
            xhr.send();
        }
        
        // Démarrer la vérification périodique
        setInterval(checkNewNotifications, 30000);
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter les écouteurs pour les boutons de filtre
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    filterNotifications(this.dataset.filter || 'all');
                });
            });
        });
    </script>
</body>
</html>