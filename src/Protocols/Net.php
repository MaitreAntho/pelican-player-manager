<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Small socket helpers shared by every protocol. */
final class Net
{
    /** @return resource */
    public static function udp(Endpoint $e)
    {
        $s = @stream_socket_client('udp://' . $e, $errno, $err, $e->timeout);
        if (!$s) {
            throw new ProtocolException("Cannot open UDP socket to $e: $err");
        }
        self::timeout($s, $e->timeout);

        return $s;
    }

    /** @return resource */
    public static function tcp(Endpoint $e)
    {
        $s = @stream_socket_client('tcp://' . $e, $errno, $err, $e->timeout);
        if (!$s) {
            throw new ProtocolException("Cannot connect to $e: " . ($err ?: 'connection refused'));
        }
        self::timeout($s, $e->timeout);

        return $s;
    }

    /** @param resource $s */
    public static function timeout($s, float $seconds): void
    {
        stream_set_timeout($s, (int) $seconds, (int) (fmod($seconds, 1) * 1e6));
    }

    /** One datagram, or an exception after the timeout. @param resource $s */
    public static function recv($s, int $len = 65535): string
    {
        $r = @fread($s, $len);
        $meta = stream_get_meta_data($s);
        if ($r === false || $r === '' || $meta['timed_out']) {
            throw new ProtocolException('No answer (timed out).');
        }

        return $r;
    }

    /** Exactly $len bytes from a stream. @param resource $s */
    public static function read($s, int $len): string
    {
        $out = '';
        while (strlen($out) < $len) {
            $chunk = @fread($s, $len - strlen($out));
            $meta = stream_get_meta_data($s);
            if ($chunk === false || ($chunk === '' && ($meta['timed_out'] || feof($s)))) {
                throw new ProtocolException($meta['timed_out'] ? 'No answer (timed out).' : 'Connection closed by the server.');
            }
            $out .= $chunk;
        }

        return $out;
    }

    /** @param resource $s */
    public static function write($s, string $data): void
    {
        if (@fwrite($s, $data) === false) {
            throw new ProtocolException('Cannot send to the server.');
        }
    }

    /**
     * Plain HTTP(S) request, kept on PHP streams so it runs anywhere.
     *
     * @param  string[]  $headers
     * @return array{status: int, body: string, json: mixed}
     */
    public static function http(string $method, string $url, float $timeout, array $headers = [], ?string $body = null): array
    {
        $ctx = stream_context_create([
            'http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '', 'timeout' => $timeout, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new ProtocolException('No answer from the server.');
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($status === 401 || $status === 403) {
            throw new AuthenticationException('The server refused the password.');
        }
        if ($status >= 400) {
            throw new ProtocolException("HTTP $status from the server.");
        }

        return ['status' => $status, 'body' => $raw, 'json' => json_decode($raw, true)];
    }
}
