<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Trip Nest</title>
    <link rel="stylesheet" href="css.css">
    <style>
        .container { max-width: 600px; margin: 100px auto; padding: 2rem; text-align: center; }
        .success-icon { font-size: 5rem; color: green; margin-bottom: 1rem; }
        .btn { display: inline-block; padding: 1rem 2rem; background: #031881; color: white; text-decoration: none; border-radius: 0.5rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <?php
        $msg = "Payment Processing...";
        $status = "pending";
        
        if (!isset($_GET['data'])) {
            // Handle malformed URL from eSewa (e.g. ?data= appended to existing query string)
            $request_uri = $_SERVER['REQUEST_URI'];
            if (strpos($request_uri, '?data=') !== false) {
                $parts = explode('?data=', $request_uri);
                $_GET['data'] = urldecode($parts[1]);
            }
        }

        if (isset($_GET['data'])) {
            $json = base64_decode($_GET['data']);
            $response = json_decode($json, true);
            
            if ($response && isset($response['status']) && $response['status'] == 'COMPLETE') {
                $status = "success";
                $msg = "Payment Successful! Your booking is pending approval.";
                
                // Update Database
                $uuid = $response['transaction_uuid'];
                $parts = explode('-', $uuid);
                if (count($parts) >= 2) {
                    $type = $parts[0]; // 'room' or 'activity'
                    $id = (int)$parts[1];
                    
                    $conn = mysqli_connect("localhost", "root", "", "tripnest_db");
                    if ($conn) {
                        if ($type == 'room') {
                            $sql = "UPDATE hotel_bookings SET status = 'pending' WHERE id = $id";
                        } else {
                            $sql = "UPDATE activity_bookings SET status = 'pending' WHERE id = $id";
                        }
                        mysqli_query($conn, $sql);
                        
                        // Remove from wishlist if applicable
                        if (isset($_GET['wishlist_id'])) {
                            $wid = (int)$_GET['wishlist_id'];
                            if ($wid > 0 && isset($_SESSION['user_id'])) {
                                $uid = $_SESSION['user_id'];
                                $del_sql = "DELETE FROM wishlist WHERE id = $wid AND user_id = $uid";
                                mysqli_query($conn, $del_sql);
                            }
                        }
                        
                        mysqli_close($conn);
                    }
                }
            } else {
                $status = "failed";
                $msg = "Payment verification failed.";
            }
        } elseif (isset($_GET['q']) && $_GET['q'] == 'su') {
             // Legacy fallback (if used)
             $status = "success";
             $msg = "Payment Successful (Legacy).";
        }
        ?>
        
        <?php if ($status == 'success'): ?>
            <div class="success-icon">✅</div>
            <h1>Success!</h1>
            <p><?php echo $msg; ?></p>
            <a href="bookings.php" class="btn">View My Bookings</a>
        <?php else: ?>
            <div class="success-icon" style="color: red;">❌</div>
            <h1>Payment Issue</h1>
            <p><?php echo $msg; ?></p>
            <a href="Tourism.php" class="btn">Return Home</a>
        <?php endif; ?>
    </div>
</body>
</html>
