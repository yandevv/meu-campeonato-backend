<?php

namespace Database\Seeders;

use App\Models\Tournament;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Available tournament names to pick from.
     *
     * @var array<int, string>
     */
    private array $tournamentNames = [
        'Brasileirão Série A',
        'Brasileirão Série B',
        'Copa do Brasil',
        'Libertadores da América',
        'Sul-Americana',
        'Recopa Sul-Americana',
        'Supercopa do Brasil',
        'Copa do Nordeste',
        'Campeonato Carioca',
        'Campeonato Paulista',
        'Campeonato Mineiro',
        'Campeonato Gaúcho',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tournament::factory(8)->create(); //! If you want to create with random tournament names.

        $alreadyAddedTournaments = [];
        for ( $i = 0; $i < 3; $i++ ) {
            $tournamentName = fake()->randomElement($this->tournamentNames);
            if (\in_array($tournamentName, $alreadyAddedTournaments)) {
                continue;
            }

            $alreadyAddedTournaments[] = $tournamentName;

            Tournament::factory()->create([
                'name' => $tournamentName,
            ]);
        }
    }
}
