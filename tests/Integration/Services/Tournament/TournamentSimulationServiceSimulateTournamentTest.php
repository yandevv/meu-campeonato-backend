<?php

namespace Tests\Integration\Services;

use App\Enums\RoundPhase;
use App\Models\RoundGame;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\PythonGoalScorePredictor;
use App\Services\TournamentSimulationRoundBuilder;
use App\Services\TournamentSimulationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\IntegrationTestCase;

class TournamentSimulationServiceSimulateTournamentTest extends IntegrationTestCase
{
    public function test_it_simulates_a_tournament_with_the_real_round_builder_and_persists_a_valid_bracket(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(8)->create();

        $tournament->teams()->attach($teams->modelKeys());

        $this->mock(PythonGoalScorePredictor::class, function (MockInterface $mock) use ($tournament, $teams): void {
            $tournamentTeamOrder = array_flip($teams->modelKeys());

            $mock->shouldReceive('predict')
                ->times(8)
                ->withArgs(fn (string $tournamentId, RoundPhase $roundPhase, string $homeTeamId, string $awayTeamId): bool => $tournamentId === $tournament->getKey()
                    && isset($tournamentTeamOrder[$homeTeamId], $tournamentTeamOrder[$awayTeamId]))
                ->andReturnUsing(function (
                    string $tournamentId,
                    RoundPhase $roundPhase,
                    string $homeTeamId,
                    string $awayTeamId,
                ) use ($tournamentTeamOrder): array {
                    $homeTeamOrder = $tournamentTeamOrder[$homeTeamId];
                    $awayTeamOrder = $tournamentTeamOrder[$awayTeamId];
                    $homeTeamHasPriority = $homeTeamOrder <= $awayTeamOrder;

                    return match ($roundPhase) {
                        RoundPhase::QuarterFinals => $homeTeamHasPriority
                            ? ['home_goals' => 1, 'away_goals' => 0]
                            : ['home_goals' => 0, 'away_goals' => 1],
                        RoundPhase::SemiFinals => min($homeTeamOrder, $awayTeamOrder) === 0
                            ? ($homeTeamHasPriority
                                ? ['home_goals' => 3, 'away_goals' => 0]
                                : ['home_goals' => 0, 'away_goals' => 3])
                            : ($homeTeamHasPriority
                                ? ['home_goals' => 1, 'away_goals' => 0]
                                : ['home_goals' => 0, 'away_goals' => 1]),
                        RoundPhase::ThirdPlace => ['home_goals' => 0, 'away_goals' => 0],
                        RoundPhase::Finals => ['home_goals' => 1, 'away_goals' => 1],
                    };
                });
        });

        $simulatedTournament = app(TournamentSimulationService::class)->simulateTournament($tournament);

        $this->assertTrue($simulatedTournament->relationLoaded('teams'));
        $this->assertTrue($simulatedTournament->relationLoaded('rounds'));
        $this->assertCount(8, $simulatedTournament->teams);
        $this->assertCount(4, $simulatedTournament->rounds);
        $this->assertSame([
            RoundPhase::QuarterFinals,
            RoundPhase::SemiFinals,
            RoundPhase::ThirdPlace,
            RoundPhase::Finals,
        ], $simulatedTournament->rounds->pluck('phase')->all());
        $this->assertSame([4, 2, 1, 1], $simulatedTournament->rounds->map(fn (TournamentRound $round): int => $round->games->count())->all());

        foreach ($simulatedTournament->rounds as $round) {
            $this->assertTrue($round->relationLoaded('games'));

            foreach ($round->games as $game) {
                $this->assertTrue($game->relationLoaded('homeTeam'));
                $this->assertTrue($game->relationLoaded('awayTeam'));
                $this->assertContains($game->winner_team_id, [
                    $game->home_team_id,
                    $game->away_team_id,
                ]);
            }
        }

        $quarterFinalGames = $simulatedTournament->rounds[0]->games;
        $semiFinalGames = $simulatedTournament->rounds[1]->games;
        $thirdPlaceGame = $simulatedTournament->rounds[2]->games->sole();
        $finalGame = $simulatedTournament->rounds[3]->games->sole();

        $this->assertEqualsCanonicalizing(
            $teams->modelKeys(),
            [
                ...$quarterFinalGames->pluck('home_team_id')->all(),
                ...$quarterFinalGames->pluck('away_team_id')->all(),
            ],
        );
        $this->assertEqualsCanonicalizing(
            $quarterFinalGames->pluck('winner_team_id')->all(),
            [
                ...$semiFinalGames->pluck('home_team_id')->all(),
                ...$semiFinalGames->pluck('away_team_id')->all(),
            ],
        );
        $this->assertEqualsCanonicalizing(
            $semiFinalGames->pluck('winner_team_id')->all(),
            [$finalGame->home_team_id, $finalGame->away_team_id],
        );
        $this->assertEqualsCanonicalizing(
            $semiFinalGames->map(fn (RoundGame $game): string => $game->winner_team_id === $game->home_team_id ? $game->away_team_id : $game->home_team_id)->all(),
            [$thirdPlaceGame->home_team_id, $thirdPlaceGame->away_team_id],
        );
        $this->assertSame($teams[0]->getKey(), $finalGame->winner_team_id);
        $this->assertSame(4, $tournament->fresh()->rounds()->count());
        $this->assertSame(8, RoundGame::query()->whereHas('round', fn ($query) => $query->where('tournament_id', $tournament->getKey()))->count());
    }

