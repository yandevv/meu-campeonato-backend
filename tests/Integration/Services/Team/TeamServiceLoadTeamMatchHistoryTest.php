<?php

namespace Tests\Integration\Services;

use App\Enums\RoundPhase;
use App\Models\RoundGame;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\TeamService;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\IntegrationTestCase;

class TeamServiceLoadTeamMatchHistoryTest extends IntegrationTestCase
{
    public function test_it_loads_recent_match_history_with_total_count_and_required_relations(): void
    {
        $team = Team::factory()->create();
        $games = [];

        foreach (range(1, 6) as $index) {
            $games[] = $this->createMatchForTeam(
                $team,
                Team::factory()->create(),
                Carbon::parse('2026-02-01 12:00:00')->addDays($index),
                $index % 2 === 1,
                "Tournament {$index}",
            );
        }

        $this->createMatchForTeam(
            Team::factory()->create(),
            Team::factory()->create(),
            Carbon::parse('2026-03-01 12:00:00'),
            true,
            'Unrelated Tournament',
        );

        $loadedTeam = app(TeamService::class)->loadTeamMatchHistory($team);

        $this->assertSame($team->getKey(), $loadedTeam->getKey());
        $this->assertSame(6, $loadedTeam->games_count);
        $this->assertTrue($loadedTeam->relationLoaded('recentGames'));
        $this->assertCount(5, $loadedTeam->recentGames);
        $this->assertSame(
            [
                $games[5]->getKey(),
                $games[4]->getKey(),
                $games[3]->getKey(),
                $games[2]->getKey(),
                $games[1]->getKey(),
            ],
            $loadedTeam->recentGames->modelKeys(),
        );

        foreach ($loadedTeam->recentGames as $game) {
            $this->assertTrue($game->relationLoaded('homeTeam'));
            $this->assertTrue($game->relationLoaded('awayTeam'));
            $this->assertTrue($game->relationLoaded('round'));
            $this->assertTrue($game->round->relationLoaded('tournament'));
            $this->assertContains($team->getKey(), [
                $game->home_team_id,
                $game->away_team_id,
            ]);
        }
    }

    public function test_it_respects_a_custom_recent_match_limit_without_changing_total_count(): void
    {
        $team = Team::factory()->create();

        foreach (range(1, 4) as $index) {
            $this->createMatchForTeam(
                $team,
                Team::factory()->create(),
                Carbon::parse('2026-02-10 09:00:00')->addDays($index),
                true,
                "Tournament {$index}",
            );
        }

        $loadedTeam = app(TeamService::class)->loadTeamMatchHistory($team, 3);

        $this->assertSame(4, $loadedTeam->games_count);
        $this->assertCount(3, $loadedTeam->recentGames);
    }

    public function test_it_wraps_database_failures_when_loading_team_match_history(): void
    {
        $team = Team::factory()->create();
        $originalDefaultConnection = config('database.default');

        config()->set('database.default', 'missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to load team match history:');

        try {
            app(TeamService::class)->loadTeamMatchHistory($team);
        } finally {
            config()->set('database.default', $originalDefaultConnection);
        }
    }

    private function createMatchForTeam(
        Team $team,
        Team $opponent,
        Carbon $playedAt,
        bool $teamIsHome,
        string $tournamentName,
    ): RoundGame {
        $tournament = Tournament::factory()->create([
            'name' => $tournamentName,
        ]);
        $round = TournamentRound::factory()
            ->for($tournament)
            ->forPhase(RoundPhase::Finals, 1)
            ->create();

        return RoundGame::factory()->forRound($round, 1)->create([
            'home_team_id' => $teamIsHome ? $team->getKey() : $opponent->getKey(),
            'away_team_id' => $teamIsHome ? $opponent->getKey() : $team->getKey(),
            'winner_team_id' => $team->getKey(),
            'home_goals' => $teamIsHome ? 3 : 1,
            'away_goals' => $teamIsHome ? 1 : 3,
            'created_at' => $playedAt,
            'updated_at' => $playedAt,
        ]);
    }
}
