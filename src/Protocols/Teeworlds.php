<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Teeworlds 0.6 and DDNet server info ("gie3"): players with their score and clan. */
final class Teeworlds implements QueryProtocol
{
    public function query(Endpoint $endpoint): QueryResult
    {
        $s = Net::udp($endpoint);
        try {
            $token = random_int(1, 255);
            Net::write($s, str_repeat("\xFF", 10) . 'gie3' . chr($token));
            $r = Net::recv($s);
        } finally {
            fclose($s);
        }
        $pos = strpos($r, 'inf3');
        if ($pos === false) {
            throw new ProtocolException('Invalid Teeworlds answer.');
        }
        $f = explode("\0", substr($r, $pos + 4));
        // token, version, name, map, gametype, flags, players, maxplayers, clients, maxclients, then 5 fields per client
        $players = [];
        for ($i = 10; $i + 4 < count($f); $i += 5) {
            if ($f[$i] === '') {
                break;
            }
            $players[] = new Player($f[$i], null, (int) $f[$i + 3], null, null, array_filter(['clan' => $f[$i + 1], 'spectator' => $f[$i + 4] === '0' ? 'yes' : null]));
        }

        return new QueryResult($players, (int) ($f[8] ?? count($players)), (int) ($f[9] ?? 0) ?: null, $f[2] ?? null, $f[3] ?? null);
    }
}
