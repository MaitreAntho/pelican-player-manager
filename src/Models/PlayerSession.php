<?php

namespace MaitreAntho\PlayerManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One stay of a player on a server.
 *
 * @property int $id
 * @property int $server_id
 * @property string $name
 * @property string|null $player_id
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
class PlayerSession extends Model
{
    protected $table = 'player_manager_sessions';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
