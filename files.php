<?php
require 'config.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$user = $_SESSION['user'];

// Change password for current user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = md5($_POST['current_password']);
    $newPassword = md5($_POST['new_password']);

    // Verify current password
    $checkSql = "SELECT id FROM users WHERE id = {$user['id']} AND password = '$currentPassword'";
    $checkResult = $conn->query($checkSql);

    if ($checkResult->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $newPassword, $user['id']);

        if ($stmt->execute()) {
            $success = "✅ Password changed successfully!";
        } else {
            $error = "❌ Error: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "❌ Current password is incorrect!";
    }
}

// Upload file (both admin + users allowed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $targetDir = __DIR__ . "/uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileCount = count($_FILES['files']['name']);
    $uploadedCount = 0;

    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = basename($_FILES["files"]["name"][$i]);
            $targetFile = $targetDir . uniqid() . "_" . $fileName;
            $dbPath = "uploads/" . basename($targetFile);
            $description = !empty($_POST['description'][$i]) ? $conn->real_escape_string($_POST['description'][$i]) : '';

            if (move_uploaded_file($_FILES["files"]["tmp_name"][$i], $targetFile)) {
                $stmt = $conn->prepare("INSERT INTO files (filename, filepath, description, uploaded_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $fileName, $dbPath, $description, $user['username']);
                $stmt->execute();
                $stmt->close();
                $uploadedCount++;
            }
        }
    }

    if ($uploadedCount > 0) {
        $success = "✅ $uploadedCount file(s) uploaded successfully!";
    } else {
        $error = "❌ Failed to upload files.";
    }
}

// Delete file (admin only)
if (isset($_GET['delete']) && $user['role'] === 'admin') {
    $fileId = intval($_GET['delete']);
    $result = $conn->query("SELECT * FROM files WHERE id=$fileId");
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        $filePath = __DIR__ . "/" . $file['filepath'];
        if (file_exists($filePath))
            unlink($filePath);
        $conn->query("DELETE FROM files WHERE id=$fileId");
        $success = "🗑️ File deleted successfully!";
    }
    header("Location: files.php");
    exit();
}

