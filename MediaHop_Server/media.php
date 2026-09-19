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
//
// No Plex, Jellyfin, Emby, Docker, database, or transcoding is required.
// =================================================


// =================================================
// MEDIA ROOT + FILE TYPES
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
// OPTIONAL LIBRARY EXCLUSIONS
// =================================================
//
// Folder names listed here are hidden from MediaHop's media list.
// This does not delete or move anything.
//
// "Sample" is excluded by default because release folders commonly
// contain short sample video files.
//
// Add any other folder names you do not want MediaHop to list.
// Matching is case-insensitive and applies anywhere in the media tree.

$excludedFolders = [
    'Sample'
];


// =================================================
// OPTIONAL MEDIAHOP LOGIN
// =================================================
//
// Leave false for a normal unprotected MediaHop server.
//
// Set true to require the MediaHop username/password login.
//
// IMPORTANT:
// Change the username, password, and token secret BEFORE enabling auth.
// The token secret should be a long random value.
//
// HTTP Basic Authentication is separate from this setting.
// If your web host already protects this URL with HTTP Basic Auth,
// configure that on the web server itself. MediaHop can handle both
// HTTP Basic Auth and this MediaHop login at the same time.

$authEnabled = false;

$authUsername = 'CHANGE_ME';
$authPassword = 'CHANGE_ME';
$authTokenSecret = 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET';

// 30 days.
$authTokenLifetimeSeconds = 30 * 24 * 60 * 60;


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


function isExcludedMediaPath(
    $relativePath,
    $excludedFolders)
{
    if (!is_array($excludedFolders) ||
        count($excludedFolders) === 0)
    {
        return false;
    }

    $parts =
        preg_split(
            '~[/\\\\]+~',
            $relativePath,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

    if (!is_array($parts))
    {
        return false;
    }

    foreach ($parts as $part)
    {
        foreach ($excludedFolders as $excluded)
        {
            if (strcasecmp(
                    (string)$part,
                    (string)$excluded) === 0)
            {
                return true;
            }
        }
    }

    return false;
}


// =================================================
// TV MEDIA METADATA
// =================================================
//
// Used only when a selected server file is about to be sent to a DLNA TV.
// MediaHop does NOT probe the entire library.
//
// ffprobe is optional. If it is unavailable, normal playback still works
// and file size is returned when possible.

function getTvMediaMetadata($path)
{
    $metadata = [
        'videoCodec' => '',
        'audioCodec' => '',
        'width' => 0,
        'height' => 0,
        'size' => 0,
        'probeAvailable' => false
    ];

    $size = @filesize($path);

    if ($size !== false)
    {
        $metadata['size'] =
            (int)$size;
    }

    $extension =
        strtolower(
            pathinfo(
                $path,
                PATHINFO_EXTENSION
            )
        );

    // Do not probe HLS playlists.
    if ($extension === 'm3u8')
    {
        return $metadata;
    }

    if (!function_exists('shell_exec'))
    {
        return $metadata;
    }

    $command =
        'ffprobe -v error ' .
        '-show_entries stream=codec_type,codec_name,width,height ' .
        '-of json ' .
        escapeshellarg($path) .
        ' 2>/dev/null';

    $output =
        @shell_exec($command);

    if (!is_string($output) ||
        trim($output) === '')
    {
        return $metadata;
    }

    $probe =
        json_decode(
            $output,
            true
        );

    if (!is_array($probe))
    {
        return $metadata;
    }

    $metadata['probeAvailable'] = true;

    if (!isset($probe['streams']) ||
        !is_array($probe['streams']))
    {
        return $metadata;
    }

    foreach ($probe['streams'] as $stream)
    {
        if (!is_array($stream))
        {
            continue;
        }

        $type =
            isset($stream['codec_type'])
                ? strtolower(
                    (string)$stream['codec_type']
                  )
                : '';

        if ($type === 'video' &&
            $metadata['videoCodec'] === '')
        {
            $metadata['videoCodec'] =
                isset($stream['codec_name'])
                    ? strtolower(
                        (string)$stream['codec_name']
                      )
                    : '';

            $metadata['width'] =
                isset($stream['width'])
                    ? (int)$stream['width']
                    : 0;

            $metadata['height'] =
                isset($stream['height'])
                    ? (int)$stream['height']
                    : 0;
        }

        if ($type === 'audio' &&
            $metadata['audioCodec'] === '')
        {
            $metadata['audioCodec'] =
                isset($stream['codec_name'])
                    ? strtolower(
                        (string)$stream['codec_name']
                      )
                    : '';
        }

        if ($metadata['videoCodec'] !== '' &&
            $metadata['audioCodec'] !== '')
        {
            break;
        }
    }

    return $metadata;
}


// =================================================
// JSON / AUTH HELPERS
// =================================================

function sendJsonResponse($statusCode, $data)
{
    http_response_code($statusCode);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store'
    );

    echo json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


function sendAuthError($error, $message)
{
    header(
        'WWW-Authenticate: Bearer realm="MediaHop"'
    );

    sendJsonResponse(
        401,
        [
            'success' => false,
            'authRequired' => true,
            'error' => $error,
            'message' => $message
        ]
    );
}


function base64UrlEncode($data)
{
    return rtrim(
        strtr(
            base64_encode($data),
            '+/',
            '-_'
        ),
        '='
    );
}


function base64UrlDecode($data)
{
    $remainder =
        strlen($data) % 4;

    if ($remainder !== 0)
    {
        $data .=
            str_repeat(
                '=',
                4 - $remainder
            );
    }

    return base64_decode(
        strtr(
            $data,
            '-_',
            '+/'
        ),
        true
    );
}


function buildAuthSigningKey(
    $secret,
    $username,
    $password)
{
    return hash(
        'sha256',
        $secret . "\n" .
        $username . "\n" .
        $password,
        true
    );
}


function createAuthToken(
    $username,
    $password,
    $secret,
    $lifetimeSeconds)
{
    $now = time();

    $payload = [
        'v' => 1,
        'u' => $username,
        'iat' => $now,
        'exp' => $now + $lifetimeSeconds
    ];

    $payloadJson =
        json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
        );

    $payloadEncoded =
        base64UrlEncode(
            $payloadJson
        );

    $signingKey =
        buildAuthSigningKey(
            $secret,
            $username,
            $password
        );

    $signature =
        hash_hmac(
            'sha256',
            $payloadEncoded,
            $signingKey,
            true
        );

    return
        $payloadEncoded . '.' .
        base64UrlEncode($signature);
}


