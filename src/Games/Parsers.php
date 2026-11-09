<?php

namespace MaitreAntho\PlayerManager\Games;

use MaitreAntho\PlayerManager\Protocols\Player;

/**
 * Reads the player list a game prints in its console (status, list, lp...).
 * The console output gives ids (SteamID, EOS id...) that queries do not, and
 * bans need them.
 */
final class Parsers
{
    /** @return Player[] */
    public static function parse(string $parser, string $output): array
    {
        $output = str_replace("\r", '', $output);

        return match ($parser) {
            'source_status' => self::sourceStatus($output),
            'goldsrc_status' => self::sourceStatus($output),
            'minecraft_list' => self::minecraftList($output),
            'zomboid_list' => self::lines($output, '/^-\s*(.+)$/'),
            'sdtd_lp' => self::sdtd($output),
            'ark_list' => self::ark($output),
            'conan_list' => self::conan($output),
            'palworld_csv' => self::palworld($output),
            'factorio_list' => self::lines($output, '/^\s+(\S.*?)(?:\s+\(online\))?$/'),
            'terraria_list' => self::terraria($output),
            'squad_list' => self::squad($output),
            'rust_json' => self::rust($output),
            default => [],
        };
    }

    /**
     * Source and GoldSrc "status":
     *   # userid name uniqueid connected ping loss state rate adr   (Source)
     *   #      2 "Alice" STEAM_1:0:123 05:12 50 0 active ...
     *   CS2 prints the name last, quoted with single quotes.
     *
     * @return Player[]
     */
    private static function sourceStatus(string $o): array
    {
        $players = [];
        foreach (explode("\n", $o) as $line) {
            $id = preg_match('/(STEAM_\d:\d:\d+|\[U:\d:\d+\]|BOT)/', $line, $m) ? $m[1] : null;
            if (preg_match('/^#\s*(\d+)\s+(?:\d+\s+)?"(.*)"\s/', $line, $m)) {
                if ($id === 'BOT') {
                    continue;
                }
                $players[] = new Player($m[2], $id, null, self::duration($line), self::ping($line), ['userid' => $m[1]]);
            } elseif (preg_match("/^\s*(\d+)\s+[\d:]+\s+(\d+)\s+\d+\s+\w+\s+\d+\s+\S+\s+'(.*)'\s*$/", $line, $m)) {
                $players[] = new Player($m[3], $m[1], null, self::duration($line), (int) $m[2], ['userid' => $m[1]]);
            }
        }

        return $players;
    }

    /** "There are 2 of a max of 20 players online: Alice, Bob" (Java), names on the next line (Bedrock). */
    private static function minecraftList(string $o): array
    {
        $o = preg_replace('/\e\[[\d;]*m|§./u', '', $o);
        if (!preg_match('/players online:?\s*(.*)$/is', $o, $m)) {
            return [];
        }
        $names = preg_split('/[,\n]+/', trim($m[1]));

        return array_values(array_map(
            fn ($n) => new Player(trim(preg_replace('/^\[[^\]]*\]\s*/', '', $n))),
            array_filter($names, fn ($n) => preg_match('/^\s*(\[[^\]]*\]\s*)?\w{2,16}\s*$/', $n)),
        ));
    }

    /** 0. id=171, Alice, pos=(..), ..., pltfmid=Steam_7656..., crossid=EOS_..., ip=1.2.3.4, ping=20 */
    private static function sdtd(string $o): array
    {
        $players = [];
        foreach (explode("\n", $o) as $line) {
            if (preg_match('/^\s*\d+\.\s+id=(\d+),\s+(.+?),\s+pos=/', $line, $m)) {
                preg_match('/(?:pltfmid|steamid)=([\w_]+)/', $line, $p);
                preg_match('/crossid=([\w_]+)/', $line, $c);
                preg_match('/score=(-?\d+)/', $line, $s);
                $players[] = new Player($m[2], $p[1] ?? $m[1], isset($s[1]) ? (int) $s[1] : null, null, self::ping($line), array_filter(['entity' => $m[1], 'crossplay' => $c[1] ?? null]));
            }
        }

        return $players;
    }

