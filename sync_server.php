<?php
/**
 * 递归扫描目录并获取文件信息
 * @param string $dir 要扫描的目录路径
 * @return array 文件信息数组
 */
function scanDirectory($dir) {
    $result = [];
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::SELF_FIRST);

    foreach ($items as $item) {
        if ($item->isFile()) {
            $result[] = [
                'path' => $item->getPathname(),
                'modified_time' => $item->getMTime()
            ];
        }
    }
    return $result;
}

// 生成 JSON 内容
$directoryToScan = '.';
$fileInfo = scanDirectory($directoryToScan);
$jsonData = json_encode($fileInfo, JSON_PRETTY_PRINT);

// 如果有请求参数，则下载对应文件
if (isset($_GET['f'])) {
    $filename = $_GET['f'];
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

    if (file_exists($filePath) && is_file($filePath)) {
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