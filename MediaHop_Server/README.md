# MediaHop Basic Media Server

This is the lightweight server-side helper used by MediaHop.

It lets MediaHop:

- Read a list of media files from your server.
- Rebuild your folder structure inside the app.
- Stream media through the phone to a DLNA / UPnP TV.
- Handle HTTP `GET`, `HEAD`, and byte-range requests used during playback and seeking.
- Optionally protect the MediaHop endpoint with a username/password login.
- Optionally return media information for the selected TV file when `ffprobe` is available.
- Hide unwanted folders from the MediaHop library without moving or deleting them.

The setup is intentionally small. No Plex, Jellyfin, Emby, Docker container, database, or server-side transcoding is required.

---

# Requirements

You need:

- A web server with PHP enabled.
- Media files stored somewhere PHP can read.
- An HTTP or HTTPS URL that your phone can access.
- MediaHop installed on your Android device.

Optional:

- `ffprobe` if you want MediaHop to request codec, resolution, and duration metadata for a file before sending it to a TV.
- HTTP Basic Authentication configured by your web host if you want another protection layer in front of `media.php`.

`ffprobe` is not required for normal browsing or playback.

---

# Basic Setup

## 1. Put `media.php` in your media folder

Place `media.php` directly inside the folder containing the media you want MediaHop to access.

Example:

```text
Media/
├── Movies/
│   ├── Movie One.mkv
│   └── Movie Two.mp4
├── TV Shows/
│   └── Example Show/
│       └── Episode 01.mkv
├── Videos/
└── media.php
```

By default the supplied file uses:

```php
$mediaDirectory = __DIR__;
```

That means the folder containing `media.php` becomes the media root automatically.

Subfolders are scanned recursively.

---

## 2. Open `media.php` in a browser

For example:

```text
https://example.com/media/media.php
```

With MediaHop authentication disabled, you should see JSON containing your media files.

Example:

```json
{
    "items": [
        {
            "name": "Movie One.mkv",
            "path": "Movies/Movie One.mkv",
            "url": "https://example.com/media/media.php?file=Movies%2FMovie%20One.mkv"
        }
    ]
}
```

If the media list appears, the basic PHP setup is working.

If you enable MediaHop authentication later, opening the URL directly without a valid token will instead return an authentication error. That is expected.

---

## 3. Use the `media.php` URL in MediaHop

Use the full HTTP or HTTPS URL to the PHP file as the MediaHop server/API address.

Example:

```text
https://example.com/media/media.php
```

Do not enter the filesystem path from the server.

Wrong:

```text
/home/user/media/media.php
```

Correct:

```text
https://example.com/media/media.php
```

---

# MediaHop Login

The supplied `media.php` includes optional MediaHop username/password authentication.

It is disabled by default:

```php
$authEnabled = false;
```

For a normal open server, leave it that way.

To enable MediaHop login:

1. Change the username.
2. Change the password.
3. Change the token secret to a long random value.
4. Set `$authEnabled` to `true`.

Example:

```php
$authEnabled = true;

$authUsername = 'your_username';
$authPassword = 'your_password';
$authTokenSecret = 'your_long_random_secret';
```

Do not leave the supplied `CHANGE_ME` placeholders in place when authentication is enabled.

When MediaHop login is enabled:

- MediaHop signs in using the username/password you configured.
- `media.php` returns a signed session token.
- MediaHop reuses that token for server browsing and playback.
- Protected media URLs include the validated token when required.
- Changing the configured username, password, or token secret invalidates old tokens.
- Tokens expire automatically.
- Repeated failed login attempts can temporarily lock further login attempts.

The default token lifetime is 14 days:

```php
$authTokenLifetimeSeconds = 14 * 24 * 60 * 60;
```

You can change that if required.

---

# Login Rate Limiting

The supplied `media.php` includes lightweight rate limiting for MediaHop's own login endpoint.

Default settings:

```php
$authRateLimitEnabled = true;
$authRateLimitMaxFailures = 5;
$authRateLimitWindowSeconds = 10 * 60;
$authRateLimitLockoutSeconds = 15 * 60;
```

With the default settings:

- Five failed login attempts within 10 minutes can trigger a lockout.
- The lockout lasts for 15 minutes.
- Correct credentials are also rejected while the lockout is active.
- A successful login clears the stored failed-login state.
- The response includes a `Retry-After` value when rate limited.

Rate limiting applies only to MediaHop's own `?action=login` endpoint.

Attempts are tracked using the connecting address reported by the web server.

The supplied implementation does not trust `X-Forwarded-For` by default.

