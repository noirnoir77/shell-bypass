<?php
session_start();

if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = __DIR__;
}

if (isset($_POST['cwd'])) {
    $newCwd = realpath($_POST['cwd']);
    if ($newCwd && is_dir($newCwd)) {
        $_SESSION['cwd'] = $newCwd;
    }
}

$currentDir = $_SESSION['cwd'];

if (isset($_GET['action']) && isset($_GET['file'])) {
    $file = $currentDir . DIRECTORY_SEPARATOR . $_GET['file'];
    $file = realpath($file);
    
    if ($file && strpos($file, $currentDir) === 0) {
        if ($_GET['action'] === 'delete' && is_file($file)) {
            unlink($file);
        } elseif ($_GET['action'] === 'mkdir') {
            mkdir($file);
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $targetFile = $currentDir . DIRECTORY_SEPARATOR . basename($_FILES['file']['name']);
    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$items = scandir($currentDir);
$files = [];
$dirs = [];

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $path = $currentDir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($path)) {
        $dirs[] = $item;
    } else {
        $files[] = $item;
    }
}

sort($dirs);
sort($files);
$allItems = array_merge($dirs, $files);
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Manager</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f0f0f0; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .path { background: #e8e8e8; padding: 10px; border-radius: 4px; word-break: break-all; font-size: 14px; flex: 1; margin-right: 10px; }
        .form-inline { display: flex; gap: 10px; flex-wrap: wrap; }
        .form-inline input, .form-inline button { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; }
        .form-inline button { background: #4CAF50; color: white; border: none; cursor: pointer; }
        .form-inline button:hover { background: #45a049; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #4CAF50; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
        .file-icon { margin-right: 10px; }
        .actions a { text-decoration: none; padding: 4px 10px; border-radius: 3px; font-size: 12px; margin: 0 2px; }
        .actions a.delete { background: #f44336; color: white; }
        .actions a.delete:hover { background: #d32f2f; }
        .upload-form { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px; border: 1px dashed #ccc; }
        .upload-form form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .upload-form input[type="file"] { padding: 8px; }
        .upload-form button { background: #2196F3; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; }
        .upload-form button:hover { background: #0b7dda; }
        .mkdir-form { margin: 10px 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .mkdir-form input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .mkdir-form button { background: #ff9800; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer; }
        .mkdir-form button:hover { background: #e68900; }
        .parent-link { display: inline-block; margin: 10px 0; padding: 8px 15px; background: #607d8b; color: white; text-decoration: none; border-radius: 4px; }
        .parent-link:hover { background: #546e7a; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="path"><strong>Current Directory:</strong> <?php echo htmlspecialchars($currentDir); ?></div>
    </div>

    <div class="form-inline">
        <form method="POST" style="display: flex; gap: 10px; flex: 1;">
            <input type="text" name="cwd" placeholder="Enter full path..." style="flex: 1; min-width: 200px;" value="<?php echo htmlspecialchars($currentDir); ?>">
            <button type="submit">Change Directory</button>
        </form>
    </div>

    <?php if ($currentDir !== __DIR__ && $currentDir !== dirname(__DIR__)): ?>
        <a href="?action=cd&file=.." class="parent-link">📁 Parent Directory</a>
    <?php endif; ?>

    <div class="mkdir-form">
        <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="action" value="mkdir">
            <input type="text" name="file" placeholder="New folder name..." required>
            <button type="submit">Create Folder</button>
        </form>
    </div>

    <div class="upload-form">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <button type="submit">Upload File</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Size</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($allItems)): ?>
                <tr><td colspan="4" style="text-align: center; color: #999;">Directory is empty</td></tr>
            <?php endif; ?>
            <?php foreach ($allItems as $item): ?>
                <?php
                $path = $currentDir . DIRECTORY_SEPARATOR . $item;
                $isDir = is_dir($path);
                $type = $isDir ? 'Directory' : 'File';
                $size = $isDir ? '-' : number_format(filesize($path)) . ' bytes';
                $icon = $isDir ? '📁' : '📄';
                ?>
                <tr>
                    <td><?php echo $icon; ?> <?php echo htmlspecialchars($item); ?></td>
                    <td><?php echo $type; ?></td>
                    <td><?php echo $size; ?></td>
                    <td class="actions">
                        <?php if ($isDir): ?>
                            <a href="?action=cd&file=<?php echo urlencode($item); ?>" style="background: #4CAF50; color: white; text-decoration: none; padding: 4px 10px; border-radius: 3px; font-size: 12px;">Open</a>
                        <?php else: ?>
                            <a href="?action=download&file=<?php echo urlencode($item); ?>" style="background: #2196F3; color: white; text-decoration: none; padding: 4px 10px; border-radius: 3px; font-size: 12px;">Download</a>
                        <?php endif; ?>
                        <a href="?action=delete&file=<?php echo urlencode($item); ?>" class="delete" onclick="return confirm('Delete <?php echo htmlspecialchars($item); ?>?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php
    if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['file'])) {
        $file = $currentDir . DIRECTORY_SEPARATOR . $_GET['file'];
        $file = realpath($file);
        if ($file && strpos($file, $currentDir) === 0 && is_file($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
    }

    if (isset($_GET['action']) && $_GET['action'] === 'cd' && isset($_GET['file'])) {
        if ($_GET['file'] === '..') {
            $parent = dirname($currentDir);
            if (is_dir($parent)) {
                $_SESSION['cwd'] = $parent;
            }
        } else {
            $newDir = $currentDir . DIRECTORY_SEPARATOR . $_GET['file'];
            $newDir = realpath($newDir);
            if ($newDir && is_dir($newDir)) {
                $_SESSION['cwd'] = $newDir;
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    ?>
</div>
</body>
</html>
