<?php

namespace Tests\Integration\Services;

use App\Enums\RoundPhase;
use App\Models\RoundGame;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\TournamentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\IntegrationTestCase;

class TournamentServiceLoadTournamentSimulationTest extends IntegrationTestCase
{
    public function test_it_loads_the_persisted_simulation_graph_from_the_database(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(8)->create();

        $tournament->teams()->attach($teams->modelKeys());
        $this->createSimulatedTournamentGraph($tournament, $teams->all());

        $loadedTournament = app(TournamentService::class)->loadTournamentSimulation($tournament);

        $this->assertTrue($loadedTournament->relationLoaded('teams'));
        $this->assertTrue($loadedTournament->relationLoaded('rounds'));
        $this->assertCount(8, $loadedTournament->teams);
        $this->assertCount(4, $loadedTournament->rounds);
        $this->assertEqualsCanonicalizing(
            $teams->modelKeys(),
            $loadedTournament->teams->modelKeys(),
        );
        $this->assertSame([
            RoundPhase::QuarterFinals,
            RoundPhase::SemiFinals,
            RoundPhase::ThirdPlace,
            RoundPhase::Finals,
        ], $loadedTournament->rounds->pluck('phase')->all());
        $this->assertSame(
            [4, 2, 1, 1],
            $loadedTournament->rounds->map(fn (TournamentRound $round): int => $round->games->count())->all(),
        );

        foreach ($loadedTournament->rounds as $round) {
            $this->assertTrue($round->relationLoaded('games'));

            foreach ($round->games as $game) {
                $this->assertTrue($game->relationLoaded('homeTeam'));
                $this->assertTrue($game->relationLoaded('awayTeam'));
            }
        }
    }

    public function test_it_throws_not_found_when_the_tournament_has_not_been_simulated_yet(): void
    {
        $tournament = Tournament::factory()->create();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The tournament does not have a simulation yet.');

        app(TournamentService::class)->loadTournamentSimulation($tournament);
    }

    public function test_it_throws_model_not_found_when_the_tournament_no_longer_exists(): void
    {
        $tournament = Tournament::factory()->create();
        $tournament->delete();

        $this->expectException(ModelNotFoundException::class);

        app(TournamentService::class)->loadTournamentSimulation($tournament);
    }

    public function test_it_wraps_database_failures_when_loading_a_tournament_simulation(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(8)->create();
        $originalDefaultConnection = config('database.default');

        $tournament->teams()->attach($teams->modelKeys());
        $this->createSimulatedTournamentGraph($tournament, $teams->all());
        config()->set('database.default', 'missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to load tournament simulation:');

        try {
            app(TournamentService::class)->loadTournamentSimulation($tournament);
        } finally {
            config()->set('database.default', $originalDefaultConnection);
        }
    }

    /**
     * @param  list<Team>  $teams
     */
    private function createSimulatedTournamentGraph(Tournament $tournament, array $teams): void
    {
        $quarterFinals = TournamentRound::factory()
            ->for($tournament)
            ->forPhase(RoundPhase::QuarterFinals, 1)
            ->create();
        $semiFinals = TournamentRound::factory()
            ->for($tournament)
            ->forPhase(RoundPhase::SemiFinals, 2)
            ->create();
        $thirdPlace = TournamentRound::factory()
            ->for($tournament)
            ->forPhase(RoundPhase::ThirdPlace, 3)
            ->create();
        $finals = TournamentRound::factory()
            ->for($tournament)
            ->forPhase(RoundPhase::Finals, 4)
            ->create();

        RoundGame::factory()->forRound($quarterFinals, 1)->create([
            'home_team_id' => $teams[0]->getKey(),
            'away_team_id' => $teams[1]->getKey(),
            'winner_team_id' => $teams[0]->getKey(),
            'home_goals' => 2,
            'away_goals' => 1,
        ]);
        RoundGame::factory()->forRound($quarterFinals, 2)->create([
            'home_team_id' => $teams[2]->getKey(),
            'away_team_id' => $teams[3]->getKey(),
            'winner_team_id' => $teams[2]->getKey(),
            'home_goals' => 1,
            'away_goals' => 0,
        ]);
        RoundGame::factory()->forRound($quarterFinals, 3)->create([
            'home_team_id' => $teams[4]->getKey(),
            'away_team_id' => $teams[5]->getKey(),
            'winner_team_id' => $teams[4]->getKey(),
            'home_goals' => 3,
            'away_goals' => 1,
        ]);
        RoundGame::factory()->forRound($quarterFinals, 4)->create([
            'home_team_id' => $teams[6]->getKey(),
            'away_team_id' => $teams[7]->getKey(),
            'winner_team_id' => $teams[6]->getKey(),
            'home_goals' => 2,
            'away_goals' => 0,
        ]);

        RoundGame::factory()->forRound($semiFinals, 1)->create([
            'home_team_id' => $teams[0]->getKey(),
            'away_team_id' => $teams[2]->getKey(),
            'winner_team_id' => $teams[0]->getKey(),
            'home_goals' => 1,
            'away_goals' => 0,
        ]);
        RoundGame::factory()->forRound($semiFinals, 2)->create([
            'home_team_id' => $teams[4]->getKey(),
            'away_team_id' => $teams[6]->getKey(),
            'winner_team_id' => $teams[6]->getKey(),
            'home_goals' => 0,
            'away_goals' => 1,
        ]);

        RoundGame::factory()->forRound($thirdPlace, 1)->create([
            'home_team_id' => $teams[2]->getKey(),
            'away_team_id' => $teams[4]->getKey(),
            'winner_team_id' => $teams[4]->getKey(),
            'home_goals' => 0,
            'away_goals' => 2,
        ]);

        RoundGame::factory()->forRound($finals, 1)->create([
            'home_team_id' => $teams[0]->getKey(),
            'away_team_id' => $teams[6]->getKey(),
            'winner_team_id' => $teams[0]->getKey(),
            'home_goals' => 2,
            'away_goals' => 1,
        ]);
    }
}
