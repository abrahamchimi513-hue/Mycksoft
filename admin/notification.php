<?php
// admin/notifications.php
require_once '../includes/config.php';
require_once 'notifications/notification.php';

// Vérifier l'accès (admin ou moderateur)
if (!isLoggedIn() || !in_array($_SESSION['role'], ['admin', 'moderateur'])) {
    header("Location: ../login.php");
    exit;
}

//$notification = new Notification($pdo);
$user_id = getUserId();

// Marquer comme lu
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification->markAsRead($_GET['mark_read'], $user_id);
    header("Location: notifications.php" . (isset($_GET['page']) ? "?page=" . $_GET['page'] : ""));
    exit;
}

// Marquer tout comme lu
if (isset($_GET['mark_all_read'])) {
    $notification->markAllAsRead($user_id);
    header("Location: notifications.php" . (isset($_GET['page']) ? "?page=" . $_GET['page'] : ""));
    exit;
}

// Supprimer une notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification->delete($_GET['delete'], $user_id);
    header("Location: notifications.php" . (isset($_GET['page']) ? "?page=" . $_GET['page'] : ""));
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$notifications = $notification->getUserNotifications($user_id, $limit, $offset);
$unread_count = $notification->countUnread($user_id);

// Compter total pour pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$total_notifications = $stmt->fetchColumn();
$total_pages = ceil($total_notifications / $limit);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .notification-item {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .notification-item.unread {
            border-left-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .notification-item.unread .notification-title {
            font-weight: 600;
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .notification-icon.success { background: linear-gradient(135deg, #198754, #0b5e42); }
        .notification-icon.danger { background: linear-gradient(135deg, #dc3545, #a71d2a); }
        .notification-icon.warning { background: linear-gradient(135deg, #ffc107, #d39e00); }
        .notification-icon.info { background: linear-gradient(135deg, #0dcaf0, #0a9ec2); }
        .notification-icon.secondary { background: linear-gradient(135deg, #6c757d, #4a535b); }
        .filter-btn.active {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .notification-time {
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-bell"></i> Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo $unread_count; ?> non lue(s)</span>
                        <?php endif; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($unread_count > 0): ?>
                            <a href="?mark_all_read=1<?php echo $page > 1 ? '&page='.$page : ''; ?>" 
                               class="btn btn-sm btn-outline-success me-2" 
                               onclick="return confirm('Marquer toutes les notifications comme lues ?')">
                                <i class="bi bi-check-all"></i> Tout marquer comme lu
                            </a>
                        <?php endif; ?>
                        <a href="?refresh=1<?php echo $page > 1 ? '&page='.$page : ''; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-repeat"></i> Actualiser
                        </a>
                    </div>
                </div>
                
                <!-- Filtres rapides -->
                <div class="btn-group mb-3">
                    <button class="btn btn-outline-secondary btn-sm filter-btn active" data-filter="all">
                        Toutes (<?php echo $total_notifications; ?>)
                    </button>
                    <button class="btn btn-outline-secondary btn-sm filter-btn" data-filter="unread">
                        Non lues (<?php echo $unread_count; ?>)
                    </button>
                </div>
                
                <!-- Liste des notifications -->
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bell-slash" style="font-size: 3rem; color: #6c757d;"></i>
                                <h5 class="mt-3">Aucune notification</h5>
                                <p class="text-muted">Vous n'avez pas encore de notifications.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush" id="notificationsList">
                                <?php foreach ($notifications as $notif): 
                                    $is_unread = $notif['est_lue'] == 0;
                                    $type_class = [
                                        'success' => 'success',
                                        'danger' => 'danger',
                                        'warning' => 'warning',
                                        'info' => 'info',
                                        'system' => 'secondary'
                                    ][$notif['type']] ?? 'info';
                                    
                                    $icon = [
                                        'success' => 'check-circle',
                                        'danger' => 'exclamation-triangle',
                                        'warning' => 'exclamation-circle',
                                        'info' => 'info-circle',
                                        'system' => 'gear'
                                    ][$notif['type']] ?? 'bell';
                                ?>
                                    <div class="list-group-item list-group-item-action notification-item <?php echo $is_unread ? 'unread' : ''; ?>" 
                                         data-status="<?php echo $is_unread ? 'unread' : 'read'; ?>">
                                        <div class="d-flex w-100 justify-content-between align-items-start">
                                            <div class="d-flex align-items-start">
                                                <div class="notification-icon <?php echo $type_class; ?> me-3">
                                                    <i class="bi bi-<?php echo $icon; ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center">
                                                        <h6 class="mb-1 notification-title">
                                                            <?php echo htmlspecialchars($notif['titre']); ?>
                                                        </h6>
                                                        <?php if ($is_unread): ?>
                                                            <span class="badge bg-primary ms-2">Nouveau</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                                    <small class="text-muted notification-time">
                                                        <i class="bi bi-clock"></i> 
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
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="btn-group ms-3">
                                                <?php if (!empty($notif['lien'])): ?>
                                                    <a href="<?php echo $notif['lien']; ?>" class="btn btn-sm btn-outline-primary" title="Voir">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($is_unread): ?>
                                                    <a href="?mark_read=<?php echo $notif['id_notification']; ?><?php echo $page > 1 ? '&page='.$page : ''; ?>" 
                                                       class="btn btn-sm btn-outline-success" 
                                                       title="Marquer comme lu">
                                                        <i class="bi bi-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="?delete=<?php echo $notif['id_notification']; ?><?php echo $page > 1 ? '&page='.$page : ''; ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('Supprimer cette notification ?')"
                                                   title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>">Précédent</a>
                        </li>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor;
                        
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'">'.$total_pages.'</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>">Suivant</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <!-- Légende -->
                <div class="card mt-4 bg-light">
                    <div class="card-body">
                        <div class="d-flex gap-4">
                            <div><i class="bi bi-check-circle-fill text-success"></i> Succès</div>
                            <div><i class="bi bi-exclamation-triangle-fill text-danger"></i> Urgent</div>
                            <div><i class="bi bi-exclamation-circle-fill text-warning"></i> Avertissement</div>
                            <div><i class="bi bi-info-circle-fill text-info"></i> Information</div>
                            <div><i class="bi bi-gear-fill text-secondary"></i> Système</div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filtrage des notifications
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const items = document.querySelectorAll('.notification-item');
                
                items.forEach(item => {
                    if (filter === 'all') {
                        item.style.display = 'block';
                    } else if (filter === 'unread') {
                        item.style.display = item.dataset.status === 'unread' ? 'block' : 'none';
                    }
                });
            });
        });
        
        // Auto-refresh toutes les 30 secondes (optionnel)
        // setInterval(function() {
        //     location.reload();
        // }, 30000);
        
        // Notification toast pour les nouvelles notifications
        function checkNewNotifications() {
            fetch('ajax/check_new_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.has_new && data.count > 0) {
                        // Créer un toast Bootstrap
                        const toast = document.createElement('div');
                        toast.className = 'position-fixed bottom-0 end-0 p-3';
                        toast.style.zIndex = '5';
                        toast.innerHTML = `
                            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                                <div class="toast-header bg-primary text-white">
                                    <i class="bi bi-bell-fill me-2"></i>
                                    <strong class="me-auto">Nouvelles notifications</strong>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                                </div>
                                <div class="toast-body">
                                    Vous avez ${data.count} nouvelle(s) notification(s).
                                    <div class="mt-2 pt-2 border-top">
                                        <a href="notifications.php" class="btn btn-primary btn-sm">Voir</a>
                                    </div>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(toast);
                        
                        setTimeout(() => {
                            toast.remove();
                        }, 5000);
                    }
                });
        }
        
        // Vérifier les nouvelles notifications toutes les 30 secondes
        setInterval(checkNewNotifications, 30000);
    </script>
</body>
</html>