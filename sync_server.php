<?php
function getDirectoryStructure($dir) {
    $result = [];

    // 创建一个目录迭代器
    $iterator = new DirectoryIterator($dir);

    foreach ($iterator as $fileinfo) {
        // 跳过 '.' 和 '..'
        if ($fileinfo->isDot()) {
            continue;
        }

        $path = $fileinfo->getPathname();
        $relativePath = str_replace(__DIR__, '', $path);

        if ($fileinfo->isDir()) {
            // 递归处理子目录
            $result[] = [
                'type' => 'directory',
                'path' => $relativePath,
                'contents' => getDirectoryStructure($path)
            ];
        } else {
            // 处理文件
            $result[] = [
                'type' => 'file',
                'path' => $relativePath,
                'size' => $fileinfo->getSize(),
                'mtime' => $fileinfo->getMTime() // 添加文件修改时间
            ];
        }
    }

    return $result;
}

// 获取指定文件的字节集
function getFileBytes($filePath) {
    if (file_exists($filePath)) {
        return file_get_contents($filePath);
    } else {
        return 'File not found.';
    }
}

if (isset($_GET['file'])) {
    $requestedFile = $_GET['file'];

    // 如果请求参数中包含文件路径
    // 则返回该文件的字节集
    echo getFileBytes(__DIR__ . $requestedFile);
} else {
    // 否则返回整个目录结构的 JSON
    $directoryStructure = getDirectoryStructure(__DIR__);

    // 将结果编码为 JSON 格式
    $jsonResult = json_encode($directoryStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // 将 JSON 输出到页面
    echo $jsonResult;

    // 如果想将 JSON 保存到文件中，可以使用下面的代码
    // file_put_contents('directory_structure.json', $jsonResult);
}
?>