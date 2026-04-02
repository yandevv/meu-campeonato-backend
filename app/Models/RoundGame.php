<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tournament_round_id',
    'home_team_id',
    'away_team_id',
    'winner_team_id',
    'home_goals',
    'away_goals',
    'position',
])]
class RoundGame extends Model
{
    use HasFactory;
    use HasUuids;

    #[Scope]
    protected function involvingTeam(Builder $query, Team|string $team): void
    {
        $teamId = $team instanceof Team ? $team->getKey() : $team;

        $query->where(function (Builder $builder) use ($teamId): void {
            $builder
                ->where('home_team_id', $teamId)
                ->orWhere('away_team_id', $teamId);
        });
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(TournamentRound::class, 'tournament_round_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }
}
