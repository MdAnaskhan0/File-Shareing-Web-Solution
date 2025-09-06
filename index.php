<?php require 'auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Sharing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container card">
        <div class="login-header">
            <h1>FileShare</h1>
            <p>Secure file sharing platform</p>
        </div>
        
        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input class="form-input" type="text" name="username" id="username" placeholder="Enter your username" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-input" type="password" name="password" id="password" placeholder="Enter your password" required>
            </div>
            
            <button class="btn" type="submit" style="width: 100%">Sign In</button>
        </form>
    </div>
</body>
</html>