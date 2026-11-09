<?php

namespace MaitreAntho\PlayerManager\Games;

/**
 * Every game the plugin knows, and how to talk to it.
 *
 * Each entry says how to recognise the egg, how to list players without touching
 * the console (a query protocol), how to run admin commands directly when the game
 * has a remote console (RCON, telnet, BattlEye...), the command that prints the
 * player list with ids, and the command templates of each action.
 *
 * Without direct access, actions go through the Pelican console: most eggs pass
 * console input to the game (or bridge it to the game's RCON themselves).
 *
 * Port and password specs are lists of sources tried in order, separated by "|":
 *   var:NAME            a startup variable of the server
 *   game, game+N        the primary allocation port (plus N)
 *   prop:FILE:KEY       a key of a .properties file (Minecraft)
 *   ini:FILE:KEY        a key of an .ini file (Project Zomboid, Unreal games)
 *   cfg:FILE:KEY        a Source/Quake cfg setting (rcon_password "secret")
 *   xml:FILE:NAME       a <property name="NAME" value="..."/> (7 Days to Die)
 *   json:FILE:a.b.c     a value inside a JSON file
 *   a number            a fixed default
 * FILE may use {VAR} placeholders, replaced by startup variables.
 *
 * Templates use {target} (the player's id when known, else the name), {id},
 * {name}, {reason} and {message}.
 */
final class Catalog
{
    public const ACTIONS = ['kick', 'ban', 'unban', 'say', 'whitelist', 'unwhitelist', 'op', 'deop'];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $source = [
            'query' => ['a2s', 'var:QUERY_PORT|game'],
            'rcon' => ['source', 'var:RCON_PORT|game', 'var:RCON_PASSWORD|var:RCON_PASS|var:RCON|startup:+rcon_password|cfg:{GAME_DIR}/cfg/server.cfg:rcon_password'],
            'list' => ['status', 'source_status'],
            'actions' => [
                'kick' => 'kickid "{target}" "{reason}"',
                'ban' => 'banid 0 "{target}" kick; writeid',
                'unban' => 'removeid "{id}"; writeid',
                'say' => 'say {message}',
            ],
        ];

        $minecraft = [
            'query' => ['minecraft', 'prop:server.properties:query.port|game'],
            'rcon' => ['source', 'prop:server.properties:rcon.port', 'prop:server.properties:rcon.password', 'prop:server.properties:enable-rcon'],
            'list' => ['list', 'minecraft_list'],
            'actions' => [
                'kick' => 'kick {name} {reason}',
                'ban' => 'ban {name} {reason}',
                'unban' => 'pardon {name}',
                'say' => 'say {message}',
                'whitelist' => 'whitelist add {name}',
                'unwhitelist' => 'whitelist remove {name}',
                'op' => 'op {name}',
                'deop' => 'deop {name}',
            ],
            'by_name' => true,
            'hint' => 'minecraft',
        ];

        $quake = [
            'query' => ['quake3', 'game'],
            'rcon' => ['quake3', 'game', 'var:RCON_PASSWORD|var:RCON|startup:+set rcon_password|startup:+rcon_password|cfg:server.cfg:rcon_password'],
            'actions' => ['kick' => 'kick {name}', 'say' => 'say "{message}"'],
            'by_name' => true,
        ];

        $steam = ['query' => ['a2s', 'var:QUERY_PORT|var:SERVER_QUERY_PORT|var:QPORT|var:STEAM_QUERY_PORT|game|game+1']];

