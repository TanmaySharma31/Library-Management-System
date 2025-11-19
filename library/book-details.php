<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login'])==0) {   
    header('location:index.php');
} else {
    
    if(!isset($_GET['bookid'])) {
        header('location:index.php');
        exit;
    }
    
    $bookId = intval($_GET['bookid']);
    $studentId = $_SESSION['stdid'];
    
    // Handle helpful vote
    if(isset($_POST['mark_helpful'])) {
        $reviewId = intval($_POST['review_id']);
        
        // Check if already marked helpful
        $sql = "SELECT id FROM tblreview_helpful WHERE review_id=:rid AND SapId=:sid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':rid', $reviewId, PDO::PARAM_INT);
        $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
        $query->execute();
        
        if($query->rowCount() == 0) {
            // Add helpful vote
            $sql = "INSERT INTO tblreview_helpful(review_id, SapId) VALUES(:rid, :sid)";
            $query = $dbh->prepare($sql);
            $query->bindParam(':rid', $reviewId, PDO::PARAM_INT);
            $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
            $query->execute();
            
            // Update count
            $sql = "UPDATE tblreviews SET helpful_count = helpful_count + 1 WHERE id=:rid";
            $query = $dbh->prepare($sql);
            $query->bindParam(':rid', $reviewId, PDO::PARAM_INT);
            $query->execute();
            
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    // Get book details
    $sql = "SELECT b.*, a.AuthorName, c.CategoryName,
            (SELECT COUNT(*) FROM tblissuedbookdetails WHERE BookId=b.id AND ReturnDate IS NULL) as issued_count,
            (SELECT AVG(rating) FROM tblreviews WHERE BookId=b.id) as avg_rating,
            (SELECT COUNT(*) FROM tblreviews WHERE BookId=b.id) as review_count
            FROM tblbooks b
            LEFT JOIN tblauthors a ON b.AuthorId = a.id
            LEFT JOIN tblcategory c ON b.CatId = c.id
            WHERE b.id=:bookid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->execute();
    $book = $query->fetch(PDO::FETCH_OBJ);
    
    if(!$book) {
        header('location:index.php');
        exit;
    }
    
    $isAvailable = ($book->issued_count == 0);
    
    // Check if student has returned this book (for review eligibility)
    $sql = "SELECT COUNT(*) as count FROM tblissuedbookdetails 
            WHERE BookId=:bookid AND StudentID=:sid AND ReturnDate IS NOT NULL";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
    $query->execute();
    $returnResult = $query->fetch(PDO::FETCH_OBJ);
    $hasReturned = ($returnResult->count > 0);
    
    // Check if already reviewed
    $sql = "SELECT id FROM tblreviews WHERE BookId=:bookid AND SapId=:sid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
    $query->execute();
    $hasReviewed = ($query->rowCount() > 0);
    
    // Check reservation status
    $sql = "SELECT * FROM tblreservations 
            WHERE BookId=:bookid AND SapId=:sid AND status='waiting'";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
    $query->execute();
    $reservation = $query->fetch(PDO::FETCH_OBJ);
    $hasReserved = ($query->rowCount() > 0);
    
    // Get reviews
    $sql = "SELECT r.*, s.FullName, s.StudentId,
            (SELECT COUNT(*) FROM tblreview_helpful WHERE review_id=r.id AND SapId=:sid) as user_marked_helpful
            FROM tblreviews r
            LEFT JOIN tblstudents s ON r.SapId = s.StudentId
            WHERE r.BookId=:bookid AND r.status='approved'
            ORDER BY r.helpful_count DESC, r.created_date DESC";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
    $query->execute();
    $reviews = $query->fetchAll(PDO::FETCH_OBJ);
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title><?php echo htmlentities($book->BookName); ?> | Library</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <style>
        .book-detail-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .book-image {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .rating-display {
            font-size: 24px;
            color: #ffc107;
            margin: 10px 0;
        }
        .rating-number {
            font-size: 36px;
            font-weight: bold;
            color: #333;
        }
        .availability-badge {
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 14px;
        }
        .available { background: #d4edda; color: #155724; }
        .unavailable { background: #f8d7da; color: #721c24; }
        .review-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .review-rating {
            color: #ffc107;
            font-size: 18px;
        }
        .verified-badge {
            background: #28a745;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .helpful-btn {
            cursor: pointer;
            color: #6c757d;
            transition: color 0.2s;
        }
        .helpful-btn:hover, .helpful-btn.marked {
            color: #007bff;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>
    
    <div class="content-wrapper">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <a href="index.php" class="btn btn-default btn-sm">← Back to Library</a>
                </div>
            </div>
            
            <div class="row" style="margin-top: 20px;">
                <!-- Book Details -->
                <div class="col-md-8">
                    <div class="book-detail-card">
                        <div class="row">
                            <div class="col-md-4">
                                <?php if($book->bookImage) { ?>
                                    <img src="admin/bookimg/<?php echo htmlentities($book->bookImage); ?>" 
                                         class="book-image">
                                <?php } else { ?>
                                    <img src="assets/img/default-book.png" class="book-image">
                                <?php } ?>
                            </div>
                            <div class="col-md-8">
                                <h2><?php echo htmlentities($book->BookName); ?></h2>
                                
                                <!-- Rating -->
                                <?php if($book->review_count > 0) { ?>
                                <div class="rating-display">
                                    <span class="rating-number"><?php echo number_format($book->avg_rating, 1); ?></span>
                                    <span>
                                        <?php 
                                        $avgRating = round($book->avg_rating);
                                        for($i=1; $i<=5; $i++) {
                                            echo ($i <= $avgRating) ? '★' : '☆';
                                        }
                                        ?>
                                    </span>
                                    <small style="color: #666;">
                                        (<?php echo $book->review_count; ?> review<?php echo $book->review_count > 1 ? 's' : ''; ?>)
                                    </small>
                                </div>
                                <?php } ?>
                                
                                <hr>
                                
                                <p><strong>Author:</strong> <?php echo htmlentities($book->AuthorName); ?></p>
                                <p><strong>Book Code:</strong> <?php echo htmlentities($book->BookCode); ?></p>
                                <p><strong>Category:</strong> <?php echo htmlentities($book->CategoryName); ?></p>
                                <p><strong>Price:</strong> ₹<?php echo htmlentities($book->BookPrice); ?></p>
                                
                                <hr>
                                
                                <!-- Availability -->
                                <span class="availability-badge <?php echo $isAvailable ? 'available' : 'unavailable'; ?>">
                                    <?php echo $isAvailable ? '✓ Available' : '✗ Currently Issued'; ?>
                                </span>
                                
                                <br><br>
                                
                                <!-- Action Buttons -->
                                <?php if($isAvailable) { ?>
                                    <a href="index.php" class="btn btn-success btn-lg">
                                        📚 Borrow This Book
                                    </a>
                                <?php } else { ?>
                                    <?php if($hasReserved) { ?>
                                        <div class="alert alert-info">
                                            ✓ You're in the queue (Position #<?php echo $reservation->queue_position; ?>)
                                            <br>
                                            <a href="reserve-book.php" class="btn btn-info btn-sm" style="margin-top: 10px;">
                                                View Reservations
                                            </a>
                                        </div>
                                    <?php } else { ?>
                                        <form method="post" action="reserve-book.php" style="display: inline;">
                                            <input type="hidden" name="bookid" value="<?php echo $bookId; ?>">
                                            <button type="submit" name="reserve" class="btn btn-warning btn-lg">
                                                🔔 Reserve This Book
                                            </button>
                                        </form>
                                        <br><small class="text-muted">
                                            We'll notify you when it's available
                                        </small>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reviews Section -->
                    <div class="book-detail-card">
                        <h3>📝 Reviews</h3>
                        <hr>
                        
                        <?php if($hasReturned && !$hasReviewed) { ?>
                        <div class="alert alert-success">
                            <strong>Share your experience!</strong>
                            <a href="submit-review.php?bookid=<?php echo $bookId; ?>" 
                               class="btn btn-primary btn-sm pull-right">
                                Write a Review
                            </a>
                            <div class="clearfix"></div>
                        </div>
                        <?php } ?>
                        
                        <?php if(count($reviews) > 0) {
                            foreach($reviews as $review) {
                        ?>
                        <div class="review-card">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="review-rating">
                                        <?php 
                                        for($i=1; $i<=5; $i++) {
                                            echo ($i <= $review->rating) ? '★' : '☆';
                                        }
                                        ?>
                                    </div>
                                    <h4 style="margin: 5px 0;"><?php echo htmlentities($review->review_title); ?></h4>
                                    <p style="margin: 10px 0; line-height: 1.6;">
                                        <?php echo nl2br(htmlentities($review->review_text)); ?>
                                    </p>
                                    <small class="text-muted">
                                        By <strong><?php echo htmlentities($review->FullName); ?></strong>
                                        <?php if($review->is_verified_borrower) { ?>
                                            <span class="verified-badge">✓ VERIFIED BORROWER</span>
                                        <?php } ?>
                                        <br>
                                        <?php echo date('F d, Y', strtotime($review->created_date)); ?>
                                    </small>
                                </div>
                                <div class="col-md-3 text-right">
                                    <button class="btn btn-sm btn-default helpful-btn <?php echo $review->user_marked_helpful > 0 ? 'marked' : ''; ?>" 
                                            onclick="markHelpful(<?php echo $review->id; ?>, this)"
                                            <?php echo $review->user_marked_helpful > 0 ? 'disabled' : ''; ?>>
                                        👍 Helpful (<span class="helpful-count-<?php echo $review->id; ?>">
                                            <?php echo $review->helpful_count; ?>
                                        </span>)
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php 
                            }
                        } else { ?>
                        <div class="alert alert-info">
                            No reviews yet. Be the first to review this book!
                        </div>
                        <?php } ?>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-md-4">
                    <div class="book-detail-card">
                        <h4>Book Details</h4>
                        <hr>
                        <?php if($book->bookDescription) { ?>
                        <p><strong>Description:</strong></p>
                        <p style="text-align: justify;">
                            <?php echo nl2br(htmlentities($book->bookDescription)); ?>
                        </p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php');?>
    
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script>
        function markHelpful(reviewId, button) {
            $.post('book-details.php?bookid=<?php echo $bookId; ?>', {
                mark_helpful: true,
                review_id: reviewId
            }, function(response) {
                var data = JSON.parse(response);
                if(data.success) {
                    var currentCount = parseInt($('.helpful-count-' + reviewId).text());
                    $('.helpful-count-' + reviewId).text(currentCount + 1);
                    $(button).addClass('marked').prop('disabled', true);
                }
            });
        }
    </script>
</body>
</html>
<?php } ?>
