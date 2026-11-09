<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** SA-MP and open.mp query ("SAMP" + address + opcode): info and the client list with scores. */
final class Samp implements QueryProtocol
{
    public function query(Endpoint $endpoint): QueryResult
    {
        $s = Net::udp($endpoint);
        try {
            $ip = gethostbyname($endpoint->host);
            $head = 'SAMP' . implode('', array_map('chr', array_map('intval', explode('.', $ip)))) . pack('v', $endpoint->port);
            Net::write($s, $head . 'i');
            $info = new Buffer(substr(Net::recv($s), 11));
            $info->byte();
            $online = $info->short();
            $max = $info->short();
            $name = $info->bytes($info->ulong());

            $players = [];
            if ($online > 0 && $online <= 100) { // the client list is only sent below 100 players
                Net::write($s, $head . 'c');
                $b = new Buffer(substr(Net::recv($s), 11));
                $count = $b->short();
                for ($i = 0; $i < $count; $i++) {
                    $n = $b->bytes($b->byte());
                    $players[] = new Player(mb_convert_encoding($n, 'UTF-8', 'Windows-1252'), null, $b->long());
                }
            }
        } finally {
            fclose($s);
        }

        return new QueryResult($players, $online, $max, mb_convert_encoding($name, 'UTF-8', 'Windows-1252'), null, $players !== [] || $online === 0);
    }
}