        return [
            // ------------------------------------------------------------- Minecraft
            'minecraft' => ['label' => 'Minecraft: Java Edition', 'match' => '/\b(paper|purpur|spigot|bukkit|vanilla|fabric|forge|neoforge|spongeforge|spongevanilla|quilt|mohist|magma|ketting|folia|sponge|cuberite|glowstone|krypton|curseforge|modrinth|feather|technic|ftb|vanillacord|tekkit|hexxit|blightfall|b-?team)\b|minecraft/i', 'exclude' => '/bedrock|nukkit|pocketmine|velocity|bungee|waterfall|travertine|waterdog|viaproxy|viaaas|limbo|reforger|arma/i'] + $minecraft,
            'minecraft-proxy' => ['label' => 'Minecraft proxy (Velocity, BungeeCord)', 'match' => '/velocity|bungee|waterfall|travertine|waterdog(?!.*pe)|viaproxy|viaaas|limbo|^gate\b/i', 'query' => ['minecraft', 'game'], 'actions' => [], 'by_name' => true],
            'minecraft-bedrock' => [
                'label' => 'Minecraft: Bedrock Edition', 'match' => '/bedrock|nukkit|pocketmine|liteloader|gomint/i',
                'query' => ['bedrock', 'prop:server.properties:server-port|game'],
                'list' => ['list', 'minecraft_list'],
                'actions' => ['kick' => 'kick {name} {reason}', 'say' => 'say {message}', 'whitelist' => 'allowlist add {name}', 'unwhitelist' => 'allowlist remove {name}', 'op' => 'op {name}', 'deop' => 'deop {name}'],
                'by_name' => true,
                'hint' => 'bedrock',
            ],

            // --------------------------------------------------------------- FiveM
            'fivem' => [
                'label' => 'FiveM / RedM', 'match' => '/fivem|redm|fxserver|cfx/i',
                'query' => ['fivem', 'game'],
                'rcon' => ['quake3', 'game', 'cfg:server.cfg:rcon_password|cfg:{TXDATA}/server.cfg:rcon_password'],
                'actions' => ['kick' => 'clientkick {id} {reason}', 'say' => 'say {message}'],
                'hint' => 'fivem',
            ],

            // ------------------------------------------------------------ Source / GoldSrc
            'gmod' => ['label' => "Garry's Mod", 'match' => '/garry|gmod/i', 'game_dir' => 'garrysmod'] + $source,
            'cs2' => ['label' => 'Counter-Strike 2', 'match' => '/counter.?strike 2|\bcs2\b/i', 'game_dir' => 'game/csgo'] + array_replace($source, ['actions' => ['kick' => 'kickid {target} "{reason}"', 'say' => 'say {message}']]),
            'source' => ['label' => 'Source engine game', 'match' => '/counter.?strike:? source|global offensive|csgo|team fortress|tf2|left 4 dead|left4dead|l4d|\binsurgency\b(?!: ?sandstorm)|no more room|nmrih|black mesa|fistful|\bfof\b|sourcecoop|open fortress|the ship|half-life 2|hl2|day of defeat: source|synergy|iosoccer|military conflict/i'] + $source,
            'goldsrc' => [
                'label' => 'Half-Life engine game (GoldSrc)', 'match' => '/hlds|counter-strike 1|cs 1\.6|condition zero|sven|day of defeat(?!: source)|team fortress classic|ricochet|deathmatch classic|half-life(?! 2)/i',
                'query' => ['a2s', 'game'],
                'rcon' => ['goldsrc', 'game', 'var:RCON_PASSWORD|var:RCON|startup:+rcon_password|cfg:{GAME_DIR}/server.cfg:rcon_password'],
                'list' => ['status', 'goldsrc_status'],
                'actions' => ['kick' => 'kick "{name}" "{reason}"', 'ban' => 'banid 0 {id} kick; writeid', 'unban' => 'removeid {id}; writeid', 'say' => 'say {message}'],
            ],

            // ----------------------------------------------------------- Survival games
            'rust' => [
                'label' => 'Rust', 'match' => '/^rust\b|rust autowipe|rust staging/i',
                'query' => ['a2s', 'var:QUERY_PORT|game'],
                'rcon' => ['webrcon', 'var:RCON_PORT', 'var:RCON_PASS|var:RCON_PASSWORD'],
                'list' => ['playerlist', 'rust_json'],
                'actions' => ['kick' => 'kick "{target}" "{reason}"', 'ban' => 'banid {target} "{name}" "{reason}"', 'unban' => 'unban {id}', 'say' => 'say "{message}"'],
            ],
            'ark-se' => [
                'label' => 'ARK: Survival Evolved', 'match' => '/ark: survival evolved|^ark\b(?!.*ascended)|pixark/i',
                'query' => ['a2s', 'var:QUERY_PORT|var:QPORT|game+1'],
                'rcon' => ['source', 'var:RCON_PORT|var:RCPORT', 'var:ARK_ADMIN_PASSWORD|var:ADMIN_PASSWORD'],
                'list' => ['ListPlayers', 'ark_list'],
                'actions' => ['kick' => 'KickPlayer {target}', 'ban' => 'BanPlayer {target}', 'unban' => 'UnbanPlayer {id}', 'say' => 'ServerChat {message}', 'whitelist' => 'AllowPlayerToJoinNoCheck {id}', 'unwhitelist' => 'DisallowPlayerToJoinNoCheck {id}'],
            ],
            'ark-sa' => [
                'label' => 'ARK: Survival Ascended', 'match' => '/survival ascended|\basa\b/i',
                'rcon' => ['source', 'var:RCON_PORT', 'var:ARK_ADMIN_PASSWORD|var:ADMIN_PASSWORD'],
                'list' => ['ListPlayers', 'ark_list'],
                'actions' => ['kick' => 'KickPlayer {target}', 'ban' => 'BanPlayer {target}', 'unban' => 'UnbanPlayer {id}', 'say' => 'ServerChat {message}'],
            ],
            'conan' => [
                'label' => 'Conan Exiles', 'match' => '/conan/i',
                'query' => ['a2s', 'var:QUERY_PORT|game'],
                'rcon' => ['source', 'var:RCON_PORT', 'var:RCON_PASSWORD|ini:ConanSandbox/Saved/Config/WindowsServer/Game.ini:RconPassword|ini:ConanSandbox/Saved/Config/LinuxServer/Game.ini:RconPassword'],
                'list' => ['ListPlayers', 'conan_list'],
                'actions' => ['kick' => 'KickPlayer {name}', 'ban' => 'BanPlayer {name}', 'unban' => 'UnbanPlayer {name}', 'say' => 'Broadcast {message}'],
                'by_name' => true,
            ],
            'palworld' => [
                'label' => 'Palworld', 'match' => '/palworld/i',
                'rcon' => ['source', 'var:RCON_PORT', 'var:ADMIN_PASSWORD'],
                'list' => ['ShowPlayers', 'palworld_csv'],
                'actions' => ['kick' => 'KickPlayer {target}', 'ban' => 'BanPlayer {target}', 'unban' => 'UnBanPlayer {id}', 'say' => 'Broadcast {message_nospace}'],
                'hint' => 'palworld',
            ],
            'zomboid' => [
                'label' => 'Project Zomboid', 'match' => '/zomboid/i',
                'query' => ['a2s', 'game|var:STEAM_PORT'],
                // The Pelican egg runs with -cachedir=/home/container/.cache; other setups use Zomboid/.
                'rcon' => ['source', 'ini:.cache/Server/{SERVER_NAME}.ini:RCONPort|ini:Zomboid/Server/{SERVER_NAME}.ini:RCONPort', 'ini:.cache/Server/{SERVER_NAME}.ini:RCONPassword|ini:Zomboid/Server/{SERVER_NAME}.ini:RCONPassword'],
                'list' => ['players', 'zomboid_list'],
                'actions' => ['kick' => 'kickuser "{name}" -r "{reason}"', 'ban' => 'banuser "{name}" -r "{reason}"', 'unban' => 'unbanuser "{name}"', 'say' => 'servermsg "{message}"', ],
                'by_name' => true,
            ],
            'sdtd' => [
                'label' => '7 Days to Die', 'match' => '/7 days|7dtd|seven days/i',
                'query' => ['a2s', 'game'],
                'rcon' => ['telnet', 'var:TELNET_PORT|xml:serverconfig.xml:TelnetPort|8081', 'var:PASSWORD|xml:serverconfig.xml:TelnetPassword'],
                'list' => ['lp', 'sdtd_lp'],
                'actions' => ['kick' => 'kick {target} "{reason}"', 'ban' => 'ban add {target} 10 years "{reason}"', 'unban' => 'ban remove {id}', 'say' => 'say "{message}"'],
            ],
            'empyrion' => ['label' => 'Empyrion: Galactic Survival', 'match' => '/empyrion/i', 'query' => ['a2s', 'game+1|game'], 'rcon' => ['telnet', 'var:TELNET_PORT', 'var:TELNET_PASSWORD'], 'actions' => ['kick' => 'kick {name} {reason}', 'ban' => 'ban {name} 10y', 'say' => "say '{message}'"], 'by_name' => true],
            'valheim' => ['label' => 'Valheim', 'match' => '/valheim/i', 'query' => ['a2s', 'game+1|game'], 'actions' => []],
            'unturned' => [
                'label' => 'Unturned', 'match' => '/unturned/i',
                'query' => ['a2s', 'game+1|game'],
                'actions' => ['kick' => 'kick {name}/{reason}', 'ban' => 'ban {name}/{reason}/31536000', 'unban' => 'unban {id}', 'say' => 'say {message}'],
                'by_name' => true,
            ],
            'vrising' => [
                'label' => 'V Rising', 'match' => '/v rising|vrising/i',
                'query' => ['a2s', 'var:QUERY_PORT|var:VR_QUERY_PORT|game+1'],
                'rcon' => ['source', 'var:RCON_PORT|var:VR_RCON_PORT', 'var:RCON_PASS|var:VR_RCON_PASSWORD'],
                'actions' => ['say' => 'announce {message}'],
            ],
            'factorio' => [
                'label' => 'Factorio', 'match' => '/factorio|clusterio/i',
                'rcon' => ['source', 'var:RCON_PORT', 'var:RCON_PASSWORD|var:RCON_PASS'],
                'list' => ['/players online', 'factorio_list'],
                'actions' => ['kick' => '/kick {name} {reason}', 'ban' => '/ban {name} {reason}', 'unban' => '/unban {name}', 'say' => '{message}', 'whitelist' => '/whitelist add {name}', 'unwhitelist' => '/whitelist remove {name}', 'op' => '/promote {name}', 'deop' => '/demote {name}'],
                'by_name' => true,
            ],
            'terraria' => [
                'label' => 'Terraria', 'match' => '/terraria|tshock|tmodloader/i',
                'rcon' => ['tshock', 'var:REST_PORT|7878', 'var:REST_TOKEN|var:TSHOCK_TOKEN'],
                'list' => ['playing', 'terraria_list'],
                'actions' => ['kick' => 'kick {name}', 'ban' => 'ban {name}', 'say' => 'say {message}'],
                'by_name' => true,
            ],
            'dayz' => [
                'label' => 'DayZ', 'match' => '/dayz/i',
                'query' => ['a2s', 'var:QUERY_PORT|game+1|game'],
                'rcon' => ['battleye', 'var:RCON_PORT', 'var:RCON_PASSWORD'],
                'actions' => ['kick' => 'kick {id} {reason}', 'ban' => 'ban {id} 0 {reason}', 'say' => 'say -1 {message}'],
                'hint' => 'battleye',
            ],
            'arma3' => [
                'label' => 'Arma 3', 'match' => '/arma 3|arma3/i',
                'query' => ['a2s', 'var:QUERY_PORT|game+1'],
                'rcon' => ['battleye', 'var:RCON_PORT', 'var:RCON_PASSWORD'],
                'actions' => ['kick' => 'kick {id} {reason}', 'ban' => 'ban {id} 0 {reason}', 'say' => 'say -1 {message}'],
                'hint' => 'battleye',
            ],
            'arma-reforger' => ['label' => 'Arma Reforger', 'match' => '/reforger/i', 'query' => ['a2s', 'var:QUERY_PORT|json:config.json:a2s.port|17777'], 'actions' => []],
            'squad' => [
                'label' => 'Squad / Post Scriptum', 'match' => '/squad|post scriptum|harsh doorstop/i',
                'query' => ['a2s', 'var:QUERY_PORT|game'],
                'rcon' => ['source', 'var:RCON_PORT|ini:SquadGame/ServerConfig/Rcon.cfg:Port', 'var:RCON_PASSWORD|ini:SquadGame/ServerConfig/Rcon.cfg:Password'],
                'list' => ['ListPlayers', 'squad_list'],
                'actions' => ['kick' => 'AdminKick "{target}" {reason}', 'ban' => 'AdminBan "{target}" 0 {reason}', 'say' => 'AdminBroadcast {message}'],
            ],
            'the-isle' => ['label' => 'The Isle', 'match' => '/the isle/i', 'query' => ['a2s', 'var:QUERY_PORT|game'], 'actions' => []],
            'satisfactory' => ['label' => 'Satisfactory', 'match' => '/satisfactory/i', 'query' => ['satisfactory', 'game'], 'actions' => []],

            // ------------------------------------------------------- Quake and friends
            'quake3' => ['label' => 'Quake 3 engine game', 'match' => '/openarena|urban ?terror|wolfenstein|enemy territory|etlegacy|et:? ?legacy|world of padman|quake ?3|ioquake|xonotic|darkplaces|call of duty|tremulous|unvanquished/i'] + $quake,
            'mohaa' => ['label' => 'Medal of Honor: Allied Assault', 'match' => '/medal of honor|mohaa|openmohaa/i'] + $quake,
            'quake-live' => ['label' => 'Quake Live', 'match' => '/quake ?live/i', 'query' => ['a2s', 'game'], 'actions' => []],
            'sandstorm' => ['label' => 'Insurgency: Sandstorm', 'match' => '/sandstorm/i', 'query' => ['a2s', 'var:QUERY_PORT|game'], 'rcon' => ['source', 'var:RCON_PORT', 'var:RCON_PASSWORD'], 'actions' => ['kick' => 'kick {target} "{reason}"', 'ban' => 'ban {target} -1 "{reason}"', 'say' => 'say {message}']],
            'teeworlds' => ['label' => 'Teeworlds / DDNet', 'match' => '/teeworlds|ddnet|ddrace/i', 'query' => ['teeworlds', 'game'], 'actions' => ['say' => 'say {message}']],

            // ---------------------------------------------------------- GTA multiplayer
            'samp' => [
                'label' => 'SA-MP / open.mp', 'match' => '/sa-?mp|open\.?mp|san andreas/i',
                'query' => ['samp', 'game'],
                'actions' => ['kick' => 'kick {id}', 'ban' => 'ban {id}', 'say' => 'say {message}'],
            ],
            'mtasa' => ['label' => 'Multi Theft Auto', 'match' => '/mta|multi theft auto/i', 'query' => ['ase', 'game+123'], 'actions' => ['kick' => 'kick {name} {reason}', 'ban' => 'ban {name} {reason}', 'say' => 'say {message}'], 'by_name' => true],

            // ------------------------------------------------------------------ Racing
            'assetto' => ['label' => 'Assetto Corsa', 'match' => '/assetto/i', 'query' => ['assetto', 'var:HTTP_PORT|8081'], 'actions' => []],

            // ------------------------------------------- Other Steam games, by query port
            'steam' => ['label' => 'Steam game (query only)', 'match' => '/./', 'generic' => true] + $steam + ['actions' => []],
        ];
    }

    /** @return array<string, string> key => label, for the admin override list */
    public static function labels(): array
    {
        $l = array_map(fn ($g) => $g['label'], self::all());
        asort($l);

        return $l;
    }

    public static function get(string $key): ?array
    {
        $g = self::all()[$key] ?? null;

        return $g ? ['key' => $key] + $g : null;
    }

    /**
     * The profile for an egg, from its name first, then its startup command (some
     * eggs have generic names such as "Reworked" or "Proton").
     *
     * The generic Steam profile only applies to eggs that look like Steam games:
     * a query port variable, a SteamCMD image or a Steam app id.
     *
     * @param  string[]  $variables  env names of the egg
     */
    public static function detect(string $eggName, string $startup = '', array $variables = [], string $image = ''): ?array
    {
        foreach (self::all() as $key => $g) {
            if (!empty($g['generic'])) {
                continue;
            }
            $hay = $eggName;
            if (preg_match($g['match'], $hay) && !(isset($g['exclude']) && preg_match($g['exclude'], $hay))) {
                return ['key' => $key] + $g;
            }
        }
        foreach (self::all() as $key => $g) {
            if (!empty($g['generic'])) {
                continue;
            }
            if ($startup !== '' && preg_match($g['match'], $startup) && !(isset($g['exclude']) && preg_match($g['exclude'], $startup))) {
                return ['key' => $key] + $g;
            }
        }

        // Modpacks and forks with their own names (Tekkit, Hexxit...): a jar file to run means Minecraft.
        if (in_array('SERVER_JARFILE', $variables, true) || preg_match('/-jar\s+\S*server.*\.jar|\{\{SERVER_JARFILE\}\}/i', $startup)) {
            $proxy = self::all()['minecraft-proxy'];
            if (preg_match($proxy['match'], $eggName)) {
                return ['key' => 'minecraft-proxy'] + $proxy;
            }

            return ['key' => 'minecraft'] + self::all()['minecraft'];
        }

        $steamLike = array_intersect($variables, ['QUERY_PORT', 'SERVER_QUERY_PORT', 'QPORT', 'STEAM_QUERY_PORT', 'SRCDS_APPID'])
            || str_contains($image, 'steamcmd') || str_contains($startup, 'srcds');

        return $steamLike ? ['key' => 'steam'] + self::all()['steam'] : null;
    }
}
