# MediaHop

**Last updated: 24 October 2026**

MediaHop is a lightweight Android media app designed to make it easy to play media from a remote server or local M3U playlist on either your phone or a compatible TV.

The goal is simple:

**Find something to play, choose where you want it to play, and switch between devices without starting over.**

MediaHop is intentionally lightweight. It does not require Plex, Jellyfin, Emby, Docker, a database, or server-side transcoding.

---

## Current Test Release

**MediaHop 0.1.23**

MediaHop 0.1.23 is the current recommended public test build and is available through the **Releases** section of this repository.

This release is focused heavily on playback stability, especially TV playlist playback while the Android phone is asleep.

Testing of 0.1.23 has shown substantially improved playback reliability. The recurring cut-outs seen during 0.1.22 testing have not reproduced in the established 0.1.23 long-session tests.

Compatibility can still vary between Android devices, media formats, IPTV sources, networks, and DLNA / UPnP TVs.

---

## What's Improved in 0.1.23

### Screen-Off TV Playlist Playback

TV playlists can now continue through multiple items while the Android phone remains asleep.

The background playback path can:

```text
Current item finishes naturally
-> confirm the TV really reached the end
-> record Recently Watched once
-> advance to the next playlist item
-> start the next TV item
-> continue while the phone remains asleep
```

The final playlist item can also finish while the phone is asleep.

When that happens MediaHop can complete the playlist cleanup in the background, stop the relay/service normally, and reconcile the visible playlist, history, and playback UI when Unity wakes again.

Background playlist transitions retain source-generation and session guards so stale work cannot double-advance the playlist or take control after a newer Stop, handoff, source change, or resume action.

### TV Seek Accuracy

TV seeking received additional timing cleanup.

A real issue was found where the requested seek target could be correct in the UI but the TV would resume several seconds beyond it because playback kept advancing while the renderer processed the seek.

For a seek issued while the TV is already playing, MediaHop can now use:

```text
Pause
-> Seek
-> Play
```

This prevents the renderer from continuing to accumulate playback time during the seek operation.

If Pause is not supported by the current renderer/state, MediaHop falls back to the existing direct-seek path.

A seek issued while the TV is already paused remains paused.

Exact seek behaviour can still vary between TV brands and models.

### Playback Stability

0.1.23 has completed repeated real-world testing including:

- Long TV playback sessions
- Screen-off multi-episode playlist playback
- Final-item completion while the phone remains asleep
- Waking the phone during later playlist playback
- Recently Watched reconciliation after background completion
- Repeated TV seeking
- Normal Server playback
- M3U / IPTV playback
- Phone ↔ TV handoff
- Background streaming while using other phone apps

The current 0.1.23 build is the playback baseline for ongoing public testing.

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
- Screen-off TV playlist progression through multiple items
- Background final-item cleanup
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
- MediaHop login/token authentication
- TV `Ready to play` idle screen
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

Playback target selection is sticky across the main playback paths.

General behaviour:

```text
0 TVs found  -> Phone
1 TV found   -> MediaHop can use it automatically
2+ TVs found -> Phone remains selected until you choose a TV
```

The selected TV remains remembered through Stop and failed playback.

Normal media files can move between Phone and TV while preserving playback position.

Live M3U streams do not use timestamp resume when switching targets.

---

## TV Ready Screen

When MediaHop has a selected TV but no real media source is active, it can show a simple idle screen on the TV:

```text
MediaHop logo
Ready to play
```

Current behaviour is intentionally narrow:

```text
First TV connection   -> Ready to play screen
Start real media      -> idle screen disappears
Playlist transition   -> no idle screen inserted
Phone / TV handoff    -> no idle screen inserted
Automatic retry       -> no idle screen inserted
Stop Everything       -> Ready to play screen returns
Start another file    -> idle screen disappears
```

