<!-- Dans le header admin, après la navbar -->
<?php
// $notification = new Notification($pdo);
$unread_count = $notification->countUnread(getUserId());
$recent_notifications = $notification->getUnreadNotifications(getUserId());
?>

<!-- Menu déroulant des notifications -->
<li class="nav-item dropdown">
    <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" 
       data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?= $unread_count > 9 ? '9+' : $unread_count ?>
            </span>
        <?php endif; ?>
    </a>
    
    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown" 
        style="width: 300px; max-height: 400px; overflow-y: auto;">
        
        <li>
            <h6 class="dropdown-header">
                Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger ms-2"><?= $unread_count ?> non lue(s)</span>
                <?php endif; ?>
            </h6>
        </li>
        
        <?php if (empty($recent_notifications)): ?>
            <li>
                <span class="dropdown-item-text text-muted text-center py-3">
                    <i class="bi bi-bell-slash d-block mb-2" style="font-size: 1.5rem;"></i>
                    Aucune notification
                </span>
            </li>
        <?php else: ?>
            <?php foreach (array_slice($recent_notifications, 0, 5) as $notif): 
                $type_class = [
                    'success' => 'success',
                    'danger' => 'danger',
                    'warning' => 'warning',
                    'info' => 'info'
                ][$notif['type']] ?? 'info';
            ?>
                <li>
                    <a class="dropdown-item <?= $notif['est_lue'] ? '' : 'fw-bold' ?>" 
                       href="<?= $notif['lien'] ?? '#' ?>">
                        <div class="d-flex">
                            <div class="me-2">
                                <i class="bi bi-<?= $notif['type'] == 'success' ? 'check-circle' : 
                                    ($notif['type'] == 'danger' ? 'exclamation-triangle' : 
                                    ($notif['type'] == 'warning' ? 'exclamation-circle' : 'info-circle')) ?> 
                                    text-<?= $type_class ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <small class="text-muted d-block">
                                    <?= htmlspecialchars($notif['titre']) ?>
                                </small>
                                <small class="text-secondary">
                                    <?= substr(htmlspecialchars($notif['message']), 0, 50) ?>...
                                </small>
                                <small class="text-muted d-block">
                                    <?= date('H:i', strtotime($notif['date_notification'])) ?>
                                </small>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
            
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item text-center" href="notifications.php">
                    Voir toutes les notifications
                </a>
            </li>
        <?php endif; ?>
    </ul>
</li>

<!-- Ajouter ce CSS -->
<style>
.notification-dropdown {
    max-height: 400px;
    overflow-y: auto;
}
.notification-dropdown .dropdown-item {
    white-space: normal;
    border-bottom: 1px solid #eee;
}
.notification-dropdown .dropdown-item:last-child {
    border-bottom: none;
}
</style>