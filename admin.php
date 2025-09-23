<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Database connection
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add Special Offer
    if (isset($_POST['add_offer'])) {
        $title = $_POST['offer_title'];
        $description = $_POST['offer_description'];
        $status = $_POST['offer_status'];
        
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['offer_image']) && $_FILES['offer_image']['error'] == 0) {
            $target_dir = "uploads/offers/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $image_extension = pathinfo($_FILES['offer_image']['name'], PATHINFO_EXTENSION);
            $image_name = 'offer_' . time() . '.' . $image_extension;
            $target_file = $target_dir . $image_name;
            
            if (move_uploaded_file($_FILES['offer_image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO special_offers (title, description, image_path, status) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $image_path, $status])) {
            $offer_success = "Special offer added successfully!";
        } else {
            $offer_error = "Error adding special offer!";
        }
    }
    
    // Add Popular Itinerary
    if (isset($_POST['add_itinerary'])) {
        $title = $_POST['itinerary_title'];
        $description = $_POST['itinerary_description'];
        $status = $_POST['itinerary_status'];
        
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['itinerary_image']) && $_FILES['itinerary_image']['error'] == 0) {
            $target_dir = "uploads/itineraries/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $image_extension = pathinfo($_FILES['itinerary_image']['name'], PATHINFO_EXTENSION);
            $image_name = 'itinerary_' . time() . '.' . $image_extension;
            $target_file = $target_dir . $image_name;
            
            if (move_uploaded_file($_FILES['itinerary_image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO popular_itineraries (title, description, image_path, status) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $image_path, $status])) {
            $itinerary_success = "Popular itinerary added successfully!";
        } else {
            $itinerary_error = "Error adding popular itinerary!";
        }
    }
    
    // Delete Special Offer
    if (isset($_POST['delete_offer'])) {
        $offer_id = $_POST['offer_id'];
        
        // Get image path to delete file
        $stmt = $pdo->prepare("SELECT image_path FROM special_offers WHERE id = ?");
        $stmt->execute([$offer_id]);
        $offer = $stmt->fetch();
        
        if ($offer && !empty($offer['image_path']) && file_exists($offer['image_path'])) {
            unlink($offer['image_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM special_offers WHERE id = ?");
        if ($stmt->execute([$offer_id])) {
            $offer_success = "Special offer deleted successfully!";
        } else {
            $offer_error = "Error deleting special offer!";
        }
    }
    
    // Delete Popular Itinerary
    if (isset($_POST['delete_itinerary'])) {
        $itinerary_id = $_POST['itinerary_id'];
        
        // Get image path to delete file
        $stmt = $pdo->prepare("SELECT image_path FROM popular_itineraries WHERE id = ?");
        $stmt->execute([$itinerary_id]);
        $itinerary = $stmt->fetch();
        
        if ($itinerary && !empty($itinerary['image_path']) && file_exists($itinerary['image_path'])) {
            unlink($itinerary['image_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM popular_itineraries WHERE id = ?");
        if ($stmt->execute([$itinerary_id])) {
            $itinerary_success = "Popular itinerary deleted successfully!";
        } else {
            $itinerary_error = "Error deleting popular itinerary!";
        }
    }
    
    // Update Offer Status
    if (isset($_POST['update_offer_status'])) {
        $offer_id = $_POST['offer_id'];
        $status = $_POST['offer_status'];
        
        $stmt = $pdo->prepare("UPDATE special_offers SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $offer_id])) {
            $offer_success = "Offer status updated successfully!";
        } else {
            $offer_error = "Error updating offer status!";
        }
    }
    
    // Update Itinerary Status
    if (isset($_POST['update_itinerary_status'])) {
        $itinerary_id = $_POST['itinerary_id'];
        $status = $_POST['itinerary_status'];
        
        $stmt = $pdo->prepare("UPDATE popular_itineraries SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $itinerary_id])) {
            $itinerary_success = "Itinerary status updated successfully!";
        } else {
            $itinerary_error = "Error updating itinerary status!";
        }
    }
}

// Get counts for dashboard
$user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$order_count = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$offer_count = $pdo->query("SELECT COUNT(*) FROM special_offers")->fetchColumn();
$itinerary_count = $pdo->query("SELECT COUNT(*) FROM popular_itineraries")->fetchColumn();

// Get recent users
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get recent orders
$recent_orders = $pdo->query("SELECT o.*, u.name as user_name FROM orders o JOIN users u ON o.user_id = u.user_id ORDER BY o.created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Trip Nest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #031881;
            --secondary: #6f7ecb;
            --light: #f5f7fa;
            --dark: #333;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            height: 100vh;
            position: fixed;
            transition: all 0.3s;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu ul {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid white;
        }
        
        .sidebar-menu i {
            margin-right: 0.8rem;
            font-size: 1.2rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .header h1 {
            color: var(--primary);
            font-size: 1.8rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .users .card-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .orders .card-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
        .offers .card-icon { background: rgba(3, 24, 129, 0.2); color: var(--primary); }
        .itineraries .card-icon { background: rgba(220, 53, 69, 0.2); color: var(--danger); }
        
        .card h3 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .card p {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .status {
            padding: 0.3rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.3rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: black; }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 600px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 0.3rem;
            font-size: 1rem;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 1rem;
        }
        
        .tab {
            padding: 0.8rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary);
            color: var(--primary);
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2, .sidebar-menu span {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 1rem;
            }
            
            .sidebar-menu i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }
        .alert {
            padding: 0.8rem 1.5rem;
            border-radius: 0.3rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            margin-top: 0.5rem;
            border-radius: 0.3rem;
            display: none;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.3rem 0.7rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Trip Nest Admin</h2>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        </div>
        <nav class="sidebar-menu">
            <ul>
                <li><a href="#" class="active" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="#" data-tab="users"><i class="fas fa-users"></i> <span>Users</span></a></li>
                <li><a href="#" data-tab="orders"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
                <li><a href="#" data-tab="offers"><i class="fas fa-gift"></i> <span>Special Offers</span></a></li>
                <li><a href="#" data-tab="itineraries"><i class="fas fa-route"></i> <span>Popular Itineraries</span></a></li>
                <li><a href="Tourism.php"><i class="fas fa-home"></i> <span>Back to Site</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <div class="user-info">
                <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=031881&color=fff" alt="Admin">
            </div>
        </div>

        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content active">
            <div class="dashboard-cards">
                <div class="card users">
                    <div class="card-header">
                        <div>
                            <h3><?php echo $user_count; ?></h3>
                            <p>Total Users</p>
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                
                <div class="card orders">
                    <div class="card-header">
                        <div>
                            <h3><?php echo $order_count; ?></h3>
                            <p>Total Orders</p>
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
                
                <div class="card offers">
                    <div class="card-header">
                        <div>
                            <h3><?php echo $offer_count; ?></h3>
                            <p>Special Offers</p>
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                    </div>
                </div>
                
                <div class="card itineraries">
                    <div class="card-header">
                        <div>
                            <h3><?php echo $itinerary_count; ?></h3>
                            <p>Popular Itineraries</p>
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-route"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3>Recent Users</h3>
                    <button class="btn btn-primary" onclick="openModal('user')">Add User</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-warning">Edit</button>
                                <button class="btn btn-danger">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3>Recent Orders</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['package_name']); ?></td>
                            <td>$<?php echo number_format($order['amount'], 2); ?></td>
                            <td><span class="status status-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-warning">Edit</button>
                                <button class="btn btn-danger">Cancel</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Users Tab -->
        <div id="users" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>All Users</h3>
                    <button class="btn btn-primary" onclick="openModal('user')">Add User</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- User data will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Orders Tab -->
        <div id="orders" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>All Orders</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Order data will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

           <!-- Special Offers Tab -->
        <div id="offers" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>Special Offers</h3>
                    <button class="btn btn-primary" onclick="openModal('offer')">Add Offer</button>
                </div>
                
                <?php if (isset($offer_success)): ?>
                    <div class="alert alert-success"><?php echo $offer_success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($offer_error)): ?>
                    <div class="alert alert-error"><?php echo $offer_error; ?></div>
                <?php endif; ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($offers as $offer): ?>
                        <tr>
                            <td><?php echo $offer['id']; ?></td>
                            <td><?php echo htmlspecialchars($offer['title']); ?></td>
                            <td><?php echo htmlspecialchars(substr($offer['description'], 0, 100)) . '...'; ?></td>
                            <td>
                                <?php if (!empty($offer['image_path'])): ?>
                                    <img src="<?php echo $offer['image_path']; ?>" alt="Offer Image" style="width: 80px; height: 60px; object-fit: cover; border-radius: 0.3rem;">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $offer['status']; ?>">
                                    <?php echo ucfirst($offer['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                        <select name="offer_status" onchange="this.form.submit()" class="form-control" style="width: 120px; display: inline-block; margin-right: 5px;">
                                            <option value="active" <?php echo $offer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $offer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <input type="hidden" name="update_offer_status" value="1">
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                        <button type="submit" name="delete_offer" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this offer?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

   <!-- Popular Itineraries Tab -->
        <div id="itineraries" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>Popular Itineraries</h3>
                    <button class="btn btn-primary" onclick="openModal('itinerary')">Add Itinerary</button>
                </div>
                
                <?php if (isset($itinerary_success)): ?>
                    <div class="alert alert-success"><?php echo $itinerary_success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($itinerary_error)): ?>
                    <div class="alert alert-error"><?php echo $itinerary_error; ?></div>
                <?php endif; ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($itineraries as $itinerary): ?>
                        <tr>
                            <td><?php echo $itinerary['id']; ?></td>
                            <td><?php echo htmlspecialchars($itinerary['title']); ?></td>
                            <td><?php echo htmlspecialchars(substr($itinerary['description'], 0, 100)) . '...'; ?></td>
                            <td>
                                <?php if (!empty($itinerary['image_path'])): ?>
                                    <img src="<?php echo $itinerary['image_path']; ?>" alt="Itinerary Image" style="width: 80px; height: 60px; object-fit: cover; border-radius: 0.3rem;">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $itinerary['status']; ?>">
                                    <?php echo ucfirst($itinerary['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="itinerary_id" value="<?php echo $itinerary['id']; ?>">
                                        <select name="itinerary_status" onchange="this.form.submit()" class="form-control" style="width: 120px; display: inline-block; margin-right: 5px;">
                                            <option value="active" <?php echo $itinerary['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $itinerary['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <input type="hidden" name="update_itinerary_status" value="1">
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="itinerary_id" value="<?php echo $itinerary['id']; ?>">
                                        <button type="submit" name="delete_itinerary" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this itinerary?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <span class="close" onclick="closeModal('userModal')">&times;</span>
            </div>
            <form id="userForm">
                <div class="form-group">
                    <label for="userName">Full Name</label>
                    <input type="text" id="userName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="userEmail">Email</label>
                    <input type="email" id="userEmail" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="userRole">Role</label>
                    <select id="userRole" class="form-control" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="userPassword">Password</label>
                    <input type="password" id="userPassword" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Save User</button>
            </form>
        </div>
    </div>

    <div id="offerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Special Offer</h3>
                <span class="close" onclick="closeModal('offerModal')">&times;</span>
            </div>
            <form id="offerForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="offerTitle">Title</label>
                    <input type="text" id="offerTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="offerDescription">Description</label>
                    <textarea id="offerDescription" class="form-control" required></textarea>
                </div>
              <div class="form-group">
                    <label for="offer_image">Image</label>
                    <input type="file" id="offer_image" name="offer_image" class="form-control" accept="image/*" onchange="previewImage(this, 'offerPreview')">
                    <img id="offerPreview" class="image-preview" src="#" alt="Image Preview">
                </div>
                <div class="form-group">
                    <label for="offerStatus">Status</label>
                    <select id="offerStatus" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Offer</button>
            </form>
        </div>
    </div>

    <div id="itineraryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Popular Itinerary</h3>
                <span class="close" onclick="closeModal('itineraryModal')">&times;</span>
            </div>
            <form id="itineraryForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="itineraryTitle">Title</label>
                    <input type="text" id="itineraryTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="itineraryDescription">Description</label>
                    <textarea id="itineraryDescription" class="form-control" required></textarea>
                </div>
              <div class="form-group">
                    <label for="itinerary_image">Image</label>
                    <input type="file" id="itinerary_image" name="itinerary_image" class="form-control" accept="image/*" onchange="previewImage(this, 'itineraryPreview')">
                    <img id="itineraryPreview" class="image-preview" src="#" alt="Image Preview">
                </div>
                <div class="form-group">
                    <label for="itineraryStatus">Status</label>
                    <select id="itineraryStatus" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Itinerary</button>
            </form>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('data-tab')) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs and links
                    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    document.getElementById(this.getAttribute('data-tab')).classList.add('active');
                }
            });
        });
        
        // Modal functionality
        function openModal(type) {
            document.getElementById(type + 'Modal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            document.querySelectorAll('.modal').forEach(modal => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });


        // img preview option
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.addEventListener('load', function() {
                    preview.src = reader.result;
                    preview.style.display = 'block';
                });
                
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }
        
         
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Form submissions
        document.getElementById('userForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Add user logic here
            alert('User added successfully!');
            closeModal('userModal');
        });
        
        document.getElementById('offerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Add offer logic here
            alert('Special offer added successfully!');
            closeModal('offerModal');
        });
        
        document.getElementById('itineraryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Add itinerary logic here
            alert('Popular itinerary added successfully!');
            closeModal('itineraryModal');
        });
        
        // Load data for tabs
        function loadTabData(tab) {
            // This would typically make an AJAX request to fetch data
            console.log('Loading data for: ' + tab);
        }
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial data
            loadTabData('dashboard');
        });
    </script>
</body>
</html>