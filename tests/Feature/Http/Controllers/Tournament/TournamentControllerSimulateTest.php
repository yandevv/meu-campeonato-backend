<?php

namespace Tests\Feature\Http\Controllers\Tournament;

use App\Enums\RoundPhase;
use App\Models\RoundGame;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\TournamentSimulationService;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\FeatureTestCase;

class TournamentControllerSimulateTest extends FeatureTestCase
{
    public function test_it_simulates_a_tournament_and_returns_the_loaded_resource(): void
    {
        $tournament = Tournament::factory()->create([
            'name' => 'Champions Cup',
        ]);
        $teams = Team::factory()->count(8)->create();

        $tournament->teams()->attach($teams->modelKeys());
        $simulatedTournament = $this->createSimulatedTournamentGraph($tournament, $teams->all());

        $this->mock(TournamentSimulationService::class, function (MockInterface $mock) use ($tournament, $simulatedTournament): void {
            $mock->shouldReceive('simulateTournament')
                ->once()
                ->withArgs(fn (Tournament $boundTournament): bool => $boundTournament->is($tournament))
                ->andReturn($simulatedTournament);
        });

        $response = $this->postJson(route('tournaments.simulate', $tournament));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Tournament simulated successfully.')
            ->assertJsonPath('data.id', $tournament->getKey())
            ->assertJsonPath('data.name', 'Champions Cup')
            ->assertJsonCount(4, 'data.rounds')
            ->assertJsonCount(4, 'data.rounds.0.games')
            ->assertJsonCount(2, 'data.rounds.1.games')
            ->assertJsonCount(1, 'data.rounds.2.games')
            ->assertJsonCount(1, 'data.rounds.3.games')
            ->assertJsonCount(8, 'data.standings')
            ->assertJsonPath('data.rounds.0.phase', RoundPhase::QuarterFinals->value)
            ->assertJsonPath('data.rounds.1.phase', RoundPhase::SemiFinals->value)
            ->assertJsonPath('data.rounds.2.phase', RoundPhase::ThirdPlace->value)
            ->assertJsonPath('data.rounds.3.phase', RoundPhase::Finals->value)
            ->assertJsonPath('data.standings.0.team.id', $teams[0]->getKey())
            ->assertJsonPath('data.standings.0.placement', 1)
            ->assertJsonPath('data.standings.0.last_phase', RoundPhase::Finals->value)
            ->assertJsonPath('data.standings.0.matches_played', 3)
            ->assertJsonPath('data.standings.0.wins', 3)
            ->assertJsonPath('data.standings.0.losses', 0)
            ->assertJsonPath('data.standings.0.goals_for', 5)
            ->assertJsonPath('data.standings.0.goals_against', 2)
            ->assertJsonPath('data.standings.0.goal_balance', 3)
            ->assertJsonPath('data.standings.1.team.id', $teams[6]->getKey())
            ->assertJsonPath('data.standings.1.placement', 2)
            ->assertJsonPath('data.standings.2.team.id', $teams[4]->getKey())
            ->assertJsonPath('data.standings.2.placement', 3)
            ->assertJsonPath('data.standings.3.team.id', $teams[2]->getKey())
            ->assertJsonPath('data.standings.3.placement', 4)
            ->assertJsonPath('data.standings.4.team.id', $teams[1]->getKey())
            ->assertJsonPath('data.standings.4.placement', null)
            ->assertJsonPath('data.standings.4.last_phase', RoundPhase::QuarterFinals->value)
            ->assertJsonPath('data.standings.4.matches_played', 1)
            ->assertJsonPath('data.standings.4.wins', 0)
            ->assertJsonPath('data.standings.4.losses', 1)
            ->assertJsonPath('data.standings.4.goals_for', 1)
            ->assertJsonPath('data.standings.4.goals_against', 2)
            ->assertJsonPath('data.standings.4.goal_balance', -1)
            ->assertJsonMissingPath('data.podium')
            ->assertJsonStructure([
                'statusCode',
                'message',
                'data' => [
                    'id',
                    'name',
                    'created_at',
                    'updated_at',
                    'teams',
                    'rounds' => [
                        '*' => [
                            'id',
                            'phase',
                            'position',
                            'games' => [
                                '*' => [
                                    'id',
                                    'position',
                                    'home_goals',
                                    'away_goals',
                                    'home_team_id',
                                    'away_team_id',
                                    'winner_team_id',
                                    'home_team',
                                    'away_team',
                                ],
                            ],
                        ],
                    ],
                    'standings' => [
                        '*' => [
                            'team' => [
                                'id',
                                'name',
                                'created_at',
                                'updated_at',
                            ],
                            'placement',
                            'last_phase',
                            'matches_played',
                            'wins',
                            'losses',
                            'goals_for',
                            'goals_against',
                            'goal_balance',
                        ],
                    ],
                ],
            ]);
    }

    public function test_it_returns_conflict_when_the_tournament_cannot_be_simulated(): void
    {
        $tournament = Tournament::factory()->create();

        $this->mock(TournamentSimulationService::class, function (MockInterface $mock) use ($tournament): void {
            $mock->shouldReceive('simulateTournament')
                ->once()
                ->withArgs(fn (Tournament $boundTournament): bool => $boundTournament->is($tournament))
                ->andThrow(new ConflictHttpException('A tournament must have exactly 8 teams to be simulated.'));
        });

        $response = $this->postJson(route('tournaments.simulate', $tournament));

        $response
            ->assertConflict()
            ->assertJsonPath('statusCode', 409)
            ->assertJsonPath('message', 'A tournament must have exactly 8 teams to be simulated.');
    }

    public function test_it_returns_not_found_when_simulating_a_tournament_that_does_not_exist(): void
    {
        $response = $this->postJson(route('tournaments.simulate', [
            'tournament' => (string) Str::uuid(),
        ]));

        $response->assertNotFound();
    }

    /**
     * @param  list<Team>  $teams
     */
    private function createSimulatedTournamentGraph(Tournament $tournament, array $teams): Tournament
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

        // Quarter-finals round game mock simulations
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

        // Semi-finals round game mock simulations
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

        // Third-place round game mock simulation
        RoundGame::factory()->forRound($thirdPlace, 1)->create([
            'home_team_id' => $teams[2]->getKey(),
            'away_team_id' => $teams[4]->getKey(),
            'winner_team_id' => $teams[4]->getKey(),
            'home_goals' => 0,
            'away_goals' => 2,
        ]);

        // Finals round game mock simulation
        RoundGame::factory()->forRound($finals, 1)->create([
            'home_team_id' => $teams[0]->getKey(),
            'away_team_id' => $teams[6]->getKey(),
            'winner_team_id' => $teams[0]->getKey(),
            'home_goals' => 2,
            'away_goals' => 1,
        ]);

        return $tournament->fresh()->load([
            'teams',
            'rounds.games.homeTeam',
            'rounds.games.awayTeam',
            'rounds.games.winnerTeam',
        ]);
    }
}
