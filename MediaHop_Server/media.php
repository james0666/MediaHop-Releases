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
// The api/ folder is always hidden by MediaHop.
//
// Folder names listed below are also hidden from MediaHop's media list
// and cannot be streamed through this endpoint. This does not delete or
// move anything. Matching is case-insensitive and applies anywhere in
// the media tree.
//
// "Sample" is excluded by default because release folders commonly
// contain short sample video files. Add your own folder names as needed.

$excludedFolders = [
    'Sample'
];


// =================================================
// OPTIONAL MEDIAHOP LOGIN
// =================================================
//
// Leave false for a normal unprotected MediaHop server.
// Set true to require the MediaHop username/password login.
//
// IMPORTANT:
// Change the username, password, and token secret BEFORE enabling auth.
// The token secret should be a long random value.
//
// HTTP Basic Authentication is separate from this setting. If your web
// host already protects this URL with HTTP Basic Auth, configure that on
// the web server itself. MediaHop can handle both at the same time.

$authEnabled = false;

$authUsername = 'CHANGE_ME';
$authPassword = 'CHANGE_ME';
$authTokenSecret = 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET';

// 14 days.
$authTokenLifetimeSeconds = 14 * 24 * 60 * 60;


// Lightweight login rate limiting.
// This applies only to MediaHop's own ?action=login endpoint.
// Attempts are tracked per REMOTE_ADDR using small temporary files.
$authRateLimitEnabled = true;
$authRateLimitMaxFailures = 5;
$authRateLimitWindowSeconds = 10 * 60;
$authRateLimitLockoutSeconds = 15 * 60;


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


