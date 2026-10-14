// admin/js/notifications.js
$(document).ready(function() {
    // Vérifier les nouvelles notifications toutes les 30 secondes
    setInterval(checkNewNotifications, 30000);
    
    // Marquer une notification comme lue
    $(document).on('click', '.mark-notification-read', function() {
        const notifId = $(this).data('id');
        markNotificationAsRead(notifId);
    });
    
    // Marquer tout comme lu
    $('#markAllRead').click(function() {
        markAllNotificationsAsRead();
    });
});

function checkNewNotifications() {
    $.ajax({
        url: '../ajax/get_notifications.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.unread_count > 0) {
                // Mettre à jour le badge
                updateNotificationBadge(response.unread_count);
                
                // Afficher une notification toast si nouveau message
                if (response.notifications.length > 0) {
                    showNotificationToast(response.notifications[0]);
                }
            }
        }
    });
}

function updateNotificationBadge(count) {
    const $badge = $('#notificationBadge');
    if (count > 0) {
        if ($badge.length) {
            $badge.text(count > 9 ? '9+' : count).show();
        } else {
            $('.notification-icon').append(
                '<span id="notificationBadge" class="badge bg-danger">' + 
                (count > 9 ? '9+' : count) + 
                '</span>'
            );
        }
    } else {
        $badge.remove();
    }
}

function markNotificationAsRead(notifId) {
    $.ajax({
        url: 'notifications.php',
        method: 'POST',
        data: {
            mark_read: notifId,
            ajax: true
        },
        success: function() {
            checkNewNotifications();
        }
    });
}

function markAllNotificationsAsRead() {
    $.ajax({
        url: 'notifications.php',
        method: 'POST',
        data: {
            mark_all_read: true,
            ajax: true
        },
        success: function() {
            $('.notification-item').removeClass('unread');
            updateNotificationBadge(0);
        }
    });
}

function showNotificationToast(notification) {
    // Utiliser Bootstrap Toast ou une autre librairie
    const toast = `
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast show" role="alert">
                <div class="toast-header bg-${notification.type} text-white">
                    <strong>${notification.titre}</strong>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${notification.message}
                    <br>
                    <small>${notification.time_ago}</small>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(toast);
    setTimeout(() => $('.toast:last').remove(), 5000);
}