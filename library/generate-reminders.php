<?php
// Auto-generate reminder notifications for books due in 5 days or less
// This script should be run daily (can be called via cron job or on login)

session_start();
error_reporting(0);
include('includes/config.php');

// Function to generate reminders for books due soon
function generateReminders($dbh) {
    $remindersGenerated = 0;
    
    // Get all issued books that are not yet returned and due within 5 days
    $sql = "SELECT 
                ibd.id as issue_id,
                ibd.StudentID,
                ibd.BookId,
                ibd.ScheduledReturnDate,
                DATEDIFF(ibd.ScheduledReturnDate, CURDATE()) as days_until_due,
                b.BookName,
                s.StudentId as student_name
            FROM tblissuedbookdetails ibd
            INNER JOIN tblbooks b ON ibd.BookId = b.id
            INNER JOIN tblstudents s ON ibd.StudentID = s.StudentId
            WHERE ibd.ReturnDate IS NULL 
            AND DATEDIFF(ibd.ScheduledReturnDate, CURDATE()) <= 5
            AND DATEDIFF(ibd.ScheduledReturnDate, CURDATE()) >= 0";
    
    $query = $dbh->prepare($sql);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
    
    if($query->rowCount() > 0) {
        foreach($results as $result) {
            $days_until_due = $result->days_until_due;
            
            // Check if notification already exists for today
            $checkSql = "SELECT id FROM tblnotifications 
                        WHERE issue_id = :issue_id 
                        AND notification_type = 'due_reminder'
                        AND DATE(created_at) = CURDATE()";
            $checkQuery = $dbh->prepare($checkSql);
            $checkQuery->bindParam(':issue_id', $result->issue_id, PDO::PARAM_INT);
            $checkQuery->execute();
            
            // Only create notification if it doesn't exist for today
            if($checkQuery->rowCount() == 0) {
                // Generate appropriate message based on days remaining
                if($days_until_due == 0) {
                    $message = "⚠️ URGENT: Your book '{$result->BookName}' is DUE TODAY! Please return it to avoid fines.";
                } elseif($days_until_due == 1) {
                    $message = "⏰ Reminder: Your book '{$result->BookName}' is due TOMORROW! Please plan to return it.";
                } else {
                    $message = "📚 Reminder: Your book '{$result->BookName}' is due in {$days_until_due} days (Due: {$result->ScheduledReturnDate}).";
                }
                
                // Insert notification
                $insertSql = "INSERT INTO tblnotifications 
                            (student_id, book_id, issue_id, notification_type, message, days_until_due, is_read) 
                            VALUES 
                            (:student_id, :book_id, :issue_id, 'due_reminder', :message, :days_until_due, 0)";
                
                $insertQuery = $dbh->prepare($insertSql);
                $insertQuery->bindParam(':student_id', $result->StudentID, PDO::PARAM_STR);
                $insertQuery->bindParam(':book_id', $result->BookId, PDO::PARAM_INT);
                $insertQuery->bindParam(':issue_id', $result->issue_id, PDO::PARAM_INT);
                $insertQuery->bindParam(':message', $message, PDO::PARAM_STR);
                $insertQuery->bindParam(':days_until_due', $days_until_due, PDO::PARAM_INT);
                
                if($insertQuery->execute()) {
                    $remindersGenerated++;
                }
            }
        }
    }
    
    // Also check for overdue books
    $overdueSql = "SELECT 
                ibd.id as issue_id,
                ibd.StudentID,
                ibd.BookId,
                ibd.ScheduledReturnDate,
                DATEDIFF(CURDATE(), ibd.ScheduledReturnDate) as days_overdue,
                b.BookName,
                s.StudentId as student_name
            FROM tblissuedbookdetails ibd
            INNER JOIN tblbooks b ON ibd.BookId = b.id
            INNER JOIN tblstudents s ON ibd.StudentID = s.StudentId
            WHERE ibd.ReturnDate IS NULL 
            AND CURDATE() > ibd.ScheduledReturnDate";
    
    $overdueQuery = $dbh->prepare($overdueSql);
    $overdueQuery->execute();
    $overdueResults = $overdueQuery->fetchAll(PDO::FETCH_OBJ);
    
    if($overdueQuery->rowCount() > 0) {
        foreach($overdueResults as $overdueBook) {
            // Check if overdue notification exists for today
            $checkSql = "SELECT id FROM tblnotifications 
                        WHERE issue_id = :issue_id 
                        AND notification_type = 'overdue'
                        AND DATE(created_at) = CURDATE()";
            $checkQuery = $dbh->prepare($checkSql);
            $checkQuery->bindParam(':issue_id', $overdueBook->issue_id, PDO::PARAM_INT);
            $checkQuery->execute();
            
            if($checkQuery->rowCount() == 0) {
                $message = "🚨 OVERDUE: Your book '{$overdueBook->BookName}' is {$overdueBook->days_overdue} days overdue! Please return immediately. Fines are accumulating.";
                
                $insertSql = "INSERT INTO tblnotifications 
                            (student_id, book_id, issue_id, notification_type, message, days_until_due, is_read) 
                            VALUES 
                            (:student_id, :book_id, :issue_id, 'overdue', :message, :days_overdue, 0)";
                
                $insertQuery = $dbh->prepare($insertSql);
                $insertQuery->bindParam(':student_id', $overdueBook->StudentID, PDO::PARAM_STR);
                $insertQuery->bindParam(':book_id', $overdueBook->BookId, PDO::PARAM_INT);
                $insertQuery->bindParam(':issue_id', $overdueBook->issue_id, PDO::PARAM_INT);
                $insertQuery->bindParam(':message', $message, PDO::PARAM_STR);
                $days_negative = -$overdueBook->days_overdue;
                $insertQuery->bindParam(':days_overdue', $days_negative, PDO::PARAM_INT);
                
                if($insertQuery->execute()) {
                    $remindersGenerated++;
                }
            }
        }
    }
    
    return $remindersGenerated;
}

// Run the reminder generation
try {
    $count = generateReminders($dbh);
    echo json_encode(['success' => true, 'reminders_generated' => $count]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