---

# HTTP Basic Authentication

HTTP Basic Authentication is separate from MediaHop's own login system.

If your host, reverse proxy, `.htaccess`, control panel, or web server already protects the `media.php` URL with HTTP Basic Authentication, configure that protection on the server as normal.

MediaHop can work with:

```text
No authentication
MediaHop login only
HTTP Basic Authentication only
HTTP Basic Authentication + MediaHop login
```

When both are enabled, the two authentication layers remain separate.

The PHP file itself does not create or manage HTTP Basic users.

---

# Folder Exclusions

You can hide folders from the MediaHop library without moving or deleting them.

The supplied file contains:

```php
$excludedFolders = [
    'Sample'
];
```

This hides any folder named `Sample`, anywhere inside the media library.

Matching is case-insensitive, so these are all treated the same:

```text
Sample
sample
SAMPLE
```

To exclude more folders, add them to the array:

```php
$excludedFolders = [
    'Sample',
    'Trailers',
    'Private Videos'
];
```

The `api` helper folder is also hidden from the returned media list.

Folder exclusions are intended for library organisation.

Do not use folder exclusions as your only security measure for sensitive files. Keep private documents, passwords, backups, keys, and unrelated data outside the media root.

---

# Advanced Media Path Setup

The basic version expects `media.php` to live directly inside the media folder.

If you want to keep the PHP file somewhere else, change the media directory near the top of the file.

The default is:

```php
$mediaDirectory = __DIR__;
```

You can replace it with an absolute path:

```php
$mediaDirectory = '/home/example/media';
```

Or use a relative path.

For example:

```text
data/
├── Movies/
├── TV Shows/
└── api/
    └── media.php
```

You could use:

```php
$mediaDirectory = dirname(__DIR__);
```

That makes the parent `data` folder the media root.

---

# Supported Media Extensions

The supplied `media.php` currently lists:

```text
mp4
m4v
mkv
avi
mov
webm
ts
m2ts
mpg
mpeg
m3u8
```

You can add or remove extensions in:

```php
$allowedExtensions = [
    ...
];
```

Adding an extension only makes the file visible to MediaHop.

It does not add codec support or transcode the file.

Actual playback still depends on:

- The Android player when playing on Phone.
- The TV when playing through DLNA / UPnP.
- The codecs and container used by the file.

---

# Folder Support

MediaHop receives both the filename and the relative path.

For example:

```text
TV Shows/Example Show/Season 01/Episode 01.mkv
```

This lets the app rebuild the same folder structure rather than showing every media file in one giant list.

---

# Streaming and Seeking

`media.php` supports:

- Normal HTTP `GET` requests.
- HTTP `HEAD` requests.
- HTTP byte-range requests.
- `200 OK`.
- `206 Partial Content`.
- `Content-Range`.
- `Content-Length`.
- `Accept-Ranges: bytes`.
- Progressive file streaming.
- Long-running media requests.
- Output-buffering cleanup for media streaming.

These features are important because TVs and media players often request only part of a file when starting playback, seeking, or resuming.

The helper does not transcode media.

Actual seek support still depends on the TV, file type, codec, and DLNA implementation.

---

# Optional TV Media Metadata

MediaHop can request information about the single selected server file before sending it to a TV.

When available, the endpoint can return:

- Video codec.
- Audio codec.
- Width.
- Height.
- File size.
- Duration.
- Whether `ffprobe` was available.

Duration metadata can be used by MediaHop when working with compatible TV time-seek behaviour.

The server does not scan the entire library with `ffprobe`.

Only the requested file is checked.

If `ffprobe` is not installed, normal MediaHop browsing and playback still work.

HLS `.m3u8` files are not probed.

---

# Stream Diagnostics

Server-side stream diagnostic logging is disabled in the supplied public helper.

The PHP file keeps the diagnostic hook in place internally, but it does not create a MediaHop stream-debug log or write those stream messages to PHP's normal error log.

Playback troubleshooting is primarily handled through the MediaHop Android diagnostic log.

---

# Security

The script includes path checks designed to stop requests from escaping outside the configured media root.

For additional protection you can use:

- MediaHop's optional username/password login.
- Login rate limiting.
- HTTP Basic Authentication provided by your web server.
- HTTPS.

Important:

- Keep sensitive files outside the media root.
- Do not publish real usernames, passwords, or token secrets in a public repository.
- Change all authentication placeholders before enabling MediaHop login.
- HTTPS is strongly recommended when accessing the server over the public internet.

If authentication is disabled, supported media files inside the configured root are intentionally accessible through the endpoint.

