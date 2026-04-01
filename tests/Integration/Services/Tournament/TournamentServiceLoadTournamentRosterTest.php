<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Models\Tournament;
use App\Services\TournamentService;
use RuntimeException;
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

    public function test_it_wraps_database_failures_when_loading_a_tournament_roster(): void
    {
        $tournament = Tournament::factory()->create();
        $team = Team::factory()->create();
        $originalConnectionName = $tournament->getConnectionName();

        $tournament->teams()->attach($team);
        $tournament->setConnection('missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to load tournament roster:');

        try {
            app(TournamentService::class)->loadTournamentRoster($tournament);
        } finally {
            $tournament->setConnection($originalConnectionName);

            $this->assertSame(
                [$team->getKey()],
                $tournament->fresh()->teams()->pluck('teams.id')->all(),
            );
        }
    }
}
