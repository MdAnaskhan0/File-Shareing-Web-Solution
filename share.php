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
    <title><?php echo htmlspecialchars($file['filename']); ?> - Preview</title>
</head>
<body style="text-align:center; font-family:Arial, sans-serif; margin-top:50px;">
    <h2><?php echo htmlspecialchars($file['filename']); ?></h2>

    <?php
    $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        echo "<img src='" . htmlspecialchars($file['filepath']) . "' alt='Preview' style='max-width:600px;'><br><br>";
    } elseif ($ext === 'pdf') {
        echo "<embed src='" . htmlspecialchars($file['filepath']) . "' type='application/pdf' width='80%' height='600'><br><br>";
    } elseif (in_array($ext, ['mp4', 'webm'])) {
        echo "<video controls width='600'><source src='" . htmlspecialchars($file['filepath']) . "' type='video/$ext'></video><br><br>";
    } elseif (in_array($ext, ['txt', 'log', 'csv', 'md'])) {
        echo "<pre style='border:1px solid #ccc; padding:10px; max-width:600px; overflow:auto; margin:0 auto; text-align:left;'>";
        echo htmlspecialchars(file_get_contents($filePath));
        echo "</pre><br>";
    } else {
        echo "<p>⚠️ Preview not available for this file type.</p>";
    }
    ?>

    <br>
    <a href="share.php?token=<?php echo urlencode($token); ?>&download=1">
        <button style="padding:10px 20px; font-size:16px; cursor:pointer;">⬇️ Download</button>
    </a>
</body>
</html>
