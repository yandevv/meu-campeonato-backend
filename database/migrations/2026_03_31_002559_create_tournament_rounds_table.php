<?php

use App\Enums\RoundPhase;
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
        Schema::create('tournament_rounds', function (Blueprint $table) {
            $table->uuid('id')
                ->primary();
            $table->foreignUuid('tournament_id')
                ->constrained('tournaments')
                ->cascadeOnDelete();
            $table->enum('phase', RoundPhase::values());
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['tournament_id', 'phase']);
            $table->unique(['tournament_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_rounds');
    }
};
