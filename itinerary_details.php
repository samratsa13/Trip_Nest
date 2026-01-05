<?php
session_start();
require_once 'db_connection.php';

// Get itinerary ID from URL
$itinerary_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($itinerary_id <= 0) {
    header("Location: Tourism.php");
    exit;
}

// Get itinerary details
$stmt = $pdo->prepare("SELECT * FROM popular_itineraries WHERE id = ? AND status = 'active'");
$stmt->execute([$itinerary_id]);
$itinerary = $stmt->fetch();

if (!$itinerary) {
    header("Location: Tourism.php");
    exit;
}

// Create itinerary_days table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS itinerary_days (
        id INT AUTO_INCREMENT PRIMARY KEY,
        itinerary_id INT NOT NULL,
        day_number INT NOT NULL,
        day_title VARCHAR(255) NOT NULL,
        day_description TEXT,
        activities TEXT,
        accommodation VARCHAR(255),
        meals VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (itinerary_id) REFERENCES popular_itineraries(id) ON DELETE CASCADE,
        INDEX idx_itinerary_id (itinerary_id),
        UNIQUE KEY unique_day (itinerary_id, day_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Table might already exist, continue
}

// Get itinerary days
$days_stmt = $pdo->prepare("SELECT * FROM itinerary_days WHERE itinerary_id = ? ORDER BY day_number ASC");
$days_stmt->execute([$itinerary_id]);
$days = $days_stmt->fetchAll();

// Get all images for the itinerary
$all_images = [];
// Add primary image if exists
if (!empty($itinerary['image_path'])) {
    $all_images[] = $itinerary['image_path'];
}
// Add additional images if exists
if (!empty($itinerary['additional_images'])) {
    $additional_images = json_decode($itinerary['additional_images'], true);
    if (is_array($additional_images)) {
        $all_images = array_merge($all_images, $additional_images);
    }
}

