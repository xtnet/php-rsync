<?php

function getDirectoryStructure($dir) {
    $result = [];

    $iterator = new DirectoryIterator($dir);

    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isDot()) {
            continue;
        }

        $path = $fileinfo->getPathname();
        $relativePath = str_replace(__DIR__, '', $path);

        if ($fileinfo->isDir()) {
            $result[] = [
                'type' => 'directory',
                'path' => $relativePath,
                'contents' => getDirectoryStructure($path)
            ];
        } else {
            $result[] = [
                'type' => 'file',
                'path' => $relativePath,
                'size' => $fileinfo->getSize(),
                'mtime' => $fileinfo->getMTime()
            ];
        }
    }

    return $result;
}

function getFileBytes($filePath) {
    if (file_exists($filePath)) {
        return file_get_contents($filePath);
    } else {
        return 'File not found.';
    }
}

if (isset($_GET['file'])) {
    $requestedFile = $_GET['file'];
    echo getFileBytes(__DIR__ . $requestedFile);
} else {
    $directoryStructure = getDirectoryStructure(__DIR__);
    $jsonResult = json_encode($directoryStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo $jsonResult;
}
?>