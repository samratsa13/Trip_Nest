<?php
session_start();
require_once 'db_connection.php';

// Get destinations from database
$destinations = $pdo->query("SELECT * FROM destinations WHERE status = 'active' ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Destinations - Trip Nest</title>
    <link rel="stylesheet" href="destination.css">
</head>
<body>
    
<?php include("components/nav.php") ?>


    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>
        <div class="hero-content">
            <h2>Discover Nepal's Amazing Destinations</h2>
            <p>Explore the breathtaking beauty of Nepal through our curated collection of must-visit destinations. From the majestic Himalayas to the lush jungles, Nepal offers unforgettable experiences for every traveler.</p>
            <button class="cta-button" onclick="scrollToDestinations()">Explore Destinations</button>
        </div>
    </section>

    <!-- Destinations Section -->
    <section id="destinations">
        <h2 class="section-title">Popular Destinations in Nepal</h2>
        <p class="section-subtitle">Discover the most beautiful and culturally rich destinations that Nepal has to offer. Each destination offers unique experiences and unforgettable memories.</p>
        
        <div class="offers-container">
            <?php if (empty($destinations)): ?>
                <!-- Default destinations if database is empty -->
                <div class="offer-card">
                    <img src="img/ktm.jpg" alt="Kathmandu" class="offer-img">
                    <h3>Kathmandu</h3>
                    <p>The capital city of Nepal, rich in culture and history. Visit ancient temples, bustling markets, and experience the vibrant local life in this UNESCO World Heritage city.</p>
                </div>
                
                <div class="offer-card">
                    <img src="img/pkr.jpg" alt="Pokhara" class="offer-img">
                    <h3>Pokhara</h3>
                    <p>Known as the gateway to the Annapurna region, Pokhara offers stunning lake views, adventure sports, and serves as the starting point for many treks in the Himalayas.</p>
                </div>
                
                <div class="offer-card">
                    <img src="img/bhr.jpg" alt="Chitwan" class="offer-img">
                    <h3>Chitwan National Park</h3>
                    <p>Experience wildlife safari in Nepal's first national park. Spot rhinos, tigers, elephants, and various bird species in their natural habitat.</p>
                </div>
                
                <div class="offer-card">
                    <img src="img/mountain-climb.jpg" alt="Mount Everest" class="offer-img">
                    <h3>Mount Everest Region</h3>
                    <p>Home to the world's highest peak, this region offers incredible trekking opportunities, Sherpa culture, and breathtaking mountain vistas.</p>
                </div>
                
                <div class="offer-card">
                    <img src="img/city.jpg" alt="Lumbini" class="offer-img">
                    <h3>Lumbini</h3>
                    <p>The birthplace of Lord Buddha, Lumbini is a sacred pilgrimage site with ancient monasteries, peaceful gardens, and spiritual significance.</p>
                </div>
                
                <div class="offer-card">
                    <img src="img/jungle-resort.jpg" alt="Bardiya National Park" class="offer-img">
                    <h3>Bardiya National Park</h3>
                    <p>One of Nepal's most pristine wildlife reserves, offering excellent opportunities to spot Bengal tigers, one-horned rhinos, and diverse wildlife.</p>
                </div>
            <?php else: ?>
                <?php foreach($destinations as $destination): ?>
                <div class="offer-card">
                    <img src="<?php echo $destination['image_path'] ?: 'img/default-destination.jpg'; ?>" alt="<?php echo htmlspecialchars($destination['name']); ?>" class="offer-img">
                    <h3><?php echo htmlspecialchars($destination['name']); ?></h3>
                    <p><?php echo htmlspecialchars($destination['description']); ?></p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- About Nepal Section -->
    <section id="about-nepal">
        <div class="about-content">
            <h2 class="section-title">Why Visit Nepal?</h2>
            <p class="about-text">Nepal is a land of incredible diversity, offering everything from the world's highest mountains to lush tropical jungles. Whether you're seeking adventure, spiritual enlightenment, or cultural immersion, Nepal has something special for every traveler.</p>
            <p class="about-text">From the bustling streets of Kathmandu to the serene lakes of Pokhara, from the wildlife of Chitwan to the spiritual sites of Lumbini, Nepal promises unforgettable experiences that will stay with you forever.</p>
            <p class="about-text">Join us at Trip Nest and let us help you discover the magic of Nepal through our carefully curated destinations and personalized travel experiences.</p>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact">
        <h2 class="section-title">Plan Your Nepal Adventure</h2>
        <p class="section-subtitle">Ready to explore Nepal? Contact us to plan your perfect trip:</p>
        <ul class="contact-info">
            <li><i class="fas fa-envelope"></i> <strong>Email</strong>: Samadk@gmail.com</li>
            <li><i class="fas fa-phone"></i> <strong>Phone</strong>: +977 123456789</li>
            <li><i class="fas fa-map-marker-alt"></i> <strong>Address</strong>: 123 Travel St, Kathmandu, Nepal</li>
        </ul>
    </section>

    <!-- Footer -->
    <section id="footer">
        <div class="footer-content">
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
            </div>
            <p>&copy; 2024 Trip Nest. All rights reserved.</p>
        </div>
    </section>

    <script>
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
        
        menuBtn.addEventListener('click', function() {
            menuBtn.classList.toggle('active');
            navLinks.classList.toggle('active');
        });
        
        // Close mobile menu when clicking on a link
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function() {
                menuBtn.classList.remove('active');
                navLinks.classList.remove('active');
            });
        });

        // Scroll to destinations section
        function scrollToDestinations() {
            document.getElementById('destinations').scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Animate elements on scroll
        function animateOnScroll() {
            const offerCards = document.querySelectorAll('.offer-card');
            const aboutTexts = document.querySelectorAll('.about-text');
            
            offerCards.forEach(card => {
                const cardPosition = card.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.3;
                
                if (cardPosition < screenPosition) {
                    card.classList.add('animated');
                }
            });
            
            aboutTexts.forEach((text, index) => {
                const textPosition = text.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.3;
                
                if (textPosition < screenPosition) {
                    setTimeout(() => {
                        text.classList.add('animated');
                    }, index * 300);
                }
            });
        }
        
        window.addEventListener('scroll', animateOnScroll);
        window.addEventListener('load', animateOnScroll);

        // Button hover effects
        document.querySelectorAll('.cta-button, .offer-button').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

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
            // Update cart count every 5 seconds
            setInterval(updateCartCount, 5000);
        });
    </script>
</body>
</html>
