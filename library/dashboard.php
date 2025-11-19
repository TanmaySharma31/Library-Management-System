<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['login'])==0)
  { 
header('location:index.php');
}
else{?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | User Dash Board</title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style>
        .notification-popup {
            position: fixed;
            top: 80px;
            right: 20px;
            max-width: 400px;
            z-index: 9999;
            animation: slideInRight 0.5s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .fine-widget {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .fine-amount {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .pay-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 10px 30px;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pay-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .due-book-item {
            background: white;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 4px solid #ff6b6b;
        }
        
        .near-due-item {
            border-left-color: #ffa500;
        }
        
        .close-popup {
            float: right;
            font-size: 1.5em;
            cursor: pointer;
            opacity: 0.7;
        }
        
        .close-popup:hover {
            opacity: 1;
        }
    </style>

</head>
<body>
      <!------MENU SECTION START-->
<?php include('includes/header.php');?>
<!-- MENU SECTION END-->
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-12">
                <h4 class="header-line">USER DASHBOARD</h4>
                
                            </div>

        </div>
             
             <div class="row">

<?php 
$sid=$_SESSION['stdid'];

// Auto-calculate fine for current student
$today = date('Y-m-d');
$finePerDay = 10; // ₹10 per day per book

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
if($totalFine >= 0) {
    $sqlUpdateFine = "UPDATE tblstudents SET Fine = :fine WHERE StudentId = :sid";
    $queryUpdateFine = $dbh->prepare($sqlUpdateFine);
    $queryUpdateFine->bindParam(':fine', $totalFine, PDO::PARAM_STR);
    $queryUpdateFine->bindParam(':sid', $sid, PDO::PARAM_STR);
    $queryUpdateFine->execute();
}

// Get student fine amount
$sqlFine = "SELECT Fine FROM tblstudents WHERE StudentId=:sid";
$queryFine = $dbh->prepare($sqlFine);
$queryFine->bindParam(':sid', $sid, PDO::PARAM_STR);
$queryFine->execute();
$fineResult = $queryFine->fetch(PDO::FETCH_OBJ);
$fineAmount = $fineResult ? $fineResult->Fine : 0;

// Check for overdue and near-due books
$today = date('Y-m-d');
$nearDueDate = date('Y-m-d', strtotime('+3 days')); // Books due within 3 days

$sqlDueBooks = "SELECT tblbooks.BookName, tblbooks.BookCode, 
                tblissuedbookdetails.IssuesDate, tblissuedbookdetails.ScheduledReturnDate,
                DATEDIFF(:today, tblissuedbookdetails.ScheduledReturnDate) as OverdueDays
                FROM tblissuedbookdetails 
                JOIN tblbooks ON tblissuedbookdetails.BookId = tblbooks.id
                WHERE tblissuedbookdetails.StudentID=:sid 
                AND tblissuedbookdetails.ReturnDate IS NULL
                AND tblissuedbookdetails.ScheduledReturnDate < :neardue
                ORDER BY tblissuedbookdetails.ScheduledReturnDate ASC";
$queryDue = $dbh->prepare($sqlDueBooks);
$queryDue->bindParam(':sid', $sid, PDO::PARAM_STR);
$queryDue->bindParam(':today', $today, PDO::PARAM_STR);
$queryDue->bindParam(':neardue', $nearDueDate, PDO::PARAM_STR);
$queryDue->execute();
$dueBooks = $queryDue->fetchAll(PDO::FETCH_OBJ);
?>

<!-- Notification Popup for Due/Near-Due Books -->
<?php if(count($dueBooks) > 0): ?>
<div class="notification-popup" id="dueNotification">
    <div class="panel panel-danger">
        <div class="panel-heading">
            <span class="close-popup" onclick="closeNotification()">&times;</span>
            <strong><i class="fa fa-warning"></i> Book Return Alerts</strong>
        </div>
        <div class="panel-body" style="max-height: 300px; overflow-y: auto;">
            <?php foreach($dueBooks as $book): ?>
                <div class="<?php echo $book->OverdueDays > 0 ? 'due-book-item' : 'near-due-item'; ?>">
                    <strong><?php echo htmlentities($book->BookName); ?></strong>
                    <br>
                    <small>Code: <?php echo htmlentities($book->BookCode); ?></small>
                    <br>
                    <?php if($book->OverdueDays > 0): ?>
                        <span class="text-danger">
                            <i class="fa fa-exclamation-circle"></i> 
                            <strong>OVERDUE by <?php echo $book->OverdueDays; ?> day(s)</strong>
                        </span>
                    <?php else: ?>
                        <span class="text-warning">
                            <i class="fa fa-clock-o"></i> 
                            Due on <?php echo date('M d, Y', strtotime($book->ScheduledReturnDate)); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Fine Display Widget -->
<div class="col-md-3 col-sm-6 col-xs-12">
    <div class="fine-widget text-center">
        <i class="fa fa-money fa-3x"></i>
        <h4>Total Fine Due</h4>
        <div class="fine-amount" id="fineAmount">₹<?php echo number_format($fineAmount, 2); ?></div>
        <?php if($fineAmount > 0): ?>
            <button class="pay-btn" onclick="payFine()">
                <i class="fa fa-credit-card"></i> Pay Now
            </button>
        <?php else: ?>
            <p style="margin-top: 10px; opacity: 0.9;">No dues pending</p>
        <?php endif; ?>
    </div>
</div>

            


                 <div class="col-md-3 col-sm-3 col-xs-6">
                      <div class="alert alert-info back-widget-set text-center">
                            <i class="fa fa-bars fa-5x"></i>
<?php 
$sid=$_SESSION['stdid'];
$sql1 ="SELECT id from tblissuedbookdetails where StudentID=:sid";
$query1 = $dbh -> prepare($sql1);
$query1->bindParam(':sid',$sid,PDO::PARAM_STR);
$query1->execute();
$results1=$query1->fetchAll(PDO::FETCH_OBJ);
$issuedbooks=$query1->rowCount();
?>

                            <h3><?php echo htmlentities($issuedbooks);?> </h3>
                            Book Issued
                        </div>
                    </div>
             
               <div class="col-md-3 col-sm-3 col-xs-6">
                      <div class="alert alert-warning back-widget-set text-center">
                            <i class="fa fa-recycle fa-5x"></i>
<?php 
$rsts=0;
$sql2 ="SELECT id from tblissuedbookdetails where StudentID=:sid and RetrunStatus=:rsts";
$query2 = $dbh -> prepare($sql2);
$query2->bindParam(':sid',$sid,PDO::PARAM_STR);
$query2->bindParam(':rsts',$rsts,PDO::PARAM_STR);
$query2->execute();
$results2=$query2->fetchAll(PDO::FETCH_OBJ);
$returnedbooks=$query2->rowCount();
?>

                            <h3><?php echo htmlentities($returnedbooks);?></h3>
                          Books Not Returned Yet
                        </div>
                    </div>
        </div>

        <!-- Overdue Books Section -->
        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <i class="fa fa-exclamation-triangle"></i> Overdue Books
                    </div>
                    <div class="panel-body">
                        <?php 
                        // Get overdue books for current student
                        $today = date('Y-m-d');
                        $sqlOverdueList = "SELECT tblbooks.BookName, tblbooks.BookCode, 
                                          tblissuedbookdetails.IssuesDate, 
                                          tblissuedbookdetails.ScheduledReturnDate,
                                          DATEDIFF(:today, tblissuedbookdetails.ScheduledReturnDate) as OverdueDays
                                          FROM tblissuedbookdetails 
                                          JOIN tblbooks ON tblissuedbookdetails.BookId = tblbooks.id
                                          WHERE tblissuedbookdetails.StudentID = :sid 
                                          AND tblissuedbookdetails.ReturnDate IS NULL
                                          AND tblissuedbookdetails.ScheduledReturnDate < :today
                                          ORDER BY tblissuedbookdetails.ScheduledReturnDate ASC";
                        $queryOverdueList = $dbh->prepare($sqlOverdueList);
                        $queryOverdueList->bindParam(':sid', $sid, PDO::PARAM_STR);
                        $queryOverdueList->bindParam(':today', $today, PDO::PARAM_STR);
                        $queryOverdueList->execute();
                        $overdueBooksList = $queryOverdueList->fetchAll(PDO::FETCH_OBJ);
                        
                        if(count($overdueBooksList) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Book Name</th>
                                            <th>Book Code</th>
                                            <th>Issued Date</th>
                                            <th>Due Date</th>
                                            <th>Days Overdue</th>
                                            <th>Fine (₹10/day)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $cnt = 1;
                                        $finePerDay = 10;
                                        foreach($overdueBooksList as $overdueBook): 
                                            $bookFine = $overdueBook->OverdueDays * $finePerDay;
                                        ?>
                                        <tr>
                                            <td><?php echo $cnt; ?></td>
                                            <td><?php echo htmlentities($overdueBook->BookName); ?></td>
                                            <td><?php echo htmlentities($overdueBook->BookCode); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($overdueBook->IssuesDate)); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($overdueBook->ScheduledReturnDate)); ?></td>
                                            <td>
                                                <span class="label label-danger">
                                                    <?php echo $overdueBook->OverdueDays; ?> days
                                                </span>
                                            </td>
                                            <td>
                                                <strong style="color: #d9534f;">₹<?php echo number_format($bookFine, 2); ?></strong>
                                            </td>
                                        </tr>
                                        <?php 
                                        $cnt++;
                                        endforeach; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-warning">
                                <strong><i class="fa fa-info-circle"></i> Note:</strong> 
                                Please return these books as soon as possible to avoid additional fines. 
                                Current fine rate is ₹10 per day per book.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> Great! You have no overdue books.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


            
    </div>
    </div>
     <!-- CONTENT-WRAPPER SECTION END-->
<?php include('includes/footer.php');?>
      <!-- FOOTER SECTION END-->
    <!-- JAVASCRIPT FILES PLACED AT THE BOTTOM TO REDUCE THE LOADING TIME  -->
    <!-- CORE JQUERY  -->
    <script src="assets/js/jquery-1.10.2.js"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="assets/js/bootstrap.js"></script>
      <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
    
    <script>
        function closeNotification() {
            document.getElementById('dueNotification').style.display = 'none';
        }
        
        function payFine() {
            if(confirm('Are you sure you want to pay the fine? This will process payment of ₹<?php echo number_format($fineAmount, 2); ?>')) {
                $.ajax({
                    url: 'pay-fine.php',
                    type: 'POST',
                    data: { pay_fine: true },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            alert(response.message);
                            $('#fineAmount').text('₹0.00');
                            $('.pay-btn').parent().html('<p style="margin-top: 10px; opacity: 0.9;">No dues pending</p>');
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            }
        }
        
        // Auto-hide notification after 30 seconds
        setTimeout(function() {
            var notification = document.getElementById('dueNotification');
            if(notification) {
                notification.style.animation = 'slideInRight 0.5s reverse';
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 500);
            }
        }, 30000);
    </script>
</body>
</html>
<?php } ?>
