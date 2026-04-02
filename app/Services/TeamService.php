<?php

namespace App\Services;

use App\Models\RoundGame;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TeamService
{
    /**
     * Get all teams.
     *
     * @return Collection<int, Team>
     *
     * @throws RuntimeException
     */
    public function getAllTeams(): Collection
    {
        try {
            return Team::all();
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to retrieve teams: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Find a team by ID.
     *
     * @throws ModelNotFoundException
     * @throws RuntimeException
     */
    public function getTeamById(string $id): Team
    {
        try {
            return Team::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to retrieve team: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Load recent match history and total match count for a team.
     *
     * @throws RuntimeException
     */
    public function loadTeamMatchHistory(Team $team, int $limit = 5): Team
    {
        try {
            $matchHistoryQuery = RoundGame::query()->involvingTeam($team);
            $gamesCount = (clone $matchHistoryQuery)->count();
            $recentGames = $matchHistoryQuery
                ->with([
                    'homeTeam',
                    'awayTeam',
                    'round.tournament',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $team->setAttribute('games_count', $gamesCount);
            $team->setRelation('recentGames', $recentGames);

            return $team;
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to load team match history: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a new team.
     *
     * @param  array{name: string}  $data
     *
     * @throws RuntimeException
     */
    public function createTeam(array $data): Team
    {
        try {
            return Team::create($data);
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to create team: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Update an existing team.
     *
     * @param  array{name?: string}  $data
     *
     * @throws RuntimeException
     */
    public function updateTeam(Team $team, array $data): Team
    {
        try {
            $team->update($data);

            return $team->refresh();
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to update team: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a team.
     *
     * @throws RuntimeException
     */
    public function deleteTeam(Team $team): void
    {
        try {
            if ($team->tournaments()->exists()) {
                throw new ConflictHttpException('A team cannot be deleted while linked to a tournament.');
            }

            $team->delete();
        } catch (ConflictHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to delete team: '.$e->getMessage(), 0, $e);
        }
    }
}
