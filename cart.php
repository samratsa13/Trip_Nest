<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connection.php';

$user_id = $_SESSION['user_id'];

// Handle remove item from cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_item'])) {
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($item_id) {
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
        $stmt->execute([$item_id, $user_id]);
    }
    header("Location: cart.php");
    exit;
}

// Handle update quantity
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_quantity'])) {
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($item_id && $quantity) {
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$quantity, $item_id, $user_id]);
    }
    header("Location: cart.php");
    exit;
}

// Handle checkout - create booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    try {
        // Get cart items
        $cart_items = $pdo->prepare("SELECT * FROM cart_items WHERE user_id = ?");
        $cart_items->execute([$user_id]);
        $items = $cart_items->fetchAll();
        
        if (empty($items)) {
            $checkout_error = "Your cart is empty!";
        } else {
            // Create orders table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                package_name VARCHAR(255) NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                booking_details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id)
            )");
            
            $pdo->beginTransaction();
            
            $total_amount = 0;
            $booking_details = [];
            
            foreach ($items as $item) {
                $subtotal = $item['item_price'] * $item['quantity'];
                $total_amount += $subtotal;
                
                $booking_details[] = [
                    'type' => $item['item_type'],
                    'name' => $item['item_name'],
                    'description' => $item['item_description'],
                    'quantity' => $item['quantity'],
                    'price' => $item['item_price'],
                    'subtotal' => $subtotal
                ];
            }
            
            // Create order for each cart item (or combine into one order)
            $package_name = count($items) > 1 ? count($items) . ' Items Package' : $items[0]['item_name'];
            $details_json = json_encode($booking_details);
            
            $order_stmt = $pdo->prepare("INSERT INTO orders (user_id, package_name, amount, booking_details, status) VALUES (?, ?, ?, ?, 'pending')");
            $order_stmt->execute([$user_id, $package_name, $total_amount, $details_json]);
            
            // Clear cart after successful order
            $delete_stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $delete_stmt->execute([$user_id]);
            
            $pdo->commit();
            $checkout_success = "Booking confirmed! Your order has been placed successfully.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $checkout_error = "Error processing booking: " . $e->getMessage();
    }
}

// Get cart items
$stmt = $pdo->prepare("SELECT * FROM cart_items WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['item_price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Cart - Trip Nest</title>
    <link rel="stylesheet" href="css.css">
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
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quantity-input {
            width: 60px;
            padding: 0.5rem;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
        }
        
        .quantity-btn {
            background: #031881;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 0.3rem;
            cursor: pointer;
            font-weight: 600;
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
        
        .cart-summary {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .summary-row.total {
            border-bottom: 2px solid #031881;
            font-size: 1.3rem;
            font-weight: 700;
            color: #031881;
            margin-top: 1rem;
        }
        
        .checkout-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1.5rem;
            transition: transform 0.3s ease;
        }
        
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(3, 24, 129, 0.3);
        }
        
        .checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
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
        
        .success-msg, .error-msg {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .success-msg {
            background: #d4edda;
            color: #155724;
        }
        
        .error-msg {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                text-align: center;
            }
            
            .cart-item-controls {
                flex-direction: column;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav id="navbar">
        <h1 class="logo">Trip Nest</h1>
        
        <div class="menu-btn">
            <span></span>
            <span></span>
            <span></span>
        </div>
        
        <ul class="nav-links">
            <li><a href="Tourism.php">Home</a></li>
            <li><a href="Tourism.php#special-offers">Special Offers</a></li>
            <li><a href="Tourism.php#itenary">Itinerary</a></li>
            <li><a href="destination.php">Destinations</a></li>
            <li><a href="Tourism.php#about-us">About Us</a></li>
            <li><a href="Tourism.php#contact">Contact</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="user-menu">
                    <a href="dashboard.php" class="user-icon">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </a>
                </li>
            <?php else: ?>
                <li><a href="login.php">Join Us</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="cart-container">
        <div class="cart-header">
            <h1><i class="fas fa-shopping-cart"></i> Your Cart</h1>
            <p>Review your booking items before checkout</p>
        </div>

        <?php if (isset($checkout_success)): ?>
            <div class="success-msg"><?php echo $checkout_success; ?></div>
        <?php endif; ?>

        <?php if (isset($checkout_error)): ?>
            <div class="error-msg"><?php echo $checkout_error; ?></div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Your cart is empty</h2>
                <p>Start adding items to your cart to begin booking</p>
                <a href="Tourism.php">Browse Offers</a>
            </div>
        <?php else: ?>
            <div class="cart-items">
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['item_image'] ?: 'img/default-offer.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($item['item_name']); ?>" 
                             class="cart-item-image">
                        <div class="cart-item-details">
                            <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                            <p><?php echo htmlspecialchars($item['item_description']); ?></p>
                            <p><strong>Type:</strong> <?php echo ucfirst($item['item_type']); ?></p>
                            <div class="cart-item-price">
                                $<?php echo number_format($item['item_price'], 2); ?> x <?php echo $item['quantity']; ?> = 
                                $<?php echo number_format($item['item_price'] * $item['quantity'], 2); ?>
                            </div>
                        </div>
                        <div class="cart-item-controls">
                            <form method="POST" class="quantity-control" style="display: inline;">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="button" class="quantity-btn" onclick="decreaseQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity']; ?>)">-</button>
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                       min="1" class="quantity-input" id="qty-<?php echo $item['id']; ?>" readonly>
                                <button type="button" class="quantity-btn" onclick="increaseQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity']; ?>)">+</button>
                                <input type="hidden" name="update_quantity" value="1">
                                <button type="submit" style="display: none;" id="update-<?php echo $item['id']; ?>"></button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this item?');">
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

            <div class="cart-summary">
                <h2>Booking Summary</h2>
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>$<?php echo number_format($total_amount, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax:</span>
                    <span>$0.00</span>
                </div>
                <div class="summary-row total">
                    <span>Total:</span>
                    <span>$<?php echo number_format($total_amount, 2); ?></span>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="checkout" value="1">
                    <button type="submit" class="checkout-btn">
                        <i class="fas fa-check"></i> Proceed to Checkout
                    </button>
                </form>
                
                <a href="Tourism.php" style="display: block; text-align: center; margin-top: 1rem; color: #031881;">Continue Shopping</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function increaseQuantity(itemId, currentQty) {
            document.getElementById('qty-' + itemId).value = currentQty + 1;
            document.getElementById('update-' + itemId).click();
        }
        
        function decreaseQuantity(itemId, currentQty) {
            if (currentQty > 1) {
                document.getElementById('qty-' + itemId).value = currentQty - 1;
                document.getElementById('update-' + itemId).click();
            }
        }
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Mobile menu toggle
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

