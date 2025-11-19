<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login'])==0) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

if(isset($_POST['pay_fine'])) {
    $sid = $_SESSION['stdid'];
    
    try {
        // Reset fine to 0
        $sql = "UPDATE tblstudents SET Fine = 0.00 WHERE StudentId = :sid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':sid', $sid, PDO::PARAM_STR);
        $query->execute();
        
        echo json_encode(['success' => true, 'message' => 'Fine paid successfully!']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Payment failed. Please try again.']);
    }
    exit;
}
?>
