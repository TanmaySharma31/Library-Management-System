<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login'])==0) {   
    header('location:index.php');
    exit;
} 

$studentId = $_SESSION['stdid'];

// Handle reservation
if(isset($_POST['reserve_book'])) {
    $bookId = intval($_POST['book_id']);
    
    // Check if already reserved
    $sql = "SELECT * FROM tblreservations WHERE SapId=:sapid AND BookId=:bookid AND status IN ('waiting', 'ready')";
    $query = $dbh->prepare($sql);
    $query->bindParam(':sapid', $studentId, PDO::PARAM_STR);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->execute();
    
    if($query->rowCount() > 0) {
        $_SESSION['error'] = "You have already reserved this book!";
    } else {
        // Check if book is currently issued
        $sql = "SELECT COUNT(*) as issued FROM tblissuedbookdetails WHERE BookId=:bookid AND ReturnDate IS NULL";
        $query = $dbh->prepare($sql);
        $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_OBJ);
        
        if($result->issued > 0) {
            // Get next queue position
            $sql = "SELECT COALESCE(MAX(queue_position), 0) + 1 as next_position FROM tblreservations WHERE BookId=:bookid";
            $query = $dbh->prepare($sql);
            $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
            $query->execute();
            $pos = $query->fetch(PDO::FETCH_OBJ);
            
            // Insert reservation
            $sql = "INSERT INTO tblreservations (SapId, BookId, queue_position, status) VALUES (:sapid, :bookid, :position, 'waiting')";
            $query = $dbh->prepare($sql);
            $query->bindParam(':sapid', $studentId, PDO::PARAM_STR);
            $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
            $query->bindParam(':position', $pos->next_position, PDO::PARAM_INT);
            $query->execute();
            
            $_SESSION['success'] = "Book reserved successfully! You are #" . $pos->next_position . " in the queue.";
        } else {
            $_SESSION['error'] = "This book is currently available. You can issue it directly!";
        }
    }
    header('location:browse-books.php');
    exit;
}

