<?php
require 'config.php';

if (!isset($_GET['token'])) {
    die("Invalid file link.");
}

$token = $conn->real_escape_string($_GET['token']);
$result = $conn->query("SELECT * FROM files WHERE share_token='$token'");

if ($result->num_rows === 0) {
    die("File not found or not shared publicly.");
}

$file = $result->fetch_assoc();
$filePath = __DIR__ . "/" . $file['filepath'];

if (!file_exists($filePath)) {
    die("File not found on server.");
}

// If download=1 → force download
if (isset($_GET['download']) && $_GET['download'] == 1) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($file['filename']); ?> - File Sharing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <p  class="brand">Fashion Cloud</p>
                <div class="nav-links">
                    <span>File Preview</span>
                </div>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="preview-container card fade-in">
            <h2 class="text-center"><?php echo htmlspecialchars($file['filename']); ?></h2>
            <?php if (!empty($file['description'])): ?>
                <p class="text-center"><?php echo htmlspecialchars($file['description']); ?></p>
            <?php endif; ?>
            
            <div class="preview-content">
                <?php
                $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));

                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    echo "<img src='" . htmlspecialchars($file['filepath']) . "' alt='Preview' style='max-width:100%; max-height:500px;'><br><br>";
                } elseif ($ext === 'pdf') {
                    echo "<embed src='" . htmlspecialchars($file['filepath']) . "' type='application/pdf' width='100%' height='600'><br><br>";
                } elseif (in_array($ext, ['mp4', 'webm'])) {
                    echo "<video controls width='100%' style='max-width:600px;'><source src='" . htmlspecialchars($file['filepath']) . "' type='video/$ext'></video><br><br>";
                } elseif (in_array($ext, ['txt', 'log', 'csv', 'md'])) {
                    echo "<pre style='border:1px solid var(--light-gray); padding:1rem; max-width:100%; overflow:auto; margin:0 auto; text-align:left;'>";
                    echo htmlspecialchars(file_get_contents($filePath));
                    echo "</pre><br>";
                } else {
                    echo "<div style='padding: 3rem; text-align: center;'>";
                    echo "<div style='font-size: 4rem; margin-bottom: 1rem;'>📄</div>";
                    echo "<p>Preview not available for this file type.</p>";
                    echo "<p>Please download the file to view it.</p>";
                    echo "</div>";
                }
                ?>
            </div>

            <div class="text-center mt-3">
                <a href="share.php?token=<?php echo urlencode($token); ?>&download=1" class="btn">
                    ⬇️ Download File
                </a>
            </div>
        </div>
    </main>
</body>
</html>