<?php

namespace Tests\Unit\Services;

use App\Enums\RoundPhase;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\PythonGoalScorePredictor;
use App\Services\TournamentMatchWinnerResolver;
use App\Services\TournamentSimulationRoundBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use RuntimeException;
use Tests\UnitTestCase;

class TournamentSimulationRoundBuilderTest extends UnitTestCase
{
    public function test_it_builds_all_rounds_in_order_with_valid_bracket_progression(): void
    {
        $tournament = $this->makeTournament();
        $teams = $this->makeTeams(8);
        $predictedPhases = [];

        $goalScorePredictor = $this->createMock(PythonGoalScorePredictor::class);
        $goalScorePredictor->expects($this->exactly(8))
            ->method('predict')
            ->willReturnCallback(function (
                string $tournamentId,
                RoundPhase $roundPhase,
                string $homeTeamId,
                string $awayTeamId,
            ) use ($tournament, &$predictedPhases): array {
                $this->assertSame($tournament->getKey(), $tournamentId);
                $this->assertNotSame($homeTeamId, $awayTeamId);

                $predictedPhases[] = $roundPhase->value;

                return [
                    'home_goals' => 2,
                    'away_goals' => 1,
                ];
            });

        $matchWinnerResolver = $this->createMock(TournamentMatchWinnerResolver::class);
        $matchWinnerResolver->expects($this->exactly(8))
            ->method('resolve')
            ->willReturnCallback(function (
                Team $homeTeam,
                Team $awayTeam,
                int $homeGoals,
                int $awayGoals,
            ): array {
                $this->assertSame(2, $homeGoals);
                $this->assertSame(1, $awayGoals);

                return [$homeTeam, $awayTeam];
            });

        $rounds = (new TournamentSimulationRoundBuilder(
            $goalScorePredictor,
            $matchWinnerResolver,
        ))->build($tournament, $teams);

        $this->assertSame([
            RoundPhase::QuarterFinals->value,
            RoundPhase::SemiFinals->value,
            RoundPhase::ThirdPlace->value,
            RoundPhase::Finals->value,
        ], array_column($rounds, 'phase'));
        $this->assertSame([1, 2, 3, 4], array_column($rounds, 'position'));
        $this->assertSame([4, 2, 1, 1], array_map(
            static fn (array $round): int => count($round['games']),
            $rounds,
        ));
        $this->assertSame([
            RoundPhase::QuarterFinals->value,
            RoundPhase::QuarterFinals->value,
            RoundPhase::QuarterFinals->value,
            RoundPhase::QuarterFinals->value,
            RoundPhase::SemiFinals->value,
            RoundPhase::SemiFinals->value,
            RoundPhase::ThirdPlace->value,
            RoundPhase::Finals->value,
        ], $predictedPhases);

        foreach ($rounds as $round) {
            $this->assertSame(
                range(1, count($round['games'])),
                array_column($round['games'], 'position'),
            );
        }

        $quarterFinalGames = $rounds[0]['games'];
        $semiFinalGames = $rounds[1]['games'];
        $thirdPlaceGame = $rounds[2]['games'][0];
        $finalGame = $rounds[3]['games'][0];

        $this->assertEqualsCanonicalizing(
            $teams->modelKeys(),
            $this->participantIdsForGames($quarterFinalGames),
        );
        $this->assertEqualsCanonicalizing(
            array_column($quarterFinalGames, 'winner_team_id'),
            $this->participantIdsForGames($semiFinalGames),
        );
        $this->assertEqualsCanonicalizing(
            array_column($semiFinalGames, 'winner_team_id'),
            [$finalGame['home_team_id'], $finalGame['away_team_id']],
        );
        $this->assertEqualsCanonicalizing(
            array_column($semiFinalGames, 'away_team_id'),
            [$thirdPlaceGame['home_team_id'], $thirdPlaceGame['away_team_id']],
        );
        $this->assertEqualsCanonicalizing(
            $this->participantIdsForGames($semiFinalGames),
            [
                $thirdPlaceGame['home_team_id'],
                $thirdPlaceGame['away_team_id'],
                $finalGame['home_team_id'],
                $finalGame['away_team_id'],
            ],
        );

        foreach (array_merge($quarterFinalGames, $semiFinalGames, [$thirdPlaceGame, $finalGame]) as $game) {
            $this->assertSame($game['home_team_id'], $game['winner_team_id']);
        }
    }

