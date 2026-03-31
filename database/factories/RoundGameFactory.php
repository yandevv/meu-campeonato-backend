<?php

namespace Database\Factories;

use App\Models\RoundGame;
use App\Models\Team;
use App\Models\TournamentRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoundGame>
 */
class RoundGameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_round_id' => TournamentRound::factory(),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'winner_team_id' => fn (array $attributes): string => fake()->randomElement([
                $attributes['home_team_id'],
                $attributes['away_team_id'],
            ]),
            'home_goals' => fake()->numberBetween(0, 7),
            'away_goals' => fake()->numberBetween(0, 7),
            'position' => fake()->numberBetween(1, 4),
        ];
    }

    public function forRound(TournamentRound $round, int $position): static
    {
        return $this->state(fn (): array => [
            'tournament_round_id' => $round->getKey(),
            'position' => $position,
        ]);
    }
}
