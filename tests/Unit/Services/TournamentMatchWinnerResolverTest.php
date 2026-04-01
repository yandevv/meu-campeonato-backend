<?php

namespace Tests\Unit\Services;

use App\Models\Team;
use App\Services\TournamentMatchWinnerResolver;
use Tests\UnitTestCase;

class TournamentMatchWinnerResolverTest extends UnitTestCase
{
    public function test_it_returns_the_home_team_when_it_scores_more_goals(): void
    {
        [$winner, $loser] = (new TournamentMatchWinnerResolver)->resolve(
            $this->makeTeam('team-1', 'Alpha'),
            $this->makeTeam('team-2', 'Beta'),
            3,
            1,
            [],
            [],
        );

        $this->assertSame('team-1', $winner->getKey());
        $this->assertSame('team-2', $loser->getKey());
    }

    public function test_it_uses_goal_balance_when_the_score_is_tied(): void
    {
        [$winner, $loser] = (new TournamentMatchWinnerResolver)->resolve(
            $this->makeTeam('team-1', 'Alpha'),
            $this->makeTeam('team-2', 'Beta'),
            2,
            2,
            [
                'team-1' => 4,
                'team-2' => 1,
            ],
            [],
        );

        $this->assertSame('team-1', $winner->getKey());
        $this->assertSame('team-2', $loser->getKey());
    }

    public function test_it_returns_the_away_team_when_it_scores_more_goals(): void
    {
        [$winner, $loser] = (new TournamentMatchWinnerResolver)->resolve(
            $this->makeTeam('team-1', 'Alpha'),
            $this->makeTeam('team-2', 'Beta'),
            0,
            2,
            [],
            [],
        );

        $this->assertSame('team-2', $winner->getKey());
        $this->assertSame('team-1', $loser->getKey());
    }

    public function test_it_uses_tournament_insertion_order_when_score_and_goal_balance_are_tied(): void
    {
        [$winner, $loser] = (new TournamentMatchWinnerResolver)->resolve(
            $this->makeTeam('team-1', 'Alpha'),
            $this->makeTeam('team-2', 'Beta'),
            1,
            1,
            [
                'team-1' => 0,
                'team-2' => 0,
            ],
            [
                'team-1' => 0,
                'team-2' => 1,
            ],
        );

        $this->assertSame('team-1', $winner->getKey());
        $this->assertSame('team-2', $loser->getKey());
    }

    private function makeTeam(string $id, string $name): Team
    {
        $team = new Team;
        $team->id = $id;
        $team->name = $name;

        return $team;
    }
}