function validateAuthToken(
    $token,
    $username,
    $password,
    $secret)
{
    if (!is_string($token) ||
        $token === '')
    {
        return false;
    }

    $parts =
        explode(
            '.',
            $token,
            2
        );

    if (count($parts) !== 2)
    {
        return false;
    }

    $payloadEncoded =
        $parts[0];

    $signatureProvided =
        base64UrlDecode(
            $parts[1]
        );

    if ($signatureProvided === false)
    {
        return false;
    }

    $signingKey =
        buildAuthSigningKey(
            $secret,
            $username,
            $password
        );

    $signatureExpected =
        hash_hmac(
            'sha256',
            $payloadEncoded,
            $signingKey,
            true
        );

    if (!hash_equals(
            $signatureExpected,
            $signatureProvided))
    {
        return false;
    }

    $payloadJson =
        base64UrlDecode(
            $payloadEncoded
        );

    if ($payloadJson === false)
    {
        return false;
    }

    $payload =
        json_decode(
            $payloadJson,
            true
        );

    if (!is_array($payload))
    {
        return false;
    }

    if (!isset($payload['v']) ||
        (int)$payload['v'] !== 1)
    {
        return false;
    }

    if (!isset($payload['u']) ||
        !hash_equals(
            (string)$username,
            (string)$payload['u']))
    {
        return false;
    }

    if (!isset($payload['exp']) ||
        !is_numeric($payload['exp']) ||
        (int)$payload['exp'] < time())
    {
        return false;
    }

    return true;
}


