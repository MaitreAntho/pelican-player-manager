<?php

namespace MaitreAntho\PlayerManager\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Most players at once on a server, for one day.
 *
 * @property int $server_id
 * @property string $day
 * @property int $peak
 */
class Peak extends Model
{
    protected $table = 'player_manager_peaks';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = [];
}
