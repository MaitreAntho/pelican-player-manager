<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Rust's WebRCON: JSON over a WebSocket at ws://host:port/password. */
final class RustWebRcon implements CommandProtocol, ListsPlayers
{
    public function command(Endpoint $endpoint, string $password, string $command): string
    {
        return (string) ($this->call($endpoint, $password, $command)['Message'] ?? '');
    }

    public function players(Endpoint $endpoint, string $password): QueryResult
    {
        $list = json_decode((string) ($this->call($endpoint, $password, 'playerlist')['Message'] ?? ''), true);
        if (!is_array($list)) {
            throw new ProtocolException('Invalid player list.');
        }
        $players = array_map(fn ($p) => new Player(
            (string) ($p['DisplayName'] ?? '?'),
            isset($p['SteamID']) ? (string) $p['SteamID'] : null,
            null,
            isset($p['ConnectedSeconds']) ? (int) $p['ConnectedSeconds'] : null,
            isset($p['Ping']) ? (int) $p['Ping'] : null,
            array_filter(['health' => isset($p['Health']) ? (int) $p['Health'] : null]),
        ), $list);

        return new QueryResult($players, count($players));
    }

    /** @return array<string, mixed> */
    private function call(Endpoint $endpoint, string $password, string $command): array
    {
        $ws = new WebSocket($endpoint, '/' . rawurlencode($password));
        try {
            $id = random_int(1000, 999999);
            $ws->send(json_encode(['Identifier' => $id, 'Message' => $command, 'Name' => 'PlayerManager']));
            $end = microtime(true) + $endpoint->timeout + 2;
            while (microtime(true) < $end) {
                $msg = json_decode($ws->receive(), true);
                if (is_array($msg) && ($msg['Identifier'] ?? null) === $id) {
                    return $msg;
                }
            }
        } finally {
            $ws->close();
        }
        throw new ProtocolException('No answer to the command.');
    }
}
