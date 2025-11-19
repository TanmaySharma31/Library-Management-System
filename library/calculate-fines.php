<?php
// This script calculates fines for overdue books
// Fine rate: ₹10 per day for each overdue book

include('includes/config.php');

try {
    // Get all students with unreturned books
    $sql = "SELECT DISTINCT StudentID FROM tblissuedbookdetails WHERE ReturnDate IS NULL";
    $query = $dbh->prepare($sql);
    $query->execute();
    $students = $query->fetchAll(PDO::FETCH_OBJ);
    
    $today = date('Y-m-d');
    $finePerDay = 10; // ₹10 per day per book
    
    foreach($students as $student) {
        $sid = $student->StudentID;
        
        // Calculate total overdue days for this student
        $sqlOverdue = "SELECT DATEDIFF(:today, ScheduledReturnDate) as OverdueDays
                       FROM tblissuedbookdetails 
                       WHERE StudentID = :sid 
                       AND ReturnDate IS NULL 
                       AND ScheduledReturnDate < :today";
        $queryOverdue = $dbh->prepare($sqlOverdue);
        $queryOverdue->bindParam(':sid', $sid, PDO::PARAM_STR);
        $queryOverdue->bindParam(':today', $today, PDO::PARAM_STR);
        $queryOverdue->execute();
        $overdueBooks = $queryOverdue->fetchAll(PDO::FETCH_OBJ);
        
        $totalFine = 0;
        foreach($overdueBooks as $book) {
            if($book->OverdueDays > 0) {
                $totalFine += $book->OverdueDays * $finePerDay;
            }
        }
        
        // Update student's fine
        if($totalFine > 0) {
            $sqlUpdate = "UPDATE tblstudents SET Fine = :fine WHERE StudentId = :sid";
            $queryUpdate = $dbh->prepare($sqlUpdate);
            $queryUpdate->bindParam(':fine', $totalFine, PDO::PARAM_STR);
            $queryUpdate->bindParam(':sid', $sid, PDO::PARAM_STR);
            $queryUpdate->execute();
        }
    }
    
    echo "Fines calculated successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
