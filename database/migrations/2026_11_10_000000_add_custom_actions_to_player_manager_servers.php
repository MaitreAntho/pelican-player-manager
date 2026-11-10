<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Commands the owner adds to each player's menu (label + command with {name}, {id}, {text}).
        Schema::table('player_manager_servers', function (Blueprint $table) {
            $table->json('custom_actions')->nullable()->after('rcon_password');
        });
    }

    public function down(): void
    {
        Schema::table('player_manager_servers', function (Blueprint $table) {
            $table->dropColumn('custom_actions');
        });
    }
};
