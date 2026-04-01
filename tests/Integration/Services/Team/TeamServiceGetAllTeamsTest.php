<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Services\TeamService;
use RuntimeException;
use Tests\IntegrationTestCase;

class TeamServiceGetAllTeamsTest extends IntegrationTestCase
{
    public function test_it_returns_an_empty_collection_when_no_teams_exist(): void
    {
        $teams = app(TeamService::class)->getAllTeams();

        $this->assertCount(0, $teams);
    }

    public function test_it_returns_persisted_teams_from_the_database(): void
    {
        $createdTeams = Team::factory()->count(2)->create();

        $teams = app(TeamService::class)->getAllTeams();

        $this->assertCount(2, $teams);
        $this->assertEqualsCanonicalizing(
            $createdTeams->modelKeys(),
            $teams->modelKeys(),
        );
        $this->assertEqualsCanonicalizing(
            $createdTeams->pluck('name')->all(),
            $teams->pluck('name')->all(),
        );
    }

    public function test_it_wraps_database_failures_when_retrieving_teams(): void
    {
        $originalDefaultConnection = config('database.default');

        config()->set('database.default', 'missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to retrieve teams:');

        try {
            app(TeamService::class)->getAllTeams();
        } finally {
            config()->set('database.default', $originalDefaultConnection);
        }
    }
}
