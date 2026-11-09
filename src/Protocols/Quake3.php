<?php

namespace MaitreAntho\PlayerManager\Protocols;

/**
 * Quake 3 style status and rcon over UDP ("\xFF\xFF\xFF\xFF" + text): Quake 3 and
 * its offspring (OpenArena, Urban Terror, Wolfenstein: Enemy Territory, World of
 * Padman, Xonotic and other DarkPlaces games, Call of Duty), MOHAA, and FiveM's
 * own rcon command.
 */
final class Quake3 implements QueryProtocol, CommandProtocol
{
    public function query(Endpoint $endpoint): QueryResult
    {
        $s = Net::udp($endpoint);
        try {
            Net::write($s, "\xFF\xFF\xFF\xFFgetstatus\n");
            $r = Net::recv($s);
        } finally {
            fclose($s);
        }
        $lines = explode("\n", trim(substr($r, 4)));
        if (!str_starts_with(array_shift($lines) ?? '', 'statusResponse')) {
            throw new ProtocolException('Invalid status answer.');
        }
        $info = [];
        $kv = explode('\\', ltrim(array_shift($lines) ?? '', '\\'));
        for ($i = 0; $i + 1 < count($kv); $i += 2) {
            $info[strtolower($kv[$i])] = $kv[$i + 1];
        }
        $players = [];
        foreach ($lines as $line) {
            // score ping "name"  (Xonotic adds a team column before the name)
            if (preg_match('/^(-?\d+)\s+(\d+)(?:\s+-?\d+)?\s+"(.*)"$/', trim($line), $m)) {
                $players[] = new Player(self::clean($m[3]), null, (int) $m[1], null, (int) $m[2]);
            }
        }

        return new QueryResult($players, count($players), isset($info['sv_maxclients']) ? (int) $info['sv_maxclients'] : null, isset($info['sv_hostname']) ? self::clean($info['sv_hostname']) : ($info['hostname'] ?? null), $info['mapname'] ?? null);
    }

    public function command(Endpoint $endpoint, string $password, string $command): string
    {
        $s = Net::udp($endpoint);
        try {
            Net::write($s, "\xFF\xFF\xFF\xFFrcon $password $command");
            $out = '';
            try {
                $out .= substr(Net::recv($s), 4);
                Net::timeout($s, 0.3);
                for ($i = 0; $i < 50; $i++) {
                    $out .= substr(Net::recv($s), 4);
                }
            } catch (ProtocolException $e) {
                if ($out === '') {
                    throw $e;
                }
            }
        } finally {
            fclose($s);
        }
        $out = preg_replace('/^print\n/m', '', $out);
        if (preg_match('/bad rcon ?password|invalid password/i', $out)) {
            throw new AuthenticationException('The server refused the RCON password.');
        }

        return self::clean($out);
    }

    /** Strip Quake colour codes (^1, ^7...). */
    public static function clean(string $s): string
    {
        return preg_replace('/\^(\d|x[0-9a-fA-F]{3})/', '', $s);
    }
}
