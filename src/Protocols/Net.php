<?php

namespace MaitreAntho\PlayerManager\Protocols;

use Illuminate\Support\Facades\Http;
use Throwable;

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

    /** Sends everything on a socket (TCP may take several calls, UDP sends one datagram). @param resource $s */
    public static function write($s, string $data): void
    {
        $sent = 0;
        $len = strlen($data);
        while ($sent < $len) {
            $n = @stream_socket_sendto($s, substr($data, $sent));
            if ($n === false || $n < 0) {
                throw new ProtocolException('Cannot send to the server.');
            }
            if ($n === 0) {
                usleep(10_000);
            }
            $sent += $n;
        }
    }

    /**
     * HTTP(S) request through Laravel's client. Game servers use self-signed
     * certificates (Satisfactory), so they are not verified.
     *
     * @param  string[]  $headers  "Name: value" lines
     * @return array{status: int, body: string, json: mixed}
     */
    public static function http(string $method, string $url, float $timeout, array $headers = [], ?string $body = null): array
    {
        $h = [];
        foreach ($headers as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
            $h[$name] = $value;
        }
        try {
            $request = Http::withHeaders($h)->timeout((int) ceil($timeout))->connectTimeout((int) ceil($timeout))->withoutVerifying();
            if ($body !== null) {
                $request = $request->withBody($body, $h['Content-Type'] ?? 'application/json');
            }
            $response = $request->send($method, $url);
        } catch (Throwable) {
            throw new ProtocolException('No answer from the server.');
        }
        $status = $response->status();
        if ($status === 401 || $status === 403) {
            throw new AuthenticationException('The server refused the password.');
        }
        if ($status >= 400) {
            throw new ProtocolException("HTTP $status from the server.");
        }

        return ['status' => $status, 'body' => $response->body(), 'json' => json_decode($response->body(), true)];
    }
}
