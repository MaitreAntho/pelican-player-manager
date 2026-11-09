<?php

namespace MaitreAntho\PlayerManager\Protocols;

/**
 * Terraria with TShock: REST API with an application token (ApplicationRestTokens
 * in config.json). Lists players and runs any server command.
 */
final class TShockRest implements CommandProtocol, ListsPlayers
{
    public function players(Endpoint $endpoint, string $password): QueryResult
    {
        $json = $this->get($endpoint, '/v2/server/status', ['players' => 'true', 'token' => $password]);
        $players = array_map(fn ($p) => new Player((string) ($p['nickname'] ?? $p['username'] ?? '?'), isset($p['index']) ? (string) $p['index'] : null), $json['players'] ?? []);

        return new QueryResult($players, (int) ($json['playercount'] ?? count($players)), isset($json['maxplayers']) ? (int) $json['maxplayers'] : null, $json['name'] ?? null, $json['world'] ?? null);
    }

    public function command(Endpoint $endpoint, string $password, string $command): string
    {
        $json = $this->get($endpoint, '/v3/server/rawcmd', ['cmd' => '/' . ltrim($command, '/'), 'token' => $password]);

        return implode("\n", (array) ($json['response'] ?? []));
    }

    /** @param array<string, string> $query @return array<string, mixed> */
    private function get(Endpoint $endpoint, string $path, array $query): array
    {
        $json = Net::http('GET', "http://$endpoint$path?" . http_build_query($query), $endpoint->timeout)['json'];
        if (!is_array($json)) {
            throw new ProtocolException('Invalid TShock answer.');
        }
        if ((string) ($json['status'] ?? '200') === '403' || (string) ($json['status'] ?? '') === '401') {
            throw new AuthenticationException('TShock refused the REST token.');
        }

        return $json;
    }
}
