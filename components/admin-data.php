<?php
// Get counts for dashboard
$user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$itinerary_count = $pdo->query("SELECT COUNT(*) FROM popular_itineraries")->fetchColumn();
$destination_count = $pdo->query("SELECT COUNT(*) FROM destinations")->fetchColumn();

// Get hotels, activities, and bookings counts
try {
    $hotel_count = $pdo->query("SELECT COUNT(*) FROM hotels")->fetchColumn();
} catch (PDOException $e) {
    $hotel_count = 0;
}

try {
    $activity_count = $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
} catch (PDOException $e) {
    $activity_count = 0;
}

try {
    $total_bookings = $pdo->query("SELECT COUNT(*) FROM hotel_bookings")->fetchColumn() + 
                      $pdo->query("SELECT COUNT(*) FROM activity_bookings")->fetchColumn();
} catch (PDOException $e) {
    $total_bookings = 0;
}

// Get comprehensive reporting data
try {
    // Booking statistics
    $hotel_bookings_count = $pdo->query("SELECT COUNT(*) FROM hotel_bookings")->fetchColumn();
    $activity_bookings_count = $pdo->query("SELECT COUNT(*) FROM activity_bookings")->fetchColumn();
    
    // Booking status breakdown
    $booking_status_stats = [
        'pending' => $pdo->query("SELECT COUNT(*) FROM hotel_bookings WHERE status = 'pending'")->fetchColumn() + 
                     $pdo->query("SELECT COUNT(*) FROM activity_bookings WHERE status = 'pending'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM hotel_bookings WHERE status = 'approved'")->fetchColumn() + 
                      $pdo->query("SELECT COUNT(*) FROM activity_bookings WHERE status = 'approved'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM hotel_bookings WHERE status = 'rejected'")->fetchColumn() + 
                      $pdo->query("SELECT COUNT(*) FROM activity_bookings WHERE status = 'rejected'")->fetchColumn(),
    ];
    
    // Revenue statistics
    $total_revenue_hotel = $pdo->query("SELECT COALESCE(SUM(total_price_npr), 0) FROM hotel_bookings WHERE status = 'approved'")->fetchColumn();
    $total_revenue_activity = $pdo->query("SELECT COALESCE(SUM(total_price_npr), 0) FROM activity_bookings WHERE status = 'approved'")->fetchColumn();
    $total_revenue = $total_revenue_hotel + $total_revenue_activity;
    
    // Monthly bookings trend (last 6 months)
    $monthly_bookings = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_hotel_count = $pdo->query("SELECT COUNT(*) FROM hotel_bookings WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $month_activity_count = $pdo->query("SELECT COUNT(*) FROM activity_bookings WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $monthly_bookings[] = [
            'month' => date('M Y', strtotime("-$i months")),
            'hotel' => $month_hotel_count,
            'activity' => $month_activity_count,
            'total' => $month_hotel_count + $month_activity_count
        ];
    }
    
} catch (PDOException $e) {
    $hotel_bookings_count = 0;
    $activity_bookings_count = 0;
    $booking_status_stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    $total_revenue = 0;
    $total_revenue_hotel = 0;
    $total_revenue_activity = 0;
    $monthly_bookings = [];
}

// Get data for tables
$itineraries = $pdo->query("SELECT * FROM popular_itineraries ORDER BY created_at DESC")->fetchAll();
$destinations = $pdo->query("SELECT * FROM destinations ORDER BY created_at DESC")->fetchAll();
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get all users
$all_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// Get hotels, activities, and bookings
try {
    $hotels = $pdo->query("SELECT * FROM hotels ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $hotels = [];
}

try {
    $activities = $pdo->query("SELECT * FROM activities ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $activities = [];
}

try {
    $hotel_bookings = $pdo->query("SELECT hb.*, u.name as user_name, h.name as hotel_name, hr.room_type, hr.ac_type 
        FROM hotel_bookings hb 
        JOIN users u ON hb.user_id = u.user_id 
        JOIN hotels h ON hb.hotel_id = h.id 
        JOIN hotel_rooms hr ON hb.room_id = hr.id 
        ORDER BY hb.created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $hotel_bookings = [];
}

try {
    $activity_bookings = $pdo->query("SELECT ab.*, u.name as user_name, a.name as activity_name 
        FROM activity_bookings ab 
        JOIN users u ON ab.user_id = u.user_id 
        JOIN activities a ON ab.activity_id = a.id 
        ORDER BY ab.created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $activity_bookings = [];
}

try {
    $room_numbers = $pdo->query("SELECT rn.*, h.name as hotel_name, hr.room_type, hr.ac_type 
        FROM room_numbers rn 
        JOIN hotels h ON rn.hotel_id = h.id 
        JOIN hotel_rooms hr ON rn.room_id = hr.id 
        ORDER BY h.name ASC, hr.room_type ASC, hr.ac_type ASC, LENGTH(rn.room_number) ASC, rn.room_number ASC")->fetchAll();
} catch (PDOException $e) {
    $room_numbers = [];
}
