<?php

$directoryStructureURL = 'https://你的域名/sync_server.php';

$remoteDirectoryStructureJSON = file_get_contents($directoryStructureURL);

$remoteDirectoryStructure = json_decode($remoteDirectoryStructureJSON, true);

$syncDirectory = 'sync';

$log = '';

function syncDirectory($items, $syncDirectory, &$remoteFiles) {
    global $log;

    foreach ($items as $item) {
        $path = $item['path'];
        $type = $item['type'];
        $size = $item['size'] ?? null; 
        $mtime = $item['mtime'] ?? null; 

        $fullPath = $syncDirectory . $path;
        $remoteFiles[] = $fullPath;

        if ($type === 'directory') {
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0777, true);
                $log .= "Created directory: $fullPath\n";
            }

            syncDirectory($item['contents'], $syncDirectory, $remoteFiles);
        } elseif ($type === 'file') {
            if (!file_exists($fullPath) || $size !== filesize($fullPath) || $mtime !== filemtime($fullPath)) {
                $fileURL = $GLOBALS['directoryStructureURL'] . '?file=' . urlencode($path);
                $fileContent = file_get_contents($fileURL);
                file_put_contents($fullPath, $fileContent);
                touch($fullPath, $mtime);
                $log .= "Synced file: $fullPath\n";
            }
        }
    }
}

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

$remoteFiles = [];

syncDirectory($remoteDirectoryStructure, $syncDirectory, $remoteFiles);

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
            cleanLocalDirectory($localPath, $remoteFiles);
        }
    }
}

cleanLocalDirectory($syncDirectory, $remoteFiles);

echo $log;

?>