<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connection.php';

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? '');

// Get user's hotel bookings (only pending)
try {
    $hotel_stmt = $pdo->prepare("
        SELECT 
            hb.id,
            hb.hotel_id,
            h.name as hotel_name,
            h.location,
            hb.room_id,
            r.room_type,
            r.ac_type,
            hb.check_in,
            hb.check_out,
            hb.guest_name,
            hb.guest_email,
            hb.guest_phone,
            hb.total_price_npr,
            hb.status,
            hb.created_at
        FROM hotel_bookings hb
        JOIN hotels h ON hb.hotel_id = h.id
        LEFT JOIN hotel_rooms r ON hb.room_id = r.id
        WHERE hb.user_id = ? AND hb.status = 'pending'
        ORDER BY hb.created_at DESC
    ");
    $hotel_stmt->execute([$user_id]);
    $hotel_bookings = $hotel_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $hotel_bookings = [];
}

// Get user's activity bookings (only pending)
try {
    $activity_stmt = $pdo->prepare("
        SELECT 
            ab.id,
            ab.activity_id,
            a.name as activity_name,
            a.description,
            ab.booking_date,
            ab.guest_name,
            ab.guest_email,
            ab.guest_phone,
            ab.quantity,
            ab.total_price_npr,
            ab.status,
            ab.created_at
        FROM activity_bookings ab
        JOIN activities a ON ab.activity_id = a.id
        WHERE ab.user_id = ? AND ab.status = 'pending'
        ORDER BY ab.created_at DESC
    ");
    $activity_stmt->execute([$user_id]);
    $activity_bookings = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activity_bookings = [];
}

// Get user's approved bookings as well
try {
    $approved_hotel_stmt = $pdo->prepare("
        SELECT 
            hb.id,
            hb.hotel_id,
            h.name as hotel_name,
            h.location,
            hb.room_id,
            r.room_type,
            r.ac_type,
            hb.check_in,
            hb.check_out,
            hb.guest_name,
            hb.guest_email,
            hb.guest_phone,
            hb.total_price_npr,
            hb.status,
            hb.created_at
        FROM hotel_bookings hb
        JOIN hotels h ON hb.hotel_id = h.id
        LEFT JOIN hotel_rooms r ON hb.room_id = r.id
        WHERE hb.user_id = ? AND hb.status = 'approved'
        ORDER BY hb.created_at DESC
    ");
    $approved_hotel_stmt->execute([$user_id]);
    $approved_hotel_bookings = $approved_hotel_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $approved_hotel_bookings = [];
}

// Get user's approved activity bookings
try {
    $approved_activity_stmt = $pdo->prepare("
        SELECT 
            ab.id,
            ab.activity_id,
            a.name as activity_name,
            a.description,
            ab.booking_date,
            ab.guest_name,
            ab.guest_email,
            ab.guest_phone,
            ab.quantity,
            ab.total_price_npr,
            ab.status,
            ab.created_at
        FROM activity_bookings ab
        JOIN activities a ON ab.activity_id = a.id
        WHERE ab.user_id = ? AND ab.status = 'approved'
        ORDER BY ab.created_at DESC
    ");
    $approved_activity_stmt->execute([$user_id]);
    $approved_activity_bookings = $approved_activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $approved_activity_bookings = [];
}

// Get user's rejected hotel bookings
try {
    $rejected_hotel_stmt = $pdo->prepare("
        SELECT 
            hb.id,
            hb.hotel_id,
            h.name as hotel_name,
            h.location,
            hb.room_id,
            r.room_type,
            r.ac_type,
            hb.check_in,
            hb.check_out,
            hb.guest_name,
            hb.guest_email,
            hb.guest_phone,
            hb.total_price_npr,
            hb.status,
            hb.created_at
        FROM hotel_bookings hb
        JOIN hotels h ON hb.hotel_id = h.id
        LEFT JOIN hotel_rooms r ON hb.room_id = r.id
        WHERE hb.user_id = ? AND hb.status = 'rejected'
        ORDER BY hb.created_at DESC
    ");
    $rejected_hotel_stmt->execute([$user_id]);
    $rejected_hotel_bookings = $rejected_hotel_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rejected_hotel_bookings = [];
}

// Get user's rejected activity bookings
try {
    $rejected_activity_stmt = $pdo->prepare("
        SELECT 
            ab.id,
            ab.activity_id,
            a.name as activity_name,
            a.description,
            ab.booking_date,
            ab.guest_name,
            ab.guest_email,
            ab.guest_phone,
            ab.quantity,
            ab.total_price_npr,
            ab.status,
            ab.created_at
        FROM activity_bookings ab
        JOIN activities a ON ab.activity_id = a.id
        WHERE ab.user_id = ? AND ab.status = 'rejected'
        ORDER BY ab.created_at DESC
    ");
    $rejected_activity_stmt->execute([$user_id]);
    $rejected_activity_bookings = $rejected_activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rejected_activity_bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Trip Nest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }

       nav,
.navbar {
    background: linear-gradient(90deg, #031881, #6f7ecb);
    color: white;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 0.5rem;
    margin: 20px auto 2rem;
    max-width: 1200px;
    /* Constraint width */
    width: 95%;
    /* Responsive width */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 20px;
    z-index: 1000;
}

.logo {
    color: white;
    font-size: 1.8rem;
    font-weight: 700;
    text-decoration: none;
}

.nav-links {
    list-style: none;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.nav-links a {
    color: white;
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 0.3rem;
    transition: all 0.3s ease;
    font-size: 1rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nav-links a:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
    color: white;
}

/* Remove old complex hover effects from previous CSS */
.nav-links a::after,
.nav-links a::before {
    display: none;
}

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h2 {
            color: #031881;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            border: none;
            background: #f0f0f0;
            color: #333;
            cursor: pointer;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .tab-button:hover {
            background: #e0e0e0;
        }

        .tab-button.active {
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .booking-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #031881;
            transition: all 0.3s ease;
        }

        .booking-card:hover {
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .booking-card.approved {
            border-left-color: #28a745;
        }

        .booking-card.pending {
            border-left-color: #ffc107;
        }

        .booking-card.rejected {
            border-left-color: #dc3545;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .card-title {
            color: #031881;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.approved {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .detail-value {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .total-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #031881;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ccc;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .empty-state p {
            font-size: 1rem;
            color: #999;
        }

        .button-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(3, 24, 129, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }

        .info-message {
            background: #e7f3ff;
            color: #004085;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #004085;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }

            .navbar-links {
                width: 100%;
                justify-content: center;
            }

            .tabs {
                flex-wrap: wrap;
            }

            .card-details {
                grid-template-columns: 1fr;
            }

            .card-footer {
                flex-direction: column;
                align-items: flex-start;
            }
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
            <!-- <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a> -->
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
        <!-- Header -->
        <div class="header">
            <h2>My Bookings</h2>
            <p>Welcome, <?php echo $user_name; ?>! View and manage your bookings below.</p>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-button active" onclick="switchTab(event, 'pending')">
                <i class="fas fa-clock"></i> Pending Approvals
            </button>
            <button class="tab-button" onclick="switchTab(event, 'approved')">
                <i class="fas fa-check-circle"></i> Approved Bookings
            </button>
            <button class="tab-button" onclick="switchTab(event, 'rejected')">
                <i class="fas fa-times-circle"></i> Rejected Bookings
            </button>
        </div>

        <!-- Pending Bookings Tab -->
        <div id="pending" class="tab-content active">
            <div class="info-message">
                <i class="fas fa-info-circle"></i>
                Bookings shown below are pending admin approval. You will be notified once they are approved or rejected.
            </div>

            <!-- Pending Hotel Bookings -->
            <h3 style="color: #031881; margin: 2rem 0 1rem; font-size: 1.3rem;">
                <i class="fas fa-hotel"></i> Hotel Bookings
            </h3>
            
            <?php if (empty($hotel_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-bed"></i>
                    <h3>No Pending Hotel Bookings</h3>
                    <p>You haven't made any hotel bookings yet.</p>
                    <a href="hotels.php" class="btn btn-primary" style="margin-top: 1rem;">
                        Browse Hotels
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($hotel_bookings as $booking): ?>
                    <div class="booking-card pending">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-hotel"></i> <?php echo htmlspecialchars($booking['hotel_name']); ?>
                            </div>
                            <span class="status-badge pending">
                                <i class="fas fa-hourglass-half"></i> Pending
                            </span>
                        </div>

                        <div class="card-details">
                            <div class="detail-item">
                                <div class="detail-label">Location</div>
                                <div class="detail-value">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($booking['location']); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Room Type</div>
                                <div class="detail-value">
                                    <?php echo htmlspecialchars($booking['room_type'] . ' (' . $booking['ac_type'] . ')'); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Check-in</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($booking['check_in'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Check-out</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar-times"></i> <?php echo date('M d, Y', strtotime($booking['check_out'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_email']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Phone</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_phone']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Booking Date</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="total-price">
                                NPR <?php echo number_format($booking['total_price_npr'], 2); ?>
                            </div>
                            <div class="button-group">
                                <a href="hotels.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Pending Activity Bookings -->
            <h3 style="color: #031881; margin: 2rem 0 1rem; font-size: 1.3rem;">
                <i class="fas fa-running"></i> Activity Bookings
            </h3>
            
            <?php if (empty($activity_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-activity"></i>
                    <h3>No Pending Activity Bookings</h3>
                    <p>You haven't booked any activities yet.</p>
                    <a href="activities.php" class="btn btn-primary" style="margin-top: 1rem;">
                        Browse Activities
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($activity_bookings as $booking): ?>
                    <div class="booking-card pending">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-running"></i> <?php echo htmlspecialchars($booking['activity_name']); ?>
                            </div>
                            <span class="status-badge pending">
                                <i class="fas fa-hourglass-half"></i> Pending
                            </span>
                        </div>

                        <div class="card-details">
                            <div class="detail-item">
                                <div class="detail-label">Description</div>
                                <div class="detail-value">
                                    <?php echo htmlspecialchars(substr($booking['description'], 0, 100)); ?>...
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Booking Date</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Quantity</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['quantity']); ?> ticket(s)</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_email']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Phone</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_phone']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Request Date</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="total-price">
                                NPR <?php echo number_format($booking['total_price_npr'], 2); ?>
                            </div>
                            <div class="button-group">
                                <a href="activities.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Approved Bookings Tab -->
        <div id="approved" class="tab-content">
            <div class="info-message">
                <i class="fas fa-check-circle"></i>
                These bookings have been approved by admin. Make sure to follow the check-in/start times.
            </div>

            <!-- Approved Hotel Bookings -->
            <h3 style="color: #031881; margin: 2rem 0 1rem; font-size: 1.3rem;">
                <i class="fas fa-hotel"></i> Hotel Bookings
            </h3>
            
            <?php if (empty($approved_hotel_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-bed"></i>
                    <h3>No Approved Hotel Bookings</h3>
                    <p>Your hotel bookings will appear here once they are approved by admin.</p>
                </div>
            <?php else: ?>
                <?php foreach($approved_hotel_bookings as $booking): ?>
                    <div class="booking-card approved">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-hotel"></i> <?php echo htmlspecialchars($booking['hotel_name']); ?>
                            </div>
                            <span class="status-badge approved">
                                <i class="fas fa-check-circle"></i> Approved
                            </span>
                        </div>

                        <div class="card-details">
                            <div class="detail-item">
                                <div class="detail-label">Location</div>
                                <div class="detail-value">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($booking['location']); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Room Type</div>
                                <div class="detail-value">
                                    <?php echo htmlspecialchars($booking['room_type'] . ' (' . $booking['ac_type'] . ')'); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Check-in</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($booking['check_in'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Check-out</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar-times"></i> <?php echo date('M d, Y', strtotime($booking['check_out'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_email']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Phone</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_phone']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Confirmed Date</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="total-price">
                                NPR <?php echo number_format($booking['total_price_npr'], 2); ?>
                            </div>
                            <div class="button-group">
                                <a href="hotels.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Approved Activity Bookings -->
            <h3 style="color: #031881; margin: 2rem 0 1rem; font-size: 1.3rem;">
                <i class="fas fa-running"></i> Activity Bookings
            </h3>
            
            <?php if (empty($approved_activity_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-activity"></i>
                    <h3>No Approved Activity Bookings</h3>
                    <p>Your activity bookings will appear here once they are approved by admin.</p>
                </div>
            <?php else: ?>
                <?php foreach($approved_activity_bookings as $booking): ?>
                    <div class="booking-card approved">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-running"></i> <?php echo htmlspecialchars($booking['activity_name']); ?>
                            </div>
                            <span class="status-badge approved">
                                <i class="fas fa-check-circle"></i> Approved
                            </span>
                        </div>

                        <div class="card-details">
                            <div class="detail-item">
                                <div class="detail-label">Description</div>
                                <div class="detail-value">
                                    <?php echo htmlspecialchars(substr($booking['description'], 0, 100)); ?>...
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Booking Date</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Quantity</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['quantity']); ?> ticket(s)</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_email']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Phone</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_phone']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Confirmed Date</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="total-price">
                                NPR <?php echo number_format($booking['total_price_npr'], 2); ?>
                            </div>
                            <div class="button-group">
                                <a href="activities.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Rejected Bookings Tab -->
        <div id="rejected" class="tab-content">
            <div class="info-message" style="background: #f8d7da; color: #721c24; border-left-color: #dc3545;">
                <i class="fas fa-times-circle"></i>
                These bookings have been rejected by admin. Please contact support or try booking again.
            </div>

            <!-- Rejected Hotel Bookings -->
            <h3 style="color: #031881; margin: 2rem 0 1rem; font-size: 1.3rem;">
                <i class="fas fa-hotel"></i> Hotel Bookings
            </h3>
            
            <?php if (empty($rejected_hotel_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-bed"></i>
                    <h3>No Rejected Hotel Bookings</h3>
                    <p>You don't have any rejected hotel bookings.</p>
                </div>
            <?php else: ?>
                <?php foreach($rejected_hotel_bookings as $booking): ?>
                    <div class="booking-card rejected">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-hotel"></i> <?php echo htmlspecialchars($booking['hotel_name']); ?>
                            </div>
                            <span class="status-badge rejected">
                                <i class="fas fa-times-circle"></i> Rejected
                            </span>
                        </div>

                        <div class="card-details">
                            <div class="detail-item">
                                <div class="detail-label">Location</div>
                                <div class="detail-value">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($booking['location']); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Room Type</div>
                                <div class="detail-value">
                                    <?php echo htmlspecialchars($booking['room_type'] . ' (' . $booking['ac_type'] . ')'); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Check-in</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($booking['check_in'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Check-out</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar-times"></i> <?php echo date('M d, Y', strtotime($booking['check_out'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Guest Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['guest_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Booking Date</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="total-price">
                                NPR <?php echo number_format($booking['total_price_npr'], 2); ?>
                            </div>
                            <div class="button-group">
                                <a href="hotels.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Rejected Activity Bookings -->
            <h3 style="color: #031881; margin: 2rem 0 1rem; font-size: 1.3rem;">
                <i class="fas fa-running"></i> Activity Bookings
            </h3>
            
            <?php if (empty($rejected_activity_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-activity"></i>
                    <h3>No Rejected Activity Bookings</h3>
                    <p>You don't have any rejected activity bookings.</p>
                </div>
            <?php else: ?>
                <?php foreach($rejected_activity_bookings as $booking): ?>
                    <div class="booking-card rejected">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-running"></i> <?php echo htmlspecialchars($booking['activity_name']); ?>
                            </div>
                            <span class="status-badge rejected">
                                <i class="fas fa-times-circle"></i> Rejected
                            </span>
                        </div>

                        <div class="card-details">
                            <div class="detail-item">
                                <div class="detail-label">Description</div>
                                <div class="detail-value">
                                    <?php echo htmlspecialchars(substr($booking['description'], 0, 100)); ?>...
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Booking Date</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Quantity</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['quantity']); ?> ticket(s)</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Booking Date</div>
                                <div class="detail-value">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="total-price">
                                NPR <?php echo number_format($booking['total_price_npr'], 2); ?>
                            </div>
                            <div class="button-group">
                                <a href="activities.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchTab(event, tabName) {
            event.preventDefault();
            
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
