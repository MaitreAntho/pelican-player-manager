<?php

namespace MaitreAntho\PlayerManager\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\Cache;
use MaitreAntho\PlayerManager\Games\Catalog;
use MaitreAntho\PlayerManager\Games\Parsers;
use MaitreAntho\PlayerManager\Models\EggGame;
use MaitreAntho\PlayerManager\Models\ServerSetting;
use MaitreAntho\PlayerManager\Protocols\A2s;
use MaitreAntho\PlayerManager\Protocols\Ase;
use MaitreAntho\PlayerManager\Protocols\AssettoCorsa;
use MaitreAntho\PlayerManager\Protocols\AuthenticationException;
use MaitreAntho\PlayerManager\Protocols\BattlEye;
use MaitreAntho\PlayerManager\Protocols\CommandProtocol;
use MaitreAntho\PlayerManager\Protocols\Endpoint;
use MaitreAntho\PlayerManager\Protocols\FivemHttp;
use MaitreAntho\PlayerManager\Protocols\GoldSrcRcon;
use MaitreAntho\PlayerManager\Protocols\ListsPlayers;
use MaitreAntho\PlayerManager\Protocols\MinecraftBedrock;
use MaitreAntho\PlayerManager\Protocols\MinecraftJava;
use MaitreAntho\PlayerManager\Protocols\PalworldRest;
use MaitreAntho\PlayerManager\Protocols\Player;
use MaitreAntho\PlayerManager\Protocols\ProtocolException;
use MaitreAntho\PlayerManager\Protocols\Quake3;
use MaitreAntho\PlayerManager\Protocols\QueryProtocol;
use MaitreAntho\PlayerManager\Protocols\QueryResult;
use MaitreAntho\PlayerManager\Protocols\RustWebRcon;
use MaitreAntho\PlayerManager\Protocols\Samp;
use MaitreAntho\PlayerManager\Protocols\Satisfactory;
use MaitreAntho\PlayerManager\Protocols\SourceRcon;
use MaitreAntho\PlayerManager\Protocols\Teeworlds;
use MaitreAntho\PlayerManager\Protocols\Telnet;
use MaitreAntho\PlayerManager\Protocols\TShockRest;
use Throwable;

/**
 * Everything the panel needs to know about the players of one server: which game
 * it runs, where to query it, how to reach its admin console, and the actions.
 */
class PlayerService
{
    /** Query protocols by catalog name. */
    private const QUERY = [
        'a2s' => A2s::class, 'minecraft' => MinecraftJava::class, 'bedrock' => MinecraftBedrock::class,
        'fivem' => FivemHttp::class, 'quake3' => Quake3::class, 'teeworlds' => Teeworlds::class,
        'satisfactory' => Satisfactory::class, 'samp' => Samp::class, 'ase' => Ase::class, 'assetto' => AssettoCorsa::class,
    ];

    /** Admin console protocols by catalog name. */
    private const RCON = [
        'source' => SourceRcon::class, 'goldsrc' => GoldSrcRcon::class, 'quake3' => Quake3::class, 'telnet' => Telnet::class,
        'webrcon' => RustWebRcon::class, 'battleye' => BattlEye::class, 'tshock' => TShockRest::class, 'palworld' => PalworldRest::class,
    ];

    /** Per-request memory, keyed by server: game, setting, variables. @var array<string, mixed> */
    private array $memo = [];

    public function __construct(private ConsoleChannel $console) {}

    private function memo(string $key, callable $compute): mixed
    {
        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $compute();
        }

