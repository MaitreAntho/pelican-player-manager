<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-server overrides: game, ports and an RCON password kept encrypted.
        Schema::create('player_manager_servers', function (Blueprint $table) {
            $table->unsignedInteger('server_id')->primary();
            $table->string('game')->nullable();
            $table->unsignedInteger('query_port')->nullable();
            $table->unsignedInteger('rcon_port')->nullable();
            $table->text('rcon_password')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        // Game chosen by an administrator for an egg, when detection is wrong.
        Schema::create('player_manager_eggs', function (Blueprint $table) {
            $table->unsignedInteger('egg_id')->primary();
            $table->string('game');
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });

        // Who played, and when: one row per stay on a server.
        Schema::create('player_manager_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->string('name');
            $table->string('player_id')->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');

            $table->index(['server_id', 'last_seen_at']);
            $table->index(['server_id', 'name']);
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        // Most players at once, per server and day.
        Schema::create('player_manager_peaks', function (Blueprint $table) {
            $table->unsignedInteger('server_id');
            $table->date('day');
            $table->unsignedSmallInteger('peak')->default(0);

            $table->primary(['server_id', 'day']);
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_manager_peaks');
        Schema::dropIfExists('player_manager_sessions');
        Schema::dropIfExists('player_manager_eggs');
        Schema::dropIfExists('player_manager_servers');
    }
};
