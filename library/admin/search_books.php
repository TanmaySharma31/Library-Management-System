<?php 
require_once("includes/config.php");

if(!empty($_GET["term"])) {
    $searchTerm = $_GET["term"];
    
    // Search by ISBN Number or Book Title
    $sql = "SELECT id, BookName, ISBNNumber, BookPrice, AuthorName 
            FROM tblbooks 
            WHERE ISBNNumber LIKE :searchTerm 
            OR BookName LIKE :searchTerm 
            LIMIT 10";
    
    $query = $dbh->prepare($sql);
    $searchParam = '%' . $searchTerm . '%';
    $query->bindParam(':searchTerm', $searchParam, PDO::PARAM_STR);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
    
    $bookList = array();
    
    if($query->rowCount() > 0) {
        foreach ($results as $result) {
            $bookList[] = array(
                "id" => $result->id,
                "label" => $result->BookName . " (ISBN: " . $result->ISBNNumber . ") - " . $result->AuthorName,
                "value" => $result->BookName,
                "isbn" => $result->ISBNNumber,
                "bookid" => $result->id,
                "bookname" => $result->BookName,
                "author" => $result->AuthorName
            );
        }
    }
    
    echo json_encode($bookList);
}
?>
