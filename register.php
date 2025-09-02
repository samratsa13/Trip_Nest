<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="reg.css">
    <title>TripNest-Login</title>
</head>
<body>
    <div class="container">
    <img src="img/logo.png" alt="">
    <h1>Register to Trip Nest</h1>
    <form action="register_process.php" method="POST">
        <div class="first"><label for="name"> Name:</label>
        <input type="text" id="name" name="name" class="input-field" required>

        <label for="dob">DOB:</label>
        <input type="date" id="dob" name="dob" class="input-field" required>
        </div>

        <div class="second">
        <label for="address">Address:</label>
        <input type="text" id="address" name="address" class="input-field" required>

        <label for="phone">Phone:</label>
        <input type="tel" id="phone" name="phone" class="input-field" required>
        </div>

        <div class="third">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" class="input-field" required>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" class="input-field" required>
        </div>

        <input type="submit" value="Register"><br>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </form>
    </div>
</body>
</html>