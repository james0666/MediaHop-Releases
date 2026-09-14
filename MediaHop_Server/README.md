# MediaHop Basic Media Server

This is the simple server-side helper for MediaHop.

It lets MediaHop:

- Read a list of media files from your server.
- Rebuild your folder structure inside the app.
- Stream media through the phone to a DLNA/UPnP TV.
- Handle HTTP `HEAD` and byte-range requests used during streaming and seeking.

The basic setup is designed to require as little configuration as possible.

---

## Requirements

You need:

- A web server with PHP enabled.
- Media files stored somewhere the PHP file can read.
- A public HTTP or HTTPS URL that your phone can access.

No Plex, Jellyfin, database, Docker container, or separate media server application is required.

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

The folder containing `media.php` becomes the media root automatically.

Subfolders are scanned recursively.

---

## 2. Open `media.php` in a browser

For example:

```text
https://example.com/media/media.php
```

If everything is working, you should see JSON containing your media files.

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

If you can see the media list, the PHP side is working.

---

## 3. Use the `media.php` URL in MediaHop

Use the full public URL to the PHP file as the MediaHop server/API address.

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

# Advanced Setup

The basic version expects `media.php` to live directly inside the media folder.

If you want to keep the PHP file somewhere else, change the media directory near the top of the file.

The basic version uses:

```php
$mediaDirectory = __DIR__;
```

You can replace it with an absolute path:

```php
$mediaDirectory = '/home/example/media';
```

Or a relative path.

For example, if your layout is:

```text
data/
├── Movies/
├── TV Shows/
└── api/
    └── media.php
```

you can use:

```php
$mediaDirectory = dirname(__DIR__);
```

That makes the parent `data` folder the media root.

This is the same style of setup used for servers where the API/helper files are kept separate from the media itself.

---

# Supported Media Extensions

The supplied basic `media.php` currently lists:

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

Adding an extension only makes the file visible to the API. The TV still needs to support the actual codec/container being played.

---

# Folder Support

MediaHop receives both the filename and the relative path.

For example:

```text
TV Shows/Example Show/Season 01/Episode 01.mkv
```

This allows the app to rebuild the same folder structure instead of showing every file in one giant list.

---

# Streaming and Seeking

`media.php` supports:

- Normal HTTP `GET` requests.
- HTTP `HEAD` requests.
- HTTP byte-range requests.
- `200 OK` responses.
- `206 Partial Content` responses.
- `Content-Range`.
- `Content-Length`.
- `Accept-Ranges: bytes`.

These are important because TVs often request only parts of a media file while starting playback or seeking.

Actual seek support still depends on the TV, file type, codec, and DLNA implementation.

---

# Security

Only put `media.php` inside a folder containing media you are comfortable exposing through this endpoint.

The script includes path checks to stop requests from escaping outside the configured media root, but the supported media files inside that root are intentionally available through the API.

Do not place passwords, private documents, backups, API keys, or other sensitive files inside the media root.

The basic version does not include authentication.

If your server is exposed to the public internet, consider adding authentication or other access controls before treating it as private storage.

HTTPS is recommended when available.

---

# Troubleshooting

## I only see an empty list

Check that:

- Your media files use one of the supported extensions.
- PHP has permission to read the media folder.
- The configured media directory is correct.
- The files are actually inside the configured media root.

## `Media directory does not exist`

The configured `$mediaDirectory` is wrong or PHP cannot access it.

For the basic version, this should normally be:

```php
$mediaDirectory = __DIR__;
```

## A file appears but will not play

That does not necessarily mean `media.php` is broken.

Possible causes include:

- The TV does not support that container.
- The TV does not support the video codec.
- The TV does not support the audio codec.
- The server blocks or alters range requests.
- The TV has manufacturer-specific DLNA behaviour.

Try a simple H.264/AAC MP4 as a compatibility test.

## `403 Forbidden`

The requested path failed the media-root security check.

Make sure the file is physically inside the configured media root.

## `404 File not found`

The file path no longer exists, was renamed, or PHP cannot resolve it.

Reload the media list and try again.

---

# Recommended First Test

For the first setup:

1. Put one small MP4 file in the same folder as `media.php`.
2. Open `media.php` in a browser.
3. Confirm the MP4 appears in the JSON list.
4. Use the PHP URL in MediaHop.
5. Try the MP4 before adding a large media library.

This makes it much easier to tell whether a problem is server setup, MediaHop, or TV compatibility.

---

# Basic vs Advanced

## Basic

Use this when you want the easiest possible setup:

```text
Media/
├── Movies/
├── TV Shows/
└── media.php
```

No media path editing required.

## Advanced

Use this when your server has a custom layout:

```text
media/
api/
    media.php
```

Configure `$mediaDirectory` to point at the actual media folder.

---

# MediaHop

`media.php` is only the bridge between MediaHop and your remote media files.

TV discovery and DLNA playback are handled by the MediaHop app.

The PHP file does not need to discover or communicate with the TV itself.
