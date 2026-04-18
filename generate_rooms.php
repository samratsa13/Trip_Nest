<?php
/**
 * Room Stock Utility Script
 * 
 * This script combines functionality to:
 * 1. View all configured room categories from the 'hotel_rooms' table.
 * 2. Automatically generate distinct physical 'room_numbers' based on quantity.
 */
require 'db_connection.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Room Generation Utility</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; }
        .box { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .info { background: #e9f5ff; border: 1px solid #bce0ff; }
        .debug { background: #f4f4f4; border: 1px solid #ddd; overflow-x: auto; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>Room Setup Utility</h1>";

try {
    // ----------------------------------------------------
    // Section 1: Display Current Configuration
    // ----------------------------------------------------
    echo "<h2>1. Current Room Configurations</h2>";
    $totalCategories = $pdo->query("SELECT COUNT(*) FROM hotel_rooms")->fetchColumn();
    echo "<div class='box info'><p>Total Room Types/Categories Defined: <strong>$totalCategories</strong></p></div>";
    
    $rooms = $pdo->query("SELECT hr.*, h.name as hotel_name FROM hotel_rooms hr JOIN hotels h ON hr.hotel_id = h.id")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rooms) > 0) {
        echo "<table>
                <tr>
                    <th>Hotel Name</th>
                    <th>Room Type</th>
                    <th>AC Type</th>
                    <th>Quantity (Total Rooms)</th>
                </tr>";
        foreach ($rooms as $room) {
            echo "<tr>
                    <td>{$room['hotel_name']}</td>
                    <td>{$room['room_type']}</td>
                    <td>{$room['ac_type']}</td>
                    <td>{$room['quantity']}</td>
                  </tr>";
        }
        echo "</table>";
    }

    echo "<hr style='margin: 30px 0;'>";

    // ----------------------------------------------------
    // Section 2: Generate Physical Room Numbers
    // ----------------------------------------------------
    echo "<h2>2. Database Generation Status</h2>";
    
    // Check if the physical room_numbers table has any data
    $existingRooms = $pdo->query("SELECT COUNT(*) FROM room_numbers")->fetchColumn();
    
    if ($existingRooms > 0) {
        echo "<div class='box success'>
                <p><strong>Physical room numbers have already been generated in the database.</strong><br>
                Current total physical rooms tracked: <strong>$existingRooms</strong></p>
              </div>";
    } else {
        echo "<div class='box info'>
                <p>No physical room numbers exist. Generating them based on quantities...</p>
              </div>";
        
        $roomsGenerated = 0;
        
        foreach ($rooms as $room) {
            $hotel_id = $room['hotel_id'];
            $room_id  = $room['id'];
            $quantity = $room['quantity'];
            
            // Create a unique prefix to avoid duplicate constraint errors.
            // A room like "Normal (AC)" might become "N-A-R5-1"
            $type_initial = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $room['room_type']), 0, 1));
            $ac_initial   = strtoupper(substr($room['ac_type'], 0, 1));
            
            // Appending room ID provides a guaranteed unique prefix per room category
            $prefix = $type_initial . $ac_initial . "-R" . $room_id . "-";
            
            for ($i = 1; $i <= $quantity; $i++) {
                $room_number = $prefix . $i; // e.g. "NA-R5-1"
                
                // INSERT IGNORE ensures we skip it if by any rare chance it already exists
                $stmt = $pdo->prepare("INSERT IGNORE INTO room_numbers (hotel_id, room_id, room_number, status) VALUES (?, ?, ?, 'available')");
                if ($stmt->execute([$hotel_id, $room_id, $room_number])) {
                    if ($stmt->rowCount() > 0) {
                        $roomsGenerated++;
                    }
                }
            }
        }
        echo "<div class='box success'>
                <p>Successfully generated <strong>$roomsGenerated</strong> physical room numbers! You can safely go back to the admin dashboard.</p>
              </div>";
    }
} catch(Exception $e) {
    echo "<div class='box error'>
            <p><strong>Database Error:</strong> " . $e->getMessage() . "</p>
          </div>";
}

echo "</body></html>";
?>
