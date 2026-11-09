<?php

namespace MaitreAntho\PlayerManager\Protocols;

/**
 * Valve's server query (A2S_INFO + A2S_PLAYER), answered by most Steam dedicated
 * servers: Source and GoldSrc games, Rust, ARK, Valheim, 7 Days to Die, Project
 * Zomboid, V Rising, Arma, DayZ, Conan Exiles, Unturned and many more.
 *
 * @see https://developer.valvesoftware.com/wiki/Server_queries
 */
final class A2s implements QueryProtocol
{
    private const HEADER = "\xFF\xFF\xFF\xFF";

    public function query(Endpoint $endpoint): QueryResult
    {
        $s = Net::udp($endpoint);
        try {
            $info = $this->info($s);
            $players = $this->players($s);
        } finally {
            fclose($s);
        }

        // Some games answer A2S_PLAYER with empty names (DayZ, some Unreal games):
        // the count is then all there is to show.
        $named = array_values(array_filter($players, fn (Player $p) => trim($p->name) !== ''));
        $online = $info['players'] ?? count($players);

        return new QueryResult($named, $online, $info['max'] ?? null, $info['name'] ?? null, $info['map'] ?? null, $named !== [] || $online === 0);
    }

    /** @param resource $s @return array{name: string, map: string, players: int, max: int} */
    private function info($s): array
    {
        $request = self::HEADER . "TSource Engine Query\0";
        Net::write($s, $request);
        $data = $this->receive($s);
        // Since 2020 servers may ask for a challenge before answering.
        if ($data[0] === 'A') {
            Net::write($s, $request . substr($data, 1, 4));
            $data = $this->receive($s);
        }
        $b = new Buffer(substr($data, 1));
        if ($data[0] === 'I') {
            $b->byte();
            $name = $b->string();
            $map = $b->string();
            $b->string();
            $b->string();
            $b->short();

            return ['name' => $name, 'map' => $map, 'players' => $b->byte(), 'max' => $b->byte()];
        }
        if ($data[0] === 'm') { // obsolete GoldSrc answer
            $b->string();
            $name = $b->string();
            $map = $b->string();
            $b->string();
            $b->string();

            return ['name' => $name, 'map' => $map, 'players' => $b->byte(), 'max' => $b->byte()];
        }

        throw new ProtocolException('Unexpected answer to A2S_INFO.');
    }

    /** @param resource $s @return Player[] */
    private function players($s): array
    {
        Net::write($s, self::HEADER . "U\xFF\xFF\xFF\xFF");
        $data = $this->receive($s);
        for ($i = 0; $i < 3 && $data[0] === 'A'; $i++) {
            Net::write($s, self::HEADER . 'U' . substr($data, 1, 4));
            $data = $this->receive($s);
        }
        if ($data[0] !== 'D') {
            return [];
        }
        $b = new Buffer(substr($data, 1));
        $count = $b->byte();
        $players = [];
        for ($i = 0; $i < $count && $b->remaining() > 0; $i++) {
            try {
                $b->byte();
                $name = $b->string();
                $score = $b->long();
                $duration = $b->float();
            } catch (ProtocolException) {
                break;
            }
            $players[] = new Player($name, null, $score, is_finite($duration) ? max(0, (int) $duration) : null);
        }

        return $players;
    }

    /** One answer, reassembled when the server splits it. @param resource $s */
    private function receive($s): string
    {
        $packet = Net::recv($s, 65535);
        $type = unpack('l', substr($packet, 0, 4))[1];
        if ($type === -1) {
            return substr($packet, 4);
        }
        if ($type !== -2) {
            throw new ProtocolException('Unknown A2S packet.');
        }

        // Split answer: id(4) total(1) number(1) [size(2) on Source]. GoldSrc packs
        // total and number in one byte. Collect until every part has arrived.
        $parts = [];
        $total = null;
        $source = null;
        for ($guard = 0; $guard < 64; $guard++) {
            $b = new Buffer(substr($packet, 4));
            $id = $b->long();
            if ($source === null) {
                // On Source the byte after "total" is the part number (< total); on
                // GoldSrc it is the start of the payload or a packed nibble pair.
                $t = ord($packet[8]);
                $n = ord($packet[9]);
                $source = $n < $t && strlen($packet) > 12;
            }
            if ($source) {
                $total = $b->byte();
                $number = $b->byte();
                $b->short();
                if ($id & 0x80000000) {
                    throw new ProtocolException('Compressed A2S answers are not supported.');
                }
            } else {
                $packed = $b->byte();
                $total = $packed & 0x0F;
                $number = $packed >> 4;
            }
            $parts[$number] = $b->rest();
            if (count($parts) >= $total) {
                break;
            }
            $packet = Net::recv($s, 65535);
        }
        ksort($parts);
        $joined = implode('', $parts);

        return substr($joined, 4); // each reassembled answer starts with FF FF FF FF
    }
}
