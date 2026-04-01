<?php

namespace Tests\Integration\Services;

use App\Models\Tournament;
use App\Services\TournamentService;
use Tests\IntegrationTestCase;

class TournamentServiceGetAllTournamentsTest extends IntegrationTestCase
{
    public function test_it_returns_an_empty_collection_when_no_tournaments_exist(): void
    {
        $tournaments = app(TournamentService::class)->getAllTournaments();

        $this->assertCount(0, $tournaments);
    }

    public function test_it_returns_persisted_tournaments_from_the_database(): void
    {
        $createdTournaments = Tournament::factory()->count(2)->create();

        $tournaments = app(TournamentService::class)->getAllTournaments();

        $this->assertCount(2, $tournaments);
        $this->assertEqualsCanonicalizing(
            $createdTournaments->modelKeys(),
            $tournaments->modelKeys(),
        );
        $this->assertEqualsCanonicalizing(
            $createdTournaments->pluck('name')->all(),
            $tournaments->pluck('name')->all(),
        );
    }
}
