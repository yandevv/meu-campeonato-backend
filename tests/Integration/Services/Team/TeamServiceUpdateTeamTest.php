<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Services\TeamService;
use RuntimeException;
use Tests\IntegrationTestCase;

class TeamServiceUpdateTeamTest extends IntegrationTestCase
{
    public function test_it_persists_and_returns_the_updated_team(): void
    {
        $team = Team::factory()->create([
            'name' => 'Alpha FC',
        ]);

        $updatedTeam = app(TeamService::class)->updateTeam($team, [
            'name' => 'Beta FC',
        ]);

        $this->assertModelExists($updatedTeam);
        $this->assertSame($team->getKey(), $updatedTeam->getKey());
        $this->assertSame('Beta FC', $updatedTeam->name);
        $this->assertSame('Beta FC', $team->fresh()->name);
    }

    public function test_it_wraps_database_failures_when_updating_a_team(): void
    {
        $team = Team::factory()->create([
            'name' => 'Alpha FC',
        ]);
        $originalConnectionName = $team->getConnectionName();

        $team->setConnection('missing');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update team:');

        try {
            app(TeamService::class)->updateTeam($team, [
                'name' => 'Beta FC',
            ]);
        } finally {
            $team->setConnection($originalConnectionName);

            $this->assertSame('Alpha FC', $team->fresh()->name);
        }
    }
}
