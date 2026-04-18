<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if (isset($_GET['hotel_id'])) {
    $hotel_id = intval($_GET['hotel_id']);
    
    try {
        $stmt = $pdo->prepare("SELECT id, room_type, ac_type, price_npr, quantity, available FROM hotel_rooms WHERE hotel_id = ? ORDER BY room_type, ac_type");
        $stmt->execute([$hotel_id]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'rooms' => $rooms
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Hotel ID is required'
    ]);
}
?>
