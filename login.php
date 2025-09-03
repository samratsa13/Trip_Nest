<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="log.css">
    <title>TripNest-Login</title>
</head>
<body>
    <div class="container">
    <img src="img/logo.png" alt="">
    <h1>Login to Trip Nest</h1>
    <form action="authenticate.php" method="POST">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" class="input-field" required>
        
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" class="input-field" required>
        
        <input type="submit" value="Login"><br>
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </form>
    </div>
</body>
</html>