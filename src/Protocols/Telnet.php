<?php

namespace MaitreAntho\PlayerManager\Protocols;

/**
 * Telnet admin consoles: 7 Days to Die and Empyrion. The server asks for the
 * password, then answers each command with plain text lines.
 */
final class Telnet implements CommandProtocol
{
    public function command(Endpoint $endpoint, string $password, string $command): string
    {
        $s = Net::tcp($endpoint);
        try {
            $greeting = $this->readUntil($s, '/password|logon successful|\n\s*$/i', $endpoint->timeout);
            if (preg_match('/password/i', $greeting)) {
                Net::write($s, $password . "\r\n");
                $answer = $this->readUntil($s, '/logon successful|incorrect|wrong|denied|failed/i', $endpoint->timeout);
                if (!preg_match('/logon successful/i', $answer)) {
                    throw new AuthenticationException('The server refused the telnet password.');
                }
            }
            // Let the welcome banner end, then send the command.
            $this->drain($s, 0.3);
            Net::write($s, $command . "\r\n");
            $out = $this->drain($s, 0.6, $endpoint->timeout);
            Net::write($s, "exit\r\n");
        } finally {
            fclose($s);
        }

        // Drop the log lines the server prints between replies (timestamps like 2026-11-09T12:00:00 12.345 INF ...).
        $lines = array_filter(preg_split('/\r?\n/', $out), fn ($l) => trim($l) !== '' && !preg_match('/^\d{4}-\d\d-\d\dT[\d:]+ [\d.]+ (INF|WRN|ERR) /', $l));

        return implode("\n", $lines);
    }

    /** @param resource $s */
    private function readUntil($s, string $pattern, float $timeout): string
    {
        $buf = '';
        $end = microtime(true) + $timeout;
        Net::timeout($s, 0.2);
        while (microtime(true) < $end) {
            $chunk = @fread($s, 8192);
            if ($chunk !== false && $chunk !== '') {
                $buf .= $chunk;
                if (preg_match($pattern, $buf)) {
                    return $buf;
                }
            } elseif (feof($s)) {
                break;
            }
        }
        if ($buf === '') {
            throw new ProtocolException('No answer (timed out).');
        }

        return $buf;
    }

    /** Everything the server sends until it goes quiet. @param resource $s */
    private function drain($s, float $quiet, float $max = 3.0): string
    {
        $buf = '';
        $end = microtime(true) + $max;
        Net::timeout($s, $quiet);
        while (microtime(true) < $end) {
            $chunk = @fread($s, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buf .= $chunk;
        }

        return $buf;
    }
}
