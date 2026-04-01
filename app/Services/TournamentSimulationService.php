<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TournamentSimulationService
{
    public function __construct(
        private TournamentSimulationRoundBuilder $roundBuilder,
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
                    'teams' => fn ($query) => $query->orderByPivot('id'),
                ])
                ->findOrFail($tournament->getKey());

            if ($tournament->teams->count() !== 8) {
                throw new ConflictHttpException('A tournament must have exactly 8 teams to be simulated.');
            }

            $simulationRounds = $this->roundBuilder->build($tournament, $tournament->teams);

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
}