function isHiddenMediaPath(
    $relativePath,
    $excludedFolders)
{
    $normalized = normalizeRelativePath($relativePath);

    // Never expose the API helper folder.
    if (preg_match('~(^|/)api(/|$)~i', $normalized))
    {
        return true;
    }

    if (!is_array($excludedFolders) ||
        count($excludedFolders) === 0)
    {
        return false;
    }

    $parts =
        preg_split(
            '~[/\\\\]+~',
            $normalized,
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
// TV MEDIA METADATA
// =================================================
// Used only when a server file is about to be sent to a DLNA TV.
// This deliberately probes one selected file at a time instead of
// scanning the whole library.
//
// ffprobe is optional. If the host does not provide it, file size is
// still returned and normal playback remains completely unaffected.
function getTvMediaMetadata($path)
{
    $metadata = [
        'videoCodec' => '',
        'audioCodec' => '',
        'width' => 0,
        'height' => 0,
        'size' => 0,
        'durationMs' => 0,
        'probeAvailable' => false
    ];

    $size = @filesize($path);

    if ($size !== false)
    {
        $metadata['size'] = (int)$size;
    }

    // Do not probe HLS playlists. Their referenced media may be remote
    // or continuously changing, and the TV path does not need this.
    $extension =
        strtolower(
            pathinfo(
                $path,
                PATHINFO_EXTENSION
            )
        );

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
        '-show_entries format=duration:stream=codec_type,codec_name,width,height ' .
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

    if (isset($probe['format']) &&
        is_array($probe['format']) &&
        isset($probe['format']['duration']) &&
        is_numeric($probe['format']['duration']))
    {
        $durationSeconds =
            (float)$probe['format']['duration'];

        if ($durationSeconds > 0)
        {
            $metadata['durationMs'] =
                (int)round(
                    $durationSeconds * 1000
                );
        }
    }

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
// MEDIAHOP STREAM DIAGNOSTICS - DISABLED
// =================================================
//
// Keep this no-op helper so the existing streaming code does not need to
// change, but do not create mediahop_stream_debug.log and do not write these
// diagnostic messages to PHP's normal error log.
function mediaHopStreamLog($message)
{
    return;
}


// =================================================
// MEDIAHOP AUTH HELPERS
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


// =================================================
// LIGHTWEIGHT LOGIN RATE LIMITING
// =================================================
// Uses REMOTE_ADDR only. Do not trust X-Forwarded-For unless a trusted
// reverse proxy is explicitly configured to provide it.
//
// Lockout responses deliberately remain HTTP 401 so the current MediaHop
// Unity login screen can display the server-provided message without any
// client-side changes.

function getLoginRateLimitClientKey()
{
    if (!isset($_SERVER['REMOTE_ADDR']))
    {
        return '';
    }

    return trim(
        (string)$_SERVER['REMOTE_ADDR']
    );
}


function getLoginRateLimitFile($clientKey)
{
    if ($clientKey === '')
    {
        return '';
    }

    $scriptScope =
        substr(
            hash(
                'sha256',
                __FILE__
            ),
            0,
            16
        );

    $clientHash =
        hash(
            'sha256',
            $clientKey
        );

    $tempDirectory =
        rtrim(
            sys_get_temp_dir(),
            DIRECTORY_SEPARATOR
        );

    return
        $tempDirectory .
        DIRECTORY_SEPARATOR .
        'mediahop_login_' .
        $scriptScope .
        '_' .
        $clientHash .
        '.json';
}


function readLoginRateLimitState($handle)
{
    $state = [
        'failures' => [],
        'lockedUntil' => 0
    ];

    if (!is_resource($handle))
    {
        return $state;
    }

    rewind($handle);

    $raw =
        stream_get_contents($handle);

    if (!is_string($raw) ||
        trim($raw) === '')
    {
        return $state;
    }

    $decoded =
        json_decode(
            $raw,
            true
        );

    if (!is_array($decoded))
    {
        return $state;
    }

    if (isset($decoded['failures']) &&
        is_array($decoded['failures']))
    {
        foreach ($decoded['failures'] as $failureTime)
        {
            if (is_numeric($failureTime))
            {
                $state['failures'][] =
                    (int)$failureTime;
            }
        }
    }

    if (isset($decoded['lockedUntil']) &&
        is_numeric($decoded['lockedUntil']))
    {
        $state['lockedUntil'] =
            (int)$decoded['lockedUntil'];
    }

    return $state;
}


function writeLoginRateLimitState(
    $handle,
    $state)
{
    if (!is_resource($handle))
    {
        return;
    }

    rewind($handle);
    ftruncate($handle, 0);

    fwrite(
        $handle,
        json_encode(
            $state,
            JSON_UNESCAPED_SLASHES
        )
    );

    fflush($handle);
}


function cleanLoginRateLimitFailures(
    $failures,
    $now,
    $windowSeconds)
{
    $minimumTime =
        $now -
        max(
            1,
            (int)$windowSeconds
        );

    $cleaned = [];

    foreach ($failures as $failureTime)
    {
        $failureTime =
            (int)$failureTime;

        if ($failureTime >= $minimumTime)
        {
            $cleaned[] =
                $failureTime;
        }
    }

    return $cleaned;
}


function getLoginRateLimitRetryAfter(
    $clientKey,
    $windowSeconds)
{
    $path =
        getLoginRateLimitFile(
            $clientKey
        );

    if ($path === '')
    {
        return 0;
    }

    $handle =
        @fopen(
            $path,
            'c+'
        );

    if ($handle === false)
    {
        // Fail open if the host cannot write temp files.
        return 0;
    }

    if (!@flock(
            $handle,
            LOCK_EX))
    {
        fclose($handle);
        return 0;
    }

    $now = time();

    $state =
        readLoginRateLimitState(
            $handle
        );

    if ($state['lockedUntil'] > $now)
    {
        $retryAfter =
            $state['lockedUntil'] -
            $now;

        @flock($handle, LOCK_UN);
        fclose($handle);

        return max(
            1,
            $retryAfter
        );
    }

    // A completed lockout starts with a clean slate.
    if ($state['lockedUntil'] > 0)
    {
        $state['lockedUntil'] = 0;
        $state['failures'] = [];
    }
    else
    {
        $state['failures'] =
            cleanLoginRateLimitFailures(
                $state['failures'],
                $now,
                $windowSeconds
            );
    }

    writeLoginRateLimitState(
        $handle,
        $state
    );

    @flock($handle, LOCK_UN);
    fclose($handle);

    return 0;
}


function recordLoginRateLimitFailure(
    $clientKey,
    $maxFailures,
    $windowSeconds,
    $lockoutSeconds)
{
    $path =
        getLoginRateLimitFile(
            $clientKey
        );

    if ($path === '')
    {
        return 0;
    }

    $handle =
        @fopen(
            $path,
            'c+'
        );

    if ($handle === false)
    {
        return 0;
    }

    if (!@flock(
            $handle,
            LOCK_EX))
    {
        fclose($handle);
        return 0;
    }

    $now = time();

    $state =
        readLoginRateLimitState(
            $handle
        );

    if ($state['lockedUntil'] > $now)
    {
        $retryAfter =
            $state['lockedUntil'] -
            $now;

        @flock($handle, LOCK_UN);
        fclose($handle);

        return max(
            1,
            $retryAfter
        );
    }

    if ($state['lockedUntil'] > 0)
    {
        $state['lockedUntil'] = 0;
        $state['failures'] = [];
    }

    $state['failures'] =
        cleanLoginRateLimitFailures(
            $state['failures'],
            $now,
            $windowSeconds
        );

    $state['failures'][] =
        $now;

    if (count($state['failures']) >=
        max(
            1,
            (int)$maxFailures
        ))
    {
        $state['lockedUntil'] =
            $now +
            max(
                1,
                (int)$lockoutSeconds
            );

        // Start fresh after the lockout expires.
        $state['failures'] = [];

        $retryAfter =
            $state['lockedUntil'] -
            $now;

        writeLoginRateLimitState(
            $handle,
            $state
        );

        @flock($handle, LOCK_UN);
        fclose($handle);

        return max(
            1,
            $retryAfter
        );
    }

    writeLoginRateLimitState(
        $handle,
        $state
    );

    @flock($handle, LOCK_UN);
    fclose($handle);

    return 0;
}


function clearLoginRateLimitState(
    $clientKey)
{
    $path =
        getLoginRateLimitFile(
            $clientKey
        );

    if ($path === '')
    {
        return;
    }

    @unlink($path);
}


function sendLoginRateLimitError(
    $retryAfterSeconds)
{
    $retryAfterSeconds =
        max(
            1,
            (int)$retryAfterSeconds
        );

    $retryMinutes =
        max(
            1,
            (int)ceil(
                $retryAfterSeconds / 60
            )
        );

    header(
        'Retry-After: ' .
        $retryAfterSeconds
    );

    sendAuthError(
        'rate_limited',
        'Too many failed login attempts. Try again in about ' .
        $retryMinutes .
        ($retryMinutes === 1
            ? ' minute.'
            : ' minutes.')
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
    // Username/password are deliberately part of the signing key.
    // Changing either one invalidates all existing tokens immediately.
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

    // Direct video players / TVs often cannot attach custom HTTP headers.
    // Protected media URLs therefore also support ?token=...
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

    $rateLimitClientKey =
        getLoginRateLimitClientKey();

    if ($authRateLimitEnabled)
    {
        $retryAfter =
            getLoginRateLimitRetryAfter(
                $rateLimitClientKey,
                $authRateLimitWindowSeconds
            );

        if ($retryAfter > 0)
        {
            sendLoginRateLimitError(
                $retryAfter
            );
        }
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
        if ($authRateLimitEnabled)
        {
            $retryAfter =
                recordLoginRateLimitFailure(
                    $rateLimitClientKey,
                    $authRateLimitMaxFailures,
                    $authRateLimitWindowSeconds,
                    $authRateLimitLockoutSeconds
                );

            if ($retryAfter > 0)
            {
                sendLoginRateLimitError(
                    $retryAfter
                );
            }
        }

        sendAuthError(
            'invalid_credentials',
            'Incorrect username or password.'
        );
    }

    if ($authRateLimitEnabled)
    {
        clearLoginRateLimitState(
            $rateLimitClientKey
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
// TV MEDIA INFO FOR DLNA METADATA
// =================================================

if (isset($_GET['info']))
{
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
                'message' =>
                    'Media directory unavailable.'
            ]
        );
    }

    $relativePath =
        normalizeRelativePath(
            rawurldecode(
                $_GET['info']
            )
        );

    if (isHiddenMediaPath(
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

    // Keep the same traversal protection used by normal streaming.
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
    // -------------------------------------------------
    // LONG-RUNNING MEDIA STREAM SETTINGS
    // -------------------------------------------------
    // A TV stream can stay open for well over an hour. Do not let PHP's
    // normal execution timer or PHP output buffering terminate/delay it.
    @set_time_limit(0);
    @ini_set('max_execution_time', '0');
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');

    // Remove any output buffers that were already created before this point.
    while (ob_get_level() > 0)
    {
        @ob_end_clean();
    }

    // nginx honours this header by disabling FastCGI/proxy response buffering.
    // Other web servers safely ignore it.
    header('X-Accel-Buffering: no');
    header('Cache-Control: no-store, no-transform');

    // Also ask Apache, when available, not to gzip this already-compressed media.
    if (function_exists('apache_setenv'))
    {
        @apache_setenv('no-gzip', '1');
    }

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

    if (isHiddenMediaPath(
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

    // -------------------------------------------------
    // STREAM DIAGNOSTICS ONLY
    // -------------------------------------------------
    $streamStartedAt = microtime(true);
    $streamBytesSent = 0;
    $streamExitReason = 'unknown';
    $streamDiagnosticActive = true;
    $lastProgressLogAt = $streamStartedAt;

    mediaHopStreamLog(
        'STREAM START' .
        ' | File: ' . basename($requestedPath) .
        ' | Status: ' . $statusCode .
        ' | Range: ' . $start . '-' . $end .
        ' | Length: ' . $length .
        ' | FileSize: ' . $fileSize .
        ' | PHP max_execution_time: ' . ini_get('max_execution_time') .
        ' | OutputBuffering: ' . ini_get('output_buffering') .
        ' | ZlibCompression: ' . ini_get('zlib.output_compression') .
        ' | OBLevel: ' . ob_get_level() .
        ' | ConnectionStatus: ' . connection_status()
    );

    register_shutdown_function(
        function () use (
            &$streamDiagnosticActive,
            &$streamExitReason,
            &$streamBytesSent,
            &$remaining,
            &$streamStartedAt,
            $requestedPath,
            $length)
        {
            if (!$streamDiagnosticActive)
            {
                return;
            }

            $lastError = error_get_last();

            $errorText = 'none';

            if (is_array($lastError))
            {
                $errorText =
                    'type=' .
                    (isset($lastError['type'])
                        ? $lastError['type']
                        : 'unknown') .
                    ', message=' .
                    (isset($lastError['message'])
                        ? str_replace(
                            ["\r", "\n"],
                            ' ',
                            $lastError['message']
                        )
                        : 'unknown') .
                    ', file=' .
                    (isset($lastError['file'])
                        ? basename($lastError['file'])
                        : 'unknown') .
                    ', line=' .
                    (isset($lastError['line'])
                        ? $lastError['line']
                        : 'unknown');
            }

            mediaHopStreamLog(
                'STREAM SHUTDOWN' .
                ' | File: ' . basename($requestedPath) .
                ' | Reason: ' . $streamExitReason .
                ' | BytesSent: ' . $streamBytesSent .
                ' | Expected: ' . $length .
                ' | Remaining: ' . $remaining .
                ' | Elapsed: ' .
                    number_format(
                        microtime(true) - $streamStartedAt,
                        1,
                        '.',
                        ''
                    ) . 's' .
                ' | ConnectionStatus: ' . connection_status() .
                ' | ConnectionAborted: ' .
                    (connection_aborted() ? 'yes' : 'no') .
                ' | LastError: ' . $errorText
            );
        }
    );

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

        if ($buffer === false)
        {
            $streamExitReason = 'fread returned false';

            mediaHopStreamLog(
                'STREAM READ FAILED' .
                ' | File: ' . basename($requestedPath) .
                ' | BytesSent: ' . $streamBytesSent .
                ' | Remaining: ' . $remaining .
                ' | Feof: ' . (feof($handle) ? 'yes' : 'no') .
                ' | ConnectionStatus: ' . connection_status()
            );

            break;
        }

        if ($buffer === '')
        {
            $streamExitReason = 'fread returned empty string';

            mediaHopStreamLog(
                'STREAM READ EMPTY' .
                ' | File: ' . basename($requestedPath) .
                ' | BytesSent: ' . $streamBytesSent .
                ' | Remaining: ' . $remaining .
                ' | Feof: ' . (feof($handle) ? 'yes' : 'no') .
                ' | ConnectionStatus: ' . connection_status()
            );

            break;
        }

        $bytesThisChunk = strlen($buffer);

        echo $buffer;
        flush();

        $streamBytesSent +=
            $bytesThisChunk;

        $remaining -=
            $bytesThisChunk;

        $now = microtime(true);

        if (($now - $lastProgressLogAt) >= 60.0)
        {
            mediaHopStreamLog(
                'STREAM PROGRESS' .
                ' | File: ' . basename($requestedPath) .
                ' | BytesSent: ' . $streamBytesSent .
                ' | Remaining: ' . $remaining .
                ' | Elapsed: ' .
                    number_format(
                        $now - $streamStartedAt,
                        1,
                        '.',
                        ''
                    ) . 's' .
                ' | ConnectionStatus: ' . connection_status()
            );

            $lastProgressLogAt = $now;
        }

        if (connection_aborted())
        {
            $streamExitReason = 'connection_aborted';

            mediaHopStreamLog(
                'STREAM CLIENT ABORTED' .
                ' | File: ' . basename($requestedPath) .
                ' | BytesSent: ' . $streamBytesSent .
                ' | Remaining: ' . $remaining .
                ' | Elapsed: ' .
                    number_format(
                        microtime(true) - $streamStartedAt,
                        1,
                        '.',
                        ''
                    ) . 's' .
                ' | ConnectionStatus: ' . connection_status()
            );

            break;
        }
    }

    if ($remaining <= 0)
    {
        $streamExitReason = 'completed normally';
    }
    elseif (feof($handle) &&
            $streamExitReason === 'unknown')
    {
        $streamExitReason = 'local file reached EOF early';
    }
    elseif ($streamExitReason === 'unknown')
    {
        $streamExitReason = 'loop ended for unknown reason';
    }

    mediaHopStreamLog(
        'STREAM END' .
        ' | File: ' . basename($requestedPath) .
        ' | Reason: ' . $streamExitReason .
        ' | BytesSent: ' . $streamBytesSent .
        ' | Expected: ' . $length .
        ' | Remaining: ' . $remaining .
        ' | Elapsed: ' .
            number_format(
                microtime(true) - $streamStartedAt,
                1,
                '.',
                ''
            ) . 's' .
        ' | Feof: ' . (feof($handle) ? 'yes' : 'no') .
        ' | ConnectionStatus: ' . connection_status() .
        ' | ConnectionAborted: ' .
            (connection_aborted() ? 'yes' : 'no')
    );

    fclose($handle);

    // Leave this true until PHP's shutdown phase so the shutdown
    // diagnostic can report anything that happens after the normal loop.
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

    if (isHiddenMediaPath(
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
        // Keep direct playback / TV hand-off simple by putting the
        // already-validated token into the protected stream URL.
        $mediaUrl .=
            '&token=' .
            rawurlencode(
                $requestToken
            );
    }

    $result['items'][] = [
        'name' =>
            $file->getFilename(),

        // Relative path is used by the Unity app
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
