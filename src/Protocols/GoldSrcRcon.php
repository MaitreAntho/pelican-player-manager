<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Half-Life 1 engine rcon over UDP (challenge + command): CS 1.6, Sven Co-op and other GoldSrc games. */
final class GoldSrcRcon implements CommandProtocol
{
    public function command(Endpoint $endpoint, string $password, string $command): string
    {
        $s = Net::udp($endpoint);
        try {
            Net::write($s, "\xFF\xFF\xFF\xFFchallenge rcon\n");
            if (!preg_match('/challenge rcon (\d+)/', Net::recv($s), $m)) {
                throw new ProtocolException('No rcon challenge.');
            }
            Net::write($s, "\xFF\xFF\xFF\xFFrcon {$m[1]} \"$password\" $command\n");
            $out = '';
            try {
                $out .= substr(Net::recv($s), 5);
                Net::timeout($s, 0.3);
                for ($i = 0; $i < 50; $i++) {
                    $out .= substr(Net::recv($s), 5);
                }
            } catch (ProtocolException) {
            }
        } finally {
            fclose($s);
        }
        if (preg_match('/bad rcon_password/i', $out)) {
            throw new AuthenticationException('The server refused the RCON password.');
        }

        return rtrim($out, "\0\n");
    }
}
