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
     *     winner_team: mixed
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
            'winner_team' => $this->whenLoaded('winnerTeam', fn () => new TeamResource($this->winnerTeam)),
        ];
    }
}
