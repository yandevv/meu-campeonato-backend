<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Tournament;
use App\Services\TournamentSimulationService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class TournamentSimulationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(TournamentSimulationService $tournamentSimulationService): void
    {
        $teamIds = Team::query()
            ->pluck('id');

        if ($teamIds->count() < 8) {
            throw new RuntimeException('At least 8 teams are required before seeding tournament simulations.');
        }

        Tournament::query()
            ->get()
            ->each(function (Tournament $tournament) use ($teamIds, $tournamentSimulationService): void {
                $selectedTeamIds = $teamIds
                    ->shuffle()
                    ->take(8)
                    ->values()
                    ->all();

                $tournament->teams()->sync($selectedTeamIds);

                $tournamentSimulationService->simulateTournament($tournament);
            });
    }
}
