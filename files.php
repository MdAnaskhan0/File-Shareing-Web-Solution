<?php
require 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$user = $_SESSION['user'];

// Upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $targetDir = __DIR__ . "/uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = basename($_FILES["file"]["name"]);
    $targetFile = $targetDir . uniqid() . "_" . $fileName;
    $dbPath = "uploads/" . basename($targetFile);

    if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
        $stmt = $conn->prepare("INSERT INTO files (filename, filepath, uploaded_by) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $fileName, $dbPath, $user['username']);
        $stmt->execute();
        $stmt->close();
    } else {
        $error = "❌ Failed to upload file.";
    }
}

// Delete file (admin only)
if (isset($_GET['delete']) && $user['role'] === 'admin') {
    $fileId = intval($_GET['delete']);
    $result = $conn->query("SELECT * FROM files WHERE id=$fileId");
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        $filePath = __DIR__ . "/" . $file['filepath'];
        if (file_exists($filePath)) unlink($filePath);
        $conn->query("DELETE FROM files WHERE id=$fileId");
    }
    header("Location: files.php");
    exit();
}

// Generate public share token
if (isset($_GET['share'])) {
    $fileId = intval($_GET['share']);
    $token = bin2hex(random_bytes(16)); // 32-char token
    $stmt = $conn->prepare("UPDATE files SET share_token=? WHERE id=?");
    $stmt->bind_param("si", $token, $fileId);
    $stmt->execute();
    $stmt->close();

    // Detect base URL dynamically
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . "://" . $host . dirname($_SERVER['PHP_SELF']);
    $shareLink = $baseUrl . "/share.php?token=$token";
}

// Fetch files
$files = $conn->query("SELECT * FROM files ORDER BY uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files</title>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
    <a href="auth.php?logout=1">Logout</a>
    <?php if ($user['role'] === 'admin') { ?> | <a href="admin.php">Admin Panel</a> <?php } ?>

    <h3>Upload File</h3>
    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <button type="submit">Upload</button>
    </form>

    <h3>Files</h3>
    <table border="1" cellpadding="10">
        <tr>
            <th>Filename</th>
            <th>Uploaded By</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $files->fetch_assoc()) { ?>
        <tr>
            <td><a href="share.php?token=<?php echo $row['share_token']; ?>"><?php echo htmlspecialchars($row['filename']); ?></a></td>
            <td><?php echo htmlspecialchars($row['uploaded_by']); ?></td>
            <td>
                <?php if ($user['role'] === 'admin') { ?>
                    <a href="files.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this file?');">Delete</a> |
                <?php } ?>
                <?php if ($user['role'] === 'admin' || $row['uploaded_by'] === $user['username']) { ?>
                    <a href="files.php?share=<?php echo $row['id']; ?>">Generate Share Link</a><br>
                    <?php if (!empty($row['share_token'])) { ?>
                        <input type="text" value="<?php echo $baseUrl . '/share.php?token=' . $row['share_token']; ?>" readonly style="width:300px;">
                    <?php } ?>
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
