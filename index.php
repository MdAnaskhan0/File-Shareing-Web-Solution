<?php require 'auth.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Sharing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .login-container {
            max-width: 400px;
            margin: auto;
        }

        .brand-text {
            color: #CD2128;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container py-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow login-container">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="d-flex justify-content-center align-items-center mb-2">
                                <img src="image/logo.png" alt="Fashion Optics Ltd.">
                                <h1 class="h3 brand-text ms-1">Fashion Cloud</h1>
                            </div>
                            <p class="text-muted text-uppercase fs-6">Secure file sharing platform</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" id="username"
                                    placeholder="Enter your username" required>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" id="password"
                                    placeholder="Enter your password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Sign In</button>
                            <p class="small text-muted mt-3">Powered by <span class="fw-bold" style="color: #CD2128">Fashion Optics Ltd.</span></p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light py-3 mt-auto">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="mb-2 mb-md-0">
                    <p class="mb-0 text-muted">
                        Copyright © 2025 <a href="https://fg-bd.com"
                            class="fw-bold text-success text-decoration-none">Fashion Group</a> All rights reserved.
                    </p>
                </div>
                <div>
                    <p class="mb-0 text-muted">
                        Developed by <span class="fw-bold" style="color: #111184">Fashion Group IT</span>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>