// Get filter parameters
$categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>Browse Books | Library Management System</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style>
        .book-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            transition: all 0.3s;
            background: white;
        }
        
        .book-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .book-card h4 {
            color: #333;
            margin-top: 0;
            font-size: 18px;
            min-height: 50px;
        }
        
        .book-meta {
            color: #666;
            font-size: 13px;
            margin: 5px 0;
        }
        
        .book-meta i {
            margin-right: 5px;
            color: #4CAF50;
        }
        
        .book-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-issued {
            background: #fff3cd;
            color: #856404;
        }
        
        .btn-reserve {
            background: #FF9800;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-reserve:hover {
            background: #F57C00;
            transform: scale(1.05);
        }
        
        .btn-details {
            background: #2196F3;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            margin-left: 5px;
        }
        
        .btn-details:hover {
            background: #1976D2;
        }
        
        .search-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .filter-group {
            display: inline-block;
            margin-right: 15px;
        }
        
        .alert-dismissible {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>
    
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">
                        <i class="fa fa-book"></i> Browse Books
                    </h4>
                </div>
            </div>
            
            <?php if(isset($_SESSION['success'])) { ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <strong>Success!</strong> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php } ?>
            
            <?php if(isset($_SESSION['error'])) { ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <strong>Error!</strong> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php } ?>
            
            <!-- Search and Filter Section -->
            <div class="search-section">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search by book name, author, ISBN..." 
                                           value="<?php echo htmlentities($searchQuery); ?>">
                                    <span class="input-group-btn">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fa fa-search"></i> Search
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <select name="category" class="form-control">
                                    <option value="0">All Categories</option>
                                    <?php
                                    $sql = "SELECT * FROM tblcategory WHERE Status=1 ORDER BY CategoryName";
                                    $query = $dbh->prepare($sql);
                                    $query->execute();
                                    $categories = $query->fetchAll(PDO::FETCH_OBJ);
                                    if($query->rowCount() > 0) {
                                        foreach($categories as $cat) { ?>
                                            <option value="<?php echo $cat->id; ?>" 
                                                <?php if($categoryFilter == $cat->id) echo 'selected'; ?>>
                                                <?php echo htmlentities($cat->CategoryName); ?>
                                            </option>
                                        <?php }
                                    } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <a href="browse-books.php" class="btn btn-default btn-block">
                                <i class="fa fa-refresh"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Books Grid -->
            <div class="row">
                <?php
                // Build query
                $sql = "SELECT b.*, c.CategoryName, a.AuthorName,
                        (SELECT COUNT(*) FROM tblissuedbookdetails 
                         WHERE BookId=b.id AND ReturnDate IS NULL) as issued_count,
                        (SELECT COUNT(*) FROM tblreservations 
                         WHERE BookId=b.id AND status='waiting') as queue_count
                        FROM tblbooks b
                        LEFT JOIN tblcategory c ON b.CatId = c.id
                        LEFT JOIN tblauthors a ON b.AuthorId = a.id
                        WHERE 1=1";
                
                if($categoryFilter > 0) {
                    $sql .= " AND b.CatId = :category";
                }
                
                if(!empty($searchQuery)) {
                    $sql .= " AND (b.BookName LIKE :search OR b.BookCode LIKE :search OR a.AuthorName LIKE :search)";
                }
                
                $sql .= " ORDER BY b.BookName ASC";
                
                $query = $dbh->prepare($sql);
                
                if($categoryFilter > 0) {
                    $query->bindParam(':category', $categoryFilter, PDO::PARAM_INT);
                }
                
                if(!empty($searchQuery)) {
                    $searchParam = '%' . $searchQuery . '%';
                    $query->bindParam(':search', $searchParam, PDO::PARAM_STR);
                }
                
                $query->execute();
                $books = $query->fetchAll(PDO::FETCH_OBJ);
                
                if($query->rowCount() > 0) {
                    foreach($books as $book) {
                        $isIssued = $book->issued_count > 0;
                        ?>
                        <div class="col-md-4">
                            <div class="book-card">
                                <h4><?php echo htmlentities($book->BookName); ?></h4>
                                
                                <div class="book-meta">
                                    <i class="fa fa-user"></i> <strong>Author:</strong> 
                                    <?php echo htmlentities($book->AuthorName); ?>
                                </div>
                                
                                <div class="book-meta">
                                    <i class="fa fa-tag"></i> <strong>Category:</strong> 
                                    <?php echo htmlentities($book->CategoryName); ?>
                                </div>
                                
                                <div class="book-meta">
                                    <i class="fa fa-barcode"></i> <strong>Book Code:</strong> 
                                    <?php echo htmlentities($book->BookCode); ?>
                                </div>
                                
                                <div style="margin-top: 15px;">
                                    <?php if($isIssued): ?>
                                        <span class="book-status status-issued">
                                            <i class="fa fa-clock-o"></i> Currently Issued
                                        </span>
                                        <?php if($book->queue_count > 0): ?>
                                            <small class="text-muted" style="display: block; margin-top: 5px;">
                                                <?php echo $book->queue_count; ?> student(s) in queue
                                            </small>
                                        <?php endif; ?>
                                        
                                        <!-- Check if current student already reserved -->
                                        <?php
                                        $checkSql = "SELECT * FROM tblreservations WHERE SapId=:sapid AND BookId=:bookid AND status IN ('waiting', 'ready')";
                                        $checkQuery = $dbh->prepare($checkSql);
                                        $checkQuery->bindParam(':sapid', $studentId, PDO::PARAM_STR);
                                        $checkQuery->bindParam(':bookid', $book->id, PDO::PARAM_INT);
                                        $checkQuery->execute();
                                        $alreadyReserved = $checkQuery->rowCount() > 0;
                                        
                                        if($alreadyReserved): ?>
                                            <button class="btn btn-success btn-sm" disabled style="margin-top: 10px;">
                                                <i class="fa fa-check"></i> Already Reserved
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline-block; margin-top: 10px;">
                                                <input type="hidden" name="book_id" value="<?php echo $book->id; ?>">
                                                <button type="submit" name="reserve_book" class="btn-reserve">
                                                    <i class="fa fa-bookmark"></i> Reserve Book
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="book-status status-available">
                                            <i class="fa fa-check-circle"></i> Available
                                        </span>
                                        <p class="text-muted" style="font-size: 12px; margin-top: 5px;">
                                            Contact librarian to issue this book
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="margin-top: 15px;">
                                    <a href="book-details.php?bookid=<?php echo $book->id; ?>" class="btn-details">
                                        <i class="fa fa-info-circle"></i> View Details & Reviews
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php 
                    }
                } else { ?>
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> No books found matching your criteria.
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php');?>
    
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
