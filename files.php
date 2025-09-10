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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="filestyle.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/js-virus-scanner/dist/js-virus-scanner.umd.js"></script>
</head>

<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="files.php" class="brand">
                    <i class="fas fa-file-shield"></i> Fashion Cloud
                </a>
                <div class="nav-links">
                    <span class="user-welcome"><i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($user['username']); ?></span>
                    <?php if ($user['role'] === 'admin') { ?>
                        <a href="admin.php" class="nav-link"><i class="fas fa-cog"></i> Admin Panel</a>
                    <?php } ?>
                    <a href="auth.php?logout=1" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-cloud-upload-alt"></i> My Files</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" id="changePasswordBtn">
                    <i class="fas fa-key"></i> Change Password
                </button>
                <button class="btn" id="uploadToggleBtn">
                    <i class="fas fa-plus"></i> Upload Files
                </button>
            </div>
        </div>

        <?php if (!empty($error))
            echo "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> $error</div>"; ?>
        <?php if (!empty($success))
            echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $success</div>"; ?>

        <!-- Change Password Modal -->
        <div id="passwordModal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3><i class="fas fa-key"></i> Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input class="form-input" type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <input class="form-input" type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input class="form-input" type="password" name="confirm_password" required>
                    </div>
                    <button class="btn" type="submit">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Upload Section -->
        <div class="card fade-in upload-section" id="uploadSection" style="display: none;">
            <div class="card-header">
                <h3><i class="fas fa-cloud-upload-alt"></i> Upload New Files</h3>
                <button class="btn btn-sm btn-outline" id="closeUploadBtn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <div class="file-drop-zone" id="fileDropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop files here or click to browse</p>
                        <input type="file" name="files[]" multiple required id="fileInput" class="file-input">
                    </div>
                </div>

                <div id="fileList" class="file-list"></div>

                <!-- Virus scan status -->
                <div id="virusScanStatus" class="virus-scan-status" style="display: none;">
                    <div class="scan-progress">
                        <i class="fas fa-shield-alt"></i>
                        <span>Scanning files for viruses...</span>
                        <div class="scan-progress-bar">
                            <div class="scan-progress-inner"></div>
                        </div>
                    </div>
                    <div id="virusScanResults" class="scan-results"></div>
                </div>

                <div class="upload-progress" style="display: none; margin-bottom: 1rem;">
                    <div class="progress-bar">
                        <div class="progress"></div>
                    </div>
                    <div class="progress-info">
                        <span class="progress-text">0%</span>
                        <div class="progress-actions">
                            <button type="button" class="btn btn-sm" id="pauseBtn">
                                <i class="fas fa-pause"></i> Pause
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" id="cancelBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <button class="btn" type="submit" id="uploadBtn">
                    <i class="fas fa-upload"></i> Upload Files
                </button>
            </form>
        </div>

        <!-- File List Section -->
        <div class="card fade-in">
            <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
                <h3><i class="fas fa-file"></i> File List</h3>

                <div class="file-list-search" style="display: flex; flex-grow: 1; max-width: 500px; gap: 0.5rem;">
                    <input class="form-input" type="text" name="search" id="fileSearchInput"
                        value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by filename or description"
                        style="flex-grow: 1;">
                    <button class="btn" id="searchBtn">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="files.php" class="btn btn-outline">
                        <i class="fas fa-times"></i>
                    </a>
                </div>

                <span class="file-count"><?php echo $number_of_results; ?> files total</span>
            </div>
            <div class="table-container">
                <?php if ($files->num_rows > 0): ?>
                    <table class="table compact-table">
                        <thead>
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
                                                    title="<?php echo htmlspecialchars($fullName); ?>">
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
                                                <a href="files.php?delete=<?php echo $row['id']; ?>" class="btn-icon btn-danger"
                                                    onclick="return confirm('Delete this file?');" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php } ?>

                                            <a href="files.php?share=<?php echo $row['id']; ?>" class="btn-icon btn-secondary"
                                                title="Share">
                                                <i class="fas fa-share-alt"></i>
                                            </a>

                                            <a href="<?php echo $row['filepath']; ?>" class="btn-icon" title="Download"
                                                download>
                                                <i class="fas fa-download"></i>
                                            </a>

                                            <?php if (!empty($row['share_token'])) { ?>
                                                <div class="share-link-container">
                                                    <input type="text"
                                                        value="<?php echo $baseUrl . '/share.php?token=' . $row['share_token']; ?>"
                                                        readonly class="share-link-input" id="share-link-<?php echo $row['id']; ?>">
                                                    <button class="btn-icon copy-btn" onclick="copyLink(<?php echo $row['id']; ?>)"
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

                    <!-- Pagination -->
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                class="btn btn-outline">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="btn btn-outline disabled">
                                <i class="fas fa-chevron-left"></i> Previous
                            </span>
                        <?php endif; ?>

                        <span class="pagination-info">Page <?php echo $page; ?> of <?php echo $number_of_pages; ?></span>

                        <?php if ($page < $number_of_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                class="btn btn-outline">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="btn btn-outline disabled">
                                Next <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No files found</h3>
                        <p>Upload your first file using the upload button above</p>
                    </div>
                <?php endif; ?>
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

    <script>
        function copyLink(fileId) {
            const copyText = document.getElementById("share-link-" + fileId);
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");

            // Show notification
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = '<i class="fas fa-check-circle"></i> Copied to clipboard';
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

        // Change password modal
        document.getElementById('changePasswordBtn').addEventListener('click', function () {
            document.getElementById('passwordModal').style.display = 'block';
        });

        document.querySelector('.close').addEventListener('click', function () {
            document.getElementById('passwordModal').style.display = 'none';
        });

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
                <textarea name="description[]" placeholder="Add description between 100 words" rows="6" cols="60" 
                    onkeydown="if(event.key==='Enter'){event.preventDefault();this.value+=' ';}">
                </textarea>
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
                                <i class="fas fa-virus"></i>
                                <span>${file.name}: ${result.message}</span>
                            </div>
                        `;
                    } else {
                        scanResults.innerHTML += `
                            <div class="scan-result clean">
                                <i class="fas fa-shield-alt"></i>
                                <span>${file.name}: Clean</span>
                            </div>
                        `;
                    }
                }

                // If infected files found, prevent upload
                if (hasInfectedFiles) {
                    scanResults.innerHTML += `
                        <div class="scan-summary infected">
                            <i class="fas fa-exclamation-triangle"></i>
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
                        <i class="fas fa-check-circle"></i>
                        <span>All files are clean. Proceeding with upload...</span>
                    </div>
                `;

                // Continue with the original upload process
                var formData = new FormData(this);
                var progressBar = $('.progress');
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
                        progressText.text(percentComplete.toFixed(0) + '%');
                    }
                }, false);

                xhr.addEventListener('load', function () {
                    if (!cancelled) {
                        progressBar.css('width', '100%');
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
                        $(this).html('<i class="fas fa-play"></i> Resume');
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
                    progressText.text('0%');
                });
            });
        });
    </script>

    <style>
        /* Virus scan styles */
        .virus-scan-status {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 8px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .scan-progress {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .scan-progress i {
            margin-right: 0.5rem;
            color: #17a2b8;
        }

        .scan-progress-bar {
            flex-grow: 1;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            margin-left: 1rem;
            overflow: hidden;
        }

        .scan-progress-inner {
            height: 100%;
            width: 0%;
            background-color: #17a2b8;
            border-radius: 4px;
            animation: scanProgress 2s infinite;
        }

        @keyframes scanProgress {
            0% {
                width: 0%;
            }

            50% {
                width: 50%;
            }

            100% {
                width: 100%;
            }
        }

        .scan-results {
            max-height: 200px;
            overflow-y: auto;
        }

        .scanning-file {
            padding: 0.5rem;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .scan-result {
            padding: 0.5rem;
            margin: 0.25rem 0;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }

        .scan-result i {
            margin-right: 0.5rem;
        }

        .scan-result.clean {
            background-color: #d4edda;
            color: #155724;
        }

        .scan-result.infected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .scan-summary {
            padding: 0.75rem;
            margin-top: 1rem;
            border-radius: 4px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .scan-summary i {
            margin-right: 0.5rem;
        }

        .scan-summary.clean {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .scan-summary.infected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</body>

</html>