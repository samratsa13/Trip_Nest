<?php
session_start();
require_once 'db_connection.php';

// Handle activity booking
$booking_success = '';
$booking_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_activity'])) {
    if (!isset($_SESSION['user_id'])) {
        $booking_error = "Please login to book an activity.";
    } else {
        $user_id = $_SESSION['user_id'];
        $activity_id = intval($_POST['activity_id']);
        $booking_date = $_POST['booking_date'];
        $guest_name = trim($_POST['guest_name']);
        $guest_email = trim($_POST['guest_email']);
        $guest_phone = trim($_POST['guest_phone']);
        
        // Validation
        if (empty($guest_name) || empty($guest_email) || empty($guest_phone)) {
            $booking_error = "Please fill all guest information fields.";
        } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
            $booking_error = "Please enter a valid email address.";
        } else {
            // Get activity price
            $activity_stmt = $pdo->prepare("SELECT price_npr FROM activities WHERE id = ? AND status = 'active'");
            $activity_stmt->execute([$activity_id]);
            $activity = $activity_stmt->fetch();
            
            if ($activity) {
                $total_price = $activity['price_npr'];
                
                // Create booking
                $stmt = $pdo->prepare("INSERT INTO activity_bookings (user_id, activity_id, booking_date, guest_name, guest_email, guest_phone, total_price_npr, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                if ($stmt->execute([$user_id, $activity_id, $booking_date, $guest_name, $guest_email, $guest_phone, $total_price])) {
                    $booking_success = "Booking request submitted successfully! Admin will review and approve your booking.";
                } else {
                    $booking_error = "Error submitting booking. Please try again.";
                }
            } else {
                $booking_error = "Invalid activity selected.";
            }
        }
    }
}

// Get activities
try {
    $activities = $pdo->query("SELECT * FROM activities WHERE status = 'active' ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $activities = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Activities - Trip Nest</title>
    <link rel="stylesheet" href="css.css">
    <style>
        .activities-container {
            max-width: 1200px;
            margin: 100px auto 20px;
            padding: 2rem;
        }
        
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .activity-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .activity-card:hover {
            transform: translateY(-5px);
        }
        
        .activity-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .activity-info {
            padding: 1.5rem;
        }
        
        .activity-info h3 {
            color: #031881;
            margin-bottom: 0.5rem;
        }
        
        .activity-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #031881;
            margin: 1rem 0;
        }
        
        .book-btn {
            width: 100%;
            padding: 0.8rem;
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
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
            <!-- <li><a href="hotels.php">Hotels</a></li>
            <li><a href="activities.php" class="active">Activities</a></li> -->
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

    <div class="activities-container">
        <h1 style="text-align: center; color: #031881; margin-bottom: 1rem;">Activities & Adventures</h1>
        <p style="text-align: center; color: #666; margin-bottom: 2rem;">Explore exciting activities and adventures</p>
        
        <?php if ($booking_success): ?>
            <div class="alert alert-success"><?php echo $booking_success; ?></div>
        <?php endif; ?>
        
        <?php if ($booking_error): ?>
            <div class="alert alert-error"><?php echo $booking_error; ?></div>
        <?php endif; ?>
        
        <?php if (empty($activities)): ?>
            <p style="text-align: center; padding: 4rem;">No activities available at this time.</p>
        <?php else: ?>
            <div class="activities-grid">
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-card">
                        <?php if (!empty($activity['image_path'])): ?>
                            <img src="<?php echo $activity['image_path']; ?>" alt="<?php echo htmlspecialchars($activity['name']); ?>" class="activity-image">
                        <?php endif; ?>
                        <div class="activity-info">
                            <h3><?php echo htmlspecialchars($activity['name']); ?></h3>
                            <?php if (!empty($activity['description'])): ?>
                                <p style="color: #666; margin-bottom: 1rem;"><?php echo htmlspecialchars(substr($activity['description'], 0, 150)) . '...'; ?></p>
                            <?php endif; ?>
                            <div class="activity-price">NPR <?php echo number_format($activity['price_npr'], 2); ?></div>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="book-btn" onclick="openBookingModal(<?php echo $activity['id']; ?>, '<?php echo htmlspecialchars(addslashes($activity['name'])); ?>', <?php echo $activity['price_npr']; ?>)">
                                    Book Now
                                </button>
                            <?php else: ?>
                                <a href="login.php" class="book-btn" style="text-decoration: none; display: block; text-align: center;">Login to Book</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="booking-modal">
        <div class="booking-modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Book Activity</h2>
                <span style="font-size: 1.5rem; cursor: pointer;" onclick="closeBookingModal()">&times;</span>
            </div>
            <form method="POST" id="bookingForm">
                <input type="hidden" name="book_activity" value="1">
                <input type="hidden" name="activity_id" id="booking_activity_id">
                
                <div id="activityInfo" style="background: #f9f9f9; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;"></div>
                
                <div class="form-group">
                    <label>Booking Date *</label>
                    <input type="date" name="booking_date" id="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                    <div id="booking_date_error" class="field-error" style="display: none;"></div>
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
                
                <button type="submit" class="book-btn">Submit Booking Request</button>
                <button type="button" class="book-btn" style="background: #666; margin-top: 0.5rem;" onclick="closeBookingModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openBookingModal(activityId, activityName, price) {
            document.getElementById('booking_activity_id').value = activityId;
            document.getElementById('activityInfo').innerHTML = `
                <strong>${activityName}</strong><br>
                <span style="color: #666;">NPR ${price.toFixed(2)}</span>
            `;
            document.getElementById('totalPrice').textContent = `Total: NPR ${price.toFixed(2)}`;
            document.getElementById('bookingModal').style.display = 'flex';
        }
        
        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
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
            if (fieldId === 'booking_date' && value) {
                const bookingDate = new Date(value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (bookingDate < today) {
                    showBookingError(field, errorElement, 'Booking date cannot be in the past');
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
        document.getElementById('booking_date')?.addEventListener('change', function() {
            validateBookingField(this);
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
            const fields = ['booking_date', 'guest_name', 'guest_email', 'guest_phone'];
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

