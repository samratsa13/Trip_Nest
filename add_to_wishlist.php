<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to add items to wishlist']);
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "tripnest_db");
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $item_type = mysqli_real_escape_string($conn, $_POST['item_type'] ?? '');
    $item_id = (int)($_POST['item_id'] ?? 0);
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name'] ?? '');
    $item_image = mysqli_real_escape_string($conn, $_POST['item_image'] ?? '');
    $item_price = (float)($_POST['item_price'] ?? 0);

    // Check if already in wishlist
    $check_sql = "SELECT id FROM wishlist WHERE user_id = $user_id AND item_type = '$item_type' AND item_id = $item_id";
    $result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($result) > 0) {
        echo json_encode(['success' => true, 'message' => 'Item already in wishlist']);
    } else {
        $insert_sql = "INSERT INTO wishlist (user_id, item_type, item_id, item_name, item_image, item_price) VALUES ($user_id, '$item_type', $item_id, '$item_name', '$item_image', $item_price)";
        if (mysqli_query($conn, $insert_sql)) {
            echo json_encode(['success' => true, 'message' => 'Item added to wishlist']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
        }
    }
}
mysqli_close($conn);
?>
