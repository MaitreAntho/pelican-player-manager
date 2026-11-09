<?php

namespace MaitreAntho\PlayerManager\Providers;

use App\Models\Role;
use App\Models\Subuser;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use MaitreAntho\PlayerManager\Console\Commands\CollectCommand;
use MaitreAntho\PlayerManager\Console\Commands\PruneCommand;

class PlayerManagerPluginProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Server subusers: see players, act on them (kick, ban, message...), run raw
        // commands, change the server's game settings. Owners and admins have all four.
        Subuser::registerCustomPermissions('players', ['read', 'manage', 'command', 'configure'], 'player-manager::strings.permissions', 'tabler-users');

        // Admin roles: the overview page of eggs and games.
        Role::registerCustomPermissions(['playerManager' => ['view', 'update']]);
        Role::registerCustomModelIcon('playerManager', 'tabler-users');

        Schedule::command(CollectCommand::class)->everyMinute()->withoutOverlapping(5);
        Schedule::command(PruneCommand::class)->daily();
    }
}
