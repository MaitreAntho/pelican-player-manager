<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Assetto Corsa server HTTP port: the entry list with the connected drivers. */
final class AssettoCorsa implements QueryProtocol
{
    public function query(Endpoint $endpoint): QueryResult
    {
        $base = 'http://' . $endpoint;
        $info = Net::http('GET', "$base/INFO", $endpoint->timeout)['json'] ?: [];
        $cars = Net::http('GET', "$base/JSON|" . random_int(1, 99999), $endpoint->timeout)['json']['Cars'] ?? [];
        $players = [];
        foreach ($cars as $c) {
            if (!empty($c['IsConnected'])) {
                $players[] = new Player((string) ($c['DriverName'] ?? '?'), isset($c['DriverGuid']) ? (string) $c['DriverGuid'] : null, null, null, null, array_filter(['car' => $c['Model'] ?? null]));
            }
        }

        return new QueryResult($players, count($players), isset($info['maxclients']) ? (int) $info['maxclients'] : null, $info['name'] ?? null, $info['track'] ?? null);
    }
}
