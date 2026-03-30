<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
    public function getTournamentById(string $id): Tournament
    {
        try {
            return Tournament::with('teams')->findOrFail($id);
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
     * @param  array<int, string>  $teamIds
     *
     * @throws ConflictHttpException
     * @throws RuntimeException
     */
    public function attachTeamsToTournament(Tournament $tournament, array $teamIds): Tournament
    {
        try {
            return DB::transaction(function () use ($tournament, $teamIds): Tournament {
                $lockedTournament = Tournament::query()
                    ->whereKey($tournament->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingTeamIds = $lockedTournament->teams()
                    ->whereIn('teams.id', $teamIds)
                    ->pluck('teams.id');

                if ($existingTeamIds->isNotEmpty()) {
                    throw new ConflictHttpException('One or more teams are already linked to this tournament.');
                }

                $linkedTeamsCount = $lockedTournament->teams()->count();

                if ($linkedTeamsCount + count($teamIds) > 8) {
                    throw new ConflictHttpException('A tournament can have at most 8 teams.');
                }

                $lockedTournament->teams()->attach($teamIds);

                return $lockedTournament->load('teams');
            });
        } catch (ConflictHttpException $e) {
            throw $e;
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to attach teams to tournament: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws NotFoundHttpException
     * @throws RuntimeException
     */
    public function detachTeamFromTournament(Tournament $tournament, string $teamId): Tournament
    {
        try {
            return DB::transaction(function () use ($tournament, $teamId): Tournament {
                $lockedTournament = Tournament::query()
                    ->whereKey($tournament->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $isLinked = $lockedTournament->teams()
                    ->where('teams.id', $teamId)
                    ->exists();

                if (! $isLinked) {
                    throw new NotFoundHttpException('The team is not linked to this tournament.');
                }

                $lockedTournament->teams()->detach($teamId);

                return $lockedTournament->load('teams');
            });
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to detach team from tournament: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws RuntimeException
     */
    public function loadTournamentRoster(Tournament $tournament): Tournament
    {
        try {
            return $tournament->load('teams');
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to load tournament roster: '.$e->getMessage(), 0, $e);
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
