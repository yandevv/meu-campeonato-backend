<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\RoundPhase;
use App\Http\Resources\TeamResource;
use App\Http\Resources\TournamentResource;
use App\Models\RoundGame;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Tests\UnitTestCase;

class TournamentResourceTest extends UnitTestCase
{
    public function test_it_builds_standings_from_the_simulated_tournament_rounds(): void
    {
        $teams = [
            $this->makeTeam('team-1', 'Alpha FC'),
            $this->makeTeam('team-2', 'Beta FC'),
            $this->makeTeam('team-3', 'Gamma FC'),
            $this->makeTeam('team-4', 'Delta FC'),
            $this->makeTeam('team-5', 'Epsilon FC'),
            $this->makeTeam('team-6', 'Zeta FC'),
            $this->makeTeam('team-7', 'Eta FC'),
            $this->makeTeam('team-8', 'Theta FC'),
        ];

        $payload = (new TournamentResource(
            $this->makeTournamentWithSimulation($teams, [
                $this->makeRound('round-1', RoundPhase::QuarterFinals, [
                    $this->makeGame('game-1', $teams[0], $teams[1], $teams[0], 2, 1),
                    $this->makeGame('game-2', $teams[2], $teams[3], $teams[2], 3, 0),
                    $this->makeGame('game-3', $teams[4], $teams[5], $teams[4], 1, 0),
                    $this->makeGame('game-4', $teams[6], $teams[7], $teams[6], 4, 2),
                ]),
                $this->makeRound('round-2', RoundPhase::SemiFinals, [
                    $this->makeGame('game-5', $teams[0], $teams[2], $teams[0], 1, 0),
                    $this->makeGame('game-6', $teams[4], $teams[6], $teams[6], 0, 2),
                ]),
                $this->makeRound('round-3', RoundPhase::ThirdPlace, [
                    $this->makeGame('game-7', $teams[2], $teams[4], $teams[2], 2, 1),
                ]),
                $this->makeRound('round-4', RoundPhase::Finals, [
                    $this->makeGame('game-8', $teams[0], $teams[6], $teams[0], 3, 2),
                ]),
            ]),
        ))->resolve(Request::create('/'));

        $this->assertArrayNotHasKey('podium', $payload);
        $this->assertCount(8, $payload['standings']);

        $this->assertSame(
            [
                'team' => [
                    'id' => 'team-1',
                    'name' => 'Alpha FC',
                    'created_at' => null,
                    'updated_at' => null,
                ],
                'placement' => 1,
                'last_phase' => RoundPhase::Finals->value,
                'matches_played' => 3,
                'wins' => 3,
                'losses' => 0,
                'goals_for' => 6,
                'goals_against' => 3,
                'goal_balance' => 3,
            ],
            $this->resolveStanding($payload['standings'][0]),
        );

        $this->assertSame(
            [
                'team' => [
                    'id' => 'team-7',
                    'name' => 'Eta FC',
                    'created_at' => null,
                    'updated_at' => null,
                ],
                'placement' => 2,
                'last_phase' => RoundPhase::Finals->value,
                'matches_played' => 3,
                'wins' => 2,
                'losses' => 1,
                'goals_for' => 8,
                'goals_against' => 5,
                'goal_balance' => 3,
            ],
            $this->resolveStanding($payload['standings'][1]),
        );

        $this->assertSame(
            [
                'team' => [
                    'id' => 'team-3',
                    'name' => 'Gamma FC',
                    'created_at' => null,
                    'updated_at' => null,
                ],
                'placement' => 3,
                'last_phase' => RoundPhase::ThirdPlace->value,
                'matches_played' => 3,
                'wins' => 2,
                'losses' => 1,
                'goals_for' => 5,
                'goals_against' => 2,
                'goal_balance' => 3,
            ],
            $this->resolveStanding($payload['standings'][2]),
        );

        $this->assertSame(
            [
                'team' => [
                    'id' => 'team-2',
                    'name' => 'Beta FC',
                    'created_at' => null,
                    'updated_at' => null,
                ],
                'placement' => null,
                'last_phase' => RoundPhase::QuarterFinals->value,
                'matches_played' => 1,
                'wins' => 0,
                'losses' => 1,
                'goals_for' => 1,
                'goals_against' => 2,
                'goal_balance' => -1,
            ],
            $this->resolveStanding($payload['standings'][4]),
        );
    }

    public function test_it_omits_standings_when_the_required_relations_are_not_loaded(): void
    {
        $tournament = new Tournament;
        $tournament->id = 'tournament-1';
        $tournament->name = 'Champions Cup';

        $payload = (new TournamentResource($tournament))->resolve(Request::create('/'));

        $this->assertArrayNotHasKey('standings', $payload);
    }

    /**
     * @param  list<Team>  $teams
     * @param  list<TournamentRound>  $rounds
     */
    private function makeTournamentWithSimulation(array $teams, array $rounds): Tournament
    {
        $tournament = new Tournament;
        $tournament->id = 'tournament-1';
        $tournament->name = 'Champions Cup';
        $tournament->setRelation('teams', new Collection($teams));
        $tournament->setRelation('rounds', new Collection($rounds));

        return $tournament;
    }

    /**
     * @param  list<RoundGame>  $games
     */
    private function makeRound(string $id, RoundPhase $phase, array $games): TournamentRound
    {
        $round = new TournamentRound;
        $round->id = $id;
        $round->phase = $phase;
        $round->setRelation('games', new Collection($games));

        return $round;
    }

    private function makeGame(
        string $id,
        Team $homeTeam,
        Team $awayTeam,
        Team $winnerTeam,
        int $homeGoals,
        int $awayGoals,
    ): RoundGame {
        $game = new RoundGame;
        $game->id = $id;
        $game->home_team_id = $homeTeam->getKey();
        $game->away_team_id = $awayTeam->getKey();
        $game->winner_team_id = $winnerTeam->getKey();
        $game->home_goals = $homeGoals;
        $game->away_goals = $awayGoals;
        $game->setRelation('homeTeam', $homeTeam);
        $game->setRelation('awayTeam', $awayTeam);

        return $game;
    }

    private function makeTeam(string $id, string $name): Team
    {
        $team = new Team;
        $team->id = $id;
        $team->name = $name;

        return $team;
    }

    /**
     * @param  array{
     *     team: TeamResource,
     *     placement: int|null,
     *     last_phase: string|null,
     *     matches_played: int,
     *     wins: int,
     *     losses: int,
     *     goals_for: int,
     *     goals_against: int,
     *     goal_balance: int
     * }  $standing
     * @return array{
     *     team: array{id: string, name: string, created_at: null, updated_at: null},
     *     placement: int|null,
     *     last_phase: string|null,
     *     matches_played: int,
     *     wins: int,
     *     losses: int,
     *     goals_for: int,
     *     goals_against: int,
     *     goal_balance: int
     * }
     */
    private function resolveStanding(array $standing): array
    {
        return [
            'team' => $this->resolveTeamResource($standing['team']),
            'placement' => $standing['placement'],
            'last_phase' => $standing['last_phase'],
            'matches_played' => $standing['matches_played'],
            'wins' => $standing['wins'],
            'losses' => $standing['losses'],
            'goals_for' => $standing['goals_for'],
            'goals_against' => $standing['goals_against'],
            'goal_balance' => $standing['goal_balance'],
        ];
    }

    /**
     * @return array{id: string, name: string, created_at: null, updated_at: null}
     */
    private function resolveTeamResource(mixed $resource): array
    {
        $this->assertInstanceOf(TeamResource::class, $resource);

        /** @var TeamResource $resource */
        return $resource->resolve(Request::create('/'));
    }
}
