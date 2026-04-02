<?php

namespace Tests\Feature\Http\Controllers\Team;

use App\Enums\RoundPhase;
use App\Models\RoundGame;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TeamControllerShowTest extends FeatureTestCase
{
    public function test_it_returns_the_requested_team_resource(): void
    {
        $team = Team::factory()->create();

        $response = $this->getJson(route('teams.show', $team));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Team retrieved successfully.')
            ->assertJsonPath('data.id', $team->getKey())
            ->assertJsonPath('data.name', $team->name)
            ->assertJsonPath('data.games_count', 0)
            ->assertJsonPath('data.recent_games', [])
            ->assertJsonStructure([
                'statusCode',
                'message',
                'data' => [
                    'id',
                    'name',
                    'created_at',
                    'updated_at',
                    'games_count',
                    'recent_games',
                ],
            ]);
    }

    public function test_it_returns_the_last_five_games_with_total_count_and_context(): void
    {
        $team = Team::factory()->create([
            'name' => 'Alpha FC',
        ]);
        $recentGames = [];

        foreach (range(1, 6) as $index) {
            $recentGames[] = $this->createMatchForTeam(
                $team,
                Team::factory()->create([
                    'name' => "Opponent {$index}",
                ]),
                Carbon::parse('2026-01-01 10:00:00')->addDays($index),
                $index % 2 === 0,
                "Tournament {$index}",
                $index <= 4 ? RoundPhase::QuarterFinals : RoundPhase::SemiFinals,
            );
        }

        $this->createMatchForTeam(
            Team::factory()->create(),
            Team::factory()->create(),
            Carbon::parse('2026-01-20 10:00:00'),
            true,
            'Unrelated Tournament',
            RoundPhase::Finals,
        );

        $response = $this->getJson(route('teams.show', $team));
        $payload = $response->json('data');

        $response
            ->assertOk()
            ->assertJsonPath('data.games_count', 6)
            ->assertJsonCount(5, 'data.recent_games')
            ->assertJsonPath('data.recent_games.0.id', $recentGames[5]->getKey())
            ->assertJsonPath('data.recent_games.0.round.phase', RoundPhase::SemiFinals->value)
            ->assertJsonPath('data.recent_games.0.tournament.name', 'Tournament 6');

        $this->assertSame(
            [
                $recentGames[5]->getKey(),
                $recentGames[4]->getKey(),
                $recentGames[3]->getKey(),
                $recentGames[2]->getKey(),
                $recentGames[1]->getKey(),
            ],
            array_column($payload['recent_games'], 'id'),
        );

        foreach ($payload['recent_games'] as $game) {
            $this->assertContains($team->getKey(), [
                $game['home_team_id'],
                $game['away_team_id'],
            ]);
            $this->assertArrayHasKey('round', $game);
            $this->assertArrayHasKey('tournament', $game);
        }
    }

    public function test_it_returns_not_found_when_the_team_does_not_exist(): void
    {
        $response = $this->getJson(route('teams.show', [
            'team' => (string) Str::uuid(),
        ]));

        $response->assertNotFound();
    }

    private function createMatchForTeam(
        Team $team,
        Team $opponent,
        Carbon $playedAt,
        bool $teamIsHome,
        string $tournamentName,
        RoundPhase $phase,
    ): RoundGame {
        $tournament = Tournament::factory()->create([
            'name' => $tournamentName,
        ]);
        $round = TournamentRound::factory()
            ->for($tournament)
            ->forPhase($phase, 1)
            ->create();

        return RoundGame::factory()->forRound($round, 1)->create([
            'home_team_id' => $teamIsHome ? $team->getKey() : $opponent->getKey(),
            'away_team_id' => $teamIsHome ? $opponent->getKey() : $team->getKey(),
            'winner_team_id' => $team->getKey(),
            'home_goals' => $teamIsHome ? 2 : 1,
            'away_goals' => $teamIsHome ? 1 : 2,
            'created_at' => $playedAt,
            'updated_at' => $playedAt,
        ]);
    }
}
