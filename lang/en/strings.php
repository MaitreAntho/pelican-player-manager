<?php

return [
    'title' => 'Players',
    'nobody' => 'Nobody is online',
    'count_only' => ':count players online',
    'count_only_help' => 'This game shares how many players are online, but not their names.',
    'no_output' => '(no answer)',
    'output' => 'Server answer',

    'columns' => ['name' => 'Player', 'id' => 'Id', 'score' => 'Score', 'time' => 'Time played', 'ping' => 'Ping'],

    'actions' => [
        'kick' => 'Kick', 'ban' => 'Ban', 'unban' => 'Unban', 'say' => 'Message all',
        'whitelist' => 'Add to whitelist', 'unwhitelist' => 'Remove from whitelist', 'op' => 'Make operator', 'deop' => 'Remove operator',
        'list' => 'List through the console', 'list_help' => 'Runs the game\'s own list command once, to see names this game does not share through its query.',
        'refresh' => 'Refresh', 'reason' => 'Reason (optional)', 'message' => 'Message', 'send' => 'Send',
        'command' => 'Run command', 'run' => 'Run',
        'command_rcon' => 'Sent to the game through its remote console. The answer is shown below the list.',
        'command_console' => 'Sent through the server console, as if typed in the Console page. The answer is shown below the list.',
        'unban_target' => 'Player', 'unban_help' => 'The name or id the player was banned with.',
        'ban_help' => 'The player is removed and cannot come back until unbanned.',
        'kick_heading' => 'Kick :name', 'ban_heading' => 'Ban :name', 'whitelist_heading' => 'Add :name to the whitelist',
        'unwhitelist_heading' => 'Remove :name from the whitelist', 'op_heading' => 'Make :name an operator', 'deop_heading' => 'Remove :name as operator',
    ],

    'done' => [
        'kick' => ':name was kicked', 'ban' => ':name was banned', 'unban' => ':name was unbanned', 'say' => 'Message sent',
        'whitelist' => ':name was added to the whitelist', 'unwhitelist' => ':name was removed from the whitelist',
        'op' => ':name is now an operator', 'deop' => ':name is no longer an operator',
        'via_console' => 'Sent through the server console.',
        'via_console_unreachable' => 'Sent through the server console: the remote console did not answer the panel.',
        'via_console_auth' => 'Sent through the server console: the remote console refused the password (check it in the settings).',
    ],

    'errors' => [
        'no_source_title' => 'No way to list players',
        'no_source' => 'This game has no query and no remote console set up. Set the remote console password in the settings (top right) to list players through it.',
        'auth_title' => 'Password refused',
        'auth' => 'The game refused the remote console password. Check it in the settings (top right).',
        'unreachable_title' => 'The server does not answer',
        'unreachable' => 'The server is stopped, still starting, or its query port is closed.',
        'action_failed' => 'The command could not be sent',
    ],

    'hints' => [
        'bedrock' => 'Recent Bedrock servers use transport=nethernet, which does not answer queries: set transport=raknet in server.properties to show the player count.',
        'minecraft' => 'Names of all players need enable-query=true in server.properties; otherwise up to 12 are shown.',
        'fivem' => 'Kick and messages go through the server console.',
        'palworld' => 'Palworld lists players through its RCON: set RCONEnabled=True and an admin password.',
        'battleye' => 'Kick, ban and messages use BattlEye RCon: set an RCon port and password.',
    ],

    'settings_title' => 'Player settings',
    'settings' => [
        'detected' => 'Detected',
        'game' => 'Game',
        'auto' => 'Automatic',
        'none' => 'Not a game server',
        'query_port' => 'Query port',
        'rcon_port' => 'Remote console port',
        'rcon_password' => 'Remote console password',
        'password_kept' => 'Saved (leave empty to keep)',
        'rcon_help' => 'Only needed when the plugin cannot read it from the startup variables or the config files. Stored encrypted.',
        'forget_password' => 'Forget the saved password',

        'host' => 'Query host',
        'host_help' => 'Address the panel uses to reach game servers. Empty: the allocation IP, or the node address when the allocation listens on 0.0.0.0.',
        'refresh' => 'Refresh every',
        'refresh_help' => 'How often the player list updates on the page.',
        'history' => 'Keep player history',
        'history_help' => 'Records who played and the daily peak, once a minute, through queries only (nothing is written to server consoles).',
        'retention' => 'Keep history for',
        'days' => 'days',
        'saved' => 'Settings saved',
    ],

    'describe' => [
        'query' => 'players through :protocol at :address',
        'no_query' => 'no query protocol',
        'rcon' => 'commands through :protocol at :address',
        'console' => 'commands through the server console',
    ],

    'protocols' => [
        'a2s' => 'Steam query', 'minecraft' => 'Minecraft query', 'bedrock' => 'Bedrock ping', 'fivem' => 'FiveM API',
        'quake3' => 'Quake 3 protocol', 'teeworlds' => 'Teeworlds query', 'satisfactory' => 'Satisfactory API', 'samp' => 'SA-MP query',
        'ase' => 'ASE query', 'assetto' => 'Assetto Corsa API', 'source' => 'RCON', 'goldsrc' => 'GoldSrc RCON', 'telnet' => 'telnet',
        'webrcon' => 'WebRCON', 'battleye' => 'BattlEye RCon', 'tshock' => 'TShock REST', 'palworld' => 'Palworld REST', 'console' => 'console',
    ],

    'summary' => [
        'players' => ':count player online|:count players online',
        'players_max' => ':count / :max players',
        'map' => 'map :map',
        'via' => 'through :protocol',
        'peak_today' => 'peak today: :count',
    ],

    'history' => [
        'peaks' => 'Daily peak',
        'peaks_help' => 'Most players online at once, last 14 days.',
        'today' => 'today',
        'recent' => 'Recently seen',
        'recent_help' => 'Last players seen on this server.',
        'online' => 'online now',
        'empty' => 'Nobody seen yet. The history fills in as players join.',
    ],

    'permissions' => [
        'players_title' => 'Players',
        'players_desc' => 'Permissions for the Players page: the list of connected players and the actions on them.',
        'players_read' => 'See who is online and the player history.',
        'players_manage' => 'Kick, ban, unban, message, whitelist and op players.',
        'players_command' => 'Run any command on the game server through the Players page.',
        'players_configure' => 'Change the game, ports and remote console password used by the Players page.',
    ],

    'admin' => [
        'title' => 'Player Manager',
        'subheading' => 'The game recognised for each egg. Players pages appear on the servers of recognised eggs.',
        'egg' => 'Egg', 'servers' => 'Servers', 'game' => 'Game', 'not_supported' => 'Not supported', 'manual' => 'Set by hand',
        'players' => 'Player list', 'actions' => 'Actions', 'filter' => 'Eggs', 'filter_used' => 'With servers', 'change' => 'Change the game',
    ],
];
