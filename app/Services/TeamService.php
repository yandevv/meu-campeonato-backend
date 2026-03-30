<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

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
    public function getTeamById(int $id): Team
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
            $team->delete();
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to delete team: '.$e->getMessage(), 0, $e);
        }
    }
}
