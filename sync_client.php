<?php
// 配置服务端脚本URL
$directoryStructureURL = 'https://你的网站/sync_server.php';

// 检查服务端状态
$ch = curl_init($directoryStructureURL);

// 设置 cURL 选项
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

// 执行请求
$response = curl_exec($ch);

// 获取 HTTP 状态码
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// 检查是否有错误
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    // 打印错误信息并终止执行
    die("服务端异常 $error_msg");
}

// 关闭 cURL 会话
curl_close($ch);

// 检查 HTTP 状态码
if ($httpCode != 200) {
    // 打印状态码信息并终止执行
    die("服务端异常 $httpCode");
}

// 获取远程目录结构的 JSON
$remoteDirectoryStructureJSON = file_get_contents($directoryStructureURL);

// 解码远程 JSON
$remoteDirectoryStructure = json_decode($remoteDirectoryStructureJSON, true);

// 指定要同步的目录
$syncDirectory = 'sync';

// 日志记录
$log = '';

// 递归函数用于同步目录和文件，并收集所有远程路径
function syncDirectory($items, $syncDirectory, &$remoteFiles) {
    global $log;

    foreach ($items as $item) {
        $path = $item['path'];
        $type = $item['type'];
        $size = $item['size'] ?? null; // 获取文件大小（如果是文件）
        $mtime = $item['mtime'] ?? null; // 获取文件修改时间（如果是文件）

        $fullPath = $syncDirectory . $path;
        $remoteFiles[] = $fullPath;

        if ($type === 'directory') {
            // 如果是目录，则尝试创建目录
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0777, true);
                $log .= "Created directory: $fullPath\n";
            }

            // 递归处理子目录
            syncDirectory($item['contents'], $syncDirectory, $remoteFiles);
        } elseif ($type === 'file') {
            // 如果是文件，则尝试下载文件并同步（仅当文件大小或修改时间不匹配时）
            if (!file_exists($fullPath) || $size !== filesize($fullPath) || $mtime !== filemtime($fullPath)) {
                $fileURL = $GLOBALS['directoryStructureURL'] . '?file=' . urlencode($path);
                $fileContent = file_get_contents($fileURL);
                file_put_contents($fullPath, $fileContent);
                touch($fullPath, $mtime); // 更新文件的修改时间
                $log .= "Synced file: $fullPath\n";
            } else {
                // $log .= "Skipped file (already synced): $fullPath\n";
            }
        }
    }
}

// 递归删除本地多余的目录及其内容
function deleteDirectory($dir) {
    global $log;

    if (!file_exists($dir)) {
        return;
    }

    $items = new DirectoryIterator($dir);
    foreach ($items as $item) {
        if ($item->isDot()) {
            continue;
        }

        $path = $item->getPathname();
        if ($item->isDir()) {
            deleteDirectory($path);
        } else {
            unlink($path);
            $log .= "Deleted file: $path\n";
        }
    }

    rmdir($dir);
    $log .= "Deleted directory: $dir\n";
}

// 收集所有远程路径
$remoteFiles = [];

// 开始同步目录和文件
syncDirectory($remoteDirectoryStructure, $syncDirectory, $remoteFiles);

// 检查并删除本地多余的文件和目录
function cleanLocalDirectory($syncDirectory, $remoteFiles) {
    global $log;

    $localItems = new DirectoryIterator($syncDirectory);
    foreach ($localItems as $localItem) {
        if ($localItem->isDot()) {
            continue;
        }

        $localPath = $localItem->getPathname();
        if (!in_array($localPath, $remoteFiles)) {
            if ($localItem->isDir()) {
                deleteDirectory($localPath);
            } elseif ($localItem->isFile()) {
                unlink($localPath);
                $log .= "Deleted file: $localPath\n";
            }
        } else if ($localItem->isDir()) {
            // 递归清理子目录
            cleanLocalDirectory($localPath, $remoteFiles);
        }
    }
}

// 开始清理本地多余的文件和目录
cleanLocalDirectory($syncDirectory, $remoteFiles);

echo $log;
?>