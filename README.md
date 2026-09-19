# MediaHop

MediaHop is an Android media app designed to make it easy to play media from a remote server or M3U playlist on either your phone or a compatible TV.

The goal is simple:

**Find something to play, choose where you want it to play, and switch between devices without starting over.**

MediaHop is intentionally lightweight. It does not require Plex, Jellyfin, Emby, Docker, a database, or server-side transcoding.

---

## Current Test Release

**MediaHop 0.1.15**

MediaHop is currently available as a public test build through the **Releases** section of this repository.

---

## Current Features

MediaHop currently supports:

- Browse media from a configured remote server
- Folder and nested-folder navigation
- Server-wide media search
- Open and browse local M3U playlists
- Play media directly on Android through Media3 / ExoPlayer
- Stream media to compatible TVs using DLNA / UPnP
- Discover compatible TVs on the local network
- Automatically use a single discovered TV
- Select between multiple discovered TVs
- Phone / TV playback target selection
- Switch playback from Phone → TV
- Switch playback from TV → Phone
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
- Play / Pause / Stop controls
- Fullscreen Android playback
- Seek and scrub controls
- 15-second skip controls
- Screen rotation support
- Multiple aspect-ratio options
- Background TV streaming
- Background TV availability detection
- Optional protected server authentication
- HTTP Basic Authentication support
- Stop all active playback

---

## Multiple TVs

MediaHop supports homes with more than one compatible DLNA / UPnP TV.

If one compatible TV is discovered, MediaHop can use it automatically.

If multiple TVs are discovered, they can be selected from the **Active Device** area.

This lets different people choose the TV they actually want instead of MediaHop simply using whichever device responds first.

---

## Recently Watched

MediaHop keeps a small rolling history of completed server media.

- Stores up to 14 completed items
- Only adds media after natural playback completion
- Stopping or backing out early does not add an item
- Recently Watched items can be tapped to play them again
- Older entries automatically roll off the list

---

## Server Setup

The Server browser uses the included MediaHop `media.php` helper.

Setup instructions and the current PHP file are available in the:

```text
MediaHop_Server/
