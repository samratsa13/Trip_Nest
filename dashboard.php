<?php
session_start();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: Tourism.php");
    exit();
}
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tripnest_db";

// Initialize variables
$success_message = "";
$errors = [];

// Get current user data
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT name, address, phone, email, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($name, $address, $phone, $email, $user_role);
$stmt->fetch();
$stmt->close();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    
    // Validation (similar to registration)
    if (empty($name)) {
        $errors['name'] = "Name is required.";
    } elseif (preg_match('/^\s/', $name)) {
        $errors['name'] = "Name cannot start with a space.";
    } elseif (preg_match('/\s{3,}/', $name)) {
        $errors['name'] = "Name cannot have more than two consecutive spaces.";
    } elseif (preg_match('/[0-9]/', $name)) {
        $errors['name'] = "Name cannot contain numbers.";
    } elseif (preg_match('/[^\w\s]/', $name)) {
        $errors['name'] = "Name cannot contain special characters.";
    }
    
    if (empty($address)) {
        $errors['address'] = "Address is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9\s,\-]+$/', $address)) {
        $errors['address'] = "Address can only contain letters, numbers, spaces, commas, and hyphens.";
    }
    
    if (empty($phone)) {
        $errors['phone'] = "Phone number is required.";
    } elseif (!preg_match('/^(97|98)[0-9]{8}$/', $phone)) {
        $errors['phone'] = "Phone number must start with 97 or 98 and be exactly 10 digits.";
    }
    
    if (empty($new_email)) {
        $errors['email'] = "Email is required.";
    } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_\-]*(\.[a-zA-Z0-9_\-]+)*@[a-zA-Z]+\.[a-zA-Z]{2,}$/', $new_email)) {
        $errors['email'] = "Email must start with a letter, can only contain one dot before @, and domain must be letters only.";
    }
    
    // Check if email is already taken by another user
    if ($new_email !== $email) {
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check_stmt->bind_param("si", $new_email, $_SESSION['user_id']);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $errors['email'] = "This email is already registered.";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        $update_stmt = $conn->prepare("UPDATE users SET name = ?, address = ?, phone = ?, email = ? WHERE user_id = ?");
        $update_stmt->bind_param("ssssi", $name, $address, $phone, $new_email, $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            $email = $new_email; // Update local variable
            $_SESSION['user_email'] = $new_email;
            $_SESSION['user_name'] = $name;
        } else {
            $errors['general'] = "Error updating profile: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Trip Nest</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 80px auto 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(90deg, #031881, #6f7ecb);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        h1 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .welcome-message {
            color: #666;
            font-size: 1.1rem;
        }
        
        .profile-form {
            display: grid;
            gap: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            text-align: center;
        }
        
        input, textarea {
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        input:focus, textarea:focus {
            border-color: #031881;
            outline: none;
            box-shadow: 0 0 0 3px rgba(3, 24, 129, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            text-decoration: none;
        }
        
        .btn:hover {
            text-decoration: none;
        }
        
        .btn:focus {
            text-decoration: none;
        }
        
        .btn:visited {
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, #031881, #6f7ecb);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .error-msg {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Trip Nest</div>
            <h1>User Profile</h1>
            <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-msg"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="error-msg"><?php echo $errors['general']; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="profileForm" class="profile-form" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required
                           pattern="^(?!\s)(?!.*\s{3,})(?!.*\d)(?!.*_)(?!.*[^\w\s]).+$"
                           title="No leading spaces, no numbers/special chars, no triple spaces">
                    <div id="nameError" class="error-msg">
                        <?php echo $errors['name'] ?? ''; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>" required
                           pattern="^[a-zA-Z0-9\s,\-]+$"
                           title="Letters, numbers, spaces, commas and hyphens only">
                    <div id="addressError" class="error-msg">
                        <?php echo $errors['address'] ?? ''; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required
                           pattern="^(97|98)[0-9]{8}$"
                           title="Must start with 97 or 98 and be exactly 10 digits">
                    <div id="phoneError" class="error-msg">
                        <?php echo $errors['phone'] ?? ''; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required
                           pattern="^[a-zA-Z][a-zA-Z0-9_\-]*(\.[a-zA-Z0-9_\-]+)*@[a-zA-Z]+\.[a-zA-Z]{2,}$"
                           title="Start with letter, one dot max before @, letters only domain">
                    <div id="emailError" class="error-msg">
                        <?php echo $errors['email'] ?? ''; ?>
                    </div>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                <a href="Tourism.php" class="btn btn-secondary">Back to Home</a>
                <?php if (isset($user_role) && $user_role === 'admin'): ?>
                    <a href="admin.php" class="btn btn-warning">Admin Dashboard</a>
                <?php endif; ?>
                <a href="logout.php?logout=true" class="btn btn-danger">Logout</a>
            </div>
        </form>
    </div>
    <script>
        // ✅ Real-time inline validation (adapted from register.php)
        document.getElementById("name").addEventListener("input", function() {
            const nameError = document.getElementById("nameError");
            if (this.value.trim() === "") {
                nameError.innerText = "❌ Name is required.";
                return;
            }
            
            // Check if starts with space
            if (/^\s/.test(this.value)) {
                nameError.innerText = "❌ Name cannot start with a space.";
                return;
            }
            
            // Check for more than two consecutive spaces
            if (/\s{3,}/.test(this.value)) {
                nameError.innerText = "❌ Name cannot have more than two consecutive spaces.";
                return;
            }
            
            // Check for numbers
            if (/[0-9]/.test(this.value)) {
                nameError.innerText = "❌ Name cannot contain numbers.";
                return;
            }
            
            // Check for special characters (except spaces)
            if (/[^\w\s]/.test(this.value)) {
                nameError.innerText = "❌ Name cannot contain special characters.";
                return;
            }
            
            nameError.innerText = "";
        });

        document.getElementById("address").addEventListener("input", function() {
            const addressError = document.getElementById("addressError");
            if (this.value.trim() === "") {
                addressError.innerText = "❌ Address is required.";
                return;
            }
            
            // Check for valid characters (letters, numbers, spaces, commas, hyphens)
            if (!/^[a-zA-Z0-9\s,\-]+$/.test(this.value)) {
                addressError.innerText = "❌ Address can only contain letters, numbers, spaces, commas, and hyphens.";
                return;
            }
            
            addressError.innerText = "";
        });

        document.getElementById("phone").addEventListener("input", function() {
            const phoneError = document.getElementById("phoneError");
            
            // Remove any non-digit characters visually
            this.value = this.value.replace(/\D/g, '');

            if (this.value === "") {
                phoneError.innerText = "❌ Phone number is required.";
                return;
            }
            
            // Check if starts with 97 or 98 and is exactly 10 digits
            if (!/^(97|98)[0-9]{8}$/.test(this.value)) {
                phoneError.innerText = "❌ Phone number must start with 97 or 98 and be exactly 10 digits.";
                return;
            }
            
            phoneError.innerText = "";
        });

        document.getElementById("email").addEventListener("input", function() {
            const emailError = document.getElementById("emailError");
            if (this.value.trim() === "") {
                emailError.innerText = "❌ Email is required.";
                return;
            }
            
            // Check if starts with letter
            if (!/^[a-zA-Z]/.test(this.value)) {
                emailError.innerText = "❌ Email must start with a letter.";
                return;
            }
            
            // Check for more than one dot before @
            const localPart = this.value.split('@')[0];
            if ((localPart.match(/\./g) || []).length > 1) {
                emailError.innerText = "❌ Email can only contain one dot before @.";
                return;
            }
            
            // Check for spaces
            if (/\s/.test(this.value)) {
                emailError.innerText = "❌ Email cannot contain spaces.";
                return;
            }
            
            // Check for valid characters (letters, numbers, _, -, .)
            if (!/^[a-zA-Z0-9_\-\.]+@[a-zA-Z]+\.[a-zA-Z]{2,}$/.test(this.value)) {
                emailError.innerText = "❌ Email format is invalid. Only letters, numbers, hyphens, and underscores are allowed before @.";
                return;
            }
            
            emailError.innerText = "";
        });

        // Form submission validation
        document.getElementById("profileForm").addEventListener("submit", function(event) {
            let isValid = true;
            
            // Validate all fields
            const name = document.getElementById("name");
            const address = document.getElementById("address");
            const phone = document.getElementById("phone");
            const email = document.getElementById("email");
            
            // Trigger validation for all fields
            name.dispatchEvent(new Event('input'));
            address.dispatchEvent(new Event('input'));
            phone.dispatchEvent(new Event('input'));
            email.dispatchEvent(new Event('input'));
            
            // Check if any errors exist
            const errorElements = document.querySelectorAll('.error-msg');
            for (let i = 0; i < errorElements.length; i++) {
                if (errorElements[i].innerText !== "" && errorElements[i].innerText.includes("❌")) {
                    isValid = false;
                    break;
                }
            }
            
            if (!isValid) {
                event.preventDefault();
                // Scroll to first error
                const firstError = document.querySelector('.error-msg:not(:empty)');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>
</body>
</html>