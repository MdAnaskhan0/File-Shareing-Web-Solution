<?php
require 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Create user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = md5($_POST['password']);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $password, $role);

    if ($stmt->execute()) {
        $msg = "✅ User created successfully!";
        $msgType = "success";
    } else {
        $msg = "❌ Error: " . $conn->error;
        $msgType = "danger";
    }
    $stmt->close();
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $userId = intval($_POST['user_id']);
    $newPassword = md5($_POST['new_password']);

    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param("si", $newPassword, $userId);

    if ($stmt->execute()) {
        $msg = "✅ Password changed successfully!";
        $msgType = "success";
    } else {
        $msg = "❌ Error: " . $conn->error;
        $msgType = "danger";
    }
    $stmt->close();
}

// Delete user
if (isset($_GET['delete'])) {
    $userId = intval($_GET['delete']);
    if ($userId != $_SESSION['user']['id']) { // prevent self-deletion
        // Delete user files too
        $result = $conn->query("SELECT * FROM files WHERE uploaded_by=(SELECT username FROM users WHERE id=$userId)");
        while ($file = $result->fetch_assoc()) {
            if (file_exists($file['filepath'])) {
                unlink($file['filepath']);
            }
            $conn->query("DELETE FROM files WHERE id=" . $file['id']);
        }

        $conn->query("DELETE FROM users WHERE id=$userId");
        $msg = "🗑️ User and their files deleted!";
        $msgType = "success";
    } else {
        $msg = "⚠️ You can't delete yourself!";
        $msgType = "warning";
    }
}

// Get all users
$users = $conn->query("SELECT id, username, role FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - File Sharing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .navbar-brand {
            font-weight: 600;
            color: #CD2128;
        }

        .navbar-brand:hover {
            color: #CD2128;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }

        .badge-admin {
            background-color: rgba(67, 97, 238, 0.1);
            color: #4361ee;
        }

        .badge-user {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light shadow p-3 mb-5 bg-body rounded">
        <div class="container">
            <a class="navbar-brand" href="files.php"><img src="image/logo.png" alt="Fashion Optics Ltd.">Fashion
                Cloud</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <!-- <span class="nav-link">Admin Panel</span> -->
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="files.php"><i class="bi bi-folder me-1"></i>Back to Files</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" style="color: #CD2128;" href="auth.php?logout=1"><i
                                class="bi bi-box-arrow-right me-1 "></i>Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Admin Panel</h1>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Create New User</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="create_user" value="1">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" placeholder="Enter username"
                                required>
                        </div>
                        <div class="col-md-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Enter password"
                                required>
                        </div>
                        <div class="col-md-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" type="submit">Create User</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="user_id" class="form-label">Select User</label>
                            <select class="form-select" name="user_id" required>
                                <?php
                                $users_list = $conn->query("SELECT id, username FROM users ORDER BY username");
                                while ($user = $users_list->fetch_assoc()) {
                                    echo "<option value='{$user['id']}'>{$user['username']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password"
                                placeholder="Enter new password" required>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" type="submit">Change Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">All Users</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $users->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['username']; ?></td>
                                    <td>
                                        <span
                                            class="badge <?php echo $row['role'] === 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                                            <?php echo ucfirst($row['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['id'] != $_SESSION['user']['id']) { ?>
                                            <a href="admin.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Delete this user and all their files?');">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        <?php } else { ?>
                                            <span class="text-muted">(Current user)</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light py-3 mt-auto" style="box-shadow: 0 -3px 6px rgba(0, 0, 0, 0.1);">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="mb-2 mb-md-0">
                    <p class="mb-0 text-muted">
                        Copyright © 2025
                        <a href="https://fg-bd.com" target="_blank" class="fw-bold text-success text-decoration-none">
                            Fashion Group
                        </a> All rights reserved.
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