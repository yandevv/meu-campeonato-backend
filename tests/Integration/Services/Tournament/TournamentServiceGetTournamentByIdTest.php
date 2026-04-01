<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Models\Tournament;
use App\Services\TournamentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\IntegrationTestCase;

class TournamentServiceGetTournamentByIdTest extends IntegrationTestCase
{
    public function test_it_returns_the_persisted_tournament_by_id_with_its_loaded_roster(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(2)->create();

        $tournament->teams()->attach($teams->modelKeys());

        $retrievedTournament = app(TournamentService::class)->getTournamentById($tournament->getKey());

        $this->assertModelExists($retrievedTournament);
        $this->assertTrue($tournament->is($retrievedTournament));
        $this->assertTrue($retrievedTournament->relationLoaded('teams'));
        $this->assertEqualsCanonicalizing(
            $teams->modelKeys(),
            $retrievedTournament->teams->modelKeys(),
        );
    }

    public function test_it_rethrows_model_not_found_when_the_tournament_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(TournamentService::class)->getTournamentById((string) Str::uuid());
    }

    public function test_it_wraps_database_failures_when_retrieving_a_tournament_by_id(): void
    {
        $tournament = Tournament::factory()->create();
        $originalDefaultConnection = config('database.default');

        config()->set('database.default', 'missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to retrieve tournament:');

        try {
            app(TournamentService::class)->getTournamentById($tournament->getKey());
        } finally {
            config()->set('database.default', $originalDefaultConnection);
        }
    }
}
