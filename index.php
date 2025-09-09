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
    <div style="min-height: 100vh">
        <div class="login-container card">
            <div class="login-header">
                <h1>Fashion Cloud</h1>
                <p>Secure file sharing platform</p>
            </div>

            <?php if (!empty($error))
                echo "<div class='alert alert-error'>$error</div>"; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-input" type="text" name="username" id="username"
                        placeholder="Enter your username" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-input" type="password" name="password" id="password"
                        placeholder="Enter your password" required>
                </div>

                <button class="btn" type="submit" style="width: 100%">Sign In</button>
            </form>
        </div>
    </div>

    <footer
        style="background:#f8f8f8; padding:15px 0; text-align:center; font-family:Arial, sans-serif; font-size:14px; color:#555; border-top:1px solid #ddd;">
        <div
            style="max-width:900px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">

            <div style="margin:5px 0;">
                <p style="margin:0;">Copyright © 2025
                    <a href="https://fg-bd.com" style="font-weight:bold; color:#05b356; text-decoration: none;">Fashion
                        Group</a> All rights reserved.
                </p>
            </div>

            <div style="margin:5px 0;">
                <p style="margin:0;">Developed by
                    <span style="font-weight:bold; color:#1c398e; cursor:pointer;">Fashion IT</span>
                </p>
            </div>
        </div>
    </footer>


</body>

</html>