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
        Schema::create('round_games', function (Blueprint $table) {
            $table->uuid('id')
                ->primary();
            $table->foreignUuid('tournament_round_id')
                ->constrained('tournament_rounds')
                ->cascadeOnDelete();
            $table->foreignUuid('home_team_id')
                ->constrained('teams')
                ->restrictOnDelete();
            $table->foreignUuid('away_team_id')
                ->constrained('teams')
                ->restrictOnDelete();
            $table->foreignUuid('winner_team_id')
                ->constrained('teams')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('home_goals');
            $table->unsignedTinyInteger('away_goals');
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['tournament_round_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_games');
    }
};
