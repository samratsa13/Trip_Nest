<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: Tourism.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tripnest_db";

// Initialize variables
$login_error = "";
$email_error = "";
$password_error = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    $valid = true;
    
    // Email validation
    if (empty($email)) {
        $email_error = "Email is required.";
        $valid = false;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_error = "Please enter a valid email address.";
        $valid = false;
    }
    
    // Password validation
    if (empty($password)) {
        $password_error = "Password is required.";
        $valid = false;
    }
    
    // If validation passes, check credentials
    if ($valid) {
        $conn = new mysqli("localhost", "root", "", "tripnest_db");
        
        if ($conn->connect_error) {
            die("Database Connection failed: " . $conn->connect_error);
        }
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT user_id, name, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id, $user_name, $hashed_password);
            $stmt->fetch();
            
            // Verify password
            if (password_verify($password, $hashed_password)) {
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $user_name;
                $_SESSION['user_email'] = $email;
                
                // Redirect to dashboard
                header("Location: Tourism.php?login_success=true");
                exit();
            } else {
                $login_error = "Invalid email or password.";
            }
        } else {
            $login_error = "Invalid email or password.";
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripNest - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }   
        
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
        }
        
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            width: 100%;
            max-width: 450px;
        }
        
        img {
            width: 120px;
            height: auto;
            object-fit: cover;
            border-radius: 1rem;
            margin-bottom: 0.5rem;
        }
        
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(90deg, #031881, #6f7ecb);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background-color: #f9f9f9;
            padding: 2em;
            border-radius: 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        form label {
            margin-bottom: 0.3rem;
            font-size: 1rem;
            color: #333;
            font-weight: 600;
        }
        
        form input[type="text"], 
        form input[type="password"],
        form input[type="email"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 3rem;
            font-size: 1rem;
        }
        
        form input[type="text"]:focus, 
        form input[type="password"]:focus,
        form input[type="email"]:focus {
            outline: none;
            border-color: #031881;
            box-shadow: 0 0 0 2px rgba(3, 24, 129, 0.2);
        }
        
        form input[type="submit"] {
            padding: 0.8rem 2rem;
            font-size: 1rem;
            border: 2px solid #031881;
            border-radius: 1rem;
            background-color: transparent;
            color: #031881;
            cursor: pointer;
            transition: 0.3s ease-in-out;
            align-self: center;
            margin-top: 1rem;
            width: 100%;
        }
        
        form input[type="submit"]:hover {
            background-color: #031881;
            color: white;
        }
        
        p {
            font-size: 0.9rem;
            color: #555;
            text-align: center;
            margin-top: 1rem;
        }
        
        p a {
            color: #031881;
            text-decoration: none;
            font-weight: 600;
        }
        
        p a:hover {
            text-decoration: underline;
        }
        
        .error-msg {
            color: red;
            font-size: 0.8rem;
            margin-top: 0.3rem;
            min-height: 1.2rem;
        }
        
        .login-error {
            color: red;
            font-size: 0.9rem;
            text-align: center;
            padding: 10px;
            border-radius: 5px;
            background-color: #ffe6e6;
            margin-bottom: 1rem;
        }
        
        /* Password visibility toggle */
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
        }
        
        /* Responsive design */
        @media (max-width: 480px) {
            form {
                padding: 1.5em;
            }
            
            h1 {
                font-size: 1.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="img/logo.png" alt="TripNest Logo">
        <h1>Login to Trip Nest</h1>
        
        <?php if (!empty($login_error)): ?>
            <div class="login-error"><?php echo $login_error; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm" novalidate>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" class="input-field" 
                       value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                <div id="emailError" class="error-msg">
                    <?php echo $email_error ?? ''; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" class="input-field" required>
                    <button type="button" class="toggle-password" id="togglePassword">👁️</button>
                </div>
                <div id="passwordError" class="error-msg">
                    <?php echo $password_error ?? ''; ?>
                </div>
            </div>

            <input type="submit" value="Login">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </form>
    </div>

    <script>
        // Real-time validation
        document.getElementById("email").addEventListener("input", function() {
            const emailError = document.getElementById("emailError");
            
            if (this.value === "") {
                emailError.innerText = "❌ Email is required.";
                return;
            }
            
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(this.value)) {
                emailError.innerText = "❌ Please enter a valid email address.";
                return;
            }
            
            emailError.innerText = "";
        });

        document.getElementById("password").addEventListener("input", function() {
            const passwordError = document.getElementById("passwordError");
            
            if (this.value === "") {
                passwordError.innerText = "❌ Password is required.";
                return;
            }
            
            passwordError.innerText = "";
        });

        // Toggle password visibility
        document.getElementById("togglePassword").addEventListener("click", function() {
            const passwordInput = document.getElementById("password");
            const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
            passwordInput.setAttribute("type", type);
            
            // Toggle eye icon
            this.textContent = type === "password" ? "👁️" : "🙈";
        });

        // Form submission validation
        document.getElementById("loginForm").addEventListener("submit", function(event) {
            let isValid = true;
            
            const email = document.getElementById("email");
            const password = document.getElementById("password");
            
            // Trigger validation
            email.dispatchEvent(new Event('input'));
            password.dispatchEvent(new Event('input'));
            
            // Check if any errors exist
            const errorElements = document.querySelectorAll('.error-msg');
            for (let i = 0; i < errorElements.length; i++) {
                if (errorElements[i].innerText !== "") {
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
        
        // Auto-focus on email field
        document.getElementById("email").focus();
    </script>
</body>
</html>