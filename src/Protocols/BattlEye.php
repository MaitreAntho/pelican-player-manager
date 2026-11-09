<?php

namespace MaitreAntho\PlayerManager\Protocols;

/**
 * BattlEye RCon over UDP: DayZ, Arma 2, Arma 3, Arma Reforger (BattlEye mode).
 *
 * @see https://www.battleye.com/downloads/BERConProtocol.txt
 */
final class BattlEye implements CommandProtocol, ListsPlayers
{
    public function command(Endpoint $endpoint, string $password, string $command): string
    {
        $s = Net::udp($endpoint);
        try {
            Net::write($s, $this->packet("\x00" . $password));
            $this->expectLogin($s);
            Net::write($s, $this->packet("\x01\x00" . $command));

            $parts = [];
            $count = null;
            $end = microtime(true) + $endpoint->timeout;
            while (microtime(true) < $end) {
                $p = $this->read($s);
                if ($p === null) {
                    continue;
                }
                [$type, $payload] = $p;
                if ($type === 0x02) { // server message: acknowledge it
                    Net::write($s, $this->packet("\x02" . $payload[0]));

                    continue;
                }
                if ($type !== 0x01 || $payload[0] !== "\x00") {
                    continue;
                }
                $body = substr($payload, 1);
                if (strlen($body) >= 3 && $body[0] === "\x00") { // multipart: 00 count index
                    $count = ord($body[1]);
                    $parts[ord($body[2])] = substr($body, 3);
                    if (count($parts) >= $count) {
                        break;
                    }

                    continue;
                }

                return $body;
            }
            ksort($parts);

            return implode('', $parts);
        } finally {
            fclose($s);
        }
    }

    public function players(Endpoint $endpoint, string $password): QueryResult
    {
        $players = [];
        // "#  IP Address:Port  Ping  GUID  Name" then one line per player.
        foreach (explode("\n", $this->command($endpoint, $password, 'players')) as $line) {
            if (preg_match('/^(\d+)\s+(\S+):\d+\s+(-?\d+)\s+([0-9a-f]{32})(?:\(\w+\))?\s+(.+?)(?:\s+\(Lobby\))?$/i', trim($line), $m)) {
                $players[] = new Player(trim($m[5]), $m[1], null, null, (int) $m[3], ['guid' => $m[4]]);
            }
        }

        return new QueryResult($players, count($players));
    }

    private function packet(string $payload): string
    {
        $body = "\xFF" . $payload;

        return 'BE' . pack('V', crc32($body)) . $body;
    }

    /** @param resource $s @return array{int, string}|null */
    private function read($s): ?array
    {
        try {
            $r = Net::recv($s);
        } catch (ProtocolException) {
            return null;
        }
        if (strlen($r) < 8 || substr($r, 0, 2) !== 'BE' || $r[6] !== "\xFF") {
            return null;
        }

        return [ord($r[7]), substr($r, 8)];
    }

    /** @param resource $s */
    private function expectLogin($s): void
    {
        $p = $this->read($s);
        if ($p === null || $p[0] !== 0x00) {
            throw new ProtocolException('No answer (timed out).');
        }
        if (($p[1][0] ?? "\x00") !== "\x01") {
            throw new AuthenticationException('The server refused the RCon password.');
        }
    }
}
