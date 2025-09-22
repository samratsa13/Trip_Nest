<?php
// ✅ PHP code will run only when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "tripnest_db";

    // Initialize error array
    $errors = [];

    // Validate and sanitize inputs
    $name = trim($_POST['name'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation rules
    // Name validation: Can't start with space, can't have more than two consecutive spaces, no numbers or special chars
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
    
    // DOB validation: Cannot be today or future date
    if (empty($dob)) {
        $errors['dob'] = "Date of Birth is required.";
    } else {
        $today = new DateTime();
        $birthdate = new DateTime($dob);
        
        if ($birthdate >= $today) {
            $errors['dob'] = "Date of Birth cannot be today or a future date.";
        }
    }
    
    // Address validation: Only letters, numbers, commas, and hyphens
    if (empty($address)) {
        $errors['address'] = "Address is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9\s,\-]+$/', $address)) {
        $errors['address'] = "Address can only contain letters, numbers, spaces, commas, and hyphens.";
    }
    
    // Phone validation: Must start with 97 or 98, exactly 10 digits
    if (empty($phone)) {
        $errors['phone'] = "Phone number is required.";
    } elseif (!preg_match('/^(97|98)[0-9]{8}$/', $phone)) {
        $errors['phone'] = "Phone number must start with 97 or 98 and be exactly 10 digits.";
    }
    
    // Email validation: Must start with letter, only one dot, no spaces, specific special chars allowed
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_\-]*(\.[a-zA-Z0-9_\-]+)*@[a-zA-Z]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors['email'] = "Email must start with a letter, can only contain one dot before @, and domain must be letters only.";
    }
    
    // Password validation: Upper, lower, special char, no spaces
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = "Password must contain at least one number.";
    } elseif (!preg_match('/[^\w]/', $password)) {
        $errors['password'] = "Password must contain at least one special character.";
    } elseif (preg_match('/\s/', $password)) {
        $errors['password'] = "Password cannot contain spaces.";
    }
    
    // Confirm password validation
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    // If no errors, proceed with database operation
    if (empty($errors)) {
        $conn = new mysqli(localhost, root, , tripnest_db);
        if ($conn->connect_error) {
            die("Database Connection failed: " . $conn->connect_error);
        }

        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $errors['email'] = "This email is already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (name, dob, address, phone, email, password) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $dob, $address, $phone, $email, $hashed_password);

            if ($stmt->execute()) {
                $success_message = "✅ Registration successful! <a href='login.php'>Login here</a>";
            } else {
                $errors['general'] = "❌ Error: " . $stmt->error;
            }

            $stmt->close();
        }
        
        $check_stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TripNest - Register</title>
    <link rel="stylesheet" href="regist.css">
