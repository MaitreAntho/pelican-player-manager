<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** All-Seeing Eye query (MTA:SA, on the game port + 123): players with score and ping. */
final class Ase implements QueryProtocol
{
    public function query(Endpoint $endpoint): QueryResult
    {
        $s = Net::udp($endpoint);
        try {
            Net::write($s, 's');
            $r = Net::recv($s);
        } finally {
            fclose($s);
        }
        if (!str_starts_with($r, 'EYE1')) {
            throw new ProtocolException('Invalid ASE answer.');
        }
        $b = new Buffer(substr($r, 4));
        $str = function () use ($b) {
            $len = $b->byte();

            return $len > 1 ? $b->bytes($len - 1) : '';
        };
        [$game, $port, $name, $type, $map, $version, $password, $online, $max] = [$str(), $str(), $str(), $str(), $str(), $str(), $str(), $str(), $str()];
        while ($b->remaining() > 0 && $str() !== '') { // rules: key, value ... until an empty key
            $str();
        }
        $players = [];
        while ($b->remaining() > 0) {
            $flags = $b->byte();
            $p = ['name' => '', 'score' => null, 'ping' => null];
            foreach (['name' => 1, 'team' => 2, 'skin' => 4, 'score' => 8, 'ping' => 16, 'time' => 32] as $field => $bit) {
                if ($flags & $bit) {
                    $p[$field] = $str();
                }
            }
            $players[] = new Player(preg_replace('/#[0-9a-fA-F]{6}/', '', $p['name']), null, is_numeric($p['score']) ? (int) $p['score'] : null, null, is_numeric($p['ping']) ? (int) $p['ping'] : null);
        }

        return new QueryResult($players, (int) $online, (int) $max ?: null, $name, $map);
    }
}
