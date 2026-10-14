<?php
require_once '../includes/config.php';
require_once '../classes/Notification.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$notification = new Notification($pdo, getUserId());

switch ($action) {
    case 'mark_read':
        $id = $_POST['notification_id'] ?? 0;
        $result = $notification->markAsRead($id);
        echo json_encode(['success' => $result]);
        break;
        
    case 'mark_all_read':
        $result = $notification->markAllAsRead();
        echo json_encode(['success' => $result]);
        break;
        
    case 'delete':
        $id = $_POST['notification_id'] ?? 0;
        $result = $notification->delete($id);
        echo json_encode(['success' => $result]);
        break;
        
    case 'get_count':
        $count = $notification->countUnread();
        echo json_encode(['count' => $count]);
        break;
        
    case 'get_preview':
        $notifications = $notification->getUnreadNotifications();
        $count = $notification->countUnread();
        
        ob_start();
        if (empty($notifications)): ?>
            <div style="padding: 20px; text-align: center; color: #666;">
                <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 10px;"></i>
                <p>Aucune nouvelle notification</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div style="padding: 10px; border-bottom: 1px solid #eee; display: flex; gap: 10px;">
                    <div style="width: 30px; height: 30px; border-radius: 5px; 
                        background: <?php echo $n['type'] == 'success' ? '#10b981' : ($n['type'] == 'danger' ? '#ce1126' : ($n['type'] == 'warning' ? '#f59e0b' : '#2d3d48')); ?>; 
                        display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-<?php echo $n['type'] == 'success' ? 'check-circle' : ($n['type'] == 'danger' ? 'exclamation-triangle' : ($n['type'] == 'warning' ? 'exclamation-circle' : 'info-circle')); ?>"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: bold;"><?php echo htmlspecialchars($n['titre']); ?></div>
                        <div style="font-size: 12px; color: #666;"><?php echo substr(htmlspecialchars($n['message']), 0, 50); ?>...</div>
                        <div style="font-size: 10px; color: #999;"><?php echo date('H:i', strtotime($n['date_creation'])); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif;
        
        $html = ob_get_clean();
        echo json_encode(['success' => true, 'html' => $html, 'count' => $count]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}