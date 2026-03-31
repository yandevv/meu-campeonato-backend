<?php

namespace App\Services;

use App\Enums\RoundPhase;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TournamentSimulationService
{
    /**
     * @param  array<string, int>  $tournamentTeamOrder
     * @param  array<string, int>  $goalBalances
     *
     * @throws RuntimeException
     */
    public function __construct(
        public PythonGoalScorePredictor $goalScorePredictor,
    ) {}

    /**
     * @throws ConflictHttpException
     * @throws RuntimeException
     */
    public function simulateTournament(Tournament $tournament): Tournament
    {
        try {
            $tournament = Tournament::query()
                ->with([
                    'teams' => fn ($query) => $query->orderByPivot('created_at'),
                ])
                ->findOrFail($tournament->getKey());

            if ($tournament->teams->count() !== 8) {
                throw new ConflictHttpException('A tournament must have exactly 8 teams to be simulated.');
            }

            $simulationRounds = $this->buildSimulationRounds($tournament, $tournament->teams);

            return DB::transaction(function () use ($tournament, $simulationRounds): Tournament {
                $lockedTournament = Tournament::query()
                    ->whereKey($tournament->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedTournament->rounds()->delete();

                foreach ($simulationRounds as $roundData) {
                    $games = $roundData['games'];

                    unset($roundData['games']);

                    $round = $lockedTournament->rounds()->create($roundData);
                    $round->games()->createMany($games);
                }

                return $lockedTournament->load([
                    'teams',
                    'rounds.games.homeTeam',
                    'rounds.games.awayTeam',
                    'rounds.games.winnerTeam',
                ]);
            });
        } catch (ConflictHttpException $e) {
            throw $e;
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to simulate tournament: '.$e->getMessage(), 0, $e);
        }
    }

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
    private function buildSimulationRounds(Tournament $tournament, EloquentCollection $teams): array
    {
        $goalBalances = array_fill_keys($teams->pluck('id')->all(), 0);

        // To be used on game untie situation
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

        /** @var Collection<int, Team> $matchups */
        $matchups = $teams->values();

        foreach ($matchups->chunk(2)->values() as $index => $matchup) {
            /** @var Collection<int, Team> $pair */
            $pair = $matchup->values();

            /** @var Team $homeTeam */
            $homeTeam = $pair->get(0);
            /** @var Team $awayTeam */
            $awayTeam = $pair->get(1);

            if (!$homeTeam instanceof Team || !$awayTeam instanceof Team) {
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

            [$winner, $loser] = $this->resolveMatchWinner(
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

    /**
     * @param  array<string, int>  $goalBalances
     * @param  array<string, int>  $tournamentTeamOrder
     * @return array{0: Team, 1: Team}
     */
    private function resolveMatchWinner(
        Team $homeTeam,
        Team $awayTeam,
        int $homeGoals,
        int $awayGoals,
        array $goalBalances,
        array $tournamentTeamOrder,
    ): array {
        // Compare goals scored
        if ($homeGoals > $awayGoals) {
            return [$homeTeam, $awayTeam];
        }

        if ($awayGoals > $homeGoals) {
            return [$awayTeam, $homeTeam];
        }

        $homeGoalBalance = $goalBalances[$homeTeam->getKey()] ?? 0;
        $awayGoalBalance = $goalBalances[$awayTeam->getKey()] ?? 0;

        // Compare goal balances
        if ($homeGoalBalance > $awayGoalBalance) {
            return [$homeTeam, $awayTeam];
        }

        if ($awayGoalBalance > $homeGoalBalance) {
            return [$awayTeam, $homeTeam];
        }

        $homeTeamOrder = $tournamentTeamOrder[$homeTeam->getKey()] ?? PHP_INT_MAX;
        $awayTeamOrder = $tournamentTeamOrder[$awayTeam->getKey()] ?? PHP_INT_MAX;

        // Compare team insertions on the tournament bracket
        if ($homeTeamOrder <= $awayTeamOrder) {
            return [$homeTeam, $awayTeam];
        }

        return [$awayTeam, $homeTeam]; // Fallback if teams are still tied, return away team as winner
    }
}