---

# No Server-Side Transcoding

MediaHop's PHP helper does not transcode media.

The server sends the original media file.

Playback compatibility depends on the device receiving it.

This keeps the server setup lightweight and avoids requiring a dedicated media-server application.

---

# Troubleshooting

## I only see an empty list

Check that:

- Your files use one of the supported extensions.
- PHP has permission to read the media folder.
- `$mediaDirectory` points to the correct location.
- The files are inside the configured media root.
- The folder has not been added to `$excludedFolders`.

---

## `Media directory does not exist`

The configured `$mediaDirectory` is wrong or PHP cannot access it.

For the basic setup this should normally be:

```php
$mediaDirectory = __DIR__;
```

---

## `Authentication required`

MediaHop authentication is enabled.

Make sure:

- You configured the username/password/token secret in `media.php`.
- You entered the same username/password in MediaHop.
- You are using the correct server URL.

---

## `Session expired or invalid`

The stored MediaHop token is no longer valid.

This can happen if:

- The token expired.
- You changed the MediaHop username.
- You changed the MediaHop password.
- You changed the token secret.

Sign in again through MediaHop.

---

## `Too many failed login attempts`

The login rate limiter has temporarily blocked new login attempts.

With the default settings:

```text
5 failed attempts within 10 minutes
-> 15 minute lockout
```

Wait for the lockout to expire and try again.

Correct credentials will not bypass an active lockout.

---

## HTTP Basic login keeps appearing

HTTP Basic Authentication is controlled by your web server, not by the MediaHop token settings in `media.php`.

Check the username/password configured by your host, `.htaccess`, reverse proxy, or server control panel.

---

## A file appears but will not play

That does not necessarily mean `media.php` is broken.

Possible causes include:

- The TV does not support that container.
- The TV does not support the video codec.
- The TV does not support the audio codec.
- The Android device does not support the media format.
- The server or reverse proxy blocks or alters range requests.
- The TV has manufacturer-specific DLNA behaviour.

A simple H.264/AAC MP4 is a useful compatibility test.

---

## Seeking does not work

Check that:

- The server allows HTTP Range requests.
- The file itself supports practical seeking.
- The TV supports seeking for that media type.
- A reverse proxy is not stripping Range headers.
- `ffprobe` metadata is available if the TV path requires duration information for time-based seeking.

---

## `403 Forbidden`

The requested path failed the media-root security check.

Make sure the requested file is physically inside the configured media root.

---

## `404 File not found`

The file may:

- No longer exist.
- Have been renamed.
- Be inside an excluded folder.
- Be inaccessible to PHP.

Reload the media list and try again.

---

## TV metadata is empty

That is not necessarily an error.

`ffprobe` is optional.

If it is unavailable, MediaHop can still browse and play media normally.

---

# Recommended First Test

For the first setup:

1. Put one small H.264/AAC MP4 file in the same folder as `media.php`.
2. Leave `$authEnabled = false`.
3. Open `media.php` in a browser.
4. Confirm the MP4 appears in the JSON list.
5. Enter the `media.php` URL in MediaHop.
6. Test playback on Phone.
7. Test playback on a compatible TV.

Once that works, add the rest of your library.

If you want authentication, enable it only after the basic server is working.

That makes it much easier to tell whether a problem is caused by:

```text
Server setup
Authentication
MediaHop
Network
TV compatibility
```

---

# Basic vs Advanced

## Basic

Use this when you want the easiest setup:

```text
Media/
├── Movies/
├── TV Shows/
└── media.php
```

Keep:

```php
$mediaDirectory = __DIR__;
$authEnabled = false;
```

No media-path editing or login setup is required.

---

## Advanced

Use this when you want one or more of the following:

- A custom media-directory path.
- MediaHop username/password authentication.
- Login rate limiting.
- HTTP Basic Authentication.
- Custom folder exclusions.
- Optional `ffprobe` metadata.

Example custom layout:

```text
data/
├── Movies/
├── TV Shows/
└── api/
    └── media.php
```

Then configure `$mediaDirectory` to point at the real media root.

---

# MediaHop

`media.php` is the bridge between MediaHop and your remote media files.

The PHP helper:

- Lists media.
- Streams media.
- Handles byte ranges.
- Optionally protects the MediaHop endpoint.
- Applies login rate limiting.
- Optionally returns selected-file metadata including duration.
- Applies configured library exclusions.

TV discovery and DLNA / UPnP control are handled by the MediaHop Android app.

The PHP file does not discover or communicate with TVs itself.
