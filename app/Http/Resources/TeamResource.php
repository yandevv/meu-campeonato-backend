<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     created_at: string|null,
     *     updated_at: string|null,
     *     games_count?: int,
     *     recent_games?: mixed
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'games_count' => $this->when(isset($this->games_count), $this->games_count),
            'recent_games' => RoundGameResource::collection($this->whenLoaded('recentGames')),
        ];
    }
}
