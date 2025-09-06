<?php
require 'config.php';

if (isset($_GET['id'])) {
    // existing file download via ID (login required) can remain if needed
    die("Please use the share link for public download.");
}

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

// Force download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit();
