<?php

namespace MaitreAntho\PlayerManager\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;
use MaitreAntho\PlayerManager\Models\Peak;
use MaitreAntho\PlayerManager\Models\PlayerSession;
use MaitreAntho\PlayerManager\Services\PlayerService;
use Throwable;

/**
 * Every minute: who is on each server with a known game. Feeds "recently seen"
 * and the daily peak. Only query protocols are used here, never the console, so
 * collecting leaves no trace in server consoles.
 */
class CollectCommand extends Command
{
    protected $signature = 'p:player-manager:collect';

    protected $description = 'Record who is playing on each game server.';

    public function handle(PlayerService $players): int
    {
        if (!config('player-manager.history', true)) {
            return self::SUCCESS;
        }

        $now = now();
        $today = $now->toDateString();

        Server::query()->whereNull('status')->with(['allocation', 'node', 'egg'])->each(function (Server $server) use ($players, $now, $today) {
            try {
                if (!$players->queryTarget($server, 1.5)) {
                    return;
                }
                $result = $players->players($server)['result'];
            } catch (Throwable) {
                return;
            }
            if (!$result) {
                return;
            }

            $peak = Peak::query()->where('server_id', $server->id)->where('day', $today)->first();
            if (!$peak) {
                Peak::query()->insert(['server_id' => $server->id, 'day' => $today, 'peak' => $result->count()]);
            } elseif ($result->count() > $peak->peak) {
                Peak::query()->where('server_id', $server->id)->where('day', $today)->update(['peak' => $result->count()]);
            }

            if (!$result->names) {
                return;
            }
            foreach ($result->players as $p) {
                $open = PlayerSession::query()
                    ->where('server_id', $server->id)->where('name', $p->name)
                    ->where('last_seen_at', '>=', $now->copy()->subMinutes(3))
                    ->latest('last_seen_at')->first();
                if ($open) {
                    $open->update(['last_seen_at' => $now] + ($p->id ? ['player_id' => $p->id] : []));
                } else {
                    PlayerSession::query()->create(['server_id' => $server->id, 'name' => mb_substr($p->name, 0, 190), 'player_id' => $p->id, 'first_seen_at' => $now, 'last_seen_at' => $now]);
                }
            }
        });

        return self::SUCCESS;
    }
}
