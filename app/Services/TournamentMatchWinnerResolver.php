<?php

namespace App\Services;

use App\Models\Team;

class TournamentMatchWinnerResolver
{
    /**
     * @param  array<string, int>  $goalBalances
     * @param  array<string, int>  $tournamentTeamOrder
     * @return array{0: Team, 1: Team}
     */
    public function resolve(
        Team $homeTeam,
        Team $awayTeam,
        int $homeGoals,
        int $awayGoals,
        array $goalBalances,
        array $tournamentTeamOrder,
    ): array {
        if ($homeGoals > $awayGoals) {
            return [$homeTeam, $awayTeam];
        }

        if ($awayGoals > $homeGoals) {
            return [$awayTeam, $homeTeam];
        }

        $homeGoalBalance = $goalBalances[$homeTeam->getKey()] ?? 0;
        $awayGoalBalance = $goalBalances[$awayTeam->getKey()] ?? 0;

        if ($homeGoalBalance > $awayGoalBalance) {
            return [$homeTeam, $awayTeam];
        }

        if ($awayGoalBalance > $homeGoalBalance) {
            return [$awayTeam, $homeTeam];
        }

        $homeTeamOrder = $tournamentTeamOrder[$homeTeam->getKey()] ?? PHP_INT_MAX;
        $awayTeamOrder = $tournamentTeamOrder[$awayTeam->getKey()] ?? PHP_INT_MAX;

        if ($homeTeamOrder <= $awayTeamOrder) {
            return [$homeTeam, $awayTeam];
        }

        return [$awayTeam, $homeTeam];
    }
}
