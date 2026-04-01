<?php

namespace Tests\Integration\Services;

use App\Enums\RoundPhase;
use App\Models\RoundGame;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\TournamentService;
use RuntimeException;
use Tests\IntegrationTestCase;

class TournamentServiceDeleteTournamentTest extends IntegrationTestCase
{
    public function test_it_deletes_a_persisted_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        app(TournamentService::class)->deleteTournament($tournament);

        $this->assertModelMissing($tournament);
    }

    public function test_it_removes_linked_roster_and_simulation_records_when_deleting_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(2)->create();

        $tournament->teams()->attach($teams->modelKeys());

        $round = TournamentRound::factory()
            ->for($tournament, 'tournament')
            ->forPhase(RoundPhase::QuarterFinals, 1)
            ->create();

        $game = RoundGame::factory()
            ->forRound($round, 1)
            ->create([
                'home_team_id' => $teams[0]->getKey(),
                'away_team_id' => $teams[1]->getKey(),
                'winner_team_id' => $teams[0]->getKey(),
            ]);

        app(TournamentService::class)->deleteTournament($tournament);

        $this->assertModelMissing($tournament);
        $this->assertModelMissing($round);
        $this->assertModelMissing($game);
        $this->assertDatabaseMissing('tournament_teams', [
            'tournament_id' => $tournament->getKey(),
            'team_id' => $teams[0]->getKey(),
        ]);
        $this->assertDatabaseMissing('tournament_teams', [
            'tournament_id' => $tournament->getKey(),
            'team_id' => $teams[1]->getKey(),
        ]);
    }

    public function test_it_wraps_database_failures_when_deleting_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $originalConnectionName = $tournament->getConnectionName();

        $tournament->setConnection('missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to delete tournament:');

        try {
            app(TournamentService::class)->deleteTournament($tournament);
        } finally {
            $tournament->setConnection($originalConnectionName);
            $this->assertModelExists(Tournament::query()->findOrFail($tournament->getKey()));
        }
    }
}