    public function test_it_persists_a_new_simulation_and_returns_the_loaded_graph(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(8)->create();

        $tournament->teams()->attach($teams->modelKeys());

        $rounds = $this->simulationRoundsFor($teams);

        $this->mock(TournamentSimulationRoundBuilder::class, function (MockInterface $mock) use ($tournament, $teams, $rounds): void {
            $mock->shouldReceive('build')
                ->once()
                ->withArgs(fn (Tournament $boundTournament, $boundTeams): bool => $boundTournament->is($tournament)
                    && $boundTeams->modelKeys() === $teams->modelKeys())
                ->andReturn($rounds);
        });

        $simulatedTournament = app(TournamentSimulationService::class)->simulateTournament($tournament);

        $this->assertTrue($simulatedTournament->relationLoaded('teams'));
        $this->assertTrue($simulatedTournament->relationLoaded('rounds'));
        $this->assertCount(8, $simulatedTournament->teams);
        $this->assertCount(4, $simulatedTournament->rounds);
        $this->assertSame([
            RoundPhase::QuarterFinals,
            RoundPhase::SemiFinals,
            RoundPhase::ThirdPlace,
            RoundPhase::Finals,
        ], $simulatedTournament->rounds->pluck('phase')->all());
        $this->assertSame([4, 2, 1, 1], $simulatedTournament->rounds->map(fn (TournamentRound $round): int => $round->games->count())->all());

        foreach ($simulatedTournament->rounds as $round) {
            $this->assertTrue($round->relationLoaded('games'));

            foreach ($round->games as $game) {
                $this->assertTrue($game->relationLoaded('homeTeam'));
                $this->assertTrue($game->relationLoaded('awayTeam'));
            }
        }

        $this->assertSame(4, $tournament->fresh()->rounds()->count());
        $this->assertSame(8, RoundGame::query()->whereHas('round', fn ($query) => $query->where('tournament_id', $tournament->getKey()))->count());
    }

    public function test_it_replaces_an_existing_simulation_instead_of_appending_to_it(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(8)->create();

        $tournament->teams()->attach($teams->modelKeys());

        $oldRound = TournamentRound::factory()
            ->for($tournament)
            ->forPhase(RoundPhase::QuarterFinals, 1)
            ->create();
        $oldGame = RoundGame::factory()->forRound($oldRound, 1)->create([
            'home_team_id' => $teams[0]->getKey(),
            'away_team_id' => $teams[1]->getKey(),
            'winner_team_id' => $teams[0]->getKey(),
        ]);

        $this->mock(TournamentSimulationRoundBuilder::class, function (MockInterface $mock) use ($teams): void {
            $mock->shouldReceive('build')
                ->once()
                ->andReturn($this->simulationRoundsFor($teams));
        });

        app(TournamentSimulationService::class)->simulateTournament($tournament);

        $this->assertModelMissing($oldRound);
        $this->assertModelMissing($oldGame);
        $this->assertSame(4, $tournament->fresh()->rounds()->count());
    }

    public function test_it_throws_a_conflict_when_the_tournament_does_not_have_exactly_eight_teams(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(7)->create();

        $tournament->teams()->attach($teams->modelKeys());

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('A tournament must have exactly 8 teams to be simulated.');

        app(TournamentSimulationService::class)->simulateTournament($tournament);
    }

