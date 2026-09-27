# MediaHop

**Last updated: 27 September 2026**

MediaHop is a lightweight Android media app designed to make it easy to play media from a remote server or local M3U playlist on either your phone or a compatible TV.

The goal is simple:

**Find something to play, choose where you want it to play, and switch between devices without starting over.**

MediaHop is intentionally lightweight. It does not require Plex, Jellyfin, Emby, Docker, a database, or server-side transcoding.

---

## Current Test Release

**MediaHop 0.1.22**

MediaHop is currently available as a public test build through the **Releases** section of this repository.

This is still a test release. Compatibility can vary between Android devices, media formats, IPTV sources, networks, and DLNA / UPnP TVs.

---

## Current Features

MediaHop currently supports:

- Browse media from a configured remote server
- Folder and nested-folder navigation
- Server-wide media search
- Open and browse local M3U playlists
- Search loaded M3U playlists
- Play media directly on Android through Media3 / ExoPlayer
- Stream server media to compatible TVs using DLNA / UPnP
- Stream M3U channels to compatible TVs through the MediaHop phone proxy
- Discover compatible TVs on the local network
- Automatically use a single discovered TV
- Select between multiple discovered TVs
- Phone / TV playback target selection
- Switch normal-file playback from Phone → TV
- Switch normal-file playback from TV → Phone
- Preserve playback position during normal-file Phone ↔ TV handoff
- Persistent playlist playback
- Tap any playlist item to start playback from that point
- Previous / Next controls
- Automatic playlist progression
- Recently Watched history for completed server media
- Tap Recently Watched items to replay them
- Main-screen TV playback controls
- TV playback position and duration display
- TV seek slider
- 30-second TV skip controls
- Play / Pause / Stop controls
- Fullscreen Android playback
- Seek and scrub controls
- 15-second phone-player skip controls
- Multiple aspect-ratio options
- Landscape phone playback in either landscape direction
- Background TV streaming
- Background TV availability detection
- Screen-off TV completion detection
- Limited automatic M3U reconnect handling
- Basic network-status display
- Optional protected server authentication
- HTTP Basic Authentication support
- MediaHop login/token authentication
- Stop all active playback
- Share the latest diagnostic log directly from Settings

---

## Phone and TV Playback

MediaHop supports both phone playback and compatible DLNA / UPnP TVs.

Typical playback paths are:

```text
Remote Server -> Android Phone -> Media3
Remote Server -> Android Phone Proxy -> Local Network -> DLNA TV

Local M3U -> Android Phone -> Media3
Local M3U -> Android Phone Proxy -> Local Network -> DLNA TV
```

Playback target selection is remembered during the current app session.

General behaviour:

```text
0 TVs found  -> Phone
1 TV found   -> MediaHop can use it automatically
2+ TVs found -> Choose the TV you want
```

Normal media files can move between Phone and TV while preserving playback position.

Live M3U streams do not use timestamp resume when switching targets.

---

## Multiple TVs

MediaHop supports homes with more than one compatible DLNA / UPnP TV.

If one compatible TV is discovered, MediaHop can use it automatically.

If multiple TVs are discovered, they can be selected from the **Active Device** area.

This lets different people choose the TV they actually want instead of MediaHop simply using whichever device responds first.

---

## M3U / IPTV Support

MediaHop can open local M3U playlists using the Android file picker.

Current M3U support includes:

- Local playlist selection
- Playlist validation
- Channel parsing
- Channel search
- Phone playback through Media3
- TV playback through the MediaHop phone proxy
- Sticky Phone / TV target selection
- Support for some oddly named downloaded M3U files
- Limited automatic reconnect after a fatal live-stream failure

Current reconnect behaviour is deliberately limited:

```text
Stream fails
-> retry same channel after about 1 second

Retry fails
-> retry same channel after about 2 seconds

Retry fails again
-> stop cleanly
-> keep selected TV
-> wait for the user to choose another channel
```

MediaHop does not endlessly reconnect to a dead source.

Live-TV reliability still depends heavily on the quality and compatibility of the M3U source.

---

## Phone ↔ TV Handoff

Normal server media can be moved between the Android phone and a compatible TV without restarting from the beginning.

### Phone → TV

MediaHop:

```text
Captures the current phone position
-> closes phone playback
-> starts TV playback
-> resumes near the captured position
```

### TV → Phone

MediaHop:

```text
Reads the TV position
-> stops TV playback
-> opens the same media on the phone
-> resumes near the previous TV position
```

Active playlists remain on the same item and continue on the newly selected target.

Live streams do not use timestamp resume.

---

## Playlist

Server media can be added to a persistent local playlist.

Playlist features include:

