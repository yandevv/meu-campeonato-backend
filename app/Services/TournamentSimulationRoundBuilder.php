<?php

namespace App\Services;

use App\Enums\RoundPhase;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use RuntimeException;

class TournamentSimulationRoundBuilder
{
    /**
     * @throws RuntimeException
     */
    public function __construct(
        private PythonGoalScorePredictor $goalScorePredictor,
        private TournamentMatchWinnerResolver $matchWinnerResolver,
    ) {}

    /**
     * @param  EloquentCollection<int, Team>  $teams
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
    public function build(Tournament $tournament, EloquentCollection $teams): array
    {
        $goalBalances = array_fill_keys($teams->pluck('id')->all(), 0);

        $tournamentTeamOrder = $teams
            ->pluck('id')
            ->values()
            ->flip()
            ->map(static fn (mixed $position): int => (int) $position)
            ->all();

        $quarterFinalOutcome = $this->playRound(
            $teams->shuffle()->values(),
            $tournament,
            RoundPhase::QuarterFinals,
            $goalBalances,
            $tournamentTeamOrder,
        );

        $semiFinalOutcome = $this->playRound(
            $quarterFinalOutcome['winners']->shuffle()->values(),
            $tournament,
            RoundPhase::SemiFinals,
            $goalBalances,
            $tournamentTeamOrder,
        );

        $thirdPlaceOutcome = $this->playRound(
            $semiFinalOutcome['losers']->values(),
            $tournament,
            RoundPhase::ThirdPlace,
            $goalBalances,
            $tournamentTeamOrder,
        );

        $finalOutcome = $this->playRound(
            $semiFinalOutcome['winners']->values(),
            $tournament,
            RoundPhase::Finals,
            $goalBalances,
            $tournamentTeamOrder,
        );

        return [
            [
                'phase' => RoundPhase::QuarterFinals->value,
                'position' => 1,
                'games' => $quarterFinalOutcome['games'],
            ],
            [
                'phase' => RoundPhase::SemiFinals->value,
                'position' => 2,
                'games' => $semiFinalOutcome['games'],
            ],
            [
                'phase' => RoundPhase::ThirdPlace->value,
                'position' => 3,
                'games' => $thirdPlaceOutcome['games'],
            ],
            [
                'phase' => RoundPhase::Finals->value,
                'position' => 4,
                'games' => $finalOutcome['games'],
            ],
        ];
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  array<string, int>  $goalBalances
     * @param  array<string, int>  $tournamentTeamOrder
     * @return array{
     *     games: array<int, array{
     *         home_team_id: string,
     *         away_team_id: string,
     *         winner_team_id: string,
     *         home_goals: int,
     *         away_goals: int,
     *         position: int
     *     }>,
     *     winners: Collection<int, Team>,
     *     losers: Collection<int, Team>
     * }
     */
    private function playRound(
        Collection $teams,
        Tournament $tournament,
        RoundPhase $roundPhase,
        array &$goalBalances,
        array $tournamentTeamOrder,
    ): array {
        $games = [];
        $winners = collect();
        $losers = collect();

        foreach ($teams->values()->chunk(2)->values() as $index => $matchup) {
            /** @var Collection<int, Team> $pair */
            $pair = $matchup->values();

            /** @var Team|null $homeTeam */
            $homeTeam = $pair->get(0);
            /** @var Team|null $awayTeam */
            $awayTeam = $pair->get(1);

            if (! $homeTeam instanceof Team || ! $awayTeam instanceof Team) {
                throw new RuntimeException(
                    \sprintf(
                        'The %s round received an incomplete matchup.',
                        $roundPhase->value,
                    ),
                );
            }

            $prediction = $this->goalScorePredictor->predict(
                $tournament->getKey(),
                $roundPhase,
                $homeTeam->getKey(),
                $awayTeam->getKey(),
            );

            [$winner, $loser] = $this->matchWinnerResolver->resolve(
                $homeTeam,
                $awayTeam,
                $prediction['home_goals'],
                $prediction['away_goals'],
                $goalBalances,
                $tournamentTeamOrder,
            );

            $goalBalances[$homeTeam->getKey()] += $prediction['home_goals'] - $prediction['away_goals'];
            $goalBalances[$awayTeam->getKey()] += $prediction['away_goals'] - $prediction['home_goals'];

            $games[] = [
                'home_team_id' => $homeTeam->getKey(),
                'away_team_id' => $awayTeam->getKey(),
                'winner_team_id' => $winner->getKey(),
                'home_goals' => $prediction['home_goals'],
                'away_goals' => $prediction['away_goals'],
                'position' => $index + 1,
            ];

            $winners->push($winner);
            $losers->push($loser);
        }

        return [
            'games' => $games,
            'winners' => $winners,
            'losers' => $losers,
        ];
    }
}
