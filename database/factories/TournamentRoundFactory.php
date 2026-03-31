<?php

namespace Database\Factories;

use App\Enums\RoundPhase;
use App\Models\Tournament;
use App\Models\TournamentRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentRound>
 */
class TournamentRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'phase' => fake()->randomElement(RoundPhase::cases()),
            'position' => fake()->numberBetween(1, 4),
        ];
    }

    public function forPhase(RoundPhase $phase, int $position): static
    {
        return $this->state(fn (): array => [
            'phase' => $phase,
            'position' => $position,
        ]);
    }
}
