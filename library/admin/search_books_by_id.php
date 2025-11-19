<?php
session_start();
include('includes/config.php');

if(strlen($_SESSION['alogin']) == 0) {
    echo json_encode([]);
    exit;
}

$term = '%' . $_GET['term'] . '%';

try {
    $sql = "SELECT tblbooks.id, tblbooks.BookName, tblbooks.BookCode, tblauthors.AuthorName 
            FROM tblbooks 
            LEFT JOIN tblauthors ON tblbooks.AuthorId = tblauthors.id 
            WHERE tblbooks.id LIKE :searchTerm 
               OR tblbooks.BookCode LIKE :searchTerm 
               OR tblbooks.BookName LIKE :searchTerm 
            ORDER BY tblbooks.id ASC
            LIMIT 10";
    
    $query = $dbh->prepare($sql);
    $query->bindParam(':searchTerm', $term, PDO::PARAM_STR);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
    
    $output = [];
    if($query->rowCount() > 0) {
        foreach($results as $result) {
            $output[] = [
                'id' => $result->id,
                'label' => 'ID: ' . $result->id . ' - ' . $result->BookName . ' (Code: ' . $result->BookCode . ')',
                'value' => $result->id,
                'bookcode' => $result->BookCode,
                'bookname' => $result->BookName,
                'author' => $result->AuthorName ? $result->AuthorName : 'Unknown'
            ];
        }
    }
    
    echo json_encode($output);
} catch(PDOException $e) {
    echo json_encode([]);
}
?>
