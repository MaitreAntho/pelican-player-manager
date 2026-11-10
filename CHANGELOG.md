# Changelog

## 1.0.1 - 2026-11-10

- **Fixed**: setting a server's game to "Not a game server" hid the Players page, and with it the settings, so there was no way to turn it back on. The page now stays available to users who can configure it, with a "turned off" notice and the settings button. Other users still don't see it.

## 1.0.0 - 2026-11-09

First release.

- **Players page** on every game server: who is online, with score, time played and ping when the game shares them, refreshed every 15 seconds.
- **Actions**: kick, ban, unban, message all, whitelist and operators (Minecraft), and any command, with the server's answer shown under the list.
- **No setup for most games**: players are listed through the game's own query protocol; actions go through the server's remote console when one is set up, else through the Pelican console.
- **100+ games** recognised from their egg, with 17 protocols: Steam (A2S), Source RCON, GoldSrc RCON, Minecraft query and ping, Bedrock ping, Rust WebRCON, BattlEye RCon, telnet, Palworld REST, TShock REST, FiveM, Quake 3, Teeworlds, Satisfactory, SA-MP / open.mp, MTA (ASE) and Assetto Corsa.
- **History**: daily peak over 14 days and recently seen players, collected every minute through queries only.
- **Permissions**: four subuser permissions (see, manage, run commands, configure) and an admin page to check or correct the game of each egg.
- **Per-server settings**: game, query port, remote console port and password (stored encrypted).
- English and French.
