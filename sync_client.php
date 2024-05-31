<?php
// 设置服务端脚本 URL 和日志文件路径
$remoteJsonUrl = 'https://127.0.0.1/sync_server.php';
$logFile = 'sync_log.txt';

$lockFile = '/tmp/my_lock_file.lock';
preventConcurrentExecution($lockFile);

function preventConcurrentExecution($lockFile) {
	global $remoteJsonUrl; // 获取全局变量
	global $logFile; // 获取全局变量

    $fp = fopen($lockFile, 'c'); // 打开或创建锁文件

    if (flock($fp, LOCK_EX | LOCK_NB)) { // 尝试获得独占锁，非阻塞模式
        // 获得锁，执行关键代码
        try {

            // 获取远程 JSON 数据
            $remoteFiles = getRemoteJson($remoteJsonUrl);

            if ($remoteFiles === null) {
                die('无法获取远程 JSON 数据');
            }

            // 扫描本地目录并获取文件信息
            $localFiles = scanDirectory('.');

            // 同步文件并记录日志
            syncFiles($remoteFiles, $localFiles, $logFile);

            echo "同步完成";
            
        } finally {
            flock($fp, LOCK_UN); // 释放锁
        }
    } else {
        // 未能获得锁，返回提示信息
        echo "同步中";
    }

    fclose($fp); // 关闭文件指针
}

/**
 * 递归扫描目录并获取文件信息
 *
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
            
            // 检查是否为要排除的文件名
            if (in_array($fileName, [basename(__FILE__), $logFile])) {
                continue; // 跳过要排除的本地文件，注意不是排除对方文件
            }

            $fileInfo = [
                'path' => $filePath,
                'modified_time' => $item->getMTime()
            ];
            $result[] = $fileInfo;
        }
    }

    return $result;
}

/**
 * 获取远程 JSON 数据
 *
 * @param string $url 远程 JSON 数据 URL
 * @return array|null 解析后的 JSON 数据数组或 null（如果请求失败）
 */
function getRemoteJson($url) {
    $response = file_get_contents($url);
    if ($response === FALSE) {
        return null;
    }
    return json_decode($response, true);
}

/**
 * 同步文件并记录日志
 *
 * @param array $remoteFiles 远程文件信息数组
 * @param array $localFiles 本地文件信息数组
 * @param string $logFile 日志文件路径
 */
function syncFiles($remoteFiles, $localFiles, $logFile) {
    $remoteFileMap = [];
    foreach ($remoteFiles as $file) {
        $remoteFileMap[$file['path']] = $file['modified_time'];
    }

    $localFileMap = [];
    foreach ($localFiles as $file) {
        $localFileMap[$file['path']] = $file['modified_time'];
    }

    $logEntries = [];

    $hasChanges = false; // 是否有文件变更标志

    // 检查新增和修改的文件
    foreach ($remoteFileMap as $path => $remoteMTime) {
        if (!isset($localFileMap[$path])) {
            // 新增文件
            $logEntries[] = "新增文件: $path";
            // 从 B 服务器下载文件
            downloadFile($path);
            $hasChanges = true; // 设置文件变更标志为 true
        } elseif ($remoteMTime > $localFileMap[$path]) {
            // 修改文件
            $logEntries[] = "修改文件: $path";
            // 从 B 服务器下载文件
            downloadFile($path);
            $hasChanges = true; // 设置文件变更标志为 true
        }
    }

    // 检查删除的文件
    foreach ($localFileMap as $path => $localMTime) {
        if (!isset($remoteFileMap[$path])) {
            // 删除文件
            $logEntries[] = "删除文件: $path";
            unlink($path);
            $hasChanges = true; // 设置文件变更标志为 true
        }
    }

    // 检查并删除不存在的文件夹
    $localDirectories = array_map('dirname', array_keys($localFileMap));
    $localDirectories = array_unique($localDirectories);
    foreach ($localDirectories as $dir) {
        deleteEmptyDirectories($dir);
    }

    // 如果有文件变更，则记录同步完成时间和写入日志
    if ($hasChanges) {
        $logEntries[] = "同步完成: " . date('Y-m-d H:i:s');
        file_put_contents($logFile, implode(PHP_EOL, $logEntries) . PHP_EOL, FILE_APPEND);
    }
}

/**
 * 递归删除空文件夹
 *
 * @param string $dir 文件夹路径
 */
function deleteEmptyDirectories($dir) {
    if (!file_exists($dir) || !is_dir($dir)) {
        return;
    }

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                deleteEmptyDirectories($filePath);
            }
        }
    }

    // 删除空文件夹
    if (count(scandir($dir)) == 2) {
        rmdir($dir);
    }
}

/**
 * 从 B 服务器下载文件
 *
 * @param string $filePath 要下载的文件路径
 */
function downloadFile($filePath) {
    global $remoteJsonUrl; // 获取全局变量
    $url = $remoteJsonUrl . '?f=' . urlencode($filePath);
    $localPath = __DIR__ . DIRECTORY_SEPARATOR . $filePath;
    $localDir = dirname($localPath);

    if (!is_dir($localDir)) {
        mkdir($localDir, 0777, true);
    }

    file_put_contents($localPath, file_get_contents($url));
}
?>