The idle screen uses a lightweight path and does not start the normal Android streaming foreground service just to keep the image visible.

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
-> attempts to resume near the captured position
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
- Screen-off TV auto-advance through multiple items
- Background-safe natural-end detection
- Final-item cleanup while the phone remains asleep
- Wake-time UI/history reconciliation

A completed proxy transfer alone is not treated as proof that the TV finished watching the file.

For TV playback MediaHop looks for real renderer end evidence before marking an item naturally completed.

---

## Recently Watched

MediaHop keeps a small rolling history of naturally completed server media.

- Stores up to 14 completed items
- Only adds media after natural playback completion
- Stopping or backing out early does not add an item
- Recently Watched items can be tapped to play them again
- Older entries automatically roll off the list
- TV completion detection can continue while the phone screen is off
- Screen-off playlist completions are reconciled correctly when the app wakes
- Completed items are protected against duplicate background recording

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

For supported playing states, 0.1.23 can use **Pause → Seek → Play** to reduce seek drift while the renderer processes the command.

If Pause is unavailable, MediaHop falls back to direct seeking.

---

## Background TV Streaming

MediaHop uses an Android foreground service while TV streaming is active.

This allows TV playback to continue while:

- MediaHop is minimised
- Other phone apps are being used
- The phone receives normal interruptions such as calls
- The phone screen is off

The streaming proxy still runs as part of the MediaHop app process.

Force-closing or killing MediaHop will eventually stop the proxy and TV playback.

Some Android manufacturers use aggressive battery-management features that can interfere with background networking. If a phone is configured to place MediaHop into a deep-sleep or similarly restricted mode, Android itself may stop the network connection.

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

Setup instructions and the current PHP file are available in:

```text
MediaHop_Server/
```

---

## Authentication

MediaHop 0.1.23 supports its own optional username/password login system with signed authentication tokens.

Current behaviour includes:

- Remembered username
- Passwords are not written to the normal settings file
- Tokens expire after 14 days
- Re-authentication when a token expires or is rejected
- Basic rate limiting against repeated failed login attempts
- Protected server listing
- Protected Phone playback
- Protected TV playback through the phone proxy
- Authentication through normal Phone ↔ TV handoff


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
- MediaHop authentication tokens

Local network addresses and TV control information may still appear in diagnostic logs because they are useful when troubleshooting local playback.

---

## Testing Status

MediaHop 0.1.23 is the current recommended public test build and the playback baseline for ongoing testing.

Current testing has covered:

- Longer real-world playback sessions
- Server playback to Phone and TV
- M3U playback to Phone and TV
- Phone ↔ TV handoff
- Screen-off TV playlist progression
- Final-item completion while the phone remains asleep
- Wake-time playlist/history/UI reconciliation
- Recently Watched natural completion
- TV seeking
- Background streaming
- Different Android connection states
- Multiple TV models
- Multiple DLNA / UPnP implementations

Repeated long screen-off TV runs, including multi-episode playlist sessions, have completed without the recurring cut-outs seen during 0.1.22 testing.

The project is deliberately staying small and focused.

MediaHop is intended to remain a lightweight way to:

**find media, choose a device, play it, switch if needed, and stop cleanly.**

---

## Reporting Problems

If something does not work as expected, please report it through the **Issues** section of this repository or send the diagnostic log if you are testing directly with the developer.

Useful information includes:

- Phone model
- Android version
- TV brand and model if relevant
- What you were trying to play
- Whether playback was on Phone or TV
- What happened
- What you expected to happen
- Whether the problem happens every time

Screenshots are also useful when relevant.

The **Send Diagnostic Log** button in Settings can attach the current MediaHop diagnostic log directly through the Android share menu.

---

## Installation

Download the **MediaHop 0.1.23 APK** attached to the current release.

Android may warn that the app is being installed from an external source because MediaHop is not currently distributed through the Google Play Store.

Only install builds downloaded directly from the official MediaHop repository.
