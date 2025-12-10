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
        
        // Validation
        if (empty($guest_name) || empty($guest_email) || empty($guest_phone)) {
            $booking_error = "Please fill all guest information fields.";
        } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
            $booking_error = "Please enter a valid email address.";
        } elseif (strtotime($check_in) >= strtotime($check_out)) {
            $booking_error = "Check-out date must be after check-in date.";
        } else {
            // Get room price
            $room_stmt = $pdo->prepare("SELECT price_npr FROM hotel_rooms WHERE id = ?");
            $room_stmt->execute([$room_id]);
            $room = $room_stmt->fetch();
            
            if ($room) {
                $nights = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
                $total_price = $room['price_npr'] * $nights;
                
                // Create booking
                $stmt = $pdo->prepare("INSERT INTO hotel_bookings (user_id, hotel_id, room_id, check_in, check_out, guest_name, guest_email, guest_phone, total_price_npr, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                if ($stmt->execute([$user_id, $hotel_id, $room_id, $check_in, $check_out, $guest_name, $guest_email, $guest_phone, $total_price])) {
                    $booking_success = "Booking request submitted successfully! Admin will review and approve your booking.";
                } else {
                    $booking_error = "Error submitting booking. Please try again.";
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav id="navbar">
        <h1 class="logo">Trip Nest</h1>
        
        <div class="menu-btn">
            <span></span>
            <span></span>
            <span></span>
        </div>
        
        <ul class="nav-links">
            <li><a href="Tourism.php">Home</a></li>
            <li><a href="Tourism.php#special-offers">Special Offers</a></li>
            <li><a href="Tourism.php#itenary">Itinerary</a></li>
            <li><a href="destination.php">Destinations</a></li>
            <li><a href="hotels.php" class="active">Hotels</a></li>
            <li><a href="activities.php">Activities</a></li>
            <li><a href="Tourism.php#about-us">About Us</a></li>
            <li><a href="Tourism.php#contact">Contact</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="user-menu">
                    <a href="dashboard.php" class="user-icon">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </a>
                </li>
            <?php else: ?>
                <li><a href="login.php">Join Us</a></li>
            <?php endif; ?>
        </ul>
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
                                <p style="color: #666;">Available: <?php echo $room['available']; ?> room(s)</p>
                            </div>
                            <div>
                                <div class="room-price">NPR <?php echo number_format($room['price_npr'], 2); ?>/night</div>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <button class="view-btn" onclick="openBookingModal(<?php echo $selected_hotel['id']; ?>, <?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_type']); ?>', '<?php echo htmlspecialchars($room['ac_type']); ?>', <?php echo $room['price_npr']; ?>)">
                                        Book Now
                                    </button>
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
            
            <?php if (empty($hotels)): ?>
                <p style="text-align: center; padding: 4rem;">No hotels available at this time.</p>
            <?php else: ?>
                <div class="hotels-grid">
                    <?php foreach ($hotels as $hotel): ?>
                        <div class="hotel-card">
                            <?php if (!empty($hotel['image_path'])): ?>
                                <img src="<?php echo $hotel['image_path']; ?>" alt="<?php echo htmlspecialchars($hotel['name']); ?>" class="hotel-image">
                            <?php endif; ?>
                            <div class="hotel-info">
                                <h3><?php echo htmlspecialchars($hotel['name']); ?></h3>
                                <p class="hotel-location">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hotel['location']); ?>
                                </p>
                                <?php if (!empty($hotel['description'])): ?>
                                    <p style="color: #666; margin-bottom: 1rem;"><?php echo htmlspecialchars(substr($hotel['description'], 0, 100)) . '...'; ?></p>
                                <?php endif; ?>
                                <a href="hotels.php?id=<?php echo $hotel['id']; ?>" class="view-btn" style="text-decoration: none; display: block; text-align: center;">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                    <label>Check-in Date *</label>
                    <input type="date" name="check_in" id="check_in" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Check-out Date *</label>
                    <input type="date" name="check_out" id="check_out" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Guest Name *</label>
                    <input type="text" name="guest_name" required value="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Guest Email *</label>
                    <input type="email" name="guest_email" required value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Guest Phone *</label>
                    <input type="tel" name="guest_phone" required>
                </div>
                
                <div id="totalPrice" style="font-size: 1.2rem; font-weight: 700; color: #031881; margin: 1rem 0; text-align: right;"></div>
                
                <button type="submit" class="view-btn">Submit Booking Request</button>
                <button type="button" class="view-btn" style="background: #666; margin-top: 0.5rem;" onclick="closeBookingModal()">Cancel</button>
            </form>
        </div>
    </div>

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
        
        function calculateTotal() {
            const checkIn = new Date(document.getElementById('check_in').value);
            const checkOut = new Date(document.getElementById('check_out').value);
            const roomInfo = document.getElementById('roomInfo').textContent;
            const priceMatch = roomInfo.match(/NPR ([\d.]+)/);
            
            if (checkIn && checkOut && priceMatch && checkOut > checkIn) {
                const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                const pricePerNight = parseFloat(priceMatch[1]);
                const total = nights * pricePerNight;
                document.getElementById('totalPrice').textContent = `Total: NPR ${total.toFixed(2)} (${nights} night${nights > 1 ? 's' : ''})`;
            } else {
                document.getElementById('totalPrice').textContent = '';
            }
        }
        
        document.getElementById('check_in')?.addEventListener('change', function() {
            const checkOut = document.getElementById('check_out');
            if (checkOut.value && new Date(checkOut.value) <= new Date(this.value)) {
                checkOut.value = '';
            }
            checkOut.min = this.value;
            calculateTotal();
        });
        
        document.getElementById('check_out')?.addEventListener('change', calculateTotal);
        
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

