<?php

namespace Tests\Feature\Http\Controllers\Team;

use App\Models\Team;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TeamControllerShowTest extends FeatureTestCase
{
    public function test_it_returns_the_requested_team_resource(): void
    {
        $team = Team::factory()->create();

        $response = $this->getJson(route('teams.show', $team));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Team retrieved successfully.')
            ->assertJsonPath('data.id', $team->getKey())
            ->assertJsonPath('data.name', $team->name)
            ->assertJsonStructure([
                'statusCode',
                'message',
                'data' => [
                    'id',
                    'name',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_it_returns_not_found_when_the_team_does_not_exist(): void
    {
        $response = $this->getJson(route('teams.show', [
            'team' => (string) Str::uuid(),
        ]));

        $response->assertNotFound();
    }
}