- Add and remove items
- Prevent duplicate entries
- Tap any item to start from that point
- Previous / Next controls
- Automatic progression
- Phone and TV playback
- Phone ↔ TV handoff
- Playback-position preservation for normal files
- Automatic cleanup after the final item finishes

---

## Recently Watched

MediaHop keeps a small rolling history of naturally completed server media.

- Stores up to 14 completed items
- Only adds media after natural playback completion
- Stopping or backing out early does not add an item
- Recently Watched items can be tapped to play them again
- Older entries automatically roll off the list
- TV completion detection can continue while the phone screen is off

---

## TV Playback Controls

When compatible TV playback is active, MediaHop can provide:

```text
Current Time    Seek Slider    Duration

Previous    Play / Pause    Stop    Next

-30 sec                          +30 sec
```

Seeking depends on TV compatibility.

Rapid `-30` or `+30` presses can be combined into a larger seek before being sent to the TV.

---

## Background TV Streaming

MediaHop uses an Android foreground service while TV streaming is active.

This allows TV playback to continue while:

- MediaHop is minimised
- Other phone apps are being used
- The phone receives normal interruptions such as calls

The streaming proxy still runs as part of the MediaHop app process.

Force-closing or killing MediaHop will eventually stop the proxy and TV playback.

---

## Network Status

The main screen includes a lightweight network-status indicator.

Examples:

```text
WI-FI - 5 GHz • ONLINE
WI-FI - 2.4 GHz • ONLINE
MOBILE - 5G • ONLINE
MOBILE - 4G • ONLINE
OFFLINE
```

This is intended as a quick indication of the phone's current connection state rather than a full network diagnostic tool.

---

## Server Setup

The Server browser uses the included MediaHop `media.php` helper.

MediaHop does not require a full media-server platform.

The helper can:

- Scan the configured media directory
- Return media listings as JSON
- Stream selected files
- Support HTTP Range requests
- Return appropriate MIME types
- Prevent path traversal outside the configured media directory
- Optionally provide selected-file metadata through `ffprobe`
- Support MediaHop authentication
- Work alongside HTTP Basic Authentication

Setup instructions and the current PHP file are available in:

```text
MediaHop_Server/
```

---

## Authentication

MediaHop supports two optional protection layers.

### MediaHop Login

MediaHop can use its own username/password login system with signed authentication tokens.

Current behaviour includes:

- Remembered username
- Passwords are not written to settings
- Tokens expire after 14 days
- Re-authentication when a token expires or is rejected
- Basic rate limiting against repeated failed login attempts

### HTTP Basic Authentication

MediaHop also supports servers protected by standard HTTP Basic Authentication.

The two systems can be used independently or together.

---

## Diagnostic Logs

MediaHop includes detailed playback diagnostics to help identify whether a problem came from:

- The media source
- The phone proxy
- The network
- Media3
- The TV / DLNA renderer
- Playback-state transitions

Sensitive remote URLs and authentication information are redacted from tested diagnostic logging.

### Send Diagnostic Log

The Settings page includes a **Send Diagnostic Log** button.

Pressing it:

```text
Finds the current MediaHop diagnostic log
-> attaches it automatically
-> opens the Android share sheet
-> lets the user choose Messenger, email, or another compatible app
```

The share message also includes the installed MediaHop version.

This makes it easier for testers to report playback problems without manually searching Android folders for the log file.

---

## Tested TVs

Primary development TV:

```text
Samsung UA32J5500
```

Also tested successfully:

```text
Hisense 50A7G
Hisense 32S4
Older DLNA TV
```

Different TV manufacturers implement DLNA / UPnP differently, so playback, seeking, physical-remote controls, and renderer behaviour may vary.

Additional external testing is ongoing.

---

## Media Compatibility

Phone playback depends on Android Media3 / ExoPlayer and the codecs supported by the Android device.

TV playback depends on the codecs and containers supported by the TV.

MediaHop does **not** transcode media.

That means a file can appear correctly in MediaHop but still fail to play if the selected device does not support its codec or container.

---

## Privacy

MediaHop diagnostic logging is designed to remain useful without exposing account credentials.

Current logging avoids writing tested:

- Passwords
- Authorization headers
- Bearer credentials
- Basic Authentication credentials
- MediaHop authentication tokens

Local network addresses and TV control information may still appear in diagnostic logs because they are useful when troubleshooting local playback.

---

## Current Status

MediaHop is still under active testing.

Current testing is focused mainly on:

- Longer real-world playback sessions
- M3U stability and recovery
- Phone and TV playback
- Phone ↔ TV handoff
- Screen-off playback completion
- TV seeking
- Background streaming
- Different Android devices
- Different networks
- Different DLNA / UPnP TVs

The project is deliberately staying small and focused.

MediaHop is intended to remain a lightweight way to:

**find media, choose a device, play it, switch if needed, and stop cleanly.**
