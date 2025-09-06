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
    } else {
        $msg = "❌ Error: " . $conn->error;
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
    } else {
        $msg = "⚠️ You can’t delete yourself!";
    }
}

// Get all users
$users = $conn->query("SELECT id, username, role FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - File Sharing</title>
</head>
<body>
    <h2>Admin Panel</h2>
    <a href="files.php">Back to Files</a> | <a href="auth.php?logout=1">Logout</a>

    <?php if (!empty($msg)) echo "<p style='color:blue;'>$msg</p>"; ?>

    <h3>Create New User</h3>
    <form method="POST">
        <input type="hidden" name="create_user" value="1">
        <input type="text" name="username" placeholder="Username" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <select name="role" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
        </select><br><br>
        <button type="submit">Create User</button>
    </form>

    <h3>All Users</h3>
    <table border="1" cellpadding="10">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $users->fetch_assoc()) { ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['username']; ?></td>
            <td><?php echo $row['role']; ?></td>
            <td>
                <?php if ($row['id'] != $_SESSION['user']['id']) { ?>
                    <a href="admin.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this user and all their files?');">Delete</a>
                <?php } else { ?>
                    (You)
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
