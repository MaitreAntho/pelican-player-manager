<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** FiveM and RedM: the public players.json / dynamic.json of the server. */
final class FivemHttp implements QueryProtocol
{
    public function query(Endpoint $endpoint): QueryResult
    {
        $base = 'http://' . $endpoint;
        $players = Net::http('GET', "$base/players.json", $endpoint->timeout)['json'];
        if (!is_array($players)) {
            throw new ProtocolException('Invalid players.json.');
        }
        $dynamic = [];
        try {
            $dynamic = Net::http('GET', "$base/dynamic.json", $endpoint->timeout)['json'] ?: [];
        } catch (ProtocolException) {
        }

        $list = array_map(function ($p) {
            $ids = [];
            foreach ($p['identifiers'] ?? [] as $i) {
                [$kind] = explode(':', (string) $i, 2);
                $ids[$kind] ??= (string) $i;
            }

            return new Player((string) ($p['name'] ?? '?'), (string) ($p['id'] ?? ''), null, null, isset($p['ping']) ? (int) $p['ping'] : null, array_filter([
                'license' => $ids['license'] ?? null,
                'discord' => $ids['discord'] ?? null,
                'steam' => $ids['steam'] ?? null,
            ]));
        }, $players);

        return new QueryResult($list, count($list), isset($dynamic['sv_maxclients']) ? (int) $dynamic['sv_maxclients'] : null, isset($dynamic['hostname']) ? preg_replace('/\^\d/', '', $dynamic['hostname']) : null, $dynamic['mapname'] ?? null);
    }
}