    public function test_it_propagates_goal_balances_and_tournament_order_into_later_rounds(): void
    {
        $tournament = $this->makeTournament();
        $teams = $this->makeTeams(8);
        $tournamentTeamOrder = $this->tournamentTeamOrder($teams);
        $pendingPhases = [];
        $resolverSnapshots = [];
        $realResolver = new TournamentMatchWinnerResolver;

        $goalScorePredictor = $this->createMock(PythonGoalScorePredictor::class);
        $goalScorePredictor->expects($this->exactly(8))
            ->method('predict')
            ->willReturnCallback(function (
                string $tournamentId,
                RoundPhase $roundPhase,
                string $homeTeamId,
                string $awayTeamId,
            ) use ($tournament, $tournamentTeamOrder, &$pendingPhases): array {
                $this->assertSame($tournament->getKey(), $tournamentId);

                $pendingPhases[] = $roundPhase;

                return $this->predictionForOrderedWinner(
                    $roundPhase,
                    $homeTeamId,
                    $awayTeamId,
                    $tournamentTeamOrder,
                );
            });

        $matchWinnerResolver = $this->createMock(TournamentMatchWinnerResolver::class);
        $matchWinnerResolver->expects($this->exactly(8))
            ->method('resolve')
            ->willReturnCallback(function (
                Team $homeTeam,
                Team $awayTeam,
                int $homeGoals,
                int $awayGoals,
                array $goalBalances,
                array $receivedTeamOrder,
            ) use (&$pendingPhases, &$resolverSnapshots, $tournamentTeamOrder, $realResolver): array {
                $roundPhase = array_shift($pendingPhases);

                $this->assertInstanceOf(RoundPhase::class, $roundPhase);
                $this->assertSame($tournamentTeamOrder, $receivedTeamOrder);

                $resolverSnapshots[$roundPhase->value][] = [
                    'home_team_id' => $homeTeam->getKey(),
                    'away_team_id' => $awayTeam->getKey(),
                    'goal_balances' => $goalBalances,
                ];

                return $realResolver->resolve(
                    $homeTeam,
                    $awayTeam,
                    $homeGoals,
                    $awayGoals,
                    $goalBalances,
                    $receivedTeamOrder,
                );
            });

        $rounds = (new TournamentSimulationRoundBuilder(
            $goalScorePredictor,
            $matchWinnerResolver,
        ))->build($tournament, $teams);

        $this->assertCount(4, $resolverSnapshots[RoundPhase::QuarterFinals->value]);
        $this->assertCount(2, $resolverSnapshots[RoundPhase::SemiFinals->value]);
        $this->assertCount(1, $resolverSnapshots[RoundPhase::ThirdPlace->value]);
        $this->assertCount(1, $resolverSnapshots[RoundPhase::Finals->value]);

        $quarterFinalGoalBalances = $this->goalBalancesAfterGames(
            $teams,
            $rounds[0]['games'],
        );

        $this->assertSame(
            $quarterFinalGoalBalances,
            $resolverSnapshots[RoundPhase::SemiFinals->value][0]['goal_balances'],
        );

        $goalBalancesBeforeSecondSemiFinal = $this->goalBalancesAfterGames(
            $teams,
            [
                ...$rounds[0]['games'],
                $rounds[1]['games'][0],
            ],
        );

        $this->assertSame(
            $goalBalancesBeforeSecondSemiFinal,
            $resolverSnapshots[RoundPhase::SemiFinals->value][1]['goal_balances'],
        );

        $goalBalancesAfterSemiFinals = $this->goalBalancesAfterGames(
            $teams,
            [
                ...$rounds[0]['games'],
                ...$rounds[1]['games'],
            ],
        );

        $this->assertSame(
            $goalBalancesAfterSemiFinals,
            $resolverSnapshots[RoundPhase::ThirdPlace->value][0]['goal_balances'],
        );

        $goalBalancesBeforeFinal = $this->goalBalancesAfterGames(
            $teams,
            [
                ...$rounds[0]['games'],
                ...$rounds[1]['games'],
                ...$rounds[2]['games'],
            ],
        );

        $this->assertSame(
            $goalBalancesBeforeFinal,
            $resolverSnapshots[RoundPhase::Finals->value][0]['goal_balances'],
        );

        $this->assertSame('team-1', $rounds[3]['games'][0]['winner_team_id']);
        $this->assertEqualsCanonicalizing(
            array_column($rounds[0]['games'], 'winner_team_id'),
            $this->participantIdsForGames($rounds[1]['games']),
        );
    }