        return $this->memo[$key];
    }

    // ------------------------------------------------------------------ game

    /** The game of a server, or null when it is unknown or turned off. @return array<string, mixed>|null */
    public function game(Server $server): ?array
    {
        return $this->memo("game.$server->id", function () use ($server) {
            $key = $this->setting($server)?->game ?? EggGame::find($server->egg_id)?->game;
            if ($key === 'none') {
                return null;
            }
            if ($key) {
                return Catalog::get($key);
            }

            return $this->detectEgg($server->egg);
        });
    }

    /** @return array<string, mixed>|null */
    public function detectEgg(\App\Models\Egg $egg): ?array
    {
        $startup = implode(' ', array_values((array) ($egg->startup_commands ?? [])));
        $images = implode(' ', array_values((array) ($egg->docker_images ?? [])));
        $vars = $egg->variables()->pluck('env_variable')->all();

        return Catalog::detect((string) $egg->name, $startup, $vars, $images);
    }

    public function setting(Server $server): ?ServerSetting
    {
        return $this->memo("setting.$server->id", fn () => ServerSetting::find($server->id));
    }

    /** Host the panel uses to reach the server: a configured one, the allocation IP, or the node. */
    public function host(Server $server): ?string
    {
        if ($override = trim((string) config('player-manager.host', ''))) {
            return $override;
        }
        $allocation = $server->allocation;
        $ip = $allocation?->ip;
        if ($ip && !in_array($ip, ['0.0.0.0', '::', '[::]'], true)) {
            return trim($ip, '[]');
        }
        $alias = $allocation?->alias;
        if ($alias && !in_array($alias, ['0.0.0.0', '::'], true) && filter_var($alias, FILTER_VALIDATE_IP)) {
            return $alias;
        }

        return $server->node?->fqdn ?: $alias;
    }

    // ------------------------------------------------------------- endpoints

    /** The query protocol and where it answers, or null when the game has none. @return array{protocol: QueryProtocol, endpoint: Endpoint, name: string}|null */
    public function queryTarget(Server $server, float $timeout = 2.0): ?array
    {
        $game = $this->game($server);
        if (!$game || empty($game['query']) || !($host = $this->host($server))) {
            return null;
        }
        [$name, $spec] = $game['query'];
        $port = $this->setting($server)?->query_port ?? $this->resolveInt($server, $spec, $game);
        if (!$port) {
            return null;
        }

        if ($name === 'minecraft') {
            // Full query when enabled (all names), else the status ping on the game port.
            $queryOn = in_array(strtolower((string) $this->resolve($server, 'prop:server.properties:enable-query', $game)), ['true', '1'], true);
            $gamePort = (int) $server->allocation?->port;

            return ['protocol' => new MinecraftJava($queryOn ? $port : null), 'endpoint' => new Endpoint($host, $gamePort ?: $port, $timeout), 'name' => $name];
        }

        $class = self::QUERY[$name];

        return ['protocol' => new $class(), 'endpoint' => new Endpoint($host, $port, $timeout), 'name' => $name];
    }

    /** Direct admin access (RCON, telnet...), or null when it is not set up. @return array{protocol: CommandProtocol, endpoint: Endpoint, password: string, name: string}|null */
    public function rconTarget(Server $server, float $timeout = 3.0): ?array
    {
        $game = $this->game($server);
        if (!$game || empty($game['rcon']) || !($host = $this->host($server))) {
            return null;
        }
        [$name, $portSpec, $passwordSpec] = $game['rcon'] + [null, null, null];
        $setting = $this->setting($server);
        // "Enabled" flag (enable-rcon in server.properties). A password set by hand wins.
        if (isset($game['rcon'][3]) && !filled($setting?->rcon_password)) {
            $enabled = strtolower((string) $this->resolve($server, $game['rcon'][3], $game));
            if (!in_array($enabled, ['true', '1', 'yes'], true)) {
                return null;
            }
        }
        $port = $setting?->rcon_port ?? $this->resolveInt($server, (string) $portSpec, $game);
        $password = $setting?->rcon_password ?: $this->resolve($server, (string) $passwordSpec, $game);
        if (!$port || $password === null || $password === '') {
            return null;
        }
        $class = self::RCON[$name];

        return ['protocol' => new $class(), 'endpoint' => new Endpoint($host, $port, $timeout), 'password' => $password, 'name' => $name];
    }

    // --------------------------------------------------------------- players

    /**
     * Who is online, cached a few seconds so the page and the collector share it.
     *
     * @return array{result: ?QueryResult, error: ?string, via: ?string, at: int}
     */
    public function players(Server $server, bool $fresh = false): array
    {
        $key = "player-manager.players.$server->id";
        if ($fresh) {
            Cache::forget($key);
        }

        $data = Cache::remember($key, 10, function () use ($server) {
            $out = ['result' => null, 'error' => null, 'via' => null, 'at' => time()];
            try {
                if ($q = $this->queryTarget($server)) {
                    $out['result'] = $q['protocol']->query($q['endpoint'])->toArray();
                    $out['via'] = $q['name'];
                } elseif (($r = $this->rconTarget($server)) && $r['protocol'] instanceof ListsPlayers) {
                    $out['result'] = $r['protocol']->players($r['endpoint'], $r['password'])->toArray();
                    $out['via'] = $r['name'];
                } elseif ($r && ($list = $this->game($server)['list'] ?? null)) {
                    $players = Parsers::parse($list[1], $r['protocol']->command($r['endpoint'], $r['password'], $list[0]));
                    $out['result'] = (new QueryResult($players, count($players)))->toArray();
                    $out['via'] = $r['name'];
                } else {
                    $out['error'] = 'no_source';
                }
            } catch (AuthenticationException) {
                $out['error'] = 'auth';
            } catch (Throwable $e) {
                $out['error'] = 'unreachable';
                $out['detail'] = $e->getMessage();
            }

            return $out;
        });
        $data['result'] = $data['result'] ? QueryResult::fromArray($data['result']) : null;

        return $data;
    }

    /**
     * The game's own player list (status, lp, ListPlayers...), which carries the ids
     * bans need. Runs through RCON when set up, else through the Pelican console.
     *
     * @return Player[]|null null when the game has no such command
     */
    public function detailedPlayers(Server $server): ?array
    {
        $list = $this->game($server)['list'] ?? null;
        $rcon = $this->rconTarget($server);
        if ($rcon && $rcon['protocol'] instanceof ListsPlayers) {
            try {
                return $rcon['protocol']->players($rcon['endpoint'], $rcon['password'])->players;
            } catch (ProtocolException $e) {
                if (!$list) {
                    throw $e;
                }
            }
        }
        if (!$list) {
            return null;
        }

        return Parsers::parse($list[1], $this->run($server, $list[0])['output']);
    }

    // --------------------------------------------------------------- actions

    /** @return string[] the actions this game supports */
    public function actions(Server $server): array
    {
        return array_keys($this->game($server)['actions'] ?? []);
    }

    /** Whether commands can reach the game: direct access, or the Pelican console. */
    public function canRun(Server $server): bool
    {
        return $this->rconTarget($server) !== null || $this->game($server) !== null;
    }

    /**
     * Runs a command through direct access when set up, else through the Pelican
     * console (the egg passes it to the game).
     *
     * @return array{output: string, via: string, fallback?: ?string}
     */
    public function run(Server $server, string $command): array
    {
        $fallback = null;
        if ($r = $this->rconTarget($server)) {
            try {
                return ['output' => $r['protocol']->command($r['endpoint'], $r['password'], $command), 'via' => $r['name']];
            } catch (ProtocolException $e) {
                // The remote console is closed to the panel (port not opened, password refused...):
                // the Pelican console still reaches the game for most eggs.
                $fallback = $e instanceof AuthenticationException ? 'auth' : 'unreachable';
            }
        }

        return ['output' => $this->console->run($server, $command), 'via' => 'console', 'fallback' => $fallback];
    }

    /**
     * Runs one of the catalog actions on a player.
     *
     * @param  array{name?: string, id?: ?string}  $player
     * @return array{command: string, output: string, via: string}
     */
    public function act(Server $server, string $action, array $player = [], string $text = ''): array
    {
        $game = $this->game($server);
        $template = $game['actions'][$action] ?? null;
        if (!$template) {
            throw new ProtocolException("This game has no $action command.");
        }

        $name = $player['name'] ?? '';
        $id = $player['id'] ?? null;

        // Bans and kicks by id need the id the game's own list gives: look it up by name.
        if (!$id && (str_contains($template, '{id}') || (str_contains($template, '{target}') && empty($game['by_name'])))) {
            foreach ($this->detailedPlayers($server) ?? [] as $p) {
                if (strcasecmp($p->name, $name) === 0) {
                    $id = $p->id;
                    break;
                }
            }
            if (!$id && str_contains($template, '{id}')) {
                throw new ProtocolException('The id of this player could not be found.');
            }
        }

        $command = strtr($template, [
            '{target}' => self::clean($id ?: $name),
            '{id}' => self::clean((string) $id),
            '{name}' => self::clean($name),
            '{reason}' => self::clean($text),
            '{message}' => self::clean($text),
            '{message_nospace}' => str_replace(' ', '_', self::clean($text)),
        ]);
        // Templates with an empty trailing argument (no reason given) leave "" or spaces behind.
        $command = trim(preg_replace('/\s+""\s*$|\s+\/$/', '', $command));

        Cache::forget("player-manager.players.$server->id");

        return ['command' => $command] + $this->run($server, $command);
    }

    /**
     * Text that goes into a game command: no line breaks, quotes or separators that
     * could end the command and start another one.
     */
    public static function clean(string $s): string
    {
        return trim(preg_replace('/\s{2,}/', ' ', preg_replace('/[\r\n\0"`;\\\\]+/', ' ', $s)));
    }

    // ------------------------------------------------------------ resolution

    /** First value of a spec that resolves ("var:A|game+1|27015"). @param array<string, mixed> $game */
    public function resolve(Server $server, string $spec, array $game = []): ?string
    {
        foreach (explode('|', $spec) as $alt) {
            $value = $this->resolveOne($server, trim($alt), $game);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $game */
    private function resolveInt(Server $server, string $spec, array $game): ?int
    {
        $v = $this->resolve($server, $spec, $game);

        return $v !== null && ctype_digit($v) && (int) $v > 0 && (int) $v < 65536 ? (int) $v : null;
    }

    /** @param array<string, mixed> $game */
    private function resolveOne(Server $server, string $alt, array $game): ?string
    {
        if ($alt === '') {
            return null;
        }
        if (ctype_digit($alt)) {
            return $alt;
        }
        if (preg_match('/^game(?:\+(\d+))?$/', $alt, $m)) {
            $port = (int) $server->allocation?->port;

            return $port ? (string) ($port + (int) ($m[1] ?? 0)) : null;
        }
        [$kind, $rest] = array_pad(explode(':', $alt, 2), 2, '');
        if ($kind === 'var') {
            return $this->variable($server, $rest);
        }
        if ($kind === 'startup') {
            // A setting passed on the command line: +rcon_password "secret", -RCONPort=27020...
            $startup = preg_replace_callback('/\{\{(\w+)\}\}|\$\{?(\w+)\}?/', fn ($m) => $this->variable($server, $m[1] ?: $m[2]) ?? '', (string) $server->startup);

            return preg_match('/(?:^|\s)' . preg_quote($rest, '/') . '(?:\s+|=)(?:"([^"]*)"|(\S+))/i', $startup, $m) ? (($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? null)) : null;
        }

        // File sources: kind:FILE:KEY, with {VAR} placeholders in FILE.
        $pos = strrpos($rest, ':');
        if ($pos === false) {
            return null;
        }
        $file = substr($rest, 0, $pos);
        $key = substr($rest, $pos + 1);
        $file = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($server, $game) {
            if ($m[1] === 'GAME_DIR') {
                return $game['game_dir'] ?? "\0";
            }

            return $this->variable($server, $m[1]) ?? "\0";
        }, $file);
        if (str_contains($file, "\0")) {
            return null;
        }
        $content = $this->file($server, $file);
        if ($content === null) {
            return null;
        }

        return ConfigReader::read($kind, $content, $key);
    }

    private function variable(Server $server, string $env): ?string
    {
        $vars = $this->memo("vars.$server->id", fn () => $server->variables()->get()->mapWithKeys(fn ($v) => [$v->env_variable => $v->server_value ?? $v->default_value])->all());
        $v = $vars[$env] ?? null;

        return $v === null ? null : trim((string) $v);
    }

    /** A server file, cached a minute; null when missing or unreadable. */
    private function file(Server $server, string $path): ?string
    {
        return Cache::remember("player-manager.file.$server->id." . md5($path), 60, function () use ($server, $path) {
            try {
                return app(DaemonFileRepository::class)->setServer($server)->getContent($path, 512 * 1024);
            } catch (Throwable) {
                return null;
            }
        });
    }
}
