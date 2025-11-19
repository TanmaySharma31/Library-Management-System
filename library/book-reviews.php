<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login'])==0) {   
    header('location:index.php');
} else { 

// Submit Review
if(isset($_POST['submit_review'])) {
    $bookId = intval($_POST['book_id']);
    $sapId = $_SESSION['stdid'];
    $rating = intval($_POST['rating']);
    $review = $_POST['review_text'];
    
    // Check if user has returned this book
    $sql = "SELECT * FROM tblissuedbookdetails WHERE BookId=:bookid AND StudentID=:sapid AND ReturnDate IS NOT NULL";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->bindParam(':sapid', $sapId, PDO::PARAM_STR);
    $query->execute();
    $returned = $query->fetch(PDO::FETCH_OBJ);
    
    if($returned) {
        // Check if already reviewed
        $sql = "SELECT * FROM tblreviews WHERE BookId=:bookid AND SapId=:sapid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
        $query->bindParam(':sapid', $sapId, PDO::PARAM_STR);
        $query->execute();
        $existing = $query->fetch(PDO::FETCH_OBJ);
        
        if($existing) {
            $_SESSION['error'] = "You have already reviewed this book!";
        } else {
            // Insert review
            $sql = "INSERT INTO tblreviews (SapId, BookId, rating, review_text) VALUES (:sapid, :bookid, :rating, :review)";
            $query = $dbh->prepare($sql);
            $query->bindParam(':sapid', $sapId, PDO::PARAM_STR);
            $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
            $query->bindParam(':rating', $rating, PDO::PARAM_INT);
            $query->bindParam(':review', $review, PDO::PARAM_STR);
            $query->execute();
            
            $_SESSION['msg'] = "Review submitted successfully!";
        }
    } else {
        $_SESSION['error'] = "You can only review books you have returned!";
    }
    header('location: book-reviews.php');
}

?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>Book Reviews</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <style>
        .star-rating {
            font-size: 24px;
            color: #ddd;
        }
        .star-rating .star {
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating .star.active,
        .star-rating .star:hover {
            color: #ffc107;
        }
        .review-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        .review-rating {
            color: #ffc107;
        }
    </style>
</head>
<body>
<?php include('includes/header.php');?>

<div class="content-wrapper">
    <div class="container">
        <div class="row pad-botm">
            <div class="col-md-12">
                <h4 class="header-line">Book Reviews</h4>
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
        
        <!-- Submit Review -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        Write a Review
                    </div>
                    <div class="panel-body">
                        <form method="post" id="reviewForm">
                            <div class="form-group">
                                <label>Select Book (Only returned books):</label>
                                <select name="book_id" class="form-control" required>
                                    <option value="">-- Select Book --</option>
<?php 
$sapId = $_SESSION['stdid'];
// Show only returned books that haven't been reviewed yet
$sql = "SELECT DISTINCT b.id, b.BookName, b.BookCode 
        FROM tblbooks b 
        INNER JOIN tblissuedbookdetails ibd ON b.id = ibd.BookId 
        WHERE ibd.StudentID=:sapid AND ibd.ReturnDate IS NOT NULL
        AND b.id NOT IN (SELECT BookId FROM tblreviews WHERE SapId=:sapid2)
        ORDER BY b.BookName";
$query = $dbh->prepare($sql);
$query->bindParam(':sapid', $sapId, PDO::PARAM_STR);
$query->bindParam(':sapid2', $sapId, PDO::PARAM_STR);
$query->execute();
$books = $query->fetchAll(PDO::FETCH_OBJ);

foreach($books as $book) { ?>
                                    <option value="<?php echo $book->id; ?>">
                                        <?php echo htmlentities($book->BookName . " (" . $book->BookCode . ")"); ?>
                                    </option>
<?php } ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Rating:</label>
                                <div class="star-rating" id="starRating">
                                    <span class="star" data-value="1">★</span>
                                    <span class="star" data-value="2">★</span>
                                    <span class="star" data-value="3">★</span>
                                    <span class="star" data-value="4">★</span>
                                    <span class="star" data-value="5">★</span>
                                </div>
                                <input type="hidden" name="rating" id="ratingValue" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Your Review:</label>
                                <textarea name="review_text" class="form-control" rows="4" 
                                          placeholder="Share your thoughts about this book..." required></textarea>
                            </div>
                            
                            <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- My Reviews -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        My Reviews
                    </div>
                    <div class="panel-body">
<?php 
$sql = "SELECT r.*, b.BookName, b.ISBNNumber 
        FROM tblreviews r 
        INNER JOIN tblbooks b ON r.BookId = b.id 
        WHERE r.SapId=:sapid 
        ORDER BY r.review_date DESC";
$query = $dbh->prepare($sql);
$query->bindParam(':sapid', $sapId, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);

if($query->rowCount() > 0) {
    foreach($results as $result) { 
        $stars = str_repeat('★', $result->rating) . str_repeat('☆', 5 - $result->rating);
?>
                        <div class="review-card">
                            <h4><?php echo htmlentities($result->BookName); ?></h4>
                            <p class="review-rating"><?php echo $stars; ?> (<?php echo $result->rating; ?>/5)</p>
                            <p><?php echo htmlentities($result->review_text); ?></p>
                            <small class="text-muted">
                                <i class="fa fa-calendar"></i> <?php echo date('d M Y', strtotime($result->review_date)); ?>
                            </small>
                        </div>
<?php 
    }
} else { ?>
                        <p class="text-center">You haven't written any reviews yet.</p>
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
<script>
// Star rating functionality
$(document).ready(function() {
    $('.star').on('click', function() {
        var rating = $(this).data('value');
        $('#ratingValue').val(rating);
        
        $('.star').removeClass('active');
        $('.star').each(function() {
            if($(this).data('value') <= rating) {
                $(this).addClass('active');
            }
        });
    });
    
    $('.star').on('mouseenter', function() {
        var rating = $(this).data('value');
        $('.star').each(function() {
            if($(this).data('value') <= rating) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });
    });
    
    $('#starRating').on('mouseleave', function() {
        var currentRating = $('#ratingValue').val();
        $('.star').removeClass('active');
        if(currentRating) {
            $('.star').each(function() {
                if($(this).data('value') <= currentRating) {
                    $(this).addClass('active');
                }
            });
        }
    });
});
</script>
</body>
</html>
<?php } ?>
