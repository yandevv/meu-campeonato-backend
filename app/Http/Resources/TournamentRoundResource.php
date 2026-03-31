<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentRoundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: string, phase: string, position: int, games: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phase' => $this->phase->value,
            'position' => $this->position,
            'games' => RoundGameResource::collection($this->whenLoaded('games')),
        ];
    }
}
