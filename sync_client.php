<?php
// 设置远程 JSON 数据 URL 和日志文件路径
$remoteJsonUrl = 'https://你的域名/sync_server.php';
$logFile = 'sync_log.txt';
$remoteFiles = getRemoteJson($remoteJsonUrl); // 获取远程 JSON 数据
if ($remoteFiles === null) {
    die('无法获取远程 JSON 数据');
}
$localFiles = scanDirectory(__DIR__); // 扫描本地目录并获取文件信息
syncFiles($remoteFiles, $localFiles, $logFile); // 同步文件并记录日志
echo "同步完成";

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
                'path' => '.' . DIRECTORY_SEPARATOR . str_replace($dir . DIRECTORY_SEPARATOR, '', $filePath),
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
    $localPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('./', '', $filePath); // 确保路径相对当前目录
    $localDir = dirname($localPath);

    if (!is_dir($localDir)) {
        mkdir($localDir, 0777, true);
    }

    file_put_contents($localPath, file_get_contents($url));
}
?>