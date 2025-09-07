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
        if (file_exists($filePath)) unlink($filePath);
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
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Get total number of files
$total_result = $conn->query("SELECT COUNT(*) as total FROM files $whereSQL");
$total_row = $total_result->fetch_assoc();
$number_of_results = $total_row['total'];

// Calculate total pages
$number_of_pages = ceil($number_of_results / $results_per_page);
if ($page > $number_of_pages && $number_of_pages > 0) $page = $number_of_pages;

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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                <a href="files.php" class="brand">
                    <i class="fas fa-file-shield"></i> FileShare
                </a>
                <div class="nav-links">
                    <span class="user-welcome"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['username']); ?></span>
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

        <?php if (!empty($error)) echo "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> $error</div>"; ?>
        <?php if (!empty($success)) echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $success</div>"; ?>

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

        <!-- Filters Section -->
        <div class="card fade-in">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> File Filters</h3>
            </div>
            <div class="filters-content">
                <form method="GET" class="filter-form">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label class="form-label" for="search">
                                <i class="fas fa-search"></i> Search Files
                            </label>
                            <input class="form-input" type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by filename or description">
                        </div>
                        
                        <div class="form-group filter-actions">
                            <button class="btn" type="submit">
                                <i class="fas fa-filter"></i> Search
                            </button>
                            <a href="files.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- File List Section -->
        <div class="card fade-in">
            <div class="card-header">
                <h3><i class="fas fa-file"></i> File List</h3>
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
                                        'doc' => 'file-word', 'docx' => 'file-word',
                                        'xls' => 'file-excel', 'xlsx' => 'file-excel',
                                        'ppt' => 'file-powerpoint', 'pptx' => 'file-powerpoint',
                                        'jpg' => 'file-image', 'jpeg' => 'file-image', 
                                        'png' => 'file-image', 'gif' => 'file-image',
                                        'zip' => 'file-archive', 'rar' => 'file-archive',
                                        'mp3' => 'file-audio', 'wav' => 'file-audio',
                                        'mp4' => 'file-video', 'mov' => 'file-video'
                                    ];
                                    
                                    if (array_key_exists(strtolower($ext), $iconMap)) {
                                        $icon = $iconMap[strtolower($ext)];
                                    }
                                    ?>
                                    <i class="far fa-<?php echo $icon; ?>"></i>
                                </div>
                                <div class="file-info">
                                    <?php if (!empty($row['share_token'])) { ?>
                                        <a href="share.php?token=<?php echo $row['share_token']; ?>">
                                            <?php echo htmlspecialchars($row['filename']); ?>
                                        </a>
                                    <?php } else { ?>
                                        <?php echo htmlspecialchars($row['filename']); ?>
                                    <?php } ?>
                                    <span class="file-size"><?php 
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
                                    ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo htmlspecialchars($row['uploaded_by']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($row['uploaded_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($user['role'] === 'admin') { ?>
                                        <a href="files.php?delete=<?php echo $row['id']; ?>" 
                                           class="btn-icon btn-danger" 
                                           onclick="return confirm('Delete this file?');"
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php } ?>

                                    <a href="files.php?share=<?php echo $row['id']; ?>" 
                                       class="btn-icon btn-secondary" title="Share">
                                        <i class="fas fa-share-alt"></i>
                                    </a>
                                    
                                    <a href="<?php echo $row['filepath']; ?>" 
                                       class="btn-icon" title="Download" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                    
                                    <?php if (!empty($row['share_token'])) { ?>
                                        <div class="share-link-container">
                                            <input type="text" 
                                                   value="<?php echo $baseUrl . '/share.php?token=' . $row['share_token']; ?>" 
                                                   readonly 
                                                   class="share-link-input"
                                                   id="share-link-<?php echo $row['id']; ?>">
                                            <button class="btn-icon copy-btn" 
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
                
                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-outline">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline disabled">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>
                    
                    <span class="pagination-info">Page <?php echo $page; ?> of <?php echo $number_of_pages; ?></span>
                    
                    <?php if ($page < $number_of_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-outline">
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
    document.getElementById('changePasswordBtn').addEventListener('click', function() {
        document.getElementById('passwordModal').style.display = 'block';
    });
    
    document.querySelector('.close').addEventListener('click', function() {
        document.getElementById('passwordModal').style.display = 'none';
    });
    
    // Toggle upload section
    document.getElementById('uploadToggleBtn').addEventListener('click', function() {
        const uploadSection = document.getElementById('uploadSection');
        uploadSection.style.display = uploadSection.style.display === 'none' ? 'block' : 'none';
    });
    
    document.getElementById('closeUploadBtn').addEventListener('click', function() {
        document.getElementById('uploadSection').style.display = 'none';
    });
    
    // File input change handler
    document.getElementById('fileInput').addEventListener('change', function(e) {
        const fileList = document.getElementById('fileList');
        fileList.innerHTML = '';
        
        for (let i = 0; i < this.files.length; i++) {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <div class="file-item-icon">
                    <i class="far fa-file"></i>
                </div>
                <div class="file-item-info">
                    <div class="file-item-name">${this.files[i].name}</div>
                    <div class="file-item-size">${formatFileSize(this.files[i].size)}</div>
                </div>
                <div class="file-item-description">
                    <input type="text" name="description[]" placeholder="Add description">
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
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Upload progress functionality
    $(document).ready(function() {
        $('#uploadForm').on('submit', function(e) {
            e.preventDefault();
            
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
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable && !paused && !cancelled) {
                    var percentComplete = (e.loaded / e.total) * 100;
                    progressBar.css('width', percentComplete + '%');
                    progressText.text(percentComplete.toFixed(0) + '%');
                }
            }, false);
            
            xhr.addEventListener('load', function() {
                if (!cancelled) {
                    progressBar.css('width', '100%');
                    progressText.text('100%');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                }
            }, false);
            
            xhr.addEventListener('error', function() {
                alert('Upload failed. Please try again.');
                uploadProgress.hide();
                uploadBtn.prop('disabled', false);
            }, false);
            
            xhr.open('POST', 'files.php', true);
            xhr.send(formData);
            
            pauseBtn.click(function() {
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
            
            cancelBtn.click(function() {
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
    /* Enhanced UI Styles */
    .header-actions {
        display: flex;
        gap: 0.75rem;
    }
    
    .user-welcome {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border);
    }
    
    .file-count {
        background-color: var(--light-gray);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        color: var(--gray);
    }
    
    .file-name {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .file-icon {
        font-size: 1.5rem;
        color: var(--gray);
        width: 40px;
        text-align: center;
    }
    
    .file-info {
        display: flex;
        flex-direction: column;
    }
    
    .file-size {
        font-size: 0.75rem;
        color: var(--gray);
        margin-top: 0.25rem;
    }
    
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--light-gray);
        color: var(--dark);
        text-decoration: none;
        transition: var(--transition);
    }
    
    .btn-icon:hover {
        background-color: var(--primary);
        color: white;
    }
    
    .btn-secondary.btn-icon:hover {
        background-color: var(--accent);
    }
    
    .btn-danger.btn-icon:hover {
        background-color: var(--error);
    }
    
    .share-link-container {
        position: relative;
        display: inline-flex;
        align-items: center;
    }
    
    .share-link-input {
        padding: 0.5rem;
        padding-right: 40px;
        border: 1px solid var(--light-gray);
        border-radius: var(--border-radius);
        width: 250px;
        font-size: 0.875rem;
    }
    
    .copy-btn {
        position: absolute;
        right: 4px;
        top: 4px;
        width: 28px;
        height: 28px;
    }
    
    .file-drop-zone {
        border: 2px dashed var(--border);
        border-radius: var(--border-radius);
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .file-drop-zone:hover, .file-drop-zone.dragover {
        border-color: var(--accent);
        background-color: rgba(52, 152, 219, 0.05);
    }
    
    .file-drop-zone i {
        font-size: 3rem;
        color: var(--gray);
        margin-bottom: 1rem;
    }
    
    .file-input {
        display: none;
    }
    
    .file-list {
        margin: 1.5rem 0;
        display: grid;
        gap: 0.75rem;
    }
    
    .file-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background-color: var(--light-gray);
        border-radius: var(--border-radius);
    }
    
    .file-item-icon {
        font-size: 1.5rem;
        color: var(--gray);
    }
    
    .file-item-info {
        flex-grow: 1;
    }
    
    .file-item-name {
        font-weight: 500;
    }
    
    .file-item-size {
        font-size: 0.875rem;
        color: var(--gray);
    }
    
    .file-item-description input {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: var(--border-radius);
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1rem;
    }
    
    .filter-actions {
        display: flex;
        align-items: flex-end;
        gap: 0.5rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--gray);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
    
    .empty-state h3 {
        margin-bottom: 0.5rem;
        color: var(--dark);
    }
    
    .notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background-color: var(--success);
        color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
        z-index: 1000;
    }
    
    .notification.show {
        opacity: 1;
        transform: translateY(0);
    }
    
    /* Pagination Styles */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }
    
    .pagination-info {
        color: var(--gray);
        font-size: 0.9rem;
    }
    
    .btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Compact table with smaller fonts */
    .compact-table {
        font-size: 0.875rem;
    }
    
    .compact-table th, 
    .compact-table td {
        padding: 0.75rem;
    }
    
    .compact-table .file-icon {
        font-size: 1.25rem;
        width: 30px;
    }
    
    .compact-table .file-size {
        font-size: 0.7rem;
    }
    
    .compact-table .btn-icon {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }
    
    .compact-table .share-link-input {
        width: 200px;
        font-size: 0.8rem;
        padding: 0.4rem;
        padding-right: 35px;
    }
    
    .compact-table .copy-btn {
        width: 25px;
        height: 25px;
        font-size: 0.7rem;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .header-actions {
            width: 100%;
            justify-content: space-between;
        }
        
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-actions {
            flex-direction: column;
        }
        
        .file-item {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .share-link-input {
            width: 180px;
        }
        
        .action-buttons {
            justify-content: center;
        }
        
        .pagination {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .compact-table {
            font-size: 0.8rem;
        }
        
        .compact-table th, 
        .compact-table td {
            padding: 0.5rem;
        }
    }
    </style>
</body>
</html>