<?php

namespace App\Http\Resources;

use App\Enums\RoundPhase;
use App\Models\RoundGame;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     created_at: mixed,
     *     updated_at: mixed,
     *     teams?: mixed,
     *     rounds?: mixed,
     *     podium?: array<string, mixed>|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'teams' => TournamentTeamResource::collection($this->whenLoaded('teams')),
            'rounds' => TournamentRoundResource::collection($this->whenLoaded('rounds')),
            'podium' => $this->whenLoaded('rounds', fn (): ?array => $this->buildPodium()),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPodium(): ?array
    {
        $finalRound = $this->rounds->firstWhere('phase', RoundPhase::Finals);
        $thirdPlaceRound = $this->rounds->firstWhere('phase', RoundPhase::ThirdPlace);

        if ($finalRound === null || $thirdPlaceRound === null) {
            return null;
        }

        /** @var RoundGame|null $finalGame */
        $finalGame = $finalRound->games->first();
        /** @var RoundGame|null $thirdPlaceGame */
        $thirdPlaceGame = $thirdPlaceRound->games->first();

        if ($finalGame === null || $thirdPlaceGame === null) {
            return null;
        }

        $secondPlaceTeam = $finalGame->winner_team_id === $finalGame->home_team_id
            ? $finalGame->awayTeam
            : $finalGame->homeTeam;

        return [
            'first_place' => TeamResource::make($finalGame->winnerTeam),
            'second_place' => TeamResource::make($secondPlaceTeam),
            'third_place' => TeamResource::make($thirdPlaceGame->winnerTeam),
        ];
    }
}
