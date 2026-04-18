<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "tripnest_db");
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

$user_id = $_SESSION['user_id'];

// Handle remove item from wishlist
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_item'])) {
    $item_id = (int)$_POST['item_id'];
    if ($item_id) {
        $sql = "DELETE FROM wishlist WHERE id = $item_id AND user_id = $user_id";
        mysqli_query($conn, $sql);
    }
    header("Location: wishlist.php");
    exit;
}

// Get wishlist items
$sql = "SELECT * FROM wishlist WHERE user_id = $user_id ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
$wishlist_items = [];
while($row = mysqli_fetch_assoc($result)) {
    $wishlist_items[] = $row;
}
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Wishlist - Trip Nest</title>
    <link rel="stylesheet" href="home.css">
    <style>
        .cart-container {
            max-width: 1200px;
            margin: 100px auto 20px;
            padding: 2rem;
        }
        
        .cart-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .cart-header h1 {
            color: #031881;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .cart-items {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .cart-item {
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            align-items: center;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 0.5rem;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-details h3 {
            color: #031881;
            margin-bottom: 0.5rem;
        }
        
        .cart-item-details p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .cart-item-price {
            font-size: 1.2rem;
            font-weight: 600;
            color: #031881;
        }
        
        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .book-btn {
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
        }
        
        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(3, 24, 129, 0.3);
        }
        
        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
        }
        
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .empty-cart i {
            font-size: 5rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .empty-cart h2 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .empty-cart a {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
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

    <div class="cart-container">
        <div class="cart-header">
            <h1><i class="fas fa-heart"></i> Your Wishlist</h1>
            <p>Save your favorite trips and book when you're ready</p>
        </div>

        <?php if (empty($wishlist_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-heart-broken"></i>
                <h2>Your wishlist is empty</h2>
                <p>Start adding items to your wishlist to begin booking</p>
                <a href="Tourism.php">Browse Offers</a>
            </div>
        <?php else: ?>
            <div class="cart-items">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['item_image'] ?: 'img/default-offer.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($item['item_name']); ?>" 
                             class="cart-item-image">
                        <div class="cart-item-details">
                            <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                            <p><strong>Type:</strong> <?php echo ucfirst($item['item_type']); ?></p>
                            <div class="cart-item-price">
                                NPR <?php echo number_format($item['item_price'], 2); ?>
                            </div>
                        </div>
                        <div class="cart-item-controls">
                            <!-- Booking Button -->
                            <?php if ($item['item_type'] !== 'itinerary'): ?>
                                <a href="booking_confirmation.php?id=<?php echo $item['item_id']; ?>&type=<?php echo $item['item_type']; ?>&wishlist_id=<?php echo $item['id']; ?>" class="book-btn">
                                    <i class="fas fa-check"></i> Book Now
                                </a>
                            <?php endif; ?>
                            
                            <!-- Remove Form -->
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Remove from wishlist?');">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="remove_item" value="1">
                                <button type="submit" class="remove-btn">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Same script as cart.php properly adapted -->
    <script>
        // Navbar scroll effect
   
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
