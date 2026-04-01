<?php

namespace Tests\Feature\Http\Controllers\Tournament;

use App\Enums\RoundPhase;
use App\Models\RoundGame;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TournamentControllerShowSimulationTest extends FeatureTestCase
{
    public function test_it_returns_the_persisted_simulation_with_podium_data(): void
    {
        $tournament = Tournament::factory()->create([
            'name' => 'Champions Cup',
        ]);
        $teams = Team::factory()->count(8)->create();

        $tournament->teams()->attach($teams->modelKeys());
        $this->createSimulatedTournamentGraph($tournament, $teams->all());

        $response = $this->getJson(route('tournaments.simulation.show', $tournament));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Tournament simulation retrieved successfully.')
            ->assertJsonPath('data.id', $tournament->getKey())
            ->assertJsonPath('data.name', 'Champions Cup')
            ->assertJsonCount(8, 'data.teams')
            ->assertJsonCount(4, 'data.rounds')
            ->assertJsonCount(4, 'data.rounds.0.games')
            ->assertJsonCount(2, 'data.rounds.1.games')
            ->assertJsonCount(1, 'data.rounds.2.games')
            ->assertJsonCount(1, 'data.rounds.3.games')
            ->assertJsonPath('data.rounds.0.phase', RoundPhase::QuarterFinals->value)
            ->assertJsonPath('data.rounds.1.phase', RoundPhase::SemiFinals->value)
            ->assertJsonPath('data.rounds.2.phase', RoundPhase::ThirdPlace->value)
            ->assertJsonPath('data.rounds.3.phase', RoundPhase::Finals->value)
            ->assertJsonPath('data.podium.first_place.id', $teams[0]->getKey())
            ->assertJsonPath('data.podium.first_place.name', $teams[0]->name)
            ->assertJsonPath('data.podium.second_place.id', $teams[6]->getKey())
            ->assertJsonPath('data.podium.second_place.name', $teams[6]->name)
            ->assertJsonPath('data.podium.third_place.id', $teams[4]->getKey())
            ->assertJsonPath('data.podium.third_place.name', $teams[4]->name)
            ->assertJsonStructure([
                'statusCode',
                'message',
                'data' => [
                    'id',
                    'name',
                    'created_at',
                    'updated_at',
                    'teams' => [
                        '*' => [
                            'id',
                            'name',
                            'joined_at',
                            'updated_at',
                        ],
                    ],
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
                    'podium' => [
                        'first_place' => [
                            'id',
                            'name',
                            'created_at',
                            'updated_at',
                        ],
                        'second_place' => [
                            'id',
                            'name',
                            'created_at',
                            'updated_at',
                        ],
                        'third_place' => [
                            'id',
                            'name',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
            ]);
    }

    public function test_it_returns_not_found_when_the_tournament_has_not_been_simulated_yet(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->getJson(route('tournaments.simulation.show', $tournament));

        $response
            ->assertNotFound()
            ->assertJsonPath('statusCode', 404)
            ->assertJsonPath('message', 'The tournament does not have a simulation yet.');
    }

    public function test_it_returns_not_found_when_the_tournament_does_not_exist(): void
    {
        $response = $this->getJson(route('tournaments.simulation.show', [
            'tournament' => (string) Str::uuid(),
        ]));

        $response->assertNotFound();
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
