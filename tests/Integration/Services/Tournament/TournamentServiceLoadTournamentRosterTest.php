<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Models\Tournament;
use App\Services\TournamentService;
use Tests\IntegrationTestCase;

class TournamentServiceLoadTournamentRosterTest extends IntegrationTestCase
{
    public function test_it_loads_the_persisted_tournament_roster_from_the_database(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(2)->create();

        $tournament->teams()->attach($teams->modelKeys());

        $this->assertFalse($tournament->relationLoaded('teams'));

        $loadedTournament = app(TournamentService::class)->loadTournamentRoster($tournament);

        $this->assertTrue($tournament->is($loadedTournament));
        $this->assertTrue($loadedTournament->relationLoaded('teams'));
        $this->assertCount(2, $loadedTournament->teams);
        $this->assertEqualsCanonicalizing(
            $teams->modelKeys(),
            $loadedTournament->teams->modelKeys(),
        );
    }
}
