<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login'])==0) {   
    header('location:index.php');
} else { 

// Reserve Book
if(isset($_POST['reserve'])) {
    $bookId = intval($_POST['book_id']);
    $sapId = $_SESSION['stdid'];
    
    // Check if already reserved by this student
    $sql = "SELECT * FROM tblreservations WHERE SapId=:sapid AND BookId=:bookid AND status IN ('waiting', 'ready')";
    $query = $dbh->prepare($sql);
    $query->bindParam(':sapid', $sapId, PDO::PARAM_STR);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->execute();
    $existing = $query->fetch(PDO::FETCH_OBJ);
    
    if($existing) {
        $_SESSION['error'] = "You have already reserved this book!";
    } else {
        // Check if book is available
        $sql = "SELECT * FROM tblbooks WHERE id=:bookid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
        $query->execute();
        $book = $query->fetch(PDO::FETCH_OBJ);
        
        if($book) {
            // Check if already issued to someone
            $sql = "SELECT * FROM tblissuedbookdetails WHERE BookId=:bookid AND ReturnDate IS NULL";
            $query = $dbh->prepare($sql);
            $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
            $query->execute();
            $issued = $query->fetch(PDO::FETCH_OBJ);
            
            if($issued) {
                // Book is issued, add to reservation queue
                // Get queue position
                $sql = "SELECT COALESCE(MAX(queue_position), 0) + 1 as next_position FROM tblreservations WHERE BookId=:bookid AND status='waiting'";
                $query = $dbh->prepare($sql);
                $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
                $query->execute();
                $position = $query->fetch(PDO::FETCH_OBJ)->next_position;
                
                // Insert reservation
                $sql = "INSERT INTO tblreservations (SapId, BookId, queue_position, status) VALUES (:sapid, :bookid, :position, 'waiting')";
                $query = $dbh->prepare($sql);
                $query->bindParam(':sapid', $sapId, PDO::PARAM_STR);
                $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
                $query->bindParam(':position', $position, PDO::PARAM_INT);
                $query->execute();
                
                $_SESSION['msg'] = "Book reserved successfully! You are #$position in the queue.";
            } else {
                $_SESSION['error'] = "This book is currently available. You can borrow it directly!";
            }
        }
    }
    header('location: book-reservation.php');
}

// Cancel Reservation
if(isset($_GET['cancel'])) {
    $resId = intval($_GET['cancel']);
    $sapId = $_SESSION['stdid'];
    
    $sql = "DELETE FROM tblreservations WHERE id=:resid AND SapId=:sapid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':resid', $resId, PDO::PARAM_INT);
    $query->bindParam(':sapid', $sapId, PDO::PARAM_STR);
    $query->execute();
    
    $_SESSION['msg'] = "Reservation cancelled successfully!";
    header('location: book-reservation.php');
}

?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>My Reservations</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
</head>
<body>
<?php include('includes/header.php');?>

<div class="content-wrapper">
    <div class="container">
        <div class="row pad-botm">
            <div class="col-md-12">
                <h4 class="header-line">My Book Reservations</h4>
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
                    <div class="panel-heading">
                        Active Reservations
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Book Name</th>
                                        <th>ISBN</th>
                                        <th>Queue Position</th>
                                        <th>Status</th>
                                        <th>Reserved Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php 
$sapId = $_SESSION['stdid'];
$sql = "SELECT r.*, b.BookName, b.BookCode 
        FROM tblreservations r 
        INNER JOIN tblbooks b ON r.BookId = b.id 
        WHERE r.SapId=:sapid AND r.status IN ('waiting', 'ready')
        ORDER BY r.reserved_date DESC";
$query = $dbh->prepare($sql);
$query->bindParam(':sapid', $sapId, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
$cnt = 1;

if($query->rowCount() > 0) {
    foreach($results as $result) { 
        $statusBadge = $result->status == 'ready' ? 'success' : 'warning';
        $statusText = $result->status == 'ready' ? 'Ready to Pickup' : 'In Queue';
?>                                      
                                    <tr>
                                        <td><?php echo htmlentities($cnt); ?></td>
                                        <td><?php echo htmlentities($result->BookName); ?></td>
                                        <td><?php echo htmlentities($result->BookCode); ?></td>
                                        <td><span class="badge badge-info">#<?php echo htmlentities($result->queue_position); ?></span></td>
                                        <td><span class="badge badge-<?php echo $statusBadge; ?>"><?php echo $statusText; ?></span></td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($result->reserved_date)); ?></td>
                                        <td>
                                            <a href="book-reservation.php?cancel=<?php echo $result->id; ?>" 
                                               onclick="return confirm('Are you sure you want to cancel this reservation?');"
                                               class="btn btn-danger btn-xs">Cancel</a>
                                        </td>
                                    </tr>
<?php 
        $cnt++;
    }
} else { ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No active reservations</td>
                                    </tr>
<?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reserve a Book -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        Reserve a Book
                    </div>
                    <div class="panel-body">
                        <form method="post">
                            <div class="form-group">
                                <label>Select Book to Reserve:</label>
                                <select name="book_id" class="form-control" required>
                                    <option value="">-- Select Book --</option>
<?php 
// Show only issued books
$sql = "SELECT DISTINCT b.id, b.BookName, b.BookCode 
        FROM tblbooks b 
        INNER JOIN tblissuedbookdetails ibd ON b.id = ibd.BookId 
        WHERE ibd.ReturnDate IS NULL 
        ORDER BY b.BookName";
$query = $dbh->prepare($sql);
$query->execute();
$books = $query->fetchAll(PDO::FETCH_OBJ);

foreach($books as $book) { ?>
                                    <option value="<?php echo $book->id; ?>">
                                        <?php echo htmlentities($book->BookName . " (" . $book->BookCode . ")"); ?>
                                    </option>
<?php } ?>
                                </select>
                            </div>
                            <button type="submit" name="reserve" class="btn btn-info">Reserve Book</button>
                        </form>
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
<?php } ?>
