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
    public function test_it_builds_the_podium_from_the_final_and_third_place_games(): void
    {
        $firstPlaceTeam = $this->makeTeam('team-1', 'Alpha FC');
        $secondPlaceTeam = $this->makeTeam('team-2', 'Beta FC');
        $semiFinalLoser = $this->makeTeam('team-3', 'Gamma FC');
        $thirdPlaceTeam = $this->makeTeam('team-4', 'Delta FC');

        $finalGame = $this->makeGame(
            'game-1',
            $firstPlaceTeam,
            $secondPlaceTeam,
            $firstPlaceTeam,
        );
        $thirdPlaceGame = $this->makeGame(
            'game-2',
            $semiFinalLoser,
            $thirdPlaceTeam,
            $thirdPlaceTeam,
        );

        $payload = (new TournamentResource(
            $this->makeTournamentWithRounds([
                $this->makeRound('round-1', RoundPhase::Finals, [$finalGame]),
                $this->makeRound('round-2', RoundPhase::ThirdPlace, [$thirdPlaceGame]),
            ]),
        ))->toArray(Request::create('/'));

        $this->assertIsArray($payload['podium']);
        $this->assertSame(
            [
                'id' => 'team-1',
                'name' => 'Alpha FC',
                'created_at' => null,
                'updated_at' => null,
            ],
            $this->resolveTeamResource($payload['podium']['first_place']),
        );
        $this->assertSame(
            [
                'id' => 'team-2',
                'name' => 'Beta FC',
                'created_at' => null,
                'updated_at' => null,
            ],
            $this->resolveTeamResource($payload['podium']['second_place']),
        );
        $this->assertSame(
            [
                'id' => 'team-4',
                'name' => 'Delta FC',
                'created_at' => null,
                'updated_at' => null,
            ],
            $this->resolveTeamResource($payload['podium']['third_place']),
        );
    }

    public function test_it_returns_a_null_podium_when_the_required_rounds_are_incomplete(): void
    {
        $firstPlaceTeam = $this->makeTeam('team-1', 'Alpha FC');
        $secondPlaceTeam = $this->makeTeam('team-2', 'Beta FC');

        $payload = (new TournamentResource(
            $this->makeTournamentWithRounds([
                $this->makeRound('round-1', RoundPhase::Finals, [
                    $this->makeGame('game-1', $firstPlaceTeam, $secondPlaceTeam, $firstPlaceTeam),
                ]),
            ]),
        ))->toArray(Request::create('/'));

        $this->assertNull($payload['podium']);
    }

    private function makeTournamentWithRounds(array $rounds): Tournament
    {
        $tournament = new Tournament;
        $tournament->id = 'tournament-1';
        $tournament->name = 'Champions Cup';
        $tournament->setRelation('rounds', new Collection($rounds));

        return $tournament;
    }

    private function makeRound(string $id, RoundPhase $phase, array $games): TournamentRound
    {
        $round = new TournamentRound;
        $round->id = $id;
        $round->phase = $phase;
        $round->setRelation('games', new Collection($games));

        return $round;
    }

    private function makeGame(string $id, Team $homeTeam, Team $awayTeam, Team $winnerTeam): RoundGame
    {
        $game = new RoundGame;
        $game->id = $id;
        $game->home_team_id = $homeTeam->getKey();
        $game->away_team_id = $awayTeam->getKey();
        $game->winner_team_id = $winnerTeam->getKey();
        $game->setRelation('homeTeam', $homeTeam);
        $game->setRelation('awayTeam', $awayTeam);
        $game->setRelation('winnerTeam', $winnerTeam);

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
     * @return array{id: string, name: string, created_at: null, updated_at: null}
     */
    private function resolveTeamResource(mixed $resource): array
    {
        $this->assertInstanceOf(TeamResource::class, $resource);

        /** @var TeamResource $resource */
        return $resource->toArray(Request::create('/'));
    }
}
