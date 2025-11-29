<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to add items to cart']);
    exit;
}

require_once 'db_connection.php';

// Handle POST request to add item to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $item_type = $_POST['item_type'] ?? ''; // 'offer', 'destination', 'itinerary'
    $item_id = $_POST['item_id'] ?? 0;
    $item_name = $_POST['item_name'] ?? '';
    $item_description = $_POST['item_description'] ?? '';
    $item_image = $_POST['item_image'] ?? '';
    $item_price = $_POST['item_price'] ?? 0;
    
    try {
        // Create cart_items table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS cart_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            item_description TEXT,
            item_image VARCHAR(255),
            item_price DECIMAL(10, 2) DEFAULT 0,
            quantity INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id)
        )");
        
        // Check if item already exists in cart
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND item_type = ? AND item_id = ?");
        $stmt->execute([$user_id, $item_type, $item_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update quantity
            $new_quantity = $existing['quantity'] + 1;
            $update_stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $update_stmt->execute([$new_quantity, $existing['id']]);
            echo json_encode(['success' => true, 'message' => 'Item quantity updated in cart']);
        } else {
            // Insert new item
            $insert_stmt = $pdo->prepare("INSERT INTO cart_items (user_id, item_type, item_id, item_name, item_description, item_image, item_price, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $insert_stmt->execute([$user_id, $item_type, $item_id, $item_name, $item_description, $item_image, $item_price]);
            echo json_encode(['success' => true, 'message' => 'Item added to cart']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>

