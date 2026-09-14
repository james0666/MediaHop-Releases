<?php
// =================================================
// MEDIAHOP - BASIC MEDIA SERVER
// =================================================
//
// BASIC SETUP:
// Put this media.php file directly inside the folder
// containing the media you want MediaHop to access.
//
// Example:
//   Media/
//   ├── Movies/
//   ├── TV Shows/
//   ├── Videos/
//   └── media.php
//
// Then enter the public URL to media.php in MediaHop.
// The folder containing media.php is automatically the media root.
// =================================================

$mediaDirectory = __DIR__;

$allowedExtensions = [
    'mp4',
    'm4v',
    'mkv',
    'avi',
    'mov',
    'webm',
    'ts',
    'm2ts',
    'mpg',
    'mpeg',
    'm3u8'
];


// =================================================
// HELPERS
// =================================================

function normalizeRelativePath($path)
{
    return ltrim(
        str_replace('\\', '/', $path),
        '/'
    );
}


function buildSelfUrl()
{
    $https =
        (!empty($_SERVER['HTTPS']) &&
         strtolower($_SERVER['HTTPS']) !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) &&
         (int)$_SERVER['SERVER_PORT'] === 443);

    $scheme =
        $https
            ? 'https'
            : 'http';

    $host =
        isset($_SERVER['HTTP_HOST'])
            ? $_SERVER['HTTP_HOST']
            : 'localhost';

    $script =
        isset($_SERVER['SCRIPT_NAME'])
            ? $_SERVER['SCRIPT_NAME']
            : '/media.php';

    return $scheme . '://' . $host . $script;
}


function getMimeTypeForPath($path)
{
    $extension = strtolower(
        pathinfo(
            $path,
            PATHINFO_EXTENSION
        )
    );

    switch ($extension)
    {
        case 'mp4':
        case 'm4v':
            return 'video/mp4';

        case 'mkv':
            return 'video/x-matroska';

        case 'avi':
            return 'video/x-msvideo';

        case 'mov':
            return 'video/quicktime';

        case 'webm':
            return 'video/webm';

        case 'ts':
        case 'm2ts':
            return 'video/mp2t';

        case 'mpg':
        case 'mpeg':
            return 'video/mpeg';

        case 'm3u8':
            return 'application/vnd.apple.mpegurl';

        default:
            return 'application/octet-stream';
    }
}


// =================================================
// STREAM ONE FILE
// =================================================

if (isset($_GET['file']))
{
    $basePath = realpath($mediaDirectory);

    if ($basePath === false ||
        !is_dir($basePath))
    {
        http_response_code(500);
        exit('Media directory does not exist.');
    }

    $relativePath =
        normalizeRelativePath(
            rawurldecode(
                $_GET['file']
            )
        );

    $requestedPath =
        realpath(
            $basePath .
            DIRECTORY_SEPARATOR .
            $relativePath
        );

    if ($requestedPath === false ||
        !is_file($requestedPath))
    {
        http_response_code(404);
        exit('File not found.');
    }

    // Stop ../ traversal outside the media root.
    $basePrefix =
        rtrim(
            str_replace('\\', '/', $basePath),
            '/'
        ) . '/';

    $requestedNormalized =
        str_replace(
            '\\',
            '/',
            $requestedPath
        );

    if (strpos(
            $requestedNormalized,
            $basePrefix
        ) !== 0)
    {
        http_response_code(403);
        exit('Forbidden.');
    }

    $fileSize =
        filesize($requestedPath);

    if ($fileSize === false)
    {
        http_response_code(500);
        exit('Unable to read file size.');
    }

    $start = 0;
    $end =
        $fileSize - 1;

    $statusCode = 200;

    if (isset($_SERVER['HTTP_RANGE']) &&
        preg_match(
            '/bytes=(\d*)-(\d*)/i',
            $_SERVER['HTTP_RANGE'],
            $matches
        ))
    {
        if ($matches[1] !== '')
        {
            $start =
                (int)$matches[1];
        }

        if ($matches[2] !== '')
        {
            $end =
                (int)$matches[2];
        }

        if ($start < 0 ||
            $start >= $fileSize ||
            $end < $start)
        {
            header(
                'Content-Range: bytes */' .
                $fileSize
            );

            http_response_code(416);
            exit;
        }

        if ($end >= $fileSize)
        {
            $end =
                $fileSize - 1;
        }

        $statusCode = 206;
    }

    $length =
        $end - $start + 1;

    http_response_code($statusCode);

    header(
        'Content-Type: ' .
        getMimeTypeForPath(
            $requestedPath
        )
    );

    header('Accept-Ranges: bytes');

    header(
        'Content-Length: ' .
        $length
    );

    if ($statusCode === 206)
    {
        header(
            'Content-Range: bytes ' .
            $start .
            '-' .
            $end .
            '/' .
            $fileSize
        );
    }

    header(
        'Content-Disposition: inline; filename="' .
        basename($requestedPath) .
        '"'
    );

    if ($_SERVER['REQUEST_METHOD'] === 'HEAD')
    {
        exit;
    }

    $handle =
        fopen(
            $requestedPath,
            'rb'
        );

    if ($handle === false)
    {
        http_response_code(500);
        exit;
    }

    fseek(
        $handle,
        $start
    );

    $remaining =
        $length;

    $chunkSize =
        1024 * 1024;

    while ($remaining > 0 &&
           !feof($handle))
    {
        $readSize =
            min(
                $chunkSize,
                $remaining
            );

        $buffer =
            fread(
                $handle,
                $readSize
            );

        if ($buffer === false ||
            $buffer === '')
        {
            break;
        }

        echo $buffer;
        flush();

        $remaining -=
            strlen($buffer);

        if (connection_aborted())
        {
            break;
        }
    }

    fclose($handle);
    exit;
}


// =================================================
// RETURN MEDIA LIST
// =================================================

header(
    'Content-Type: application/json; charset=utf-8'
);

header(
    'Cache-Control: no-store'
);

$result = [
    'items' => []
];

$basePath =
    realpath(
        $mediaDirectory
    );

if ($basePath === false ||
    !is_dir($basePath))
{
    http_response_code(500);

    echo json_encode(
        [
            'error' =>
                'Media directory does not exist: ' .
                $mediaDirectory,
            'items' => []
        ],
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

$selfUrl =
    buildSelfUrl();

$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $basePath,
            FilesystemIterator::SKIP_DOTS
        )
    );

foreach ($iterator as $file)
{
    if (!$file->isFile())
        continue;

    $extension =
        strtolower(
            pathinfo(
                $file->getFilename(),
                PATHINFO_EXTENSION
            )
        );

    if (!in_array(
            $extension,
            $allowedExtensions,
            true))
    {
        continue;
    }

    $fullPath =
        $file->getRealPath();

    if ($fullPath === false)
        continue;

    $relativePath =
        ltrim(
            str_replace(
                '\\',
                '/',
                substr(
                    $fullPath,
                    strlen($basePath)
                )
            ),
            '/'
        );

    $result['items'][] = [
        'name' =>
            $file->getFilename(),

        // Relative path is used by MediaHop
        // to rebuild the real folder structure.
        'path' =>
            $relativePath,

        'url' =>
            $selfUrl .
            '?file=' .
            rawurlencode(
                $relativePath
            )
    ];
}


// =================================================
// SORT A-Z BY RELATIVE PATH
// =================================================

usort(
    $result['items'],
    function ($a, $b)
    {
        return strcasecmp(
            $a['path'],
            $b['path']
        );
    }
);


echo json_encode(
    $result,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_SLASHES
);
?>