function getBearerToken()
{
    $authorization = '';

    if (!empty($_SERVER['HTTP_AUTHORIZATION']))
    {
        $authorization =
            $_SERVER['HTTP_AUTHORIZATION'];
    }
    elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
    {
        $authorization =
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    elseif (function_exists('getallheaders'))
    {
        $headers = getallheaders();

        foreach ($headers as $name => $value)
        {
            if (strcasecmp(
                    $name,
                    'Authorization') === 0)
            {
                $authorization = $value;
                break;
            }
        }
    }

    if (preg_match(
            '/^Bearer\s+(.+)$/i',
            trim($authorization),
            $matches))
    {
        return trim($matches[1]);
    }

    return null;
}


function getRequestToken()
{
    $token =
        getBearerToken();

    if ($token !== null &&
        $token !== '')
    {
        return $token;
    }

    // TVs and direct players often cannot attach custom HTTP headers.
    if (isset($_GET['token']) &&
        is_string($_GET['token']) &&
        $_GET['token'] !== '')
    {
        return $_GET['token'];
    }

    return null;
}


// =================================================
// MEDIAHOP LOGIN + AUTH GATE
// =================================================

if ($authEnabled)
{
    if ($authUsername === 'CHANGE_ME' ||
        $authPassword === 'CHANGE_ME' ||
        $authTokenSecret === 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET')
    {
        sendJsonResponse(
            500,
            [
                'success' => false,
                'error' => 'auth_not_configured',
                'message' =>
                    'Authentication is enabled but the MediaHop auth settings have not been configured.'
            ]
        );
    }
}

$authAction =
    isset($_GET['action'])
        ? strtolower((string)$_GET['action'])
        : '';

if ($authAction === 'login')
{
    if (!$authEnabled)
    {
        sendJsonResponse(
            200,
            [
                'success' => true,
                'authRequired' => false,
                'message' =>
                    'Authentication is disabled.'
            ]
        );
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    {
        header('Allow: POST');

        sendJsonResponse(
            405,
            [
                'success' => false,
                'authRequired' => true,
                'error' => 'method_not_allowed',
                'message' =>
                    'Login requires POST.'
            ]
        );
    }

    $requestBody =
        file_get_contents(
            'php://input'
        );

    $loginData =
        json_decode(
            $requestBody,
            true
        );

    if (!is_array($loginData))
    {
        $loginData = $_POST;
    }

    $username =
        isset($loginData['username'])
            ? (string)$loginData['username']
            : '';

    $password =
        isset($loginData['password'])
            ? (string)$loginData['password']
            : '';

    $usernameMatches =
        hash_equals(
            (string)$authUsername,
            $username
        );

    $passwordMatches =
        hash_equals(
            (string)$authPassword,
            $password
        );

    if (!$usernameMatches ||
        !$passwordMatches)
    {
        sendAuthError(
            'invalid_credentials',
            'Incorrect username or password.'
        );
    }

    $token =
        createAuthToken(
            $authUsername,
            $authPassword,
            $authTokenSecret,
            $authTokenLifetimeSeconds
        );

    sendJsonResponse(
        200,
        [
            'success' => true,
            'authRequired' => true,
            'message' =>
                'Authentication successful.',
            'token' => $token,
            'expiresIn' =>
                $authTokenLifetimeSeconds
        ]
    );
}


$requestToken = null;

if ($authEnabled)
{
    $requestToken =
        getRequestToken();

    if ($requestToken === null)
    {
        sendAuthError(
            'authentication_required',
            'Authentication required.'
        );
    }

    if (!validateAuthToken(
            $requestToken,
            $authUsername,
            $authPassword,
            $authTokenSecret))
    {
        sendAuthError(
            'invalid_token',
            'Session expired or invalid. Please sign in again.'
        );
    }
}


// =================================================
// RESOLVE MEDIA ROOT
// =================================================

$basePath =
    realpath(
        $mediaDirectory
    );

if ($basePath === false ||
    !is_dir($basePath))
{
    sendJsonResponse(
        500,
        [
            'success' => false,
            'error' => 'media_directory_missing',
            'message' =>
                'Media directory does not exist.',
            'items' => []
        ]
    );
}


// =================================================
// TV MEDIA INFO FOR DLNA METADATA
// =================================================

if (isset($_GET['info']))
{
    $relativePath =
        normalizeRelativePath(
            rawurldecode(
                $_GET['info']
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
        sendJsonResponse(
            404,
            [
                'success' => false,
                'message' =>
                    'Media file not found.'
            ]
        );
    }

    $basePrefix =
        rtrim(
            str_replace(
                '\\',
                '/',
                $basePath
            ),
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
        sendJsonResponse(
            403,
            [
                'success' => false,
                'message' =>
                    'Forbidden.'
            ]
        );
    }

    if (isExcludedMediaPath(
            $relativePath,
            $excludedFolders))
    {
        sendJsonResponse(
            404,
            [
                'success' => false,
                'message' =>
                    'Media file not found.'
            ]
        );
    }

    $metadata =
        getTvMediaMetadata(
            $requestedPath
        );

    sendJsonResponse(
        200,
        array_merge(
            [
                'success' => true
            ],
            $metadata
        )
    );
}


// =================================================
// STREAM ONE FILE
// =================================================

if (isset($_GET['file']))
{
    // Media streams can remain open for a long time.
    @set_time_limit(0);
    @ini_set('max_execution_time', '0');
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');

    while (ob_get_level() > 0)
    {
        @ob_end_clean();
    }

    // nginx honours this; other servers safely ignore it.
    header('X-Accel-Buffering: no');
    header('Cache-Control: no-store, no-transform');

    if (function_exists('apache_setenv'))
    {
        @apache_setenv('no-gzip', '1');
    }

    $relativePath =
        normalizeRelativePath(
            rawurldecode(
                $_GET['file']
            )
        );

    if (isExcludedMediaPath(
            $relativePath,
            $excludedFolders))
    {
        http_response_code(404);
        exit('File not found.');
    }

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
            str_replace(
                '\\',
                '/',
                $basePath
            ),
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

    $safeFilename =
        str_replace(
            ['"', "\r", "\n"],
            '',
            basename($requestedPath)
        );

    header(
        'Content-Disposition: inline; filename="' .
        $safeFilename .
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

    if (isExcludedMediaPath(
            $relativePath,
            $excludedFolders))
    {
        continue;
    }

    $mediaUrl =
        $selfUrl .
        '?file=' .
        rawurlencode(
            $relativePath
        );

    if ($authEnabled &&
        $requestToken !== null)
    {
        $mediaUrl .=
            '&token=' .
            rawurlencode(
                $requestToken
            );
    }

    $result['items'][] = [
        'name' =>
            $file->getFilename(),

        // Relative path is used by MediaHop
        // to rebuild the real folder structure.
        'path' =>
            $relativePath,

        'url' =>
            $mediaUrl
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
