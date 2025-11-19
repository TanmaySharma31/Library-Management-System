<?php
// Get notifications for the logged-in student
if(!isset($_SESSION['stdid'])) {
    exit('Not logged in');
}

$studentId = $_SESSION['stdid'];

// Get unread notification count
$countSql = "SELECT COUNT(*) as unread_count FROM tblnotifications WHERE student_id = :student_id AND is_read = 0";
$countQuery = $dbh->prepare($countSql);
$countQuery->bindParam(':student_id', $studentId, PDO::PARAM_STR);
$countQuery->execute();
$countResult = $countQuery->fetch(PDO::FETCH_OBJ);
$unreadCount = $countResult->unread_count;

// Get all notifications (last 30 days)
$sql = "SELECT 
            n.*,
            b.BookName,
            DATE_FORMAT(n.created_at, '%b %d, %Y %h:%i %p') as formatted_date
        FROM tblnotifications n
        LEFT JOIN tblbooks b ON n.book_id = b.id
        WHERE n.student_id = :student_id
        AND n.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY n.created_at DESC
        LIMIT 50";

$query = $dbh->prepare($sql);
$query->bindParam(':student_id', $studentId, PDO::PARAM_STR);
$query->execute();
$notifications = $query->fetchAll(PDO::FETCH_OBJ);
?>

<style>
.notification-bell {
    position: relative;
    cursor: pointer;
    font-size: 28px;
    color: #333 !important;
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 50%;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 45px;
    height: 45px;
    border: 2px solid #ddd;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.notification-bell:hover {
    background: #e9ecef;
    transform: scale(1.1);
    border-color: #9170E4;
    box-shadow: 0 3px 8px rgba(145, 112, 228, 0.3);
}

.notification-badge {
    position: absolute;
    top: 0px;
    right: 0px;
    background: #ff0000;
    color: white;
    border-radius: 50%;
    padding: 3px 7px;
    font-size: 11px;
    font-weight: bold;
    min-width: 22px;
    text-align: center;
    border: 2px solid #333;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}

.notification-dropdown {
    position: absolute;
    top: 50px;
    right: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    width: 400px;
    max-height: 500px;
    overflow-y: auto;
    z-index: 9999;
    display: none;
}

.notification-dropdown.show {
    display: block;
}

.notification-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    font-weight: bold;
    color: #333;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-item {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background 0.2s;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item.unread {
    background: #e3f2fd;
}

.notification-item.unread:hover {
    background: #bbdefb;
}

.notification-icon {
    display: inline-block;
    margin-right: 10px;
    font-size: 18px;
}

.notification-message {
    color: #333;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 5px;
}

.notification-time {
    color: #999;
    font-size: 12px;
}

.notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #999;
}

.mark-all-read {
    color: #2196F3;
    font-size: 13px;
    cursor: pointer;
    font-weight: normal;
}

.mark-all-read:hover {
    text-decoration: underline;
}

.notification-due-0 { border-left: 4px solid #f44336; }
.notification-due-1 { border-left: 4px solid #ff9800; }
.notification-due-2 { border-left: 4px solid #ffc107; }
.notification-overdue { border-left: 4px solid #d32f2f; }

/* Pulse animation for notification bell with unread messages */
@keyframes bellPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.notification-bell.has-unread {
    animation: bellPulse 2s ease-in-out infinite;
}

.notification-bell.has-unread .fa-bell {
    animation: bellShake 0.5s ease-in-out;
}

@keyframes bellShake {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-15deg); }
    75% { transform: rotate(15deg); }
}
</style>

<!-- Notification Bell Icon -->
<div class="notification-bell <?php echo $unreadCount > 0 ? 'has-unread' : ''; ?>" onclick="toggleNotifications()" title="Notifications">
    <i class="fa fa-bell" style="color: #9170E4;"></i>
    <?php if($unreadCount > 0): ?>
        <span class="notification-badge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
    <?php endif; ?>
</div>

<!-- Notification Dropdown -->
<div id="notificationDropdown" class="notification-dropdown">
    <div class="notification-header">
        <span>📬 Notifications (<?php echo $unreadCount; ?> unread)</span>
        <?php if($unreadCount > 0): ?>
            <span class="mark-all-read" onclick="markAllAsRead()">Mark all as read</span>
        <?php endif; ?>
    </div>
    
    <div id="notificationList">
        <?php if($query->rowCount() > 0): ?>
            <?php foreach($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification->is_read == 0 ? 'unread' : ''; ?> 
                            <?php 
                                if($notification->notification_type == 'overdue') echo 'notification-overdue';
                                elseif($notification->days_until_due !== null) echo 'notification-due-'.$notification->days_until_due;
                            ?>"
                     data-notification-id="<?php echo $notification->id; ?>"
                     onclick="markAsRead(<?php echo $notification->id; ?>)">
                    <div class="notification-message">
                        <?php echo htmlspecialchars($notification->message); ?>
                    </div>
                    <div class="notification-time">
                        <?php echo $notification->formatted_date; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notification-empty">
                <i class="fa fa-bell-slash" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                <p>No notifications yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const bell = document.querySelector('.notification-bell');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

function markAsRead(notificationId) {
    fetch('mark-notification-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if(item) {
                item.classList.remove('unread');
            }
            updateBadgeCount();
        }
    });
}

function markAllAsRead() {
    fetch('mark-notification-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mark_all=1'
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        }
    });
}

function updateBadgeCount() {
    const unreadItems = document.querySelectorAll('.notification-item.unread').length;
    const badge = document.querySelector('.notification-badge');
    
    if(unreadItems === 0 && badge) {
        badge.remove();
    } else if(badge) {
        badge.textContent = unreadItems > 99 ? '99+' : unreadItems;
    }
}

// Auto-generate reminders on page load (simulates daily check)
fetch('generate-reminders.php')
    .then(response => response.json())
    .then(data => {
        if(data.success && data.reminders_generated > 0) {
            console.log('Generated ' + data.reminders_generated + ' new reminders');
        }
    });
</script>
