<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('round_games', function (Blueprint $table) {
            $table->index(
                ['home_team_id', 'created_at'],
                'round_games_home_team_created_at_index',
            );
            $table->index(
                ['away_team_id', 'created_at'],
                'round_games_away_team_created_at_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('round_games', function (Blueprint $table) {
            $table->dropIndex('round_games_home_team_created_at_index');
            $table->dropIndex('round_games_away_team_created_at_index');
        });
    }
};
