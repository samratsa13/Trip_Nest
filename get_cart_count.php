<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

require_once 'db_connection.php';

try {
    // Check if table exists
    $table_exists = $pdo->query("SHOW TABLES LIKE 'cart_items'")->rowCount() > 0;
    
    if (!$table_exists) {
        echo json_encode(['count' => 0]);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $count = $result['total'] ?? 0;
    echo json_encode(['count' => (int)$count]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0]);
}
?>

