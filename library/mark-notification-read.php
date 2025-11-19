<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(!isset($_SESSION['stdid'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$studentId = $_SESSION['stdid'];

if(isset($_POST['mark_all'])) {
    // Mark all notifications as read for this student
    $sql = "UPDATE tblnotifications SET is_read = 1 WHERE student_id = :student_id AND is_read = 0";
    $query = $dbh->prepare($sql);
    $query->bindParam(':student_id', $studentId, PDO::PARAM_STR);
    
    if($query->execute()) {
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update']);
    }
} elseif(isset($_POST['notification_id'])) {
    // Mark specific notification as read
    $notificationId = $_POST['notification_id'];
    
    $sql = "UPDATE tblnotifications SET is_read = 1 WHERE id = :id AND student_id = :student_id";
    $query = $dbh->prepare($sql);
    $query->bindParam(':id', $notificationId, PDO::PARAM_INT);
    $query->bindParam(':student_id', $studentId, PDO::PARAM_STR);
    
    if($query->execute()) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>
