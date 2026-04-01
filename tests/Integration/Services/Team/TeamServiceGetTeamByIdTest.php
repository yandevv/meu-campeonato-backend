<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\IntegrationTestCase;

class TeamServiceGetTeamByIdTest extends IntegrationTestCase
{
    public function test_it_returns_the_persisted_team_by_id(): void
    {
        $team = Team::factory()->create();

        $retrievedTeam = app(TeamService::class)->getTeamById($team->getKey());

        $this->assertModelExists($retrievedTeam);
        $this->assertTrue($team->is($retrievedTeam));
        $this->assertSame($team->name, $retrievedTeam->name);
    }

    public function test_it_rethrows_model_not_found_when_the_team_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(TeamService::class)->getTeamById((string) Str::uuid());
    }

    public function test_it_wraps_database_failures_when_retrieving_a_team_by_id(): void
    {
        $team = Team::factory()->create();
        $originalDefaultConnection = config('database.default');

        config()->set('database.default', 'missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to retrieve team:');

        try {
            app(TeamService::class)->getTeamById($team->getKey());
        } finally {
            config()->set('database.default', $originalDefaultConnection);
        }
    }
}
