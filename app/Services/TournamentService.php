<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

class TournamentService
{
    /**
     * Get all tournaments.
     *
     * @return Collection<int, Tournament>
     *
     * @throws RuntimeException
     */
    public function getAllTournaments(): Collection
    {
        try {
            return Tournament::all();
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to retrieve tournaments: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Find a tournament by ID.
     *
     * @throws ModelNotFoundException
     * @throws RuntimeException
     */
    public function getTournamentById(int $id): Tournament
    {
        try {
            return Tournament::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to retrieve tournament: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a new tournament.
     *
     * @param  array{name: string}  $data
     *
     * @throws RuntimeException
     */
    public function createTournament(array $data): Tournament
    {
        try {
            return Tournament::create($data);
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to create tournament: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Update an existing tournament.
     *
     * @param  array{name?: string}  $data
     *
     * @throws RuntimeException
     */
    public function updateTournament(Tournament $tournament, array $data): Tournament
    {
        try {
            $tournament->update($data);

            return $tournament->refresh();
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to update tournament: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a tournament.
     *
     * @throws RuntimeException
     */
    public function deleteTournament(Tournament $tournament): void
    {
        try {
            $tournament->delete();
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to delete tournament: '.$e->getMessage(), 0, $e);
        }
    }
}