</head>
<body>
    <div class="container">
        <img src="img/logo.png" alt="TripNest Logo">
        <h1>Register to Trip Nest</h1>
        
        <?php if (!empty($success_message)): ?>
            <p class="success-msg"><?php echo $success_message; ?></p>
        <?php endif; ?>
        
        <?php if (!empty($errors['general'])): ?>
            <p class="error-msg"><?php echo $errors['general']; ?></p>
        <?php endif; ?>
        
        <form method="POST" id="registerForm" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" class="input-field" 
                           value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                    <div id="nameError" class="error-msg">
                        <?php echo $errors['name'] ?? ''; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="dob">DOB:</label>
                    <input type="date" id="dob" name="dob" class="input-field" 
                           value="<?php echo htmlspecialchars($dob ?? ''); ?>" required>
                    <div id="dobError" class="error-msg">
                        <?php echo $errors['dob'] ?? ''; ?>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address" class="input-field" 
                           value="<?php echo htmlspecialchars($address ?? ''); ?>" required>
                    <div id="addressError" class="error-msg">
                        <?php echo $errors['address'] ?? ''; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" class="input-field" 
                           value="<?php echo htmlspecialchars($phone ?? ''); ?>" required>
                    <div id="phoneError" class="error-msg">
                        <?php echo $errors['phone'] ?? ''; ?>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="input-field" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    <div id="emailError" class="error-msg">
                        <?php echo $errors['email'] ?? ''; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" class="input-field" required>
                    <div id="passwordError" class="error-msg">
                        <?php echo $errors['password'] ?? ''; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="input-field" required>
                    <div id="confirmPasswordError" class="error-msg">
                        <?php echo $errors['confirm_password'] ?? ''; ?>
                    </div>
                </div>
            </div>

            <input type="submit" value="Register"><br>
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </form>
    </div>

    <script>
        // ✅ Real-time inline validation
        document.getElementById("name").addEventListener("input", function() {
            const nameError = document.getElementById("nameError");
            
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

        document.getElementById("dob").addEventListener("change", function() {
            const dobError = document.getElementById("dobError");
            
            if (this.value === "") {
                dobError.innerText = "❌ Please enter your Date of Birth.";
                return;
            }
            
            // Check if date is today or in the future
            const today = new Date();
            const selectedDate = new Date(this.value);
            today.setHours(0, 0, 0, 0); // Reset time part for accurate comparison
            
            if (selectedDate >= today) {
                dobError.innerText = "❌ Date of Birth cannot be today or a future date.";
                return;
            }
            
            dobError.innerText = "";
        });

        document.getElementById("address").addEventListener("input", function() {
            const addressError = document.getElementById("addressError");
            
            // Check for valid characters (letters, numbers, spaces, commas, hyphens)
            if (!/^[a-zA-Z0-9\s,\-]+$/.test(this.value)) {
                addressError.innerText = "❌ Address can only contain letters, numbers, spaces, commas, and hyphens.";
                return;
            }
            
            addressError.innerText = "";
        });

        document.getElementById("phone").addEventListener("input", function() {
            const phoneError = document.getElementById("phoneError");
            
            // Remove any non-digit characters
            this.value = this.value.replace(/\D/g, '');
            
            // Check if starts with 97 or 98 and is exactly 10 digits
            if (!/^(97|98)[0-9]{8}$/.test(this.value)) {
                phoneError.innerText = "❌ Phone number must start with 97 or 98 and be exactly 10 digits.";
                return;
            }
            
            phoneError.innerText = "";
        });

        document.getElementById("email").addEventListener("input", function() {
            const emailError = document.getElementById("emailError");
            
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

        document.getElementById("password").addEventListener("input", function() {
            validatePassword();
        });
        
        document.getElementById("confirm_password").addEventListener("input", function() {
            validatePassword();
        });
        
        function validatePassword() {
            const password = document.getElementById("password").value;
            const confirmPass = document.getElementById("confirm_password").value;
            const errorElement = document.getElementById("passwordError");
            const confirmErrorElement = document.getElementById("confirmPasswordError");
            
            // Reset errors
            errorElement.innerText = "";
            confirmErrorElement.innerText = "";
            
            // Check length
            if (password.length < 8) {
                errorElement.innerText = "❌ Must be at least 8 characters.";
                return;
            }
            
            // Check for uppercase
            if (!/[A-Z]/.test(password)) {
                errorElement.innerText = "❌ Must contain at least one uppercase letter.";
                return;
            }
            
            // Check for lowercase
            if (!/[a-z]/.test(password)) {
                errorElement.innerText = "❌ Must contain at least one lowercase letter.";
                return;
            }
            
            // Check for number
            if (!/[0-9]/.test(password)) {
                errorElement.innerText = "❌ Must contain at least one number.";
                return;
            }
            
            // Check for special character
            if (!/[^\w]/.test(password)) {
                errorElement.innerText = "❌ Must contain at least one special character.";
                return;
            }
            
            // Check for spaces
            if (/\s/.test(password)) {
                errorElement.innerText = "❌ Password cannot contain spaces.";
                return;
            }
            
            // Check if passwords match
            if (confirmPass && password !== confirmPass) {
                confirmErrorElement.innerText = "❌ Passwords do not match.";
                return;
            }
        }
        
        // Form submission validation
        document.getElementById("registerForm").addEventListener("submit", function(event) {
            let isValid = true;
            
            // Validate all fields
            const name = document.getElementById("name");
            const dob = document.getElementById("dob");
            const address = document.getElementById("address");
            const phone = document.getElementById("phone");
            const email = document.getElementById("email");
            const password = document.getElementById("password");
            const confirmPassword = document.getElementById("confirm_password");
            
            // Trigger validation for all fields
            name.dispatchEvent(new Event('input'));
            dob.dispatchEvent(new Event('change'));
            address.dispatchEvent(new Event('input'));
            phone.dispatchEvent(new Event('input'));
            email.dispatchEvent(new Event('input'));
            validatePassword();
            
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
    </script>
</body>
</html>