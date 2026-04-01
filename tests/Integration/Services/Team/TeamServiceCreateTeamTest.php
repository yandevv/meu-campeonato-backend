<?php

namespace Tests\Integration\Services;

use App\Services\TeamService;
use RuntimeException;
use Tests\IntegrationTestCase;

class TeamServiceCreateTeamTest extends IntegrationTestCase
{
    public function test_it_persists_and_returns_the_created_team(): void
    {
        $team = app(TeamService::class)->createTeam([
            'name' => 'Alpha FC',
        ]);

        $this->assertModelExists($team);
        $this->assertSame('Alpha FC', $team->name);
    }

    public function test_it_wraps_database_failures_when_creating_a_team(): void
    {
        $originalDefaultConnection = config('database.default');

        config()->set('database.default', 'missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create team:');

        try {
            app(TeamService::class)->createTeam([
                'name' => 'Alpha FC',
            ]);
        } finally {
            config()->set('database.default', $originalDefaultConnection);
        }
    }
}
