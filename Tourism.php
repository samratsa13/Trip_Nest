<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Trip Nest</title>
    <link rel="stylesheet" href="css.css">

</head>
<body>
    <!-- Navigation -->
<!-- Navigation -->
<nav id="navbar">
    <h1 class="logo">Trip Nest</h1>
    
    <div class="menu-btn">
        <span></span>
        <span></span>
        <span></span>
    </div>
    
    <ul class="nav-links">
        <li><a href="#home" class="active">Home</a></li>
        <li><a href="#special-offers">Special Offers</a></li>
        <li><a href="#itenary">Itinerary</a></li>
        <li><a href="#about-us">About Us</a></li>
        <li><a href="#contact">Contact</a></li>
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

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>
        <div class="hero-content">
            <h2>Your Trip, From Adventure to Stay.</h2>
            <p>Discover amazing destinations and create unforgettable memories with Trip Nest. We offer personalized travel experiences tailored just for you.</p>
            <button class="cta-button">Explore Destinations</button>
        </div>
    </section>


<?php if (isset($_SESSION['user_id']) && isset($_GET['login_success'])): ?>
    <div style="background: #d4edda; color: #155724; padding: 1rem; text-align: center; border-radius: 0.5rem; margin: 1rem auto; max-width: 800px;">
        Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 🎉
    </div>
<?php endif; ?>



    <!-- Special Offers Section -->
    <section id="special-offers">
        <h2 class="section-title">Special Offers</h2>
        <p class="section-subtitle">Check out our exclusive travel packages designed to give you the best experience at amazing prices.</p>
        
        <div class="offers-container">
            <div class="offer-card">
                <img src="img/jungle-resort.jpg" alt="Jungle Resort" class="offer-img">
                <h3>Jungle Resort</h3>
                <p>Immerse yourself in nature at our Jungle Resort, offering eco-friendly stays and guided wildlife tours.</p>
                <button class="offer-button">Book Now</button>
            </div>
            <div class="offer-card">
                <img src="img/mountain-climb.jpg" alt="Mountain Adventure" class="offer-img">
                <h3>Mountain Adventure</h3>
                <p>Experience thrilling mountain adventures with guided hikes and outdoor activities.</p>
                <button class="offer-button">Book Now</button>
            </div>
            <div class="offer-card">
                <img src="img/city.jpg" alt="City Tour" class="offer-img">
                <h3>City Tour</h3>
                <p>Discover the hidden gems of the city with our comprehensive city tour packages.</p>
                <button class="offer-button">Book Now</button>
            </div>
        </div>
    </section>

    <!-- Itinerary Section -->
    <section id="itenary">
        <h2 class="section-title">Create Your Itinerary</h2>
        <p class="section-subtitle">Plan your perfect trip with our easy-to-use itinerary builder. Customize your travel plans, add destinations, and organize activities to make the most of your journey.</p>
        <button class="itenary-button">Create Itinerary</button>
        
        <h3 class="popular-title">Popular Itineraries</h3>
        
        <div class="offers-container">
            <div class="offer-card">
                <img src="img/pkr.jpg" alt="Pokhara Valley" class="offer-img">
                <h3>Pokhara Valley</h3>
                <p>Explore the scenic beauty of Pokhara Valley with lakeside strolls, mountain views, and adventure activities like paragliding, bungee jumping, zipline and boating.</p>
                <button class="offer-button">View Details</button>
            </div>
            <div class="offer-card">
                <img src="img/ktm.jpg" alt="Kathmandu Exploration" class="offer-img">
                <h3>Kathmandu Exploration</h3>
                <p>Discover the rich heritage of Kathmandu with guided tours to ancient temples, World Heritage sites, and authentic local cuisine experiences.</p>
                <button class="offer-button">View Details</button>
            </div>
            <div class="offer-card">
                <img src="img/bhr.jpg" alt="Chitwan Adventure" class="offer-img">
                <h3>Chitwan Adventure</h3>
                <p>Embark on a thrilling journey in Chitwan with jungle safaris, elephant rides, canoeing, and wildlife spotting in the heart of Nepal's national park.</p>
                <button class="offer-button">View Details</button>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about-us">
        <div class="about-content">
            <img src="img/logo.png" alt="Trip Nest Logo" class="about-logo">
            <p class="about-text">At <strong>Trip Nest</strong>, we are passionate about creating unforgettable travel experiences. Our team of experts is dedicated to curating personalized trips that cater to your unique interests and preferences. Whether you're seeking adventure, relaxation, or cultural immersion, we have the perfect package for you.</p>  
            <p class="about-text">With years of experience in the travel industry, we pride ourselves on our exceptional customer service and attention to detail. From the moment you book with us to the end of your journey, we are committed to ensuring your satisfaction and making your travel dreams a reality.</p>
            <p class="about-text">Join us at Trip Nest and let us help you explore the world in a way that is meaningful and memorable.</p>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact">
        <h2 class="section-title">Contact Us</h2>
        <p class="section-subtitle">If you have any questions or need assistance, feel free to reach out to us:</p>
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
        
        // Active link highlighting on scroll
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.nav-links a');
            
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                
                if (window.scrollY >= (sectionTop - 150)) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('.nav-links a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId.startsWith('#')) {
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                } else {
                    window.location.href = this.getAttribute('href');
                }
            });
        });

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
        document.querySelectorAll('.cta-button, .itenary-button, .offer-button').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });


    </script>
</body>
</html>