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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        html,
        body {
            height: 100%;
        }

        body {
            display: flex;
            flex-direction: column;
        }

        .content {
            flex: 1 0 auto;
        }

        .footer {
            flex-shrink: 0;
        }

        .preview-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .preview-content {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 2rem;
            margin: 1.5rem 0;
            text-align: center;
        }

        .brand-text {
            color: #4361ee;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="content">
        <nav class="navbar navbar-expand-lg navbar-dark shadow p-3 mb-5 bg-body rounded">
            <div class="container">
                <div>
                    <img src="image/logo.png" alt="Fashion Optics Ltd." class="img-fluid">
                    <span class="navbar-brand fw-bold" style="color: #CD2128">Fashion Cloud</span>
                </div>
                <div class="navbar-nav ms-auto">
                    <span class="nav-link" style="color: #000000">File Preview</span>
                </div>
            </div>
        </nav>

        <div class="container py-4">
            <div class="preview-container card shadow">
                <div class="card-body">
                    <h2 class="text-center mb-2"><?php echo htmlspecialchars($file['filename']); ?></h2>
                    <?php if (!empty($file['description'])): ?>
                        <p class="text-center text-muted"><?php echo htmlspecialchars($file['description']); ?></p>
                    <?php endif; ?>

                    <div class="preview-content">
                        <?php
                        $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));

                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            echo "<img src='" . htmlspecialchars($file['filepath']) . "' class='img-fluid' alt='Preview' style='max-height: 500px;'><br><br>";
                        } elseif ($ext === 'pdf') {
                            echo "<embed src='" . htmlspecialchars($file['filepath']) . "' type='application/pdf' width='100%' height='600'><br><br>";
                        } elseif (in_array($ext, ['mp4', 'webm'])) {
                            echo "<video controls class='w-100' style='max-width:600px;'><source src='" . htmlspecialchars($file['filepath']) . "' type='video/$ext'></video><br><br>";
                        } elseif (in_array($ext, ['txt', 'log', 'csv', 'md'])) {
                            echo "<div class='bg-white p-3 border rounded' style='max-height: 500px; overflow: auto;'>";
                            echo "<pre class='mb-0'>" . htmlspecialchars(file_get_contents($filePath)) . "</pre>";
                            echo "</div><br>";
                        } else {
                            echo "<div class='py-5'>";
                            echo "<div style='font-size: 4rem; margin-bottom: 1rem;'><i class='bi bi-file-earmark'></i></div>";
                            echo "<p class='text-muted'>Preview not available for this file type.</p>";
                            echo "<p class='text-muted'>Please download the file to view it.</p>";
                            echo "</div>";
                        }
                        ?>
                    </div>

                    <div class="text-center">
                        <a href="share.php?token=<?php echo urlencode($token); ?>&download=1" class="btn btn-primary">
                            <i class="bi bi-download me-2"></i>Download File
                        </a>
                    </div>
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