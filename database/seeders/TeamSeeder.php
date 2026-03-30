<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Available team names to pick from.
     *
     * @var array<int, string>
     */
    private array $teamNames = [
        'Athletico Paranaense',
        'Atlético-MG',
        'Bahia',
        'Botafogo',
        'Chapecoense',
        'Corinthians',
        'Coritiba',
        'Cruzeiro',
        'Flamengo',
        'Fluminense',
        'Grêmio',
        'Internacional',
        'Mirassol',
        'Palmeiras',
        'Red Bull Bragantino',
        'Remo',
        'Santos',
        'Santos',
        'São Paulo',
        'Vasco da Gama',
        'Vitória',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Team::factory(8)->create(); //! If you want to create with random company names.

        foreach ($this->teamNames as $teamName) {
            Team::factory()->create([
                'name' => $teamName,
            ]);
        };
    }
}
