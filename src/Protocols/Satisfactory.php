<?php

namespace MaitreAntho\PlayerManager\Protocols;

/** Satisfactory's HTTPS API (game port, TCP): player count only, the game shares no names. */
final class Satisfactory implements QueryProtocol
{
    public function query(Endpoint $endpoint): QueryResult
    {
        $url = "https://$endpoint/api/v1";
        $login = Net::http('POST', $url, $endpoint->timeout, ['Content-Type: application/json'], json_encode(['function' => 'PasswordlessLogin', 'data' => ['MinimumPrivilegeLevel' => 'Client']]))['json'];
        $token = $login['data']['authenticationToken'] ?? null;
        if (!$token) {
            throw new AuthenticationException('The server needs a client password to share its state.');
        }
        $state = Net::http('POST', $url, $endpoint->timeout, ['Content-Type: application/json', "Authorization: Bearer $token"], json_encode(['function' => 'QueryServerState']))['json'];
        $g = $state['data']['serverGameState'] ?? [];

        return new QueryResult([], (int) ($g['numConnectedPlayers'] ?? 0), isset($g['playerLimit']) ? (int) $g['playerLimit'] : null, $g['activeSessionName'] ?? null, null, false);
    }
}