// Get selected day from filter (default to first day or all)
$selected_day = isset($_GET['day']) ? intval($_GET['day']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title><?php echo htmlspecialchars($itinerary['title']); ?> - Trip Nest</title>
    <link rel="stylesheet" href="css.css">
    <style>
        .itinerary-details-container {
            max-width: 1200px;
            margin: 100px auto 20px;
            padding: 2rem;
        }
        
        .itinerary-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 0.5rem;
            cursor: pointer;
            aspect-ratio: 16/9;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .gallery-item:hover {
            transform: scale(1.05);
        }
        
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-item.primary {
            grid-column: span 2;
            grid-row: span 2;
        }
        
        /* Image Modal/Lightbox */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            cursor: pointer;
        }
        
        .image-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-image {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #ccc;
        }
        
        @media (max-width: 768px) {
            .image-gallery {
                grid-template-columns: 1fr;
            }
            
            .gallery-item.primary {
                grid-column: span 1;
                grid-row: span 1;
            }
        }
        
        .itinerary-header h1 {
            color: #031881;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .itinerary-header .description {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .book-now-btn {
            padding: 1rem 2.5rem;
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.3s ease;
        }
        
        .book-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(3, 24, 129, 0.3);
        }
        
        .day-filter {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .day-filter h3 {
            color: #031881;
            margin-bottom: 1rem;
        }
        
        .day-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .day-btn {
            padding: 0.7rem 1.5rem;
            background: #f0f0f0;
            color: #333;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .day-btn:hover, .day-btn.active {
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
            border-color: #031881;
        }
        
        .days-container {
            display: grid;
            gap: 2rem;
        }
        
        .day-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .day-card:hover {
            transform: translateY(-5px);
        }
        
        .day-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .day-number {
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .day-card h3 {
            color: #031881;
            font-size: 1.5rem;
            margin: 0;
        }
        
        .day-info {
            margin-top: 1rem;
        }
        
        .day-info p {
            color: #666;
            line-height: 1.8;
            margin-bottom: 0.8rem;
        }
        
        .day-info strong {
            color: #031881;
        }
        
        .activities-list {
            background: #f5f7fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
        }
        
        .no-days {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .no-days i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .back-btn {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.7rem 1.5rem;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .itinerary-header h1 {
                font-size: 2rem;
            }
            
            .day-buttons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav id="navbar">
        <div style="display: flex; align-items: center; gap: 2rem;">
            <h1 class="logo">Trip Nest</h1>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="cart.php" class="cart-button" id="cartButton">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count" id="cartCount">0</span>
                </a>
            <?php endif; ?>
        </div>
        
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
            <li><a href="dashboard.php?tab=bookings">Bookings</a></li>
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

    <div class="itinerary-details-container">
        <a href="Tourism.php#itenary" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Itineraries
        </a>

        <div class="itinerary-header">
            <?php if (!empty($all_images)): ?>
                <div class="image-gallery">
                    <?php foreach ($all_images as $index => $image_path): ?>
                        <?php if ($image_path && file_exists($image_path)): ?>
                            <div class="gallery-item <?php echo $index === 0 ? 'primary' : ''; ?>" onclick="openImageModal('<?php echo htmlspecialchars($image_path); ?>')">
                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                     alt="<?php echo htmlspecialchars($itinerary['title']); ?> - Image <?php echo $index + 1; ?>">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($itinerary['image_path'] ?: 'img/default-itinerary.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($itinerary['title']); ?>"
                     onclick="openImageModal('<?php echo htmlspecialchars($itinerary['image_path'] ?: 'img/default-itinerary.jpg'); ?>')"
                     style="cursor: pointer;">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($itinerary['title']); ?></h1>
            <div class="description">
                <?php echo nl2br(htmlspecialchars($itinerary['description'])); ?>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <button class="book-now-btn" onclick="addToCart('itinerary', <?php echo $itinerary['id']; ?>, '<?php echo htmlspecialchars(addslashes($itinerary['title'])); ?>', '<?php echo htmlspecialchars(addslashes($itinerary['description'])); ?>', '<?php echo htmlspecialchars(addslashes($itinerary['image_path'] ?: 'img/default-itinerary.jpg')); ?>', 200)">
                    <i class="fas fa-shopping-cart"></i> Book Now
                </button>
            <?php else: ?>
                <a href="login.php" class="book-now-btn">
                    <i class="fas fa-sign-in-alt"></i> Login to Book
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($days)): ?>
            <div class="day-filter">
                <h3><i class="fas fa-calendar-alt"></i> Filter by Day</h3>
                <div class="day-buttons">
                    <a href="?id=<?php echo $itinerary_id; ?>&day=0" class="day-btn <?php echo $selected_day == 0 ? 'active' : ''; ?>">
                        All Days
                    </a>
                    <?php foreach ($days as $day): ?>
                        <a href="?id=<?php echo $itinerary_id; ?>&day=<?php echo $day['day_number']; ?>" 
                           class="day-btn <?php echo $selected_day == $day['day_number'] ? 'active' : ''; ?>">
                            Day <?php echo $day['day_number']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="days-container">
                <?php 
                $display_days = $selected_day == 0 ? $days : array_filter($days, function($day) use ($selected_day) {
                    return $day['day_number'] == $selected_day;
                });
                
                foreach ($display_days as $day): 
                ?>
                    <div class="day-card">
                        <div class="day-card-header">
                            <div class="day-number"><?php echo $day['day_number']; ?></div>
                            <h3><?php echo htmlspecialchars($day['day_title']); ?></h3>
                        </div>
                        <div class="day-info">
                            <?php if (!empty($day['day_description'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($day['day_description'])); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($day['activities'])): ?>
                                <div class="activities-list">
                                    <strong><i class="fas fa-list"></i> Activities:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($day['activities'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($day['accommodation'])): ?>
                                <p><strong><i class="fas fa-bed"></i> Accommodation:</strong> <?php echo htmlspecialchars($day['accommodation']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($day['meals'])): ?>
                                <p><strong><i class="fas fa-utensils"></i> Meals:</strong> <?php echo htmlspecialchars($day['meals']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-days">
                <i class="fas fa-calendar-times"></i>
                <h2>No Day Details Available</h2>
                <p>Day-wise itinerary details are being prepared. Please check back soon!</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Cart functionality
        function addToCart(itemType, itemId, itemName, itemDescription, itemImage, itemPrice) {
            <?php if (isset($_SESSION['user_id'])): ?>
                const formData = new FormData();
                formData.append('item_type', itemType);
                formData.append('item_id', itemId);
                formData.append('item_name', itemName);
                formData.append('item_description', itemDescription);
                formData.append('item_image', itemImage);
                formData.append('item_price', itemPrice);
                
                fetch('add_to_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Item added to cart!');
                        updateCartCount();
                    } else {
                        alert(data.message || 'Error adding item to cart');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error adding item to cart');
                });
            <?php else: ?>
                alert('Please login to add items to cart');
                window.location.href = 'login.php';
            <?php endif; ?>
        }

        function updateCartCount() {
            <?php if (isset($_SESSION['user_id'])): ?>
                fetch('get_cart_count.php')
                    .then(response => response.json())
                    .then(data => {
                        const cartCount = document.getElementById('cartCount');
                        if (cartCount) {
                            if (data.count > 0) {
                                cartCount.textContent = data.count;
                                cartCount.style.display = 'inline-flex';
                            } else {
                                cartCount.style.display = 'none';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error updating cart count:', error);
                        const cartCount = document.getElementById('cartCount');
                        if (cartCount) {
                            cartCount.style.display = 'none';
                        }
                    });
            <?php endif; ?>
        }

        // Update cart count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
        });

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
        
        // Image Modal/Lightbox functionality
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modalImg.src = imageSrc;
            modal.classList.add('active');
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }
        
        // Close modal on click outside image
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('imageModal');
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeImageModal();
                }
            });
            
            // Close on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeImageModal();
                }
            });
        });
    </script>
    
    <!-- Image Modal/Lightbox -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img id="modalImage" class="modal-image" src="" alt="Full size image">
    </div>
</body>
</html>

