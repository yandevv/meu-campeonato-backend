<?php

namespace Tests\Feature\Http\Controllers\Team;

use App\Models\Team;
use Tests\FeatureTestCase;

class TeamControllerIndexTest extends FeatureTestCase
{
    public function test_it_returns_the_team_collection_response(): void
    {
        $teams = Team::factory()->count(2)->create();

        $response = $this->getJson(route('teams.index'));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Teams retrieved successfully.')
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'statusCode',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        foreach ($teams as $team) {
            $response->assertJsonFragment([
                'id' => $team->getKey(),
                'name' => $team->name,
            ]);
        }
    }

    public function test_it_returns_an_empty_collection_when_there_are_no_teams(): void
    {
        $response = $this->getJson(route('teams.index'));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Teams retrieved successfully.')
            ->assertJsonCount(0, 'data')
            ->assertExactJson([
                'statusCode' => 200,
                'message' => 'Teams retrieved successfully.',
                'data' => [],
            ]);
    }
}
