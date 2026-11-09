<?php

namespace MaitreAntho\PlayerManager\Protocols;

/**
 * Source RCON over TCP. Also spoken by Minecraft, ARK, Conan Exiles, Palworld,
 * Project Zomboid, Squad, Factorio, The Isle, V Rising and others.
 *
 * @see https://developer.valvesoftware.com/wiki/Source_RCON_Protocol
 */
final class SourceRcon implements CommandProtocol
{
    private const AUTH = 3;

    private const EXEC = 2;

    public function command(Endpoint $endpoint, string $password, string $command): string
    {
        $s = Net::tcp($endpoint);
        try {
            $this->send($s, 1, self::AUTH, $password);
            // Some servers send an empty RESPONSE_VALUE before the AUTH_RESPONSE.
            for ($i = 0; $i < 3; $i++) {
                [$id, $type] = $this->receive($s);
                if ($type === 2) {
                    if ($id === -1) {
                        throw new AuthenticationException('The server refused the RCON password.');
                    }
                    break;
                }
            }

            $this->send($s, 2, self::EXEC, $command);
            // Multi-packet answers have no end marker that every game honours:
            // read until the server goes quiet for a short moment.
            $out = '';
            Net::timeout($s, $endpoint->timeout);
            try {
                [, , $body] = $this->receive($s);
                $out .= $body;
                Net::timeout($s, 0.35);
                for ($i = 0; $i < 200; $i++) {
                    [, , $body] = $this->receive($s);
                    $out .= $body;
                }
            } catch (ProtocolException) {
                // quiet: the answer is complete
            }

            return $out;
        } finally {
            fclose($s);
        }
    }

    /** @param resource $s */
    private function send($s, int $id, int $type, string $body): void
    {
        $payload = pack('VV', $id, $type) . $body . "\0\0";
        Net::write($s, pack('V', strlen($payload)) . $payload);
    }

    /** @param resource $s @return array{int, int, string} */
    private function receive($s): array
    {
        $size = unpack('V', Net::read($s, 4))[1];
        if ($size < 10 || $size > 1 << 20) {
            throw new ProtocolException('Invalid RCON packet.');
        }
        $b = new Buffer(Net::read($s, $size));
        $id = $b->long();
        $type = $b->long();

        return [$id, $type, rtrim($b->rest(), "\0")];
    }
}
