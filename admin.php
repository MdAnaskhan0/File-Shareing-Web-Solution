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
        $msgType = "error";
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
        $msgType = "error";
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
        $msgType = "error";
    }
}

// Get all users
$users = $conn->query("SELECT id, username, role FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html>

<head>
    <title>Admin Panel - File Sharing System</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="files.php" class="brand">Fashion Cloud</a>
                <div class="nav-links">
                    <span>Admin Panel</span>
                    <a href="files.php" class="nav-link">Back to Files</a>
                    <a href="auth.php?logout=1" class="nav-link logout">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
        <div class="page-header">
            <h1 class="page-title">Admin Panel</h1>
        </div>

        <?php if (!empty($msg))
            echo "<div class='alert alert-$msgType'>$msg</div>"; ?>

        <div class="card fade-in">
            <h3 class="mb-3">Create New User</h3>
            <form method="POST">
                <input type="hidden" name="create_user" value="1">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-input" type="text" name="username" placeholder="Enter username" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-input" type="password" name="password" placeholder="Enter password" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <button class="btn" type="submit">Create User</button>
                </div>
            </form>
        </div>

        <div class="card fade-in">
            <h3 class="mb-3">Change Password</h3>
            <form method="POST">
                <input type="hidden" name="change_password" value="1">
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                    <div class="form-group">
                        <label class="form-label" for="user_id">Select User</label>
                        <select class="form-select" name="user_id" required>
                            <?php
                            $users_list = $conn->query("SELECT id, username FROM users ORDER BY username");
                            while ($user = $users_list->fetch_assoc()) {
                                echo "<option value='{$user['id']}'>{$user['username']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <input class="form-input" type="password" name="new_password" placeholder="Enter new password"
                            required>
                    </div>

                    <button class="btn" type="submit">Change Password</button>
                </div>
            </form>
        </div>

        <div class="card fade-in">
            <h3 class="mb-3">All Users</h3>
            <div class="table-container">
                <table class="table">
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
                                        style="padding: 0.25rem 0.5rem; border-radius: 4px; 
                                    background-color: <?php echo $row['role'] === 'admin' ? 'rgba(67, 97, 238, 0.1)' : 'rgba(108, 117, 125, 0.1)'; ?>;
                                    color: <?php echo $row['role'] === 'admin' ? 'var(--primary)' : 'var(--gray)'; ?>;">
                                        <?php echo ucfirst($row['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['id'] != $_SESSION['user']['id']) { ?>
                                        <a href="admin.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Delete this user and all their files?');">Delete</a>
                                    <?php } else { ?>
                                        <span style="color: var(--gray);">(Current user)</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer
        style="background:#f8f8f8; padding:15px 0; text-align:center; font-family:Arial, sans-serif; font-size:14px; color:#555; border-top:1px solid #ddd;">
        <div
            style="max-width:900px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">

            <div style="margin:5px 0;">
                <p style="margin:0;">Copyright © 2025
                    <span style="font-weight:bold; color:#05b356;">Fashion Group</span> All rights reserved.
                </p>
            </div>

            <div style="margin:5px 0;">
                <p style="margin:0;">Developed by
                    <span style="font-weight:bold; color:#1c398e;">Fashion IT</span>
                </p>
            </div>

        </div>
    </footer>
</body>

</html>