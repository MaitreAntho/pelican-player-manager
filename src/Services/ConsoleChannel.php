<?php

namespace MaitreAntho\PlayerManager\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use MaitreAntho\PlayerManager\Protocols\ProtocolException;
use Throwable;

/**
 * Commands through the Pelican console: Wings writes them to the game's input, as
 * if typed in the panel console, and the answer is read back from the console log.
 * Needs no password or port, only a running server whose egg passes input on.
 */
class ConsoleChannel
{
    public function run(Server $server, string $command, float $wait = 1.5): string
    {
        $before = $this->logs($server);
        try {
            $response = $server->send($command);
        } catch (Throwable $e) {
            throw new ProtocolException('The server must be running to receive commands.', previous: $e);
        }
        // Wings answers 204 when the command reached the server, 502 when it is offline.
        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 204;
        if ($status >= 300) {
            throw new ProtocolException($status === 502 ? 'The server must be running to receive commands.' : "The node refused the command (HTTP $status).");
        }

        // Poll the log until new lines stop coming, within $wait seconds.
        $new = [];
        $end = microtime(true) + $wait;
        $last = -1;
        while (microtime(true) < $end) {
            usleep(300_000);
            $new = $this->since($before, $this->logs($server));
            if (count($new) > 0 && count($new) === $last) {
                break;
            }
            $last = count($new);
        }

        // Drop the echo of the command itself.
        $new = array_values(array_filter($new, fn ($l) => trim(self::strip($l)) !== trim($command)));

        return implode("\n", array_map([self::class, 'strip'], $new));
    }

    /** @return string[] */
    private function logs(Server $server): array
    {
        try {
            return (array) Http::daemon($server->node)->get("/api/servers/{$server->uuid}/logs", ['size' => 150])->json('data');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Lines of $after that come after the end of $before: find where the last lines
     * we had already seen sit in the new log.
     *
     * @param  string[]  $before
     * @param  string[]  $after
     * @return string[]
     */
    private function since(array $before, array $after): array
    {
        if ($before === []) {
            return $after;
        }
        for ($tail = min(5, count($before)); $tail >= 1; $tail--) {
            $needle = array_slice($before, -$tail);
            for ($i = count($after) - $tail; $i >= 0; $i--) {
                if (array_slice($after, $i, $tail) === $needle) {
                    return array_slice($after, $i + $tail);
                }
            }
        }

        return $after;
    }

    /** Remove terminal colours and Wings' own prefixes. */
    private static function strip(string $line): string
    {
        return preg_replace('/\e\[[\d;?]*[A-Za-z]|\x1b\][^\x07]*\x07/', '', $line);
    }
}
