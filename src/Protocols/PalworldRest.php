<?php

namespace MaitreAntho\PlayerManager\Protocols;

/**
 * Palworld's REST API (RESTAPIEnabled=True): player list, kick, ban, unban and
 * announcements, authenticated with the admin password.
 *
 * @see https://docs.palworldgame.com/category/rest-api
 */
final class PalworldRest implements CommandProtocol, ListsPlayers
{
    public function players(Endpoint $endpoint, string $password): QueryResult
    {
        $json = $this->call($endpoint, $password, 'GET', 'players')['json'];
        $players = array_map(fn ($p) => new Player(
            (string) ($p['name'] ?? '?'),
            (string) ($p['userId'] ?? $p['playerId'] ?? ''),
            isset($p['level']) ? (int) $p['level'] : null,
            null,
            isset($p['ping']) ? (int) round((float) $p['ping']) : null,
        ), $json['players'] ?? []);

        return new QueryResult($players, count($players));
    }

    /**
     * Commands are the REST verbs: "announce <message>", "kick <userId> [reason]",
     * "ban <userId> [reason]", "unban <userId>", "save", "info".
     */
    public function command(Endpoint $endpoint, string $password, string $command): string
    {
        [$verb, $rest] = array_pad(explode(' ', trim($command), 2), 2, '');
        [$target, $reason] = array_pad(explode(' ', $rest, 2), 2, '');
        $r = match (strtolower($verb)) {
            'announce', 'broadcast', 'say' => $this->call($endpoint, $password, 'POST', 'announce', ['message' => $rest]),
            'kick' => $this->call($endpoint, $password, 'POST', 'kick', ['userid' => $target, 'message' => $reason]),
            'ban' => $this->call($endpoint, $password, 'POST', 'ban', ['userid' => $target, 'message' => $reason]),
            'unban' => $this->call($endpoint, $password, 'POST', 'unban', ['userid' => $target]),
            'save' => $this->call($endpoint, $password, 'POST', 'save'),
            'info' => $this->call($endpoint, $password, 'GET', 'info'),
            default => throw new ProtocolException('Palworld only accepts: announce, kick, ban, unban, save, info.'),
        };

        return is_array($r['json']) ? json_encode($r['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'OK';
    }

    /** @param array<string, string>|null $body @return array{status: int, body: string, json: mixed} */
    private function call(Endpoint $endpoint, string $password, string $method, string $path, ?array $body = null): array
    {
        return Net::http($method, "http://$endpoint/v1/api/$path", $endpoint->timeout, [
            'Authorization: Basic ' . base64_encode('admin:' . $password),
            'Content-Type: application/json',
            'Accept: application/json',
        ], $body === null ? null : json_encode($body));
    }
}
