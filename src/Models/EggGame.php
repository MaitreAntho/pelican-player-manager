<?php

namespace MaitreAntho\PlayerManager\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The game an administrator assigned to an egg.
 *
 * @property int $egg_id
 * @property string $game
 */
class EggGame extends Model
{
    protected $table = 'player_manager_eggs';

    protected $primaryKey = 'egg_id';

    public $incrementing = false;

    protected $guarded = [];
}
