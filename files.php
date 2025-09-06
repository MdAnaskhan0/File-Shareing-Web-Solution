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
        $success = "✅ File uploaded successfully!";
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
        $success = "🗑️ File deleted successfully!";
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
    $success = "🔗 Share link generated!";
}

// Fetch files
$files = $conn->query("SELECT * FROM files ORDER BY uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files - File Sharing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="files.php" class="brand">FileShare</a>
                <div class="nav-links">
                    <span>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</span>
                    <?php if ($user['role'] === 'admin') { ?>
                        <a href="admin.php" class="nav-link">Admin Panel</a>
                    <?php } ?>
                    <a href="auth.php?logout=1" class="nav-link logout">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
        <div class="page-header">
            <h1 class="page-title">My Files</h1>
        </div>

        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
        <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

        <div class="card fade-in">
            <h3 class="mb-3">Upload New File</h3>
            <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center;">
                <input type="file" name="file" required style="flex-grow: 1;">
                <button class="btn" type="submit">Upload</button>
            </form>
        </div>

        <div class="card fade-in">
            <h3 class="mb-3">File List</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $files->fetch_assoc()) { ?>
                        <tr>
                            <td>
                                <a href="share.php?token=<?php echo $row['share_token']; ?>">
                                    <?php echo htmlspecialchars($row['filename']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($row['uploaded_by']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($row['uploaded_at'])); ?></td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <?php if ($user['role'] === 'admin') { ?>
                                        <a href="files.php?delete=<?php echo $row['id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Delete this file?');">Delete</a>
                                    <?php } ?>
                                    
                                    <?php if ($user['role'] === 'admin' || $row['uploaded_by'] === $user['username']) { ?>
                                        <a href="files.php?share=<?php echo $row['id']; ?>" 
                                           class="btn btn-secondary btn-sm">Share</a>
                                        
                                        <?php if (!empty($row['share_token'])) { ?>
                                            <div style="position: relative;">
                                                <input type="text" 
                                                       value="<?php echo $baseUrl . '/share.php?token=' . $row['share_token']; ?>" 
                                                       readonly 
                                                       style="padding: 0.5rem; border: 1px solid var(--light-gray); border-radius: var(--border-radius); width: 250px;"
                                                       id="share-link-<?php echo $row['id']; ?>">
                                                <button class="btn btn-sm" 
                                                        style="position: absolute; right: 4px; top: 4px; padding: 0.25rem 0.5rem;"
                                                        onclick="copyLink(<?php echo $row['id']; ?>)">Copy</button>
                                            </div>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    function copyLink(fileId) {
        const copyText = document.getElementById("share-link-" + fileId);
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        alert("Copied the share link: " + copyText.value);
    }
    </script>
</body>
</html>