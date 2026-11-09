<?php

namespace MaitreAntho\PlayerManager\Protocols;

/**
 * Minecraft Java edition: the full query (all player names, needs enable-query=true)
 * and the status ping every server answers (a sample of up to 12 names).
 */
final class MinecraftJava implements QueryProtocol
{
    public function __construct(private readonly ?int $queryPort = null) {}

    public function query(Endpoint $endpoint): QueryResult
    {
        try {
            return $this->fullQuery($endpoint->withPort($this->queryPort ?? $endpoint->port));
        } catch (ProtocolException) {
            return $this->ping($endpoint);
        }
    }

    public function fullQuery(Endpoint $e): QueryResult
    {
        $s = Net::udp($e);
        try {
            $session = pack('N', random_int(1, 0x7FFFFFFF)) & "\x0F\x0F\x0F\x0F";
            Net::write($s, "\xFE\xFD\x09" . $session);
            $r = Net::recv($s);
            $token = (int) rtrim(substr($r, 5), "\0");
            Net::write($s, "\xFE\xFD\x00" . $session . pack('N', $token) . "\0\0\0\0");
            $r = Net::recv($s);
        } finally {
            fclose($s);
        }
        // type(1) session(4) "splitnum\0\x80\0" then key\0value\0 ... \0\0 "\x01player_\0\0" names... \0
        $body = substr($r, 16);
        [$kv, $names] = array_pad(explode("\x00\x01player_\x00\x00", $body, 2), 2, '');
        $parts = explode("\0", $kv);
        $info = [];
        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $info[$parts[$i]] = $parts[$i + 1];
        }
        $players = array_map(fn ($n) => new Player($n), array_values(array_filter(explode("\0", $names), 'strlen')));

        return new QueryResult($players, (int) ($info['numplayers'] ?? count($players)), (int) ($info['maxplayers'] ?? 0) ?: null, $info['hostname'] ?? null, $info['map'] ?? null);
    }

    public function ping(Endpoint $e): QueryResult
    {
        $s = Net::tcp($e);
        try {
            $handshake = "\x00" . self::varint(767) . self::varint(strlen($e->host)) . $e->host . pack('n', $e->port) . "\x01";
            Net::write($s, self::varint(strlen($handshake)) . $handshake . "\x01\x00");
            self::readVarint($s);
            self::readVarint($s);
            $len = self::readVarint($s);
            $json = json_decode(Net::read($s, $len), true);
        } finally {
            fclose($s);
        }
        if (!is_array($json)) {
            throw new ProtocolException('Invalid Minecraft status answer.');
        }
        $online = (int) ($json['players']['online'] ?? 0);
        $sample = array_values(array_filter(
            array_map(fn ($p) => new Player((string) ($p['name'] ?? ''), $p['id'] ?? null), $json['players']['sample'] ?? []),
            // Some servers fill the sample with formatted text lines instead of players.
            fn (Player $p) => preg_match('/^\w{1,16}$/', $p->name) === 1,
        ));
        $motd = is_array($json['description'] ?? null) ? ($json['description']['text'] ?? '') : (string) ($json['description'] ?? '');

        return new QueryResult($sample, $online, (int) ($json['players']['max'] ?? 0) ?: null, preg_replace('/§./u', '', $motd) ?: null, null, count($sample) >= $online);
    }

    private static function varint(int $v): string
    {
        $out = '';
        do {
            $b = $v & 0x7F;
            $v >>= 7;
            $out .= chr($v ? $b | 0x80 : $b);
        } while ($v);

        return $out;
    }

    /** @param resource $s */
    private static function readVarint($s): int
    {
        $v = 0;
        for ($i = 0; $i < 5; $i++) {
            $b = ord(Net::read($s, 1));
            $v |= ($b & 0x7F) << (7 * $i);
            if (!($b & 0x80)) {
                return $v;
            }
        }
        throw new ProtocolException('Invalid VarInt.');
    }
}
