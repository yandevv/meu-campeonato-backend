<?php

namespace Tests\Integration\Services;

use App\Services\TournamentService;
use RuntimeException;
use Tests\IntegrationTestCase;

class TournamentServiceCreateTournamentTest extends IntegrationTestCase
{
    public function test_it_persists_and_returns_the_created_tournament(): void
    {
        $tournament = app(TournamentService::class)->createTournament([
            'name' => 'Champions Cup',
        ]);

        $this->assertModelExists($tournament);
        $this->assertSame('Champions Cup', $tournament->name);
    }

    public function test_it_wraps_database_failures_when_creating_a_tournament(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create tournament:');

        app(TournamentService::class)->createTournament([
            'name' => str_repeat('A', 256),
        ]);
    }
}