    public function test_it_throws_model_not_found_when_the_tournament_no_longer_exists(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(8)->create();

        $tournament->teams()->attach($teams->modelKeys());
        $tournament->delete();

        $this->expectException(ModelNotFoundException::class);

        app(TournamentSimulationService::class)->simulateTournament($tournament);
    }

    public function test_it_wraps_database_failures_and_leaves_no_partial_simulation_behind(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(8)->create();

        $tournament->teams()->attach($teams->modelKeys());

        $this->mock(TournamentSimulationRoundBuilder::class, function (MockInterface $mock) use ($teams): void {
            $rounds = $this->simulationRoundsFor($teams);
            $rounds[0]['games'][0]['winner_team_id'] = (string) Str::uuid();

            $mock->shouldReceive('build')
                ->once()
                ->andReturn($rounds);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to simulate tournament:');

        try {
            app(TournamentSimulationService::class)->simulateTournament($tournament);
        } finally {
            $this->assertSame(0, $tournament->fresh()->rounds()->count());
        }
    }

    public function test_it_uses_the_pivot_uuidv7_order_as_the_final_tie_breaker(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(8)->create()->reverse()->values();

        $tournament->teams()->attach($teams->modelKeys());

        $this->mock(PythonGoalScorePredictor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('predict')
                ->times(8)
                ->andReturn([
                    'home_goals' => 0,
                    'away_goals' => 0,
                ]);
        });

        $simulatedTournament = app(TournamentSimulationService::class)->simulateTournament($tournament);
        $finalRound = $simulatedTournament->rounds->firstWhere('phase', RoundPhase::Finals);

        $this->assertInstanceOf(TournamentRound::class, $finalRound);
        $this->assertSame($teams[0]->getKey(), $finalRound->games->sole()->winner_team_id);
        $this->assertSame($teams->modelKeys(), $simulatedTournament->teams->modelKeys());
    }

    /**
     * @return array<int, array{
     *     phase: string,
     *     position: int,
     *     games: array<int, array{
     *         home_team_id: string,
     *         away_team_id: string,
     *         winner_team_id: string,
     *         home_goals: int,
     *         away_goals: int,
     *         position: int
     *     }>
     * }>
     */
    private function simulationRoundsFor($teams): array
    {
        return [
            [
                'phase' => RoundPhase::QuarterFinals->value,
                'position' => 1,
                'games' => [
                    $this->gameData($teams[0]->getKey(), $teams[1]->getKey(), $teams[0]->getKey(), 2, 1, 1),
                    $this->gameData($teams[2]->getKey(), $teams[3]->getKey(), $teams[2]->getKey(), 1, 0, 2),
                    $this->gameData($teams[4]->getKey(), $teams[5]->getKey(), $teams[4]->getKey(), 3, 1, 3),
                    $this->gameData($teams[6]->getKey(), $teams[7]->getKey(), $teams[6]->getKey(), 2, 0, 4),
                ],
            ],
            [
                'phase' => RoundPhase::SemiFinals->value,
                'position' => 2,
                'games' => [
                    $this->gameData($teams[0]->getKey(), $teams[2]->getKey(), $teams[0]->getKey(), 1, 0, 1),
                    $this->gameData($teams[4]->getKey(), $teams[6]->getKey(), $teams[6]->getKey(), 0, 1, 2),
                ],
            ],
            [
                'phase' => RoundPhase::ThirdPlace->value,
                'position' => 3,
                'games' => [
                    $this->gameData($teams[2]->getKey(), $teams[4]->getKey(), $teams[4]->getKey(), 0, 2, 1),
                ],
            ],
            [
                'phase' => RoundPhase::Finals->value,
                'position' => 4,
                'games' => [
                    $this->gameData($teams[0]->getKey(), $teams[6]->getKey(), $teams[0]->getKey(), 2, 1, 1),
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     home_team_id: string,
     *     away_team_id: string,
     *     winner_team_id: string,
     *     home_goals: int,
     *     away_goals: int,
     *     position: int
     * }
     */
    private function gameData(
        string $homeTeamId,
        string $awayTeamId,
        string $winnerTeamId,
        int $homeGoals,
        int $awayGoals,
        int $position,
    ): array {
        return [
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'winner_team_id' => $winnerTeamId,
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'position' => $position,
        ];
    }
}
