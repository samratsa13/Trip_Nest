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
            <!-- <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a> -->
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