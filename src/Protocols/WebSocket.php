<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Minimal WebSocket client (text frames, masking, fragments, ping): enough for game RCON. */
final class WebSocket
{
    /** @var resource */
    private $s;

    public function __construct(Endpoint $endpoint, string $path)
    {
        $this->s = Net::tcp($endpoint);
        $key = base64_encode(random_bytes(16));
        Net::write($this->s, "GET $path HTTP/1.1\r\nHost: $endpoint\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n");
        $head = '';
        while (!str_contains($head, "\r\n\r\n")) {
            $c = Net::read($this->s, 1);
            $head .= $c;
            if (strlen($head) > 8192) {
                break;
            }
        }
        if (!preg_match('#^HTTP/1\.1 101#', $head)) {
            // Rust closes the handshake when the password in the path is wrong.
            throw new AuthenticationException('The server refused the RCON password.');
        }
    }

    public function send(string $text): void
    {
        $mask = random_bytes(4);
        $len = strlen($text);
        $header = "\x81" . ($len < 126 ? chr(0x80 | $len) : ($len < 65536 ? chr(0x80 | 126) . pack('n', $len) : chr(0x80 | 127) . pack('J', $len)));
        $masked = '';
        for ($i = 0; $i < $len; $i++) {
            $masked .= $text[$i] ^ $mask[$i % 4];
        }
        Net::write($this->s, $header . $mask . $masked);
    }

    public function receive(): string
    {
        $message = '';
        while (true) {
            [$b1, $b2] = array_values(unpack('C2', Net::read($this->s, 2)));
            $op = $b1 & 0x0F;
            $len = $b2 & 0x7F;
            if ($len === 126) {
                $len = unpack('n', Net::read($this->s, 2))[1];
            } elseif ($len === 127) {
                $len = unpack('J', Net::read($this->s, 8))[1];
            }
            $mask = ($b2 & 0x80) ? Net::read($this->s, 4) : null;
            $data = $len ? Net::read($this->s, $len) : '';
            if ($mask) {
                for ($i = 0; $i < $len; $i++) {
                    $data[$i] = $data[$i] ^ $mask[$i % 4];
                }
            }
            if ($op === 0x8) {
                throw new ProtocolException('Connection closed by the server.');
            }
            if ($op === 0x9) { // ping -> pong
                Net::write($this->s, "\x8A\x80" . random_bytes(4));

                continue;
            }
            $message .= $data;
            if ($b1 & 0x80) {
                return $message;
            }
        }
    }

    public function close(): void
    {
        @stream_socket_sendto($this->s, "\x88\x80" . random_bytes(4));
        @fclose($this->s);
    }
}
