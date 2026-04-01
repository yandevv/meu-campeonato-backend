<?php

namespace Tests\Feature\Http\Controllers\Team;

use App\Models\Team;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TeamControllerUpdateTest extends FeatureTestCase
{
    public function test_it_updates_a_team_and_returns_the_updated_resource(): void
    {
        $team = Team::factory()->create([
            'name' => 'Alpha FC',
        ]);

        $response = $this->putJson(route('teams.update', $team), [
            'name' => 'Beta FC',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Team updated successfully.')
            ->assertJsonPath('data.id', $team->getKey())
            ->assertJsonPath('data.name', 'Beta FC')
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

        $team->refresh();

        $this->assertModelExists($team);
        $this->assertSame('Beta FC', $team->name);
    }

    public function test_it_requires_a_name_to_update_a_team(): void
    {
        $team = Team::factory()->create();

        $response = $this->putJson(route('teams.update', $team), []);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_requires_the_name_to_be_a_string_to_update_a_team(): void
    {
        $team = Team::factory()->create();

        $response = $this->putJson(route('teams.update', $team), [
            'name' => ['Beta FC'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_requires_the_name_to_be_at_most_255_characters_to_update_a_team(): void
    {
        $team = Team::factory()->create();

        $response = $this->putJson(route('teams.update', $team), [
            'name' => str_repeat('A', 256),
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_returns_not_found_when_updating_a_team_that_does_not_exist(): void
    {
        $response = $this->putJson(route('teams.update', [
            'team' => (string) Str::uuid(),
        ]), [
            'name' => 'Beta FC',
        ]);

        $response->assertNotFound();
    }
}
