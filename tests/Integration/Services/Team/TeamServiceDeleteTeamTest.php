<?php

namespace Tests\Integration\Services;

use App\Models\Team;
use App\Models\Tournament;
use App\Services\TeamService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\IntegrationTestCase;

class TeamServiceDeleteTeamTest extends IntegrationTestCase
{
    public function test_it_deletes_a_persisted_team(): void
    {
        $team = Team::factory()->create();

        app(TeamService::class)->deleteTeam($team);

        $this->assertModelMissing($team);
    }

    public function test_it_throws_a_conflict_when_deleting_a_team_linked_to_a_tournament(): void
    {
        $team = Team::factory()->create();
        $tournament = Tournament::factory()->create();

        $tournament->teams()->attach($team);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('A team cannot be deleted while linked to a tournament.');

        try {
            app(TeamService::class)->deleteTeam($team);
        } finally {
            $this->assertModelExists($team);
        }
    }
}