// Generate public share token (admin and normal users allowed)
if (isset($_GET['share'])) {
    $fileId = intval($_GET['share']);

    // Check if file already has a token
    $result = $conn->query("SELECT share_token FROM files WHERE id=$fileId");
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();

        if (!empty($file['share_token'])) {
            // Reuse existing token
            $token = $file['share_token'];
        } else {
            // Generate new token only if none exists
            $token = bin2hex(random_bytes(16));
            $stmt = $conn->prepare("UPDATE files SET share_token=? WHERE id=?");
            $stmt->bind_param("si", $token, $fileId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Detect base URL dynamically
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . "://" . $host . dirname($_SERVER['PHP_SELF']);
    $shareLink = $baseUrl . "/share.php?token=$token";
    $success = "🔗 Share link generated!";
}

// Build query with filters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$whereClauses = [];
if (!empty($search)) {
    $whereClauses[] = "(filename LIKE '%$search%' OR description LIKE '%$search%')";
}

$whereSQL = '';
if (!empty($whereClauses)) {
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
}

// Pagination setup
$results_per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

// Get total number of files
$total_result = $conn->query("SELECT COUNT(*) as total FROM files $whereSQL");
$total_row = $total_result->fetch_assoc();
$number_of_results = $total_row['total'];

// Calculate total pages
$number_of_pages = ceil($number_of_results / $results_per_page);
if ($page > $number_of_pages && $number_of_pages > 0)
    $page = $number_of_pages;

// Calculate the starting limit for the results
$starting_limit = ($page - 1) * $results_per_page;

// Fetch files with pagination
$files = $conn->query("SELECT * FROM files $whereSQL ORDER BY uploaded_at DESC LIMIT $starting_limit, $results_per_page");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files - File Sharing System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/js-virus-scanner/dist/js-virus-scanner.umd.js"></script>
    <style>
        .navbar-brand {
            font-weight: 600;
            color: #CD2128;
        }

        .navbar-brand:hover {
            color: #CD2128;
        }

        .file-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .file-name {
            display: flex;
            align-items: center;
        }

        .file-info {
            display: flex;
            flex-direction: column;
        }

        .file-size {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .share-link-container {
            display: flex;
            align-items: center;
        }

        .share-link-input {
            flex: 1;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            margin-right: 0.5rem;
        }

        .file-drop-zone {
            border: 2px dashed #dee2e6;
            border-radius: 0.375rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-drop-zone:hover,
        .file-drop-zone.dragover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #dee2e6;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-item-icon {
            margin-right: 1rem;
            font-size: 1.5rem;
            color: #6c757d;
        }

        .file-item-info {
            flex: 1;
        }

        .file-item-name {
            font-weight: 500;
        }

        .file-item-description {
            margin-left: 1rem;
            flex: 2;
        }

        .virus-scan-status {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .scan-progress {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .scan-progress-bar {
            flex: 1;
            height: 0.5rem;
            background-color: #e9ecef;
            border-radius: 0.25rem;
            margin: 0 1rem;
            overflow: hidden;
        }

        .scan-progress-inner {
            height: 100%;
            background-color: #0d6efd;
            width: 0%;
            transition: width 0.3s;
        }

        .scan-results {
            margin-top: 1rem;
        }

        .scan-result,
        .scan-summary {
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .scan-result.infected,
        .scan-summary.infected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .scan-result.clean,
        .scan-summary.clean {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .scanning-file {
            padding: 0.25rem;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #198754;
            color: white;
            padding: 1rem;
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            opacity: 0;
            transform: translateY(1rem);
            transition: all 0.3s;
            z-index: 1050;
        }

        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }

        .btn-icon {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .file-list-search {
            display: flex;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .file-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .file-item-description {
                margin-left: 0;
                margin-top: 0.5rem;
                width: 100%;
            }

            .file-list-search {
                margin-top: 1rem;
                width: 100%;
            }
        }
    </style>
</head>

<body class="bg-light">
    <main class="d-flex flex-column min-vh-100">
        <nav class="navbar navbar-expand-lg navbar-light shadow p-3 mb-5 bg-body rounded">
            <div class="container">
                <a class="navbar-brand" href="files.php">
                    <img src="image/logo.png" alt="Fashion Optics Ltd.">Fashion Cloud
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown me-2">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i
                                    class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <button class="dropdown-item" data-bs-toggle="modal"
                                        data-bs-target="#passwordModal">
                                        <i class="fas fa-key me-1"></i>Change Password
                                    </button>
                                </li>
                            </ul>
                        </li>
                        <?php if ($user['role'] === 'admin') { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin.php"><i class="fas fa-cog me-1"></i>Admin Panel</a>
                            </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a class="nav-link fw-bold" style="color: #CD2128;" href="auth.php?logout=1"><i
                                    class="fas fa-sign-out-alt me-1"></i>Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container my-4 ">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><i class="fas fa-cloud-upload-alt me-2"></i>My Files</h1>
                <div>
                    <button class="btn btn-primary" id="uploadToggleBtn">
                        <i class="fas fa-plus me-1"></i>Upload Files
                    </button>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-1"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-1"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Change Password Modal -->
            <div class="modal fade" id="passwordModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-key me-1"></i>Change Password</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="change_password" value="1">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Upload Section -->
            <div class="card mb-4 upload-section" id="uploadSection" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-cloud-upload-alt me-1"></i>Upload New Files</h5>
                    <button type="button" class="btn-close" id="closeUploadBtn"></button>
                </div>
                <div class="card-body">
                    <form id="uploadForm" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <div class="file-drop-zone" id="fileDropZone">
                                <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted"></i>
                                <p class="mb-0">Drag & drop files here or click to browse</p>
                                <input type="file" name="files[]" multiple required id="fileInput" class="d-none">
                            </div>
                        </div>

                        <div id="fileList" class="file-list mb-3"></div>

                        <!-- Virus scan status -->
                        <div id="virusScanStatus" class="virus-scan-status" style="display: none;">
                            <div class="scan-progress">
                                <i class="fas fa-shield-alt me-2"></i>
                                <span>Scanning files for viruses...</span>
                                <div class="scan-progress-bar">
                                    <div class="scan-progress-inner"></div>
                                </div>
                            </div>
                            <div id="virusScanResults" class="scan-results"></div>
                        </div>

                        <div class="upload-progress mb-3" style="display: none;">
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0"
                                    aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="progress-text">0%</span>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="pauseBtn">
                                        <i class="fas fa-pause me-1"></i>Pause
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" id="cancelBtn">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-primary" type="submit" id="uploadBtn">
                            <i class="fas fa-upload me-1"></i>Upload Files
                        </button>
                    </form>
                </div>
            </div>

            <!-- File List Section -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <h5 class="mb-2 mb-md-0"><i class="fas fa-file me-1"></i>File List</h5>

                        <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                            <div class="input-group me-sm-2" style="max-width: 500px;">
                                <input type="text" class="form-control" name="search" id="fileSearchInput"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Search by filename or description">
                                <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                    <i class="fas fa-search"></i>
                                </button>
                                <a href="files.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                            <span
                                class="badge bg-secondary align-self-center align-self-sm-end"><?php echo $number_of_results; ?>
                                files total</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($files->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Filename</th>
                                        <th>Description</th>
                                        <th>Uploaded By</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $files->fetch_assoc()) { ?>
                                        <tr>
                                            <td class="file-name">
                                                <div class="d-flex align-items-center">
                                                    <div class="file-icon">
                                                        <?php
                                                        $ext = pathinfo($row['filename'], PATHINFO_EXTENSION);
                                                        $icon = "file";

                                                        $iconMap = [
                                                            'pdf' => 'file-pdf',
                                                            'doc' => 'file-word',
                                                            'docx' => 'file-word',
                                                            'xls' => 'file-excel',
                                                            'xlsx' => 'file-excel',
                                                            'ppt' => 'file-powerpoint',
                                                            'pptx' => 'file-powerpoint',
                                                            'jpg' => 'file-image',
                                                            'jpeg' => 'file-image',
                                                            'png' => 'file-image',
                                                            'gif' => 'file-image',
                                                            'zip' => 'file-archive',
                                                            'rar' => 'file-archive',
                                                            'mp3' => 'file-audio',
                                                            'wav' => 'file-audio',
                                                            'mp4' => 'file-video',
                                                            'mov' => 'file-video'
                                                        ];

                                                        if (array_key_exists(strtolower($ext), $iconMap)) {
                                                            $icon = $iconMap[strtolower($ext)];
                                                        }

                                                        // Handle filename display (slice if longer than 30 chars)
                                                        $fullName = $row['filename'];
                                                        $displayName = (mb_strlen($fullName) > 25)
                                                            ? mb_strimwidth($fullName, 0, 25, '...')
                                                            : $fullName;
                                                        ?>
                                                        <i class="far fa-<?php echo $icon; ?>"></i>
                                                    </div>
                                                    <div class="file-info">
                                                        <?php if (!empty($row['share_token'])) { ?>
                                                            <a href="share.php?token=<?php echo $row['share_token']; ?>"
                                                                title="<?php echo htmlspecialchars($fullName); ?>"
                                                                class="text-decoration-none">
                                                                <?php echo htmlspecialchars($displayName); ?>
                                                            </a>
                                                        <?php } else { ?>
                                                            <span title="<?php echo htmlspecialchars($fullName); ?>">
                                                                <?php echo htmlspecialchars($displayName); ?>
                                                            </span>
                                                        <?php } ?>
                                                        <span class="file-size">
                                                            <?php
                                                            $filePath = __DIR__ . "/" . $row['filepath'];
                                                            if (file_exists($filePath)) {
                                                                $size = filesize($filePath);
                                                                $units = ['B', 'KB', 'MB', 'GB'];
                                                                $index = 0;
                                                                while ($size >= 1024 && $index < count($units) - 1) {
                                                                    $size /= 1024;
                                                                    $index++;
                                                                }
                                                                echo round($size, 2) . ' ' . $units[$index];
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td title="<?php echo htmlspecialchars($row['description']); ?>">
                                                <?php
                                                $desc = htmlspecialchars($row['description']);
                                                if (strlen($desc) > 25) {
                                                    echo substr($desc, 0, 25) . '...';
                                                } else {
                                                    echo $desc;
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['uploaded_by']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($row['uploaded_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($user['role'] === 'admin') { ?>
                                                        <a href="files.php?delete=<?php echo $row['id']; ?>"
                                                            class="btn btn-sm btn-danger"
                                                            onclick="return confirm('Delete this file?');" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php } ?>

                                                    <a href="files.php?share=<?php echo $row['id']; ?>"
                                                        class="btn btn-sm btn-outline-secondary" title="Share">
                                                        <i class="fas fa-share-alt"></i>
                                                    </a>

                                                    <a href="<?php echo $row['filepath']; ?>"
                                                        class="btn btn-sm btn-outline-primary" title="Download" download>
                                                        <i class="fas fa-download"></i>
                                                    </a>

                                                    <?php if (!empty($row['share_token'])) { ?>
                                                        <div class="share-link-container mt-1">
                                                            <input type="text"
                                                                value="<?php echo $baseUrl . '/share.php?token=' . $row['share_token']; ?>"
                                                                readonly class="form-control form-control-sm"
                                                                id="share-link-<?php echo $row['id']; ?>">
                                                            <button class="btn btn-sm btn-outline-secondary copy-btn ms-1"
                                                                onclick="copyLink(<?php echo $row['id']; ?>)"
                                                                title="Copy share link">
                                                                <i class="fas fa-copy"></i>
                                                            </button>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <div>
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                        class="btn btn-outline-primary">
                                        <i class="fas fa-chevron-left me-1"></i>Previous
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-outline-primary" disabled>
                                        <i class="fas fa-chevron-left me-1"></i>Previous
                                    </button>
                                <?php endif; ?>
                            </div>

                            <span class="text-muted">Page <?php echo $page; ?> of <?php echo $number_of_pages; ?></span>

                            <div>
                                <?php if ($page < $number_of_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                        class="btn btn-outline-primary">
                                        Next<i class="fas fa-chevron-right ms-1"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-outline-primary" disabled>
                                        Next<i class="fas fa-chevron-right ms-1"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h4>No files found</h4>
                            <p class="text-muted">Upload your first file using the upload button above</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>


    <footer class="footer bg-light py-3 mt-5">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="mb-2 mb-md-0">
                    <p class="mb-0 text-muted">
                        Copyright © 2025 <a href="https://fg-bd.com/"
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

    <script>
        function copyLink(fileId) {
            const copyText = document.getElementById("share-link-" + fileId);
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");

            // Show notification
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = '<i class="fas fa-check-circle me-1"></i> Copied to clipboard';
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 2000);
        }

        // Toggle upload section
        document.getElementById('uploadToggleBtn').addEventListener('click', function () {
            const uploadSection = document.getElementById('uploadSection');
            uploadSection.style.display = uploadSection.style.display === 'none' ? 'block' : 'none';
        });

        document.getElementById('closeUploadBtn').addEventListener('click', function () {
            document.getElementById('uploadSection').style.display = 'none';
        });

        // File input change handler
        document.getElementById('fileInput').addEventListener('change', function (e) {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';

            for (let i = 0; i < this.files.length; i++) {
                let fullName = this.files[i].name;
                let displayName = fullName.length > 35
                    ? fullName.substring(0, 35) + "..."
                    : fullName;

                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <div class="file-item-icon">
                        <i class="far fa-file"></i>
                    </div>
                    <div class="file-item-info">
                        <div class="file-item-name" title="${fullName}">${displayName}</div>
                        <div class="file-item-size">${formatFileSize(this.files[i].size)}</div>
                    </div>
                    <div class="file-item-description">
                        <textarea class="form-control" name="description[]" placeholder="Add description between 100 words" rows="2"></textarea>
                    </div>
                `;
                fileList.appendChild(fileItem);
            }
        });

        // Drag and drop functionality
        const dropZone = document.getElementById('fileDropZone');
        const fileInput = document.getElementById('fileInput');

        dropZone.addEventListener('click', () => {
            fileInput.click();
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');

            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                const event = new Event('change', { bubbles: true });
                fileInput.dispatchEvent(event);
            }
        });

        // Search functionality
        document.getElementById('searchBtn').addEventListener('click', function () {
            const searchValue = document.getElementById('fileSearchInput').value;
            const url = new URL(window.location.href);
            url.searchParams.set('search', searchValue);
            url.searchParams.delete('page'); // Reset to first page when searching
            window.location.href = url.toString();
        });

        // Allow pressing Enter to search
        document.getElementById('fileSearchInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Virus scanning functionality
        async function scanFileForViruses(file) {
            return new Promise((resolve) => {
                // Show scanning status
                const scanStatus = document.getElementById('virusScanStatus');
                const scanResults = document.getElementById('virusScanResults');
                scanStatus.style.display = 'block';
                scanResults.innerHTML = `<div class="scanning-file">Scanning: ${file.name}</div>`;

                // Simulate scanning process (in a real implementation, you would use an API)
                setTimeout(() => {
                    // This is a mock implementation - in a real scenario, you would:
                    // 1. Use a service like VirusTotal API
                    // 2. Or implement a server-side scanner like ClamAV

                    // For demo purposes, we'll flag files with certain extensions as "suspicious"
                    const dangerousExtensions = ['.exe', '.bat', '.cmd', '.scr', '.msi', '.com', '.vbs', '.js', '.jar'];
                    const fileExtension = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();

                    if (dangerousExtensions.includes(fileExtension)) {
                        resolve({
                            infected: true,
                            viruses: ['Potential threat detected'],
                            message: `Warning: ${file.name} may contain malicious code`
                        });
                    } else if (file.size > 50 * 1024 * 1024) { // Files larger than 50MB
                        resolve({
                            infected: true,
                            viruses: ['Oversized file'],
                            message: `Warning: ${file.name} is too large and may be suspicious`
                        });
                    } else {
                        resolve({
                            infected: false,
                            message: `${file.name} is clean`
                        });
                    }
                }, 2000); // Simulate 2-second scan
            });
        }

        // Upload progress functionality with virus scanning
        $(document).ready(function () {
            $('#uploadForm').on('submit', async function (e) {
                e.preventDefault();

                const files = document.getElementById('fileInput').files;
                const scanStatus = document.getElementById('virusScanStatus');
                const scanResults = document.getElementById('virusScanResults');

                // Show scanning UI
                scanStatus.style.display = 'block';
                scanResults.innerHTML = '<div class="scanning-file">Starting virus scan...</div>';

                let hasInfectedFiles = false;
                let infectedFiles = [];

                // Scan each file
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    scanResults.innerHTML += `<div class="scanning-file">Scanning: ${file.name}</div>`;

                    const result = await scanFileForViruses(file);

                    if (result.infected) {
                        hasInfectedFiles = true;
                        infectedFiles.push({ name: file.name, message: result.message });
                        scanResults.innerHTML += `
                                    <div class="scan-result infected">
                                        <i class="fas fa-virus me-1"></i>
                                        <span>${file.name}: ${result.message}</span>
                                    </div>
                                `;
                    } else {
                        scanResults.innerHTML += `
                                    <div class="scan-result clean">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        <span>${file.name}: Clean</span>
                                    </div>
                                `;
                    }
                }

                // If infected files found, prevent upload
                if (hasInfectedFiles) {
                    scanResults.innerHTML += `
                                <div class="scan-summary infected">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <span>Virus scan completed. ${infectedFiles.length} infected file(s) found.</span>
                                    <p>Upload cancelled for security reasons.</p>
                                </div>
                            `;

                    // Disable upload button
                    document.getElementById('uploadBtn').disabled = true;

                    // Show warning
                    alert(`Virus detected! The following files appear to be infected:\n\n${infectedFiles.map(f => f.name).join('\n')}\n\nUpload has been cancelled.`);
                    return;
                }

                // If no viruses found, proceed with upload
                scanResults.innerHTML += `
                            <div class="scan-summary clean">
                                <i class="fas fa-check-circle me-1"></i>
                                <span>All files are clean. Proceeding with upload...</span>
                            </div>
                        `;

                // Continue with the original upload process
                var formData = new FormData(this);
                var progressBar = $('.progress-bar');
                var progressText = $('.progress-text');
                var uploadProgress = $('.upload-progress');
                var pauseBtn = $('#pauseBtn');
                var cancelBtn = $('#cancelBtn');
                var uploadBtn = $('#uploadBtn');

                uploadProgress.show();
                uploadBtn.prop('disabled', true);

                var xhr = new XMLHttpRequest();
                var paused = false;
                var cancelled = false;

                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable && !paused && !cancelled) {
                        var percentComplete = (e.loaded / e.total) * 100;
                        progressBar.css('width', percentComplete + '%');
                        progressBar.attr('aria-valuenow', percentComplete);
                        progressText.text(percentComplete.toFixed(0) + '%');
                    }
                }, false);

                xhr.addEventListener('load', function () {
                    if (!cancelled) {
                        progressBar.css('width', '100%');
                        progressBar.attr('aria-valuenow', 100);
                        progressText.text('100%');
                        setTimeout(function () {
                            window.location.reload();
                        }, 1000);
                    }
                }, false);

                xhr.addEventListener('error', function () {
                    alert('Upload failed. Please try again.');
                    uploadProgress.hide();
                    uploadBtn.prop('disabled', false);
                }, false);

                xhr.open('POST', 'files.php', true);
                xhr.send(formData);

                pauseBtn.click(function () {
                    if (!paused) {
                        xhr.abort();
                        paused = true;
                        $(this).html('<i class="fas fa-play me-1"></i>Resume');
                        progressText.text('Paused');
                    } else {
                        // Create a new request to resume
                        var newXhr = new XMLHttpRequest();
                        // This would need server-side support for resumable uploads
                        // For simplicity, we'll just restart the upload
                        $('#uploadForm').submit();
                    }
                });

                cancelBtn.click(function () {
                    xhr.abort();
                    cancelled = true;
                    uploadProgress.hide();
                    uploadBtn.prop('disabled', false);
                    progressBar.css('width', '0%');
                    progressBar.attr('aria-valuenow', 0);
                    progressText.text('0%');
                });
            });
        });
    </script>
</body>

</html>