<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
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
}
