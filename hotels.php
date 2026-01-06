<?php
session_start();
require_once 'db_connection.php';

// Handle hotel booking
$booking_success = '';
$booking_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_hotel'])) {
    if (!isset($_SESSION['user_id'])) {
        $booking_error = "Please login to book a hotel.";
    } else {
        $user_id = $_SESSION['user_id'];
        $hotel_id = intval($_POST['hotel_id']);
        $room_id = intval($_POST['room_id']);
        $check_in = $_POST['check_in'];
        $check_out = $_POST['check_out'];
        $guest_name = trim($_POST['guest_name']);
        $guest_email = trim($_POST['guest_email']);
        $guest_phone = trim($_POST['guest_phone']);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        // Validation
        if (empty($guest_name) || empty($guest_email) || empty($guest_phone)) {
            $booking_error = "Please fill all guest information fields.";
        } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
            $booking_error = "Please enter a valid email address.";
        } elseif (strtotime($check_in) >= strtotime($check_out)) {
            $booking_error = "Check-out date must be after check-in date.";
        } elseif ($quantity < 1) {
            $booking_error = "Please select at least 1 room.";
        } else {
            // Get room price
            // Get room price and quantity
            $room_stmt = $pdo->prepare("SELECT price_npr, quantity FROM hotel_rooms WHERE id = ?");
            $room_stmt->execute([$room_id]);
            $room = $room_stmt->fetch();
            
            if ($room) {
                // Check availability
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings 
                    WHERE room_id = ? 
                    AND status != 'rejected' 
                    AND NOT (check_out <= ? OR check_in >= ?)");
                $check_stmt->execute([$room_id, $check_in, $check_out]);
                $booked_count = $check_stmt->fetchColumn();

                $available_rooms = ($room['quantity'] ?? 1) - $booked_count;
                
                if ($available_rooms <= 0) {
                    $booking_error = "Sorry, this room is fully booked for the selected dates.";
                } elseif ($quantity > $available_rooms) {
                    $booking_error = "Only " . $available_rooms . " room(s) available for the selected dates. You requested " . $quantity . ".";
                } else {
                    $nights = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
                    $total_price = $room['price_npr'] * $nights * $quantity;
                    
                    // Create booking
                    $stmt = $pdo->prepare("INSERT INTO hotel_bookings (user_id, hotel_id, room_id, check_in, check_out, guest_name, guest_email, guest_phone, total_price_npr, quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                    if ($stmt->execute([$user_id, $hotel_id, $room_id, $check_in, $check_out, $guest_name, $guest_email, $guest_phone, $total_price, $quantity])) {
                        $booking_success = "Booking request submitted successfully! Admin will review and approve your booking.";
                    } else {
                        $booking_error = "Error submitting booking. Please try again.";
                    }
                }
            } else {
                $booking_error = "Invalid room selected.";
            }
        }
    }
}

