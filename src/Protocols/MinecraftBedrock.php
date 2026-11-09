<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Minecraft Bedrock (RakNet unconnected ping): player count only, Bedrock gives no names. */
final class MinecraftBedrock implements QueryProtocol
{
    private const MAGIC = "\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78";

    public function query(Endpoint $endpoint): QueryResult
    {
        $s = Net::udp($endpoint);
        try {
            Net::write($s, "\x01" . pack('J', (int) (microtime(true) * 1000)) . self::MAGIC . pack('J', random_int(1, PHP_INT_MAX)));
            $r = Net::recv($s);
        } finally {
            fclose($s);
        }
        if ($r[0] !== "\x1c") {
            throw new ProtocolException('Invalid Bedrock answer.');
        }
        $parts = explode(';', substr($r, 35));

        return new QueryResult([], (int) ($parts[4] ?? 0), (int) ($parts[5] ?? 0) ?: null, $parts[1] ?? null, $parts[7] ?? null, false);
    }
}
