<?php

namespace Tests\Integration\Services;

use App\Models\Tournament;
use App\Services\TournamentService;
use RuntimeException;
use Tests\IntegrationTestCase;

class TournamentServiceUpdateTournamentTest extends IntegrationTestCase
{
    public function test_it_persists_and_returns_the_updated_tournament(): void
    {
        $tournament = Tournament::factory()->create([
            'name' => 'Champions Cup',
        ]);

        $updatedTournament = app(TournamentService::class)->updateTournament($tournament, [
            'name' => 'Legends Cup',
        ]);

        $this->assertModelExists($updatedTournament);
        $this->assertSame($tournament->getKey(), $updatedTournament->getKey());
        $this->assertSame('Legends Cup', $updatedTournament->name);
        $this->assertSame('Legends Cup', $tournament->fresh()->name);
    }

    public function test_it_wraps_database_failures_when_updating_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update tournament:');

        app(TournamentService::class)->updateTournament($tournament, [
            'name' => str_repeat('A', 256),
        ]);
    }
}
