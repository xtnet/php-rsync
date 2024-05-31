<?php
// 设置远程 JSON 数据 URL 和日志文件路径
$remoteJsonUrl = 'https://你的域名/sync_server.php';
$logFile = 'sync_log.txt';
$lockFile = '/tmp/my_lock_file.lock';

preventConcurrentExecution($lockFile);

function preventConcurrentExecution($lockFile) {
    global $remoteJsonUrl, $logFile; // 获取全局变量
    $fp = fopen($lockFile, 'c'); // 打开或创建锁文件

    if (flock($fp, LOCK_EX | LOCK_NB)) { // 尝试获得独占锁，非阻塞模式
        try {
            $remoteFiles = getRemoteJson($remoteJsonUrl); // 获取远程 JSON 数据
            if ($remoteFiles === null) {
                die('无法获取远程 JSON 数据');
            }

            $localFiles = scanDirectory('.'); // 扫描本地目录并获取文件信息
            syncFiles($remoteFiles, $localFiles, $logFile); // 同步文件并记录日志
            echo "同步完成";
        } finally {
            flock($fp, LOCK_UN); // 释放锁
        }
    } else {
        echo "同步中"; // 未能获得锁，返回提示信息
    }
    fclose($fp); // 关闭文件指针
}

/**
 * 递归扫描目录并获取文件信息
 * @param string $dir 要扫描的目录路径
 * @return array 文件信息数组
 */
function scanDirectory($dir) {
    global $logFile; // 获取全局变量
    $result = [];
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::SELF_FIRST);

    foreach ($items as $item) {
        if ($item->isFile()) {
            $filePath = $item->getPathname();
            $fileName = basename($filePath);

            if (in_array($fileName, [basename(__FILE__), $logFile])) {
                continue; // 跳过要排除的本地文件
            }

            $result[] = [
                'path' => $filePath,
                'modified_time' => $item->getMTime()
            ];
        }
    }
    return $result;
}

/**
 * 获取远程 JSON 数据
 * @param string $url 远程 JSON 数据 URL
 * @return array|null 解析后的 JSON 数据数组或 null（如果请求失败）
 */
function getRemoteJson($url) {
    $response = file_get_contents($url);
    return $response !== false ? json_decode($response, true) : null;
}

/**
 * 同步文件并记录日志
 * @param array $remoteFiles 远程文件信息
 * @param array $localFiles 本地文件信息
 * @param string $logFile 日志文件路径
 */
function syncFiles($remoteFiles, $localFiles, $logFile) {
    $remoteFileMap = array_column($remoteFiles, 'modified_time', 'path');
    $localFileMap = array_column($localFiles, 'modified_time', 'path');
    $logEntries = [];
    $hasChanges = false;

    foreach ($remoteFiles as $remoteFile) {
        $path = $remoteFile['path'];
        $remoteMTime = $remoteFile['modified_time'];

        if (!isset($localFileMap[$path])) {
            $logEntries[] = "新增文件: $path";
            downloadFile($path);
            $hasChanges = true;
        } elseif ($remoteMTime > $localFileMap[$path]) {
            $logEntries[] = "修改文件: $path";
            downloadFile($path);
            $hasChanges = true;
        }
    }

    foreach ($localFileMap as $path => $localMTime) {
        if (!isset($remoteFileMap[$path])) {
            $logEntries[] = "删除文件: $path";
            unlink($path);
            $hasChanges = true;
        }
    }

    $localDirectories = array_unique(array_map('dirname', array_keys($localFileMap)));
    foreach ($localDirectories as $dir) {
        deleteEmptyDirectories($dir);
    }

    if ($hasChanges) {
        $logEntries[] = "同步完成: " . date('Y-m-d H:i:s');
        file_put_contents($logFile, implode(PHP_EOL, $logEntries) . PHP_EOL, FILE_APPEND);
    }
}

/**
 * 递归删除空文件夹
 * @param string $dir 文件夹路径
 */
function deleteEmptyDirectories($dir) {
    if (!is_dir($dir)) return;

    foreach (scandir($dir) as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                deleteEmptyDirectories($filePath);
            }
        }
    }

    if (count(scandir($dir)) == 2) {
        rmdir($dir);
    }
}

/**
 * 从远程服务器下载文件
 * @param string $filePath 要下载的文件路径
 */
function downloadFile($filePath) {
    global $remoteJsonUrl;
    $url = $remoteJsonUrl . '?f=' . urlencode($filePath);
    $localPath = __DIR__ . DIRECTORY_SEPARATOR . $filePath;
    $localDir = dirname($localPath);

    if (!is_dir($localDir)) {
        mkdir($localDir, 0777, true);
    }

    file_put_contents($localPath, file_get_contents($url));
}
?>