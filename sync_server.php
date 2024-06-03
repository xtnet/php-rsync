<?php
/**
 * 递归扫描目录并获取文件信息
 * @param string $dir 要扫描的目录路径
 * @return array 文件信息数组
 */
function scanDirectory($dir) {
    $result = [];
    $baseDir = realpath($dir);

    // 确保基目录是有效路径并且在允许的范围内
    if ($baseDir === false || strpos($baseDir, __DIR__) !== 0) {
        throw new RuntimeException('Invalid directory path');
    }

    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir), RecursiveIteratorIterator::SELF_FIRST);

    foreach ($items as $item) {
        $itemPath = $item->getRealPath();
        if ($itemPath !== false && strpos($itemPath, $baseDir) === 0 && $item->isFile()) {
            $result[] = [
                'path' => '.' . DIRECTORY_SEPARATOR . str_replace($baseDir . DIRECTORY_SEPARATOR, '', $itemPath), // 相对路径
                'modified_time' => $item->getMTime()
            ];
        }
    }
    return $result;
}

// 设置当前目录作为扫描目录
$directoryToScan = __DIR__;
$fileInfo = scanDirectory($directoryToScan);
$jsonData = json_encode($fileInfo, JSON_PRETTY_PRINT);

// 如果有请求参数，则下载对应文件
if (isset($_GET['f'])) {
    $filename = basename($_GET['f']); // 使用 basename 确保文件名安全
    $filePath = realpath($directoryToScan . DIRECTORY_SEPARATOR . $filename);

    // 确保文件路径在当前目录范围内
    if ($filePath !== false && strpos($filePath, $directoryToScan) === 0 && is_file($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        ob_clean();
        flush();
        readfile($filePath);
        exit;
    } else {
        echo 'File not found.';
        exit;
    }
}

// 如果没有请求参数，返回 JSON 内容
header('Content-Type: application/json');
echo $jsonData;
?>