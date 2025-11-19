<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login'])==0) {   
    header('location:index.php');
} else {
    
    // Check if book ID is provided
    if(!isset($_GET['bookid'])) {
        header('location:dashboard.php');
        exit;
    }
    
    $bookId = intval($_GET['bookid']);
    $studentId = $_SESSION['stdid'];
    
    // Verify that student has returned this book
    $sql = "SELECT COUNT(*) as count FROM tblissuedbookdetails 
            WHERE BookId=:bookid AND StudentID=:sid AND ReturnDate IS NOT NULL";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_OBJ);
    
    if($result->count == 0) {
        $_SESSION['error'] = "You can only review books you've borrowed and returned";
        header('location:dashboard.php');
        exit;
    }
    
    // Check if already reviewed
    $sql = "SELECT id FROM tblreviews WHERE BookId=:bookid AND StudentId=:sid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
    $query->execute();
    
    if($query->rowCount() > 0) {
        $_SESSION['error'] = "You have already reviewed this book";
        header('location:book-details.php?bookid='.$bookId);
        exit;
    }
    
    // Handle review submission
    if(isset($_POST['submit'])) {
        $rating = intval($_POST['rating']);
        $title = $_POST['title'];
        $reviewText = $_POST['review'];
        
        if($rating < 1 || $rating > 5) {
            $_SESSION['error'] = "Please select a rating between 1 and 5 stars";
        } elseif(strlen($reviewText) < 50) {
            $_SESSION['error'] = "Review must be at least 50 characters long";
        } else {
            $sql = "INSERT INTO tblreviews(BookId, StudentId, rating, review_title, review_text, is_verified_borrower, status) 
                    VALUES(:bookid, :sid, :rating, :title, :review, 1, 'approved')";
            $query = $dbh->prepare($sql);
            $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
            $query->bindParam(':sid', $studentId, PDO::PARAM_STR);
            $query->bindParam(':rating', $rating, PDO::PARAM_INT);
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':review', $reviewText, PDO::PARAM_STR);
            $query->execute();
            
            $_SESSION['msg'] = "Thank you! Your review has been submitted successfully.";
            header('location:book-details.php?bookid='.$bookId);
            exit;
        }
    }
    
    // Get book details
    $sql = "SELECT b.*, a.AuthorName, c.CategoryName 
            FROM tblbooks b
            LEFT JOIN tblauthors a ON b.AuthorId = a.id
            LEFT JOIN tblcategory c ON b.CatId = c.id
            WHERE b.id=:bookid";
    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookId, PDO::PARAM_INT);
    $query->execute();
    $book = $query->fetch(PDO::FETCH_OBJ);
    
    if(!$book) {
        header('location:dashboard.php');
        exit;
    }
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>Submit Review | Online Library Management System</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <style>
        .rating-container {
            font-size: 40px;
            margin: 20px 0;
        }
        .star {
            cursor: pointer;
            color: #ddd;
            transition: color 0.2s;
        }
        .star:hover, .star.active {
            color: #ffc107;
        }
        .book-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .review-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .char-counter {
            float: right;
            color: #6c757d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>
    
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">✍️ Write a Review</h4>
                </div>
            </div>
            
            <?php if($_SESSION['error']!='') { ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo htmlentities($_SESSION['error']); ?>
                <?php $_SESSION['error']=''; ?>
            </div>
            <?php } ?>
            
            <div class="row">
                <div class="col-md-8 col-md-offset-2">
                    <!-- Book Info -->
                    <div class="book-info">
                        <div class="row">
                            <div class="col-md-3">
                                <?php if($book->bookImage) { ?>
                                    <img src="admin/bookimg/<?php echo htmlentities($book->bookImage); ?>" 
                                         width="100%" style="border-radius: 5px;">
                                <?php } ?>
                            </div>
                            <div class="col-md-9">
                                <h3 style="margin-top: 0;"><?php echo htmlentities($book->BookName); ?></h3>
                                <p><strong>Author:</strong> <?php echo htmlentities($book->AuthorName); ?></p>
                                <p><strong>ISBN:</strong> <?php echo htmlentities($book->ISBNNumber); ?></p>
                                <p><strong>Category:</strong> <?php echo htmlentities($book->CategoryName); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Review Form -->
                    <div class="review-form">
                        <form method="post" onsubmit="return validateReview();">
                            <div class="form-group">
                                <label><strong>Your Rating <span style="color:red;">*</span></strong></label>
                                <div class="rating-container">
                                    <span class="star" data-rating="1">★</span>
                                    <span class="star" data-rating="2">★</span>
                                    <span class="star" data-rating="3">★</span>
                                    <span class="star" data-rating="4">★</span>
                                    <span class="star" data-rating="5">★</span>
                                </div>
                                <input type="hidden" name="rating" id="rating" value="0" required>
                                <small class="text-muted">Click on the stars to rate this book</small>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Review Title <span style="color:red;">*</span></strong></label>
                                <input type="text" name="title" class="form-control" 
                                       placeholder="Sum up your review in one line" 
                                       maxlength="200" required>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Your Review <span style="color:red;">*</span></strong></label>
                                <span class="char-counter">
                                    <span id="charCount">0</span>/1000 (min 50 characters)
                                </span>
                                <textarea name="review" id="reviewText" class="form-control" 
                                          rows="8" maxlength="1000" required
                                          placeholder="Share your thoughts about this book. What did you like or dislike? Would you recommend it to others?"></textarea>
                                <small class="text-muted">
                                    💡 Tip: Good reviews mention specific aspects like plot, writing style, 
                                    characters, or what you learned from the book.
                                </small>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>📌 Review Guidelines:</strong>
                                <ul style="margin-bottom: 0;">
                                    <li>Be honest and constructive</li>
                                    <li>Avoid spoilers or mark them clearly</li>
                                    <li>Respect other readers' opinions</li>
                                    <li>Focus on the book content, not irrelevant topics</li>
                                </ul>
                            </div>
                            
                            <div class="form-group text-center">
                                <button type="submit" name="submit" class="btn btn-primary btn-lg">
                                    Submit Review
                                </button>
                                <a href="book-details.php?bookid=<?php echo $bookId; ?>" 
                                   class="btn btn-default btn-lg">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php');?>
    
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script>
        // Star rating functionality
        $(document).ready(function() {
            $('.star').on('click', function() {
                var rating = $(this).data('rating');
                $('#rating').val(rating);
                
                $('.star').removeClass('active');
                $('.star').each(function() {
                    if($(this).data('rating') <= rating) {
                        $(this).addClass('active');
                    }
                });
            });
            
            $('.star').on('mouseenter', function() {
                var rating = $(this).data('rating');
                $('.star').each(function() {
                    if($(this).data('rating') <= rating) {
                        $(this).css('color', '#ffc107');
                    } else {
                        $(this).css('color', '#ddd');
                    }
                });
            });
            
            $('.rating-container').on('mouseleave', function() {
                var selectedRating = $('#rating').val();
                $('.star').each(function() {
                    if($(this).data('rating') <= selectedRating) {
                        $(this).css('color', '#ffc107');
                    } else {
                        $(this).css('color', '#ddd');
                    }
                });
            });
            
            // Character counter
            $('#reviewText').on('input', function() {
                var length = $(this).val().length;
                $('#charCount').text(length);
                
                if(length < 50) {
                    $('#charCount').css('color', '#dc3545');
                } else {
                    $('#charCount').css('color', '#28a745');
                }
            });
        });
        
        function validateReview() {
            var rating = $('#rating').val();
            var reviewText = $('#reviewText').val();
            
            if(rating == 0) {
                alert('Please select a rating');
                return false;
            }
            
            if(reviewText.length < 50) {
                alert('Review must be at least 50 characters long');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>
<?php } ?>
