<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoundGameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string,
     *     position: int,
     *     home_goals: int,
     *     away_goals: int,
     *     home_team_id: string,
     *     away_team_id: string,
     *     winner_team_id: string,
     *     home_team: mixed,
     *     away_team: mixed,
     *     round?: array{id: string, phase: string, position: int},
     *     tournament?: array{id: string, name: string}
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'home_goals' => $this->home_goals,
            'away_goals' => $this->away_goals,
            'home_team_id' => $this->home_team_id,
            'away_team_id' => $this->away_team_id,
            'winner_team_id' => $this->winner_team_id,
            'home_team' => $this->whenLoaded('homeTeam', fn () => new TeamResource($this->homeTeam)),
            'away_team' => $this->whenLoaded('awayTeam', fn () => new TeamResource($this->awayTeam)),
            'round' => $this->whenLoaded('round', fn (): array => [
                'id' => $this->round->getKey(),
                'phase' => $this->round->phase->value,
                'position' => $this->round->position,
            ]),
            'tournament' => $this->when(
                $this->relationLoaded('round')
                    && $this->round !== null
                    && $this->round->relationLoaded('tournament')
                    && $this->round->tournament !== null,
                fn (): array => [
                    'id' => $this->round->tournament->getKey(),
                    'name' => $this->round->tournament->name,
                ],
            ),
        ];
    }
}