    public function test_it_throws_when_a_round_receives_an_incomplete_matchup(): void
    {
        $tournament = $this->makeTournament();
        $teams = $this->makeTeams(7);

        $goalScorePredictor = $this->createMock(PythonGoalScorePredictor::class);
        $goalScorePredictor->expects($this->exactly(3))
            ->method('predict')
            ->willReturn([
                'home_goals' => 1,
                'away_goals' => 0,
            ]);

        $matchWinnerResolver = $this->createMock(TournamentMatchWinnerResolver::class);
        $matchWinnerResolver->expects($this->exactly(3))
            ->method('resolve')
            ->willReturnCallback(static fn (Team $homeTeam, Team $awayTeam): array => [$homeTeam, $awayTeam]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The quarter_finals round received an incomplete matchup.');

        (new TournamentSimulationRoundBuilder(
            $goalScorePredictor,
            $matchWinnerResolver,
        ))->build($tournament, $teams);
    }

    private function makeTournament(): Tournament
    {
        $tournament = new Tournament;
        $tournament->id = 'tournament-1';
        $tournament->name = 'Champions Cup';

        return $tournament;
    }

    /**
     * @return EloquentCollection<int, Team>
     */
    private function makeTeams(int $count): EloquentCollection
    {
        $teams = [];

        for ($index = 1; $index <= $count; $index++) {
            $team = new Team;
            $team->id = 'team-'.$index;
            $team->name = 'Team '.$index;

            $teams[] = $team;
        }

        return new EloquentCollection($teams);
    }

    /**
     * @param  EloquentCollection<int, Team>  $teams
     * @return array<string, int>
     */
    private function tournamentTeamOrder(EloquentCollection $teams): array
    {
        /** @var array<string, int> $teamOrder */
        $teamOrder = $teams
            ->pluck('id')
            ->values()
            ->flip()
            ->map(static fn (mixed $position): int => (int) $position)
            ->all();

        return $teamOrder;
    }

    /**
     * @param  array<string, int>  $tournamentTeamOrder
     * @return array{home_goals: int, away_goals: int}
     */
    private function predictionForOrderedWinner(
        RoundPhase $roundPhase,
        string $homeTeamId,
        string $awayTeamId,
        array $tournamentTeamOrder,
    ): array {
        $homeTeamOrder = $tournamentTeamOrder[$homeTeamId];
        $awayTeamOrder = $tournamentTeamOrder[$awayTeamId];
        $homeTeamHasPriority = $homeTeamOrder <= $awayTeamOrder;

        return match ($roundPhase) {
            RoundPhase::QuarterFinals => $homeTeamHasPriority
                ? ['home_goals' => 1, 'away_goals' => 0]
                : ['home_goals' => 0, 'away_goals' => 1],
            RoundPhase::SemiFinals => $this->semiFinalPrediction(
                $homeTeamHasPriority,
                min($homeTeamOrder, $awayTeamOrder) === 0,
            ),
            RoundPhase::ThirdPlace => ['home_goals' => 0, 'away_goals' => 0],
            RoundPhase::Finals => ['home_goals' => 1, 'away_goals' => 1],
        };
    }

    /**
     * @return array{home_goals: int, away_goals: int}
     */
    private function semiFinalPrediction(bool $homeTeamHasPriority, bool $containsTopSeed): array
    {
        $winningGoals = $containsTopSeed ? 3 : 1;

        return $homeTeamHasPriority
            ? ['home_goals' => $winningGoals, 'away_goals' => 0]
            : ['home_goals' => 0, 'away_goals' => $winningGoals];
    }

    /**
     * @param  array<int, array{
     *     home_team_id: string,
     *     away_team_id: string
     * }>  $games
     * @return list<string>
     */
    private function participantIdsForGames(array $games): array
    {
        return array_merge(
            array_column($games, 'home_team_id'),
            array_column($games, 'away_team_id'),
        );
    }

    /**
     * @param  EloquentCollection<int, Team>  $teams
     * @param  array<int, array{
     *     home_team_id: string,
     *     away_team_id: string,
     *     home_goals: int,
     *     away_goals: int
     * }>  $games
     * @return array<string, int>
     */
    private function goalBalancesAfterGames(EloquentCollection $teams, array $games): array
    {
        $goalBalances = array_fill_keys($teams->modelKeys(), 0);

        foreach ($games as $game) {
            $goalBalances[$game['home_team_id']] += $game['home_goals'] - $game['away_goals'];
            $goalBalances[$game['away_team_id']] += $game['away_goals'] - $game['home_goals'];
        }

        return $goalBalances;
    }
}