// Get hotels
try {
    $hotels = $pdo->query("SELECT * FROM hotels WHERE status = 'active' ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $hotels = [];
}

// Handle server-side search with searching algorithm
if (isset($_GET['q']) && $_GET['q'] !== '') {
    $search_key = strtolower(trim($_GET['q']));
    
    /* 
       MERGE SORT (By Name)
    */
    function mergeSortHotels(array $arr): array {
        $count = count($arr);
        if ($count <= 1) {
            return $arr;
        }

        $mid = intdiv($count, 2);
        $left = array_slice($arr, 0, $mid);
        $right = array_slice($arr, $mid);

        return mergeHotels(
            mergeSortHotels($left),
            mergeSortHotels($right)
        );
    }

    function mergeHotels(array $left, array $right): array {
        $result = [];
        $i = $j = 0;
        $lCount = count($left);
        $rCount = count($right);

        while ($i < $lCount && $j < $rCount) {
            if (strcasecmp($left[$i]['name'], $right[$j]['name']) <= 0) {
                $result[] = $left[$i++];
            } else {
                $result[] = $right[$j++];
            }
        }

        while ($i < $lCount) {
            $result[] = $left[$i++];
        }

        while ($j < $rCount) {
            $result[] = $right[$j++];
        }

        return $result;
    }

    // Sort hotels before binary search
    $hotels = mergeSortHotels($hotels);
    
    // Binary search function
    function binarySearchHotels($arr, $key) {
        $low = 0;
        $high = count($arr) - 1;
        $results = [];
        
        while ($low <= $high) {
            $mid = floor(($low + $high) / 2);
            $name = strtolower($arr[$mid]['name']);
            
            if (strpos($name, $key) !== false) {
                $results[] = $arr[$mid];
                
                // Left neighbors
                $i = $mid - 1;
                while ($i >= 0 && strpos(strtolower($arr[$i]['name']), $key) !== false) {
                    $results[] = $arr[$i--];
                }
                
                // Right neighbors
                $i = $mid + 1;
                while ($i < count($arr) && strpos(strtolower($arr[$i]['name']), $key) !== false) {
                    $results[] = $arr[$i++];
                }
                break;
            }
            elseif ($key < $name) {
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }
        
        // Sort matched results
        usort($results, function ($a, $b) {
            return strcmp(strtolower($a['name']), strtolower($b['name']));
        });
        
        return $results;
    }
    
    // Perform search
    $hotels = binarySearchHotels($hotels, $search_key);
}

// Get hotel details if ID is provided
$selected_hotel = null;
$hotel_rooms = [];
if (isset($_GET['id'])) {
    $hotel_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM hotels WHERE id = ? AND status = 'active'");
    $stmt->execute([$hotel_id]);
    $selected_hotel = $stmt->fetch();
    
    if ($selected_hotel) {
        $rooms_stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = ? ORDER BY room_type, ac_type");
        $rooms_stmt->execute([$hotel_id]);
        $hotel_rooms = $rooms_stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Hotels - Trip Nest</title>
    <link rel="stylesheet" href="css.css">
    <style>
        .hotels-container {
            max-width: 1200px;
            margin: 100px auto 20px;
            padding: 2rem;
        }
        
        .hotels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .hotel-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .hotel-card:hover {
            transform: translateY(-5px);
        }
        
        .hotel-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .hotel-info {
            padding: 1.5rem;
        }
        
        .hotel-info h3 {
            color: #031881;
            margin-bottom: 0.5rem;
        }
        
        .hotel-location {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .view-btn {
            width: 100%;
            padding: 0.8rem;
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
        }
        
        .hotel-details {
            max-width: 1000px;
            margin: 100px auto 20px;
            padding: 2rem;
        }
        
        .hotel-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .hotel-image-large {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 1rem;
        }
        
        .rooms-section {
            margin-top: 2rem;
        }
        
        .room-card {
            border: 1px solid #e0e0e0;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .room-info h4 {
            color: #031881;
            margin-bottom: 0.5rem;
        }
        
        .room-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #031881;
        }
        
        .booking-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .booking-modal-content {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 0.5rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .field-error {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        .form-group input.error,
        .form-group select.error {
            border-color: #dc3545;
        }
        
        .search-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            padding: 0.8rem 1rem;
            border: 2px solid #031881;
            border-radius: 0.5rem;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(3, 24, 129, 0.1);
        }
        
        .search-button {
            padding: 0.8rem 2rem;
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
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

    <?php if ($selected_hotel): ?>
        <!-- Hotel Details Page -->
        <div class="hotel-details">
            <a href="hotels.php" style="color: #031881; text-decoration: none; margin-bottom: 1rem; display: inline-block;">
                <i class="fas fa-arrow-left"></i> Back to Hotels
            </a>
            
            <?php if ($booking_success): ?>
                <div class="alert alert-success"><?php echo $booking_success; ?></div>
            <?php endif; ?>
            
            <?php if ($booking_error): ?>
                <div class="alert alert-error"><?php echo $booking_error; ?></div>
            <?php endif; ?>
            
            <div class="hotel-header">
                <div>
                    <?php if (!empty($selected_hotel['image_path'])): ?>
                        <img src="<?php echo $selected_hotel['image_path']; ?>" alt="<?php echo htmlspecialchars($selected_hotel['name']); ?>" class="hotel-image-large">
                    <?php endif; ?>
                </div>
                <div>
                    <h1><?php echo htmlspecialchars($selected_hotel['name']); ?></h1>
                    <p style="color: #666; font-size: 1.1rem;">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($selected_hotel['location']); ?>
                    </p>
                    <?php if (!empty($selected_hotel['description'])): ?>
                        <p style="margin-top: 1rem; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($selected_hotel['description'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="rooms-section">
                <h2>Available Rooms</h2>
                <?php if (empty($hotel_rooms)): ?>
                    <p>No rooms available at this time.</p>
                <?php else: ?>
                    <?php foreach ($hotel_rooms as $room): ?>
                        <div class="room-card">
                            <div class="room-info">
                                <h4><?php echo htmlspecialchars($room['room_type']); ?> - <?php echo htmlspecialchars($room['ac_type']); ?></h4>
                            </div>
                            <div>
                                <div class="room-price">NPR <?php echo number_format($room['price_npr'], 2); ?>/night</div>
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] !== 'admin'): ?>
                                    <a href="booking_confirmation.php?id=<?php echo $room['id']; ?>&type=room" class="view-btn" style="text-decoration: none; display: block; text-align: center; margin-bottom: 0.5rem; background: #28a745;">
                                        <i class="fas fa-check"></i> Book Now
                                    </a>
                                    <button class="view-btn" onclick="addToWishlist('room', <?php echo $room['id']; ?>, '<?php echo addslashes($selected_hotel['name']) . ' - ' . $room['room_type']; ?>', '<?php echo $selected_hotel['image_path']; ?>', <?php echo $room['price_npr']; ?>)">
                                        <i class="fas fa-heart"></i> Add to Wishlist
                                    </button>
                                <?php elseif (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin'): ?>
                                    <button class="view-btn" style="background: #999; cursor: not-allowed; opacity: 0.6;" disabled>Not Available</button>
                                <?php else: ?>
                                    <a href="login.php" class="view-btn" style="text-decoration: none; display: block; text-align: center;">Login to Book</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Hotels List Page -->
        <div class="hotels-container">
            <h1 style="text-align: center; color: #031881; margin-bottom: 1rem;">Our Hotels</h1>
            <p style="text-align: center; color: #666; margin-bottom: 2rem;">Find the perfect accommodation for your stay</p>
            
            <!-- Search Bar -->
            <div class="search-container">
                <form method="GET" style="display: flex; gap: 0.5rem; width: 100%;">
                    <input type="text" name="q" class="search-input" placeholder="Search hotels by name or location..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button type="submit" class="search-button"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>
            
            <div class="hotels-grid">
                <?php if (empty($hotels)): ?>
                    <p style="text-align: center; padding: 4rem; grid-column: 1/-1;">No hotels found.</p>
                <?php else: ?>
                    <?php foreach ($hotels as $hotel): ?>
                        <div class="hotel-card">
                            <?php if (!empty($hotel['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($hotel['image_path']); ?>" alt="<?php echo htmlspecialchars($hotel['name']); ?>" class="hotel-image">
                            <?php endif; ?>
                            <div class="hotel-info">
                                <h3><?php echo htmlspecialchars($hotel['name']); ?></h3>
                                <p class="hotel-location">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hotel['location']); ?>
                                </p>
                                <?php if (!empty($hotel['description'])): ?>
                                    <p style="color: #666; margin-bottom: 1rem;"><?php echo htmlspecialchars(substr($hotel['description'], 0, 100)); ?>...</p>
                                <?php endif; ?>
                                <a href="hotels.php?id=<?php echo $hotel['id']; ?>" class="view-btn" style="text-decoration: none; display: block; text-align: center;">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Booking Modal -->
    <div id="bookingModal" class="booking-modal">
        <div class="booking-modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Book Room</h2>
                <span style="font-size: 1.5rem; cursor: pointer;" onclick="closeBookingModal()">&times;</span>
            </div>
            <form method="POST" id="bookingForm">
                <input type="hidden" name="book_hotel" value="1">
                <input type="hidden" name="hotel_id" id="booking_hotel_id">
                <input type="hidden" name="room_id" id="booking_room_id">
                
                <div id="roomInfo" style="background: #f9f9f9; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;"></div>
                
                <div class="form-group">
                    <label>Number of Rooms *</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <button type="button" onclick="decreaseQuantity()" style="padding: 0.5rem 1rem; cursor: pointer; border: 1px solid #ccc; background: #f0f0f0; border-radius: 0.3rem; font-weight: 600;">−</button>
                        <input type="number" id="quantity" name="quantity" min="1" value="1" style="width: 70px; text-align: center; padding: 0.8rem; border: 1px solid #ccc; border-radius: 0.3rem; font-weight: 600;">
                        <button type="button" onclick="increaseQuantity()" style="padding: 0.5rem 1rem; cursor: pointer; border: 1px solid #ccc; background: #f0f0f0; border-radius: 0.3rem; font-weight: 600;">+</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Check-in Date *</label>
                    <input type="date" name="check_in" id="check_in" required min="<?php echo date('Y-m-d'); ?>">
                    <div id="check_in_error" class="field-error" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label>Check-out Date *</label>
                    <input type="date" name="check_out" id="check_out" required min="<?php echo date('Y-m-d'); ?>">
                    <div id="check_out_error" class="field-error" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label>Guest Name *</label>
                    <input type="text" name="guest_name" id="guest_name" 
                           pattern="^(?!\s)(?!.*\s{3,})(?!.*\d)(?!.*_)(?!.*[^\w\s]).+$" 
                           title="No leading spaces, no numbers/special chars, no triple spaces"
                           required value="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''; ?>">
                    <div id="guest_name_error" class="field-error" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label>Guest Email *</label>
                    <input type="email" name="guest_email" id="guest_email" 
                           pattern="^[a-zA-Z][a-zA-Z0-9_\-]*(\.[a-zA-Z0-9_\-]+)*@[a-zA-Z]+\.[a-zA-Z]{2,}$" 
                           title="Start with letter, one dot max before @, letters only domain"
                           required value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>">
                    <div id="guest_email_error" class="field-error" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label>Guest Phone *</label>
                    <input type="tel" name="guest_phone" id="guest_phone" 
                           pattern="^(97|98)[0-9]{8}$" 
                           title="Must start with 97 or 98 and be exactly 10 digits"
                           required>
                    <div id="guest_phone_error" class="field-error" style="display: none;"></div>
                </div>
                
                <div id="totalPrice" style="font-size: 1.2rem; font-weight: 700; color: #031881; margin: 1rem 0; text-align: right;"></div>
                
                <button type="submit" class="view-btn">Submit Booking Request</button>
                <button type="button" class="view-btn" style="background: #666; margin-top: 0.5rem;" onclick="closeBookingModal()">Cancel</button>
            </form>
        </div>
    </div>
<?php
?>

    <script>
        function openBookingModal(hotelId, roomId, roomType, acType, pricePerNight) {
            document.getElementById('booking_hotel_id').value = hotelId;
            document.getElementById('booking_room_id').value = roomId;
            document.getElementById('roomInfo').innerHTML = `
                <strong>${roomType} - ${acType}</strong><br>
                <span style="color: #666;">NPR ${pricePerNight.toFixed(2)} per night</span>
            `;
            document.getElementById('bookingModal').style.display = 'flex';
            calculateTotal();
        }
        
        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }
        
        function increaseQuantity() {
            const qtyInput = document.getElementById('quantity');
            const currentQty = parseInt(qtyInput.value) || 1;
            qtyInput.value = currentQty + 1;
            calculateTotal();
        }
        
        function decreaseQuantity() {
            const qtyInput = document.getElementById('quantity');
            const currentQty = parseInt(qtyInput.value) || 1;
            if (currentQty > 1) {
                qtyInput.value = currentQty - 1;
                calculateTotal();
            }
        }
        
        function calculateTotal() {
            const checkIn = new Date(document.getElementById('check_in').value);
            const checkOut = new Date(document.getElementById('check_out').value);
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            const roomInfo = document.getElementById('roomInfo').textContent;
            const priceMatch = roomInfo.match(/NPR ([\d.]+)/);
            
            if (checkIn && checkOut && priceMatch && checkOut > checkIn) {
                const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                const pricePerNight = parseFloat(priceMatch[1]);
                const total = nights * pricePerNight * quantity;
                document.getElementById('totalPrice').textContent = `Total: NPR ${total.toFixed(2)} (${quantity} room${quantity > 1 ? 's' : ''} × ${nights} night${nights > 1 ? 's' : ''})`;
            } else {
                document.getElementById('totalPrice').textContent = '';
            }
        }
        
        // Validation functions
        function validateBookingField(field) {
            const value = field.value.trim();
            const fieldId = field.id;
            const errorElement = document.getElementById(fieldId + '_error');
            
            clearBookingError(field, errorElement);
            
            if (field.hasAttribute('required') && !value) {
                showBookingError(field, errorElement, 'This field is required');
                return false;
            }
            
            if (!value && !field.hasAttribute('required')) {
                return true;
            }
            
            // Pattern validation
            if (field.hasAttribute('pattern')) {
                const pattern = new RegExp(field.getAttribute('pattern'));
                if (!pattern.test(value)) {
                    showBookingError(field, errorElement, field.getAttribute('title') || 'Invalid format');
                    return false;
                }
            }
            
            // Date validation
            if (fieldId === 'check_in' && value) {
                const checkIn = new Date(value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (checkIn < today) {
                    showBookingError(field, errorElement, 'Check-in date cannot be in the past');
                    return false;
                }
            }
            
            if (fieldId === 'check_out' && value) {
                const checkIn = document.getElementById('check_in').value;
                if (checkIn && new Date(value) <= new Date(checkIn)) {
                    showBookingError(field, errorElement, 'Check-out date must be after check-in date');
                    return false;
                }
            }
            
            // Email validation
            if (field.type === 'email' && value) {
                if (!/^[a-zA-Z]/.test(value)) {
                    showBookingError(field, errorElement, 'Email must start with a letter');
                    return false;
                }
                const localPart = value.split('@')[0];
                if ((localPart.match(/\./g) || []).length > 1) {
                    showBookingError(field, errorElement, 'Email can only contain one dot before @');
                    return false;
                }
            }
            
            // Name validation
            if (fieldId === 'guest_name' && value) {
                if (/^\s/.test(value)) {
                    showBookingError(field, errorElement, 'Name cannot start with a space');
                    return false;
                }
                if (/\s{3,}/.test(value)) {
                    showBookingError(field, errorElement, 'Name cannot have more than two consecutive spaces');
                    return false;
                }
                if (/[0-9]/.test(value)) {
                    showBookingError(field, errorElement, 'Name cannot contain numbers');
                    return false;
                }
                if (/[^\w\s]/.test(value)) {
                    showBookingError(field, errorElement, 'Name cannot contain special characters');
                    return false;
                }
            }
            
            // Phone validation
            if (fieldId === 'guest_phone' && value) {
                const phoneValue = value.replace(/\D/g, '');
                if (!/^(97|98)[0-9]{8}$/.test(phoneValue)) {
                    showBookingError(field, errorElement, 'Phone must start with 97 or 98 and be exactly 10 digits');
                    return false;
                }
            }
            
            return true;
        }
        
        function showBookingError(field, errorElement, message) {
            field.style.borderColor = '#dc3545';
            if (errorElement) {
                errorElement.textContent = '❌ ' + message;
                errorElement.style.display = 'block';
            }
        }
        
        function clearBookingError(field, errorElement) {
            field.style.borderColor = '';
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.style.display = 'none';
            }
        }
        
        // Real-time validation
        document.getElementById('check_in')?.addEventListener('change', function() {
            validateBookingField(this);
            const checkOut = document.getElementById('check_out');
            if (checkOut.value && new Date(checkOut.value) <= new Date(this.value)) {
                checkOut.value = '';
            }
            checkOut.min = this.value;
            calculateTotal();
        });
        
        document.getElementById('check_out')?.addEventListener('change', function() {
            validateBookingField(this);
            calculateTotal();
        });
        
        document.getElementById('quantity')?.addEventListener('change', function() {
            let qty = parseInt(this.value) || 1;
            if (qty < 1) qty = 1;
            this.value = qty;
            calculateTotal();
        });
        
        document.getElementById('guest_name')?.addEventListener('input', function() {
            validateBookingField(this);
        });
        
        document.getElementById('guest_name')?.addEventListener('blur', function() {
            validateBookingField(this);
        });
        
        document.getElementById('guest_email')?.addEventListener('input', function() {
            validateBookingField(this);
        });
        
        document.getElementById('guest_email')?.addEventListener('blur', function() {
            validateBookingField(this);
        });
        
        document.getElementById('guest_phone')?.addEventListener('input', function() {
            // Auto-format phone (remove non-digits)
            this.value = this.value.replace(/\D/g, '');
            validateBookingField(this);
        });
        
        document.getElementById('guest_phone')?.addEventListener('blur', function() {
            validateBookingField(this);
        });
        
        // Form submission validation
        document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
            let isValid = true;
            const fields = ['check_in', 'check_out', 'guest_name', 'guest_email', 'guest_phone'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && !validateBookingField(field)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                const firstError = document.querySelector('.field-error[style*="block"]');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
        
        function addToWishlist(type, id, name, image, price) {
            const formData = new FormData();
            formData.append('item_type', type);
            formData.append('item_id', id);
            formData.append('item_name', name);
            formData.append('item_image', image);
            formData.append('item_price', price);
            
            fetch('add_to_wishlist.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                } else {
                    alert(data.message);
                    if (data.message.toLowerCase().includes('login')) {
                        window.location.href = 'login.php';
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Mobile menu toggle
        const menuBtn = document.querySelector('.menu-btn');
        const navLinks = document.querySelector('.nav-links');
        
        if (menuBtn) {
            menuBtn.addEventListener('click', function() {
                menuBtn.classList.toggle('active');
                navLinks.classList.toggle('active');
            });
        }
    </script>
</body>
</html>

