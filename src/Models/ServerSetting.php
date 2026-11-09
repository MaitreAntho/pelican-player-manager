<?php

namespace MaitreAntho\PlayerManager\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-server overrides of what the plugin detects.
 *
 * @property int $server_id
 * @property string|null $game
 * @property int|null $query_port
 * @property int|null $rcon_port
 * @property string|null $rcon_password
 */
class ServerSetting extends Model
{
    protected $table = 'player_manager_servers';

    protected $primaryKey = 'server_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rcon_password' => 'encrypted', 'query_port' => 'integer', 'rcon_port' => 'integer'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
