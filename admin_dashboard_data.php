<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once 'db_connection.php';

$filter = $_GET['time_filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$date_condition = "1=1";
$params = [];

if ($filter !== 'all') {
    if ($filter === 'today') {
        $date_condition = "DATE(created_at) = CURDATE()";
    } elseif ($filter === 'last_7_days') {
        $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($filter === 'last_30_days') {
        $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($filter === 'this_month') {
        $date_condition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
    } elseif ($filter === 'this_year') {
        $date_condition = "YEAR(created_at) = YEAR(CURDATE())";
    } elseif ($filter === 'custom' && !empty($start_date) && !empty($end_date)) {
        // Validate dates using regex (basic validation for YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $date_condition = "DATE(created_at) >= ? AND DATE(created_at) <= ?";
            $params = [$start_date, $end_date];
        }
    }
}

try {
    // Helper function to fetch count with conditions
    function getCount($pdo, $table, $date_condition, $params) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE " . $date_condition);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    $user_count = getCount($pdo, 'users', $date_condition, $params);
    $itinerary_count = getCount($pdo, 'popular_itineraries', $date_condition, $params);
    $destination_count = getCount($pdo, 'destinations', $date_condition, $params);
    
    // Some tables might not exist if they are handled conditionally in main files, but based on admin.php they exist.
    $hotel_count = getCount($pdo, 'hotels', $date_condition, $params);
    $activity_count = getCount($pdo, 'activities', $date_condition, $params);
    
    $hotel_bookings_count = getCount($pdo, 'hotel_bookings', $date_condition, $params);
    $activity_bookings_count = getCount($pdo, 'activity_bookings', $date_condition, $params);
    $total_bookings = $hotel_bookings_count + $activity_bookings_count;

    // Booking Status Breakdown
    function getStatusCount($pdo, $table, $status, $date_condition, $params) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE status = ? AND " . $date_condition);
        $exec_params = array_merge([$status], $params);
        $stmt->execute($exec_params);
        return $stmt->fetchColumn();
    }

    $booking_status_stats = [
        'pending' => getStatusCount($pdo, 'hotel_bookings', 'pending', $date_condition, $params) + 
                     getStatusCount($pdo, 'activity_bookings', 'pending', $date_condition, $params),
        'approved' => getStatusCount($pdo, 'hotel_bookings', 'approved', $date_condition, $params) + 
                      getStatusCount($pdo, 'activity_bookings', 'approved', $date_condition, $params),
        'rejected' => getStatusCount($pdo, 'hotel_bookings', 'rejected', $date_condition, $params) + 
                      getStatusCount($pdo, 'activity_bookings', 'rejected', $date_condition, $params),
    ];

    // Revenue
    function getRevenue($pdo, $table, $date_condition, $params) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price_npr), 0) FROM {$table} WHERE status = 'approved' AND " . $date_condition);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    $total_revenue_hotel = getRevenue($pdo, 'hotel_bookings', $date_condition, $params);
    $total_revenue_activity = getRevenue($pdo, 'activity_bookings', $date_condition, $params);
    $total_revenue = $total_revenue_hotel + $total_revenue_activity;

    echo json_encode([
        'users' => $user_count,
        'itineraries' => $itinerary_count,
        'destinations' => $destination_count,
        'hotels' => $hotel_count,
        'activities' => $activity_count,
        'total_bookings' => $total_bookings,
        'hotel_bookings' => $hotel_bookings_count,
        'activity_bookings' => $activity_bookings_count,
        'booking_status' => $booking_status_stats,
        'revenue_hotel' => $total_revenue_hotel,
        'revenue_activity' => $total_revenue_activity,
        'total_revenue' => $total_revenue
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
