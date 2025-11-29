<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'db_connection.php';

$itinerary_id = isset($_GET['itinerary_id']) ? intval($_GET['itinerary_id']) : 0;

if ($itinerary_id <= 0) {
    echo json_encode(['error' => 'Invalid itinerary ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM itinerary_days WHERE itinerary_id = ? ORDER BY day_number ASC");
    $stmt->execute([$itinerary_id]);
    $days = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'days' => $days]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

