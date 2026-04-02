<?php

namespace App\Http\Resources;

use App\Enums\RoundPhase;
use App\Models\RoundGame;
use App\Models\Team;
use App\Models\TournamentRound;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     created_at: mixed,
     *     updated_at: mixed,
     *     teams?: mixed,
     *     rounds?: mixed,
     *     standings?: list<array{
     *         team: mixed,
     *         placement: int|null,
     *         last_phase: string|null,
     *         matches_played: int,
     *         wins: int,
     *         losses: int,
     *         goals_for: int,
     *         goals_against: int,
     *         goal_balance: int
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'teams' => TournamentTeamResource::collection($this->whenLoaded('teams')),
            'rounds' => TournamentRoundResource::collection($this->whenLoaded('rounds')),
            'standings' => $this->when(
                $this->resource->relationLoaded('teams') && $this->resource->relationLoaded('rounds'),
                fn (): array => $this->buildStandings(),
            ),
        ];
    }

    /**
     * @return list<array{
     *     team: TeamResource,
     *     placement: int|null,
     *     last_phase: string|null,
     *     matches_played: int,
     *     wins: int,
     *     losses: int,
     *     goals_for: int,
     *     goals_against: int,
     *     goal_balance: int
     * }>
     */
    private function buildStandings(): array
    {
        $finalRound = $this->findRoundByPhase(RoundPhase::Finals);
        $thirdPlaceRound = $this->findRoundByPhase(RoundPhase::ThirdPlace);
        $tournamentTeamOrder = $this->teams
            ->pluck('id')
            ->values()
            ->flip()
            ->map(static fn (mixed $position): int => (int) $position)
            ->all();

        /** @var array<string, array{
         *     team: Team,
         *     placement: int|null,
         *     last_phase: string|null,
         *     matches_played: int,
         *     wins: int,
         *     losses: int,
         *     goals_for: int,
         *     goals_against: int,
         *     goal_balance: int
         * }> $standings
         */
        $standings = $this->teams
            ->mapWithKeys(function (Team $team): array {
                return [
                    $team->getKey() => [
                        'team' => $team,
                        'placement' => null,
                        'last_phase' => null,
                        'matches_played' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'goals_for' => 0,
                        'goals_against' => 0,
                        'goal_balance' => 0,
                    ],
                ];
            })
            ->all();

        foreach ($this->rounds as $round) {
            foreach ($round->games as $game) {
                $this->accumulateGameStats($standings, $round, $game);
            }
        }

        $this->assignPlacements($standings, $finalRound, $thirdPlaceRound);

        return collect($standings)
            ->sort(function (array $leftStanding, array $rightStanding) use ($tournamentTeamOrder): int {
                $leftPlacement = $leftStanding['placement'] ?? PHP_INT_MAX;
                $rightPlacement = $rightStanding['placement'] ?? PHP_INT_MAX;

                if ($leftPlacement !== $rightPlacement) {
                    return $leftPlacement <=> $rightPlacement;
                }

                $leftTeamOrder = $tournamentTeamOrder[$leftStanding['team']->getKey()] ?? PHP_INT_MAX;
                $rightTeamOrder = $tournamentTeamOrder[$rightStanding['team']->getKey()] ?? PHP_INT_MAX;

                return $leftTeamOrder <=> $rightTeamOrder;
            })
            ->values()
            ->map(static fn (array $standing): array => [
                'team' => TeamResource::make($standing['team']),
                'placement' => $standing['placement'],
                'last_phase' => $standing['last_phase'],
                'matches_played' => $standing['matches_played'],
                'wins' => $standing['wins'],
                'losses' => $standing['losses'],
                'goals_for' => $standing['goals_for'],
                'goals_against' => $standing['goals_against'],
                'goal_balance' => $standing['goal_balance'],
            ])
            ->all();
    }

    /**
     * @param  array<string, array{
     *     team: Team,
     *     placement: int|null,
     *     last_phase: string|null,
     *     matches_played: int,
     *     wins: int,
     *     losses: int,
     *     goals_for: int,
     *     goals_against: int,
     *     goal_balance: int
     * }>  $standings
     */
    private function accumulateGameStats(array &$standings, TournamentRound $round, RoundGame $game): void
    {
        $this->updateStandingForGameSide(
            $standings,
            $game->home_team_id,
            $game->home_goals,
            $game->away_goals,
            $game->winner_team_id,
            $round->phase,
        );

        $this->updateStandingForGameSide(
            $standings,
            $game->away_team_id,
            $game->away_goals,
            $game->home_goals,
            $game->winner_team_id,
            $round->phase,
        );
    }

    /**
     * @param  array<string, array{
     *     team: Team,
     *     placement: int|null,
     *     last_phase: string|null,
     *     matches_played: int,
     *     wins: int,
     *     losses: int,
     *     goals_for: int,
     *     goals_against: int,
     *     goal_balance: int
     * }>  $standings
     */
    private function updateStandingForGameSide(
        array &$standings,
        string $teamId,
        int $goalsFor,
        int $goalsAgainst,
        string $winnerTeamId,
        RoundPhase $roundPhase,
    ): void {
        if (! isset($standings[$teamId])) {
            return;
        }

        $standings[$teamId]['matches_played']++;
        $standings[$teamId]['goals_for'] += $goalsFor;
        $standings[$teamId]['goals_against'] += $goalsAgainst;
        $standings[$teamId]['goal_balance'] += $goalsFor - $goalsAgainst;
        $standings[$teamId]['last_phase'] = $roundPhase->value;

        if ($winnerTeamId === $teamId) {
            $standings[$teamId]['wins']++;

            return;
        }

        $standings[$teamId]['losses']++;
    }

    /**
     * @param  array<string, array{
     *     team: Team,
     *     placement: int|null,
     *     last_phase: string|null,
     *     matches_played: int,
     *     wins: int,
     *     losses: int,
     *     goals_for: int,
     *     goals_against: int,
     *     goal_balance: int
     * }>  $standings
     */
    private function assignPlacements(
        array &$standings,
        ?TournamentRound $finalRound,
        ?TournamentRound $thirdPlaceRound,
    ): void {
        $this->assignPlacementFromRound($standings, $finalRound, 1, 2);
        $this->assignPlacementFromRound($standings, $thirdPlaceRound, 3, 4);
    }

    /**
     * @param  array<string, array{
     *     team: Team,
     *     placement: int|null,
     *     last_phase: string|null,
     *     matches_played: int,
     *     wins: int,
     *     losses: int,
     *     goals_for: int,
     *     goals_against: int,
     *     goal_balance: int
     * }>  $standings
     */
    private function assignPlacementFromRound(
        array &$standings,
        ?TournamentRound $round,
        int $winnerPlacement,
        int $loserPlacement,
    ): void {
        if ($round === null) {
            return;
        }

        /** @var RoundGame|null $game */
        $game = $round->games->first();

        if ($game === null) {
            return;
        }

        $loserTeamId = $this->resolveLoserTeamId($game);

        if (isset($standings[$game->winner_team_id])) {
            $standings[$game->winner_team_id]['placement'] = $winnerPlacement;
        }

        if ($loserTeamId !== null && isset($standings[$loserTeamId])) {
            $standings[$loserTeamId]['placement'] = $loserPlacement;
        }
    }

    private function resolveLoserTeamId(RoundGame $game): ?string
    {
        if ($game->winner_team_id === $game->home_team_id) {
            return $game->away_team_id;
        }

        if ($game->winner_team_id === $game->away_team_id) {
            return $game->home_team_id;
        }

        return null;
    }

    private function findRoundByPhase(RoundPhase $phase): ?TournamentRound
    {
        /** @var TournamentRound|null $round */
        $round = $this->rounds->first(
            static fn (TournamentRound $tournamentRound): bool => $tournamentRound->phase === $phase,
        );

        return $round;
    }
}
