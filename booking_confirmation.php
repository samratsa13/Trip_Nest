<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "tripnest_db");
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

$user_id = $_SESSION['user_id'];
$item_type = $_GET['type'] ?? '';
$item_id = (int)($_GET['id'] ?? 0);
$wishlist_id = (int)($_GET['wishlist_id'] ?? 0);

if (!$item_type || !$item_id) {
    die("Invalid booking request.");
}

// Fetch Item Details
$item = null;
if ($item_type == 'room') {
    // Join with hotel to get hotel name/image
    $sql = "SELECT r.*, h.name as hotel_name, h.image_path, h.location 
            FROM hotel_rooms r 
            JOIN hotels h ON r.hotel_id = h.id 
            WHERE r.id = $item_id";
    $result = mysqli_query($conn, $sql);
    $item = mysqli_fetch_assoc($result);
} elseif ($item_type == 'activity') {
    $sql = "SELECT * FROM activities WHERE id = $item_id";
    $result = mysqli_query($conn, $sql);
    $item = mysqli_fetch_assoc($result);
    if ($item) {
        $item['item_name'] = $item['name'];
    }
}

if (!$item) {
    die("Item not found.");
}

// Handle Payment Request (POST)
$esewa_form = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect specific inputs
    $guest_name = mysqli_real_escape_string($conn, $_POST['guest_name']);
    $guest_email = mysqli_real_escape_string($conn, $_POST['guest_email']);
    $guest_phone = mysqli_real_escape_string($conn, $_POST['guest_phone']);
    $quantity = (int)$_POST['quantity'];
    
    $total_price = 0;
    $booking_id = 0;
    
    // Validation simple
    if (empty($guest_name) || empty($guest_email) || empty($guest_phone)) {
        $error_msg = "Please fill all fields.";
    } else {
        if ($item_type == 'room') {
            $check_in = $_POST['check_in'];
            $check_out = $_POST['check_out'];
            
            if (strtotime($check_out) <= strtotime($check_in)) {
                $error_msg = "Check-out must be after check-in.";
            } else {
                $nights = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
                $total_price = $item['price_npr'] * $nights * $quantity;
                
                // Insert Pending Booking
                $hotel_id = $item['hotel_id'];
                $insert_sql = "INSERT INTO hotel_bookings 
                    (user_id, hotel_id, room_id, check_in, check_out, guest_name, guest_email, guest_phone, total_price_npr, quantity, status) 
                    VALUES ($user_id, $hotel_id, $item_id, '$check_in', '$check_out', '$guest_name', '$guest_email', '$guest_phone', $total_price, $quantity, 'pending_payment')";
                
                if (mysqli_query($conn, $insert_sql)) {
                    $booking_id = mysqli_insert_id($conn);
                } else {
                    $error_msg = "Database Error: " . mysqli_error($conn);
                }
            }
        } elseif ($item_type == 'activity') {
            $booking_date = $_POST['booking_date'];
            $total_price = $item['price_npr'] * $quantity;
            
            $insert_sql = "INSERT INTO activity_bookings 
                (user_id, activity_id, booking_date, guest_name, guest_email, guest_phone, quantity, total_price_npr, status) 
                VALUES ($user_id, $item_id, '$booking_date', '$guest_name', '$guest_email', '$guest_phone', $quantity, $total_price, 'pending_payment')";
            
            if (mysqli_query($conn, $insert_sql)) {
                $booking_id = mysqli_insert_id($conn);
            } else {
                $error_msg = "Database Error: " . mysqli_error($conn);
            }
        }
    }
    
    if ($booking_id > 0) {
        // Prepare eSewa Form Data
        // Use TEST environment
        $transaction_uuid = $item_type . '-' . $booking_id . '-' . time(); // Unique ID
        $product_code = 'EPAYTEST';
        $amount = $total_price;
        $tax_amount = 0;
        $product_service_charge = 0;
        $product_delivery_charge = 0;
        $total_amount = $amount + $tax_amount + $product_service_charge + $product_delivery_charge;
        $success_url = "http://localhost/Tourism/esewa_success.php?oid=$booking_id&otype=$item_type&wishlist_id=$wishlist_id";
        $failure_url = "http://localhost/Tourism/esewa_failure.php";
        $signed_field_names = "total_amount,transaction_uuid,product_code";
        
        // Generate Signature
        $message = "total_amount=$total_amount,transaction_uuid=$transaction_uuid,product_code=$product_code";
        $secret_key = "8gBm/:&EnhH.1/q";
        $s = hash_hmac('sha256', $message, $secret_key, true);
        $signature = base64_encode($s);
        
        // eSewa Button Form
        $esewa_form = "
        <div class='esewa-redirect'>
            <h3>Confirm Payment</h3>
            <p>Total Amount: NPR " . number_format($total_amount, 2) . "</p>
            <form action='https://rc-epay.esewa.com.np/api/epay/main/v2/form' method='POST'>
                <input type='hidden' name='amount' value='$amount'>
                <input type='hidden' name='tax_amount' value='$tax_amount'>
                <input type='hidden' name='product_service_charge' value='$product_service_charge'>
                <input type='hidden' name='product_delivery_charge' value='$product_delivery_charge'>
                <input type='hidden' name='total_amount' value='$total_amount'>
                <input type='hidden' name='transaction_uuid' value='$transaction_uuid'>
                <input type='hidden' name='product_code' value='$product_code'>
                <input type='hidden' name='success_url' value='$success_url'>
                <input type='hidden' name='failure_url' value='$failure_url'>
                <input type='hidden' name='signed_field_names' value='$signed_field_names'>
                <input type='hidden' name='signature' value='$signature'>
                <button type='submit' class='pay-btn'><i class='fas fa-wallet'></i> Pay with eSewa</button>
            </form>
        </div>";
        
        // Remove from wishlist if needed (optional, maybe do it on success?)
        // Doing it here or on success is fine. Let's do it on success.
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Confirmation - Trip Nest</title>
    <link rel="stylesheet" href="css.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 800px; margin: 100px auto; padding: 2rem; background: white; border-radius: 1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .item-summary { display: flex; gap: 2rem; border-bottom: 2px solid #eee; padding-bottom: 2rem; margin-bottom: 2rem; }
        .item-img { width: 150px; height: 100px; object-fit: cover; border-radius: 0.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #ccc; border-radius: 0.5rem; }
        .price-summary { background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem; text-align: right; }
        .submit-btn { width: 100%; background: #031881; color: white; padding: 1rem; border: none; border-radius: 0.5rem; font-size: 1.1rem; cursor: pointer; margin-top: 1rem; }
        .pay-btn { background: #60bb46; color: white; padding: 1rem 2rem; border: none; border-radius: 0.5rem; font-size: 1.2rem; cursor: pointer; width: 100%; margin-top: 1rem; }
        .pay-btn:hover { background: #4cae32; }
        .error { color: red; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <h1 class="logo">Trip Nest</h1>
        <div class="nav-links">
            <a href="Tourism.php">Home</a>
            <a href="Tourism.php#itenary">Itinerary</a>
            <a href="destination.php">Destinations</a>
            <a href="Tourism.php#contact">Contact</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="bookings.php">Bookings</a>
                <a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a>
                <a href="dashboard.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="login.php">Join Us</a>
            <?php endif; ?>
        </div>
        <div class="menu-btn">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </nav>

    <div class="container">
        <?php if ($esewa_form): ?>
            <?php echo $esewa_form; ?>
        <?php else: ?>
            <h2>Confirm Booking</h2>
            <?php if ($error_msg): ?>
                <div class="error"><?php echo $error_msg; ?></div>
            <?php endif; ?>
            
            <div class="item-summary">
                <img src="<?php echo htmlspecialchars($item['image_path'] ?? $item['image'] ?? 'img/default.jpg'); ?>" class="item-img" alt="Item">
                <div>
                    <h3><?php echo htmlspecialchars($item['hotel_name'] ?? $item['item_name']); ?></h3>
                    <?php if ($item_type == 'room'): ?>
                        <p><?php echo htmlspecialchars($item['room_type'] . " - " . $item['ac_type']); ?></p>
                        <p>NPR <?php echo number_format($item['price_npr'], 2); ?> / night</p>
                    <?php else: ?>
                        <p>Total Price per person: NPR <?php echo number_format($item['price_npr'], 2); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Guest Name</label>
                    <input type="text" name="guest_name" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Guest Email</label>
                    <input type="email" name="guest_email" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Guest Phone</label>
                    <input type="tel" name="guest_phone" required placeholder="98XXXXXXXX">
                </div>
                <div class="form-group">
                    <label>Quantity <?php echo $item_type=='room'?'(Rooms)':'(Tickets)'; ?></label>
                    <input type="number" name="quantity" id="quantity" value="1" min="1" required>
                </div>

                <?php if ($item_type == 'room'): ?>
                    <div class="form-group">
                        <label>Check-in</label>
                        <input type="date" name="check_in" id="check_in" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Check-out</label>
                        <input type="date" name="check_out" id="check_out" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                <?php endif; ?>

                <div class="price-summary">
                    <h3>Estimated Total: <span id="total_display">NPR 0</span></h3>
                </div>

                <div class="form-group">
                    <label>Payment Method</label>
                    <select disabled style="width: 100%; padding: 0.8rem; background: #eee;">
                        <option>eSewa (Online Payment)</option>
                    </select>
                </div>

                <button type="submit" class="submit-btn">Proceed to Payment</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        const price = <?php echo $item['price_npr']; ?>;
        const type = '<?php echo $item_type; ?>';
        
        function calculate() {
            const qty = document.getElementById('quantity').value;
            let total = 0;
            if (type === 'room') {
                const start = document.getElementById('check_in').value;
                const end = document.getElementById('check_out').value;
                if (start && end) {
                    const d1 = new Date(start);
                    const d2 = new Date(end);
                    const diffTime = Math.abs(d2 - d1);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
                    if (diffDays > 0) {
                        total = price * diffDays * qty;
                    }
                }
            } else {
                total = price * qty;
            }
            document.getElementById('total_display').innerText = "NPR " + total.toFixed(2);
        }

        if (document.getElementById('quantity')) {
            document.getElementById('quantity').addEventListener('change', calculate);
            if (type === 'room') {
                document.getElementById('check_in').addEventListener('change', calculate);
                document.getElementById('check_out').addEventListener('change', calculate);
            }
        }
    </script>
</body>
</html>