    /** 0. Alice, 0002a1b2c3... (EOS id on ASA, SteamID on ASE) */
    private static function ark(string $o): array
    {
        return self::lines($o, '/^\s*\d+\.\s+(.+?),\s+([0-9a-fA-F]{10,40})\s*$/', idGroup: 2);
    }

    /** Idx | Char name | Player name | User ID | Platform ID | Platform Name */
    private static function conan(string $o): array
    {
        $players = [];
        foreach (explode("\n", $o) as $line) {
            $cols = array_map('trim', explode('|', $line));
            if (count($cols) >= 4 && ctype_digit($cols[0])) {
                $players[] = new Player($cols[1] !== '' ? $cols[1] : $cols[2], $cols[3] ?: null, null, null, null, array_filter(['account' => $cols[2], 'platform' => $cols[4] ?? null]));
            }
        }

        return $players;
    }

    /** name,playeruid,steamid */
    private static function palworld(string $o): array
    {
        $players = [];
        foreach (array_slice(explode("\n", trim($o)), 1) as $line) {
            $cols = str_getcsv($line);
            if (count($cols) >= 3 && $cols[0] !== '') {
                $players[] = new Player($cols[0], $cols[2] !== '' ? $cols[2] : $cols[1], null, null, null, ['uid' => $cols[1]]);
            }
        }

        return $players;
    }

    /** Vanilla "playing": "Alice (1.2.3.4:5678)"; TShock "who": "Alice, Bob" after a header. */
    private static function terraria(string $o): array
    {
        $players = self::lines($o, '/^\s*([^():,]+?)\s+\(\d+\.\d+\.\d+\.\d+:\d+\)\s*$/');
        if ($players) {
            return $players;
        }
        if (preg_match('/Online Players.*\n(.+)/i', $o, $m)) {
            return array_map(fn ($n) => new Player(trim($n)), array_filter(explode(',', $m[1]), 'trim'));
        }

        return [];
    }

    /** ID: 0 | Online IDs: EOS: abc steam: 7656... | Name: Alice | Team ID: 1 | ... */
    private static function squad(string $o): array
    {
        $players = [];
        foreach (explode("\n", explode('Recently Disconnected', $o)[0]) as $line) {
            if (preg_match('/^ID:\s*(\d+)\s*\|.*?Name:\s*(.+?)\s*\|/', $line, $m)) {
                $steam = preg_match('/steam:\s*(\d{17})/i', $line, $s) ? $s[1] : null;
                $eos = preg_match('/EOS:\s*(\w+)/', $line, $e) ? $e[1] : null;
                $players[] = new Player($m[2], $steam ?? $eos ?? $m[1], null, null, null, array_filter(['slot' => $m[1], 'eos' => $eos]));
            }
        }

        return $players;
    }

    private static function rust(string $o): array
    {
        $json = json_decode(trim($o), true);
        if (!is_array($json)) {
            return [];
        }

        return array_map(fn ($p) => new Player((string) ($p['DisplayName'] ?? '?'), isset($p['SteamID']) ? (string) $p['SteamID'] : null, null, isset($p['ConnectedSeconds']) ? (int) $p['ConnectedSeconds'] : null, isset($p['Ping']) ? (int) $p['Ping'] : null), $json);
    }

    /** One player per line matching $pattern: group 1 is the name, $idGroup the id. @return Player[] */
    private static function lines(string $o, string $pattern, ?int $idGroup = null): array
    {
        $players = [];
        foreach (explode("\n", $o) as $line) {
            if (preg_match($pattern, $line, $m) && trim($m[1]) !== '') {
                $players[] = new Player(trim($m[1]), $idGroup ? $m[$idGroup] : null);
            }
        }

        return $players;
    }

    private static function duration(string $line): ?int
    {
        if (preg_match('/\s(\d+):(\d{2}):(\d{2})\s/', $line, $m)) {
            return $m[1] * 3600 + $m[2] * 60 + $m[3];
        }
        if (preg_match('/\s(\d+):(\d{2})\s/', $line, $m)) {
            return $m[1] * 60 + $m[2];
        }

        return null;
    }

    private static function ping(string $line): ?int
    {
        return preg_match('/ping=(\d+)/', $line, $m) ? (int) $m[1] : null;
    }
}
