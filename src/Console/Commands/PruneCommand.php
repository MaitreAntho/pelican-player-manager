<?php

namespace MaitreAntho\PlayerManager\Console\Commands;

use Illuminate\Console\Command;
use MaitreAntho\PlayerManager\Models\Peak;
use MaitreAntho\PlayerManager\Models\PlayerSession;

class PruneCommand extends Command
{
    protected $signature = 'p:player-manager:prune';

    protected $description = 'Delete player history older than the retention period.';

    public function handle(): int
    {
        $before = now()->subDays(max(1, (int) config('player-manager.retention_days', 30)));
        PlayerSession::query()->where('last_seen_at', '<', $before)->delete();
        Peak::query()->where('day', '<', $before->toDateString())->delete();

        return self::SUCCESS;
    }
}
