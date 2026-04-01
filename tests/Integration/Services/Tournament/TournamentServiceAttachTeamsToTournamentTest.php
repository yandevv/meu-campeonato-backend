<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Models\Tournament;
use App\Services\TournamentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\IntegrationTestCase;

class TournamentServiceAttachTeamsToTournamentTest extends IntegrationTestCase
{
    public function test_it_attaches_persisted_teams_and_returns_the_tournament_with_the_loaded_roster(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(2)->create();

        $updatedTournament = app(TournamentService::class)->attachTeamsToTournament($tournament, $teams->modelKeys());

        $this->assertTrue($updatedTournament->relationLoaded('teams'));
        $this->assertCount(2, $updatedTournament->teams);
        $this->assertEqualsCanonicalizing(
            $teams->modelKeys(),
            $updatedTournament->teams->modelKeys(),
        );
        $this->assertEqualsCanonicalizing(
            $teams->modelKeys(),
            $tournament->fresh()->teams()->pluck('teams.id')->all(),
        );
        $this->assertTrue(
            collect(DB::table('tournament_teams')
                ->where('tournament_id', $tournament->getKey())
                ->pluck('id'))
                ->every(static fn (string $id): bool => Str::isUuid($id, version: 7)),
        );
    }

    public function test_it_throws_a_conflict_when_any_team_is_already_linked_to_the_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $linkedTeam = Team::factory()->create();
        $newTeam = Team::factory()->create();

        $tournament->teams()->attach($linkedTeam);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('One or more teams are already linked to this tournament.');

        try {
            app(TournamentService::class)->attachTeamsToTournament($tournament, [
                $linkedTeam->getKey(),
                $newTeam->getKey(),
            ]);
        } finally {
            $this->assertEqualsCanonicalizing(
                [$linkedTeam->getKey()],
                $tournament->fresh()->teams()->pluck('teams.id')->all(),
            );
        }
    }

    public function test_it_throws_a_conflict_when_attaching_teams_would_exceed_the_maximum_roster_size(): void
    {
        $tournament = Tournament::factory()->create();
        $existingTeams = Team::factory()->count(7)->create();
        $newTeams = Team::factory()->count(2)->create();

        $tournament->teams()->attach($existingTeams->modelKeys());

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('A tournament can have at most 8 teams.');

        try {
            app(TournamentService::class)->attachTeamsToTournament($tournament, $newTeams->modelKeys());
        } finally {
            $this->assertEqualsCanonicalizing(
                $existingTeams->modelKeys(),
                $tournament->fresh()->teams()->pluck('teams.id')->all(),
            );
        }
    }

    public function test_it_throws_model_not_found_when_the_tournament_no_longer_exists(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(2)->create();

        $tournament->delete();

        $this->expectException(ModelNotFoundException::class);

        app(TournamentService::class)->attachTeamsToTournament($tournament, $teams->modelKeys());
    }

    public function test_it_wraps_database_failures_when_attaching_teams_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to attach teams to tournament:');

        app(TournamentService::class)->attachTeamsToTournament($tournament, [
            (string) Str::uuid(),
        ]);
    }
}
