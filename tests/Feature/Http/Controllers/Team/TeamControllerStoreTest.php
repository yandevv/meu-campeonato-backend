<?php

namespace Tests\Feature\Http\Controllers\Team;

use App\Models\Team;
use Tests\FeatureTestCase;

class TeamControllerStoreTest extends FeatureTestCase
{
    public function test_it_creates_a_team_and_returns_the_created_resource(): void
    {
        $response = $this->postJson(route('teams.store'), [
            'name' => 'Alpha FC',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('statusCode', 201)
            ->assertJsonPath('message', 'Team created successfully.')
            ->assertJsonPath('data.name', 'Alpha FC')
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

        $createdTeam = Team::query()->findOrFail($response->json('data.id'));

        $response->assertJsonPath('data.id', $createdTeam->getKey());

        $this->assertModelExists($createdTeam);
        $this->assertSame('Alpha FC', $createdTeam->name);
    }

    public function test_it_requires_a_name_to_create_a_team(): void
    {
        $response = $this->postJson(route('teams.store'), []);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_requires_the_name_to_be_a_string_to_create_a_team(): void
    {
        $response = $this->postJson(route('teams.store'), [
            'name' => ['Alpha FC'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_requires_the_name_to_be_at_most_255_characters_to_create_a_team(): void
    {
        $response = $this->postJson(route('teams.store'), [
            'name' => str_repeat('A', 256),
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }
}
