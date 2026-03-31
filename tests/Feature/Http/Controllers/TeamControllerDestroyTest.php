<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TeamControllerDestroyTest extends FeatureTestCase
{
    public function test_it_deletes_a_team_and_returns_no_content(): void
    {
        $team = Team::factory()->create();

        $response = $this->deleteJson(route('teams.destroy', $team));

        $response->assertNoContent();

        $this->assertSame('', $response->getContent());
        $this->assertModelMissing($team);
    }

    public function test_it_returns_conflict_when_deleting_a_team_linked_to_a_tournament(): void
    {
        $team = Team::factory()->create();
        $tournament = Tournament::factory()->create();

        $tournament->teams()->attach($team);

        $response = $this->deleteJson(route('teams.destroy', $team));

        $response
            ->assertConflict()
            ->assertJsonPath('statusCode', 409)
            ->assertJsonPath('message', 'A team cannot be deleted while linked to a tournament.');

        $this->assertModelExists($team);
    }

    public function test_it_returns_not_found_when_deleting_a_team_that_does_not_exist(): void
    {
        $response = $this->deleteJson(route('teams.destroy', [
            'team' => (string) Str::uuid(),
        ]));

        $response->assertNotFound();
    }
}
