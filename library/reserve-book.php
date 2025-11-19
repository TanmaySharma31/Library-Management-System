<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login'])==0) {   
    header('location:index.php');
} else { 
    
    // Handle reservation cancellation
    if(isset($_GET['cancel']) && isset($_GET['rid'])) {
        $rid = intval($_GET['rid']);
        $sid = $_SESSION['stdid'];
        
        $sql = "UPDATE tblreservations SET status='cancelled' WHERE id=:rid AND StudentId=:sid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':rid', $rid, PDO::PARAM_INT);
        $query->bindParam(':sid', $sid, PDO::PARAM_STR);
        $query->execute();
        
        // Recalculate queue positions
        $bookId = intval($_GET['bid']);
        recalculateQueue($bookId, $dbh);
        
        $_SESSION['msg'] = "Reservation cancelled successfully";
        header('location:reserve-book.php');
        exit;
    }
    
    // Handle new reservation
    if(isset($_POST['reserve'])) {
        $bookId = intval($_POST['bookid']);
        $studentId = $_SESSION['stdid'];
        
        // Check if book is available
        $sql = "SELECT id FROM tblissuedbookdetails WHERE BookId=:bookid AND ReturnDate IS NULL";
        $query = $dbh->prepare($sql);
        $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
        $query->execute();
        
        if($query->rowCount() == 0) {
            $_SESSION['error'] = "This book is currently available. Please borrow it directly.";
        } else {
            // Check if already reserved
            $sql = "SELECT id FROM tblreservations WHERE BookId=:bookid AND StudentId=:sid AND status='waiting'";
            $query = $dbh->prepare($sql);
            $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
            $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
            $query->execute();
            
            if($query->rowCount() > 0) {
                $_SESSION['error'] = "You have already reserved this book";
            } else {
                // Get next queue position
                $sql = "SELECT COALESCE(MAX(queue_position), 0) + 1 as next_position 
                        FROM tblreservations 
                        WHERE BookId=:bookid AND status='waiting'";
                $query = $dbh->prepare($sql);
                $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
                $query->execute();
                $result = $query->fetch(PDO::FETCH_OBJ);
                $position = $result->next_position;
                
                // Create reservation
                $sql = "INSERT INTO tblreservations(StudentId, BookId, queue_position, status) 
                        VALUES(:sid, :bookid, :position, 'waiting')";
                $query = $dbh->prepare($sql);
                $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
                $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
                $query->bindParam(':position', $position, PDO::PARAM_INT);
                $query->execute();
                
                $_SESSION['msg'] = "Book reserved successfully! You are #$position in the queue.";
            }
        }
        header('location:reserve-book.php');
        exit;
    }
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Book Reservations | Online Library Management System</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <style>
        .reservation-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .queue-position {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            display: inline-block;
            padding: 10px 20px;
            background: #e7f3ff;
            border-radius: 50px;
            margin-bottom: 10px;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-waiting { background: #ffc107; color: #000; }
        .status-ready { background: #28a745; color: #fff; }
        .status-cancelled { background: #6c757d; color: #fff; }
        .status-expired { background: #dc3545; color: #fff; }
        .alert-info-custom {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 5px;
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
                    <h4 class="header-line">📚 My Book Reservations</h4>
                </div>
            </div>
            
            <?php if($_SESSION['error']!='') { ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo htmlentities($_SESSION['error']); ?>
                <?php $_SESSION['error']=''; ?>
            </div>
            <?php } ?>
            
            <?php if($_SESSION['msg']!='') { ?>
            <div class="alert alert-success">
                <strong>Success:</strong> <?php echo htmlentities($_SESSION['msg']); ?>
                <?php $_SESSION['msg']=''; ?>
            </div>
            <?php } ?>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">Active Reservations</div>
                        <div class="panel-body">
                            <?php 
                            $sid = $_SESSION['stdid'];
                            $sql = "SELECT r.*, b.BookName, b.ISBNNumber, b.bookImage, b.AuthorId, a.AuthorName,
                                    (SELECT COUNT(*) FROM tblreservations r2 
                                     WHERE r2.BookId = r.BookId AND r2.status = 'waiting' 
                                     AND r2.queue_position < r.queue_position) as ahead_count
                                    FROM tblreservations r
                                    LEFT JOIN tblbooks b ON r.BookId = b.id
                                    LEFT JOIN tblauthors a ON b.AuthorId = a.id
                                    WHERE r.StudentId=:sid AND r.status IN ('waiting', 'ready')
                                    ORDER BY r.status DESC, r.reserved_date DESC";
                            $query = $dbh->prepare($sql);
                            $query->bindParam(':sid', $sid, PDO::PARAM_STR);
                            $query->execute();
                            $results = $query->fetchAll(PDO::FETCH_OBJ);
                            
                            if($query->rowCount() > 0) {
                                foreach($results as $result) {
                            ?>
                            <div class="reservation-card">
                                <div class="row">
                                    <div class="col-md-2">
                                        <?php if($result->bookImage) { ?>
                                            <img src="admin/bookimg/<?php echo htmlentities($result->bookImage); ?>" 
                                                 width="100" height="150" style="border-radius: 5px;">
                                        <?php } else { ?>
                                            <img src="assets/img/default-book.png" width="100" height="150" 
                                                 style="border-radius: 5px;">
                                        <?php } ?>
                                    </div>
                                    <div class="col-md-7">
                                        <h4 style="margin-top: 0;"><?php echo htmlentities($result->BookName); ?></h4>
                                        <p><strong>Author:</strong> <?php echo htmlentities($result->AuthorName); ?></p>
                                        <p><strong>ISBN:</strong> <?php echo htmlentities($result->ISBNNumber); ?></p>
                                        <p><strong>Reserved on:</strong> 
                                            <?php echo date('d M Y, g:i A', strtotime($result->reserved_date)); ?></p>
                                        
                                        <?php if($result->status == 'waiting') { ?>
                                            <div class="alert-info-custom">
                                                <strong>📍 Queue Position:</strong> 
                                                <span class="queue-position">#<?php echo $result->queue_position; ?></span>
                                                <br>
                                                <?php if($result->ahead_count > 0) { ?>
                                                    <small><?php echo $result->ahead_count; ?> person(s) ahead of you</small>
                                                <?php } else { ?>
                                                    <small>You're next! We'll notify you when the book is available.</small>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                        
                                        <?php if($result->status == 'ready') { ?>
                                            <div class="alert alert-success">
                                                <strong>🎉 Good News!</strong> Your book is ready for pickup!
                                                <br>
                                                <small>Please collect it by: 
                                                    <?php echo date('d M Y', strtotime($result->expiry_date)); ?></small>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="col-md-3 text-right">
                                        <span class="status-badge status-<?php echo $result->status; ?>">
                                            <?php echo strtoupper($result->status); ?>
                                        </span>
                                        <br><br>
                                        
                                        <?php if($result->status == 'waiting') { ?>
                                            <a href="reserve-book.php?cancel=1&rid=<?php echo $result->id; ?>&bid=<?php echo $result->BookId; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to cancel this reservation?');">
                                                Cancel Reservation
                                            </a>
                                        <?php } ?>
                                        
                                        <?php if($result->status == 'ready') { ?>
                                            <a href="index.php" class="btn btn-success btn-sm">
                                                Go to Library
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                }
                            } else { ?>
                            <div class="alert alert-info">
                                <strong>No active reservations.</strong> 
                                <a href="index.php">Browse books</a> to reserve one!
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    
                    <!-- Past Reservations -->
                    <div class="panel panel-default">
                        <div class="panel-heading">Reservation History</div>
                        <div class="panel-body">
                            <?php 
                            $sql = "SELECT r.*, b.BookName, b.AuthorId, a.AuthorName
                                    FROM tblreservations r
                                    LEFT JOIN tblbooks b ON r.BookId = b.id
                                    LEFT JOIN tblauthors a ON b.AuthorId = a.id
                                    WHERE r.StudentId=:sid AND r.status IN ('fulfilled', 'cancelled', 'expired')
                                    ORDER BY r.reserved_date DESC
                                    LIMIT 10";
                            $query = $dbh->prepare($sql);
                            $query->bindParam(':sid', $sid, PDO::PARAM_STR);
                            $query->execute();
                            $results = $query->fetchAll(PDO::FETCH_OBJ);
                            
                            if($query->rowCount() > 0) { ?>
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Book Name</th>
                                        <th>Author</th>
                                        <th>Reserved Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($results as $result) { ?>
                                    <tr>
                                        <td><?php echo htmlentities($result->BookName); ?></td>
                                        <td><?php echo htmlentities($result->AuthorName); ?></td>
                                        <td><?php echo date('d M Y', strtotime($result->reserved_date)); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $result->status; ?>">
                                                <?php echo strtoupper($result->status); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <?php } else { ?>
                            <p>No past reservations.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>

<?php 
}

// Helper function to recalculate queue positions
function recalculateQueue($bookId, $dbh) {
    $sql = "SELECT id FROM tblreservations 
            WHERE BookId=:bookid AND status='waiting' 
            ORDER BY reserved_date ASC";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
    
    $position = 1;
    foreach($results as $result) {
        $sql = "UPDATE tblreservations SET queue_position=:position WHERE id=:id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':position', $position, PDO::PARAM_INT);
        $query->bindParam(':id', $result->id, PDO::PARAM_INT);
        $query->execute();
        $position++;
    }
}
?>
