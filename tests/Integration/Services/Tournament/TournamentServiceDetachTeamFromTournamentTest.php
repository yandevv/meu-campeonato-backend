<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\TournamentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\IntegrationTestCase;

class TournamentServiceDetachTeamFromTournamentTest extends IntegrationTestCase
{
    public function test_it_detaches_a_persisted_team_and_returns_the_tournament_with_the_loaded_roster(): void
    {
        $tournament = Tournament::factory()->create();
        $detachedTeam = Team::factory()->create();
        $remainingTeam = Team::factory()->create();

        $tournament->teams()->attach([
            $detachedTeam->getKey(),
            $remainingTeam->getKey(),
        ]);

        $updatedTournament = app(TournamentService::class)->detachTeamFromTournament(
            $tournament,
            $detachedTeam->getKey(),
        );

        $this->assertTrue($updatedTournament->relationLoaded('teams'));
        $this->assertCount(1, $updatedTournament->teams);
        $this->assertSame(
            [$remainingTeam->getKey()],
            $updatedTournament->teams->modelKeys(),
        );
        $this->assertSame(
            [$remainingTeam->getKey()],
            $tournament->fresh()->teams()->pluck('teams.id')->all(),
        );
    }

    public function test_it_throws_not_found_when_the_team_is_not_linked_to_the_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $linkedTeam = Team::factory()->create();
        $unlinkedTeam = Team::factory()->create();

        $tournament->teams()->attach($linkedTeam);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The team is not linked to this tournament.');

        try {
            app(TournamentService::class)->detachTeamFromTournament($tournament, $unlinkedTeam->getKey());
        } finally {
            $this->assertSame(
                [$linkedTeam->getKey()],
                $tournament->fresh()->teams()->pluck('teams.id')->all(),
            );
        }
    }

    public function test_it_throws_a_conflict_when_the_tournament_already_has_a_simulation(): void
    {
        $tournament = Tournament::factory()->create();
        $linkedTeam = Team::factory()->create();

        $tournament->teams()->attach($linkedTeam);
        TournamentRound::factory()->create([
            'tournament_id' => $tournament->getKey(),
        ]);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('A team cannot be removed from a tournament that already has a simulation.');

        try {
            app(TournamentService::class)->detachTeamFromTournament($tournament, $linkedTeam->getKey());
        } finally {
            $this->assertSame(
                [$linkedTeam->getKey()],
                $tournament->fresh()->teams()->pluck('teams.id')->all(),
            );
        }
    }

    public function test_it_throws_model_not_found_when_the_tournament_no_longer_exists(): void
    {
        $tournament = Tournament::factory()->create();
        $team = Team::factory()->create();

        $tournament->delete();

        $this->expectException(ModelNotFoundException::class);

        app(TournamentService::class)->detachTeamFromTournament($tournament, $team->getKey());
    }

    public function test_it_wraps_database_failures_when_detaching_a_team_from_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $team = Team::factory()->create();
        $originalDefaultConnection = config('database.default');

        $tournament->teams()->attach($team);
        config()->set('database.default', 'missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to detach team from tournament:');

        try {
            app(TournamentService::class)->detachTeamFromTournament($tournament, $team->getKey());
        } finally {
            config()->set('database.default', $originalDefaultConnection);

            $this->assertSame(
                [$team->getKey()],
                $tournament->fresh()->teams()->pluck('teams.id')->all(),
            );
        }
    }
}
