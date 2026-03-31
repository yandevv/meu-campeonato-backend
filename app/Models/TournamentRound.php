<?php

namespace App\Models;

use App\Enums\RoundPhase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tournament_id', 'phase', 'position'])]
class TournamentRound extends Model
{
    use HasFactory;
    use HasUuids;

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(RoundGame::class)->orderBy('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => RoundPhase::class,
        ];
    }
}
