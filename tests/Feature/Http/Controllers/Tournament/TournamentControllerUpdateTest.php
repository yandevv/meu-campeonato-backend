<?php

namespace Tests\Feature\Http\Controllers\Tournament;

use App\Models\Tournament;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TournamentControllerUpdateTest extends FeatureTestCase
{
    public function test_it_updates_a_tournament_and_returns_the_updated_resource(): void
    {
        $tournament = Tournament::factory()->create([
            'name' => 'Champions Cup',
        ]);

        $response = $this->putJson(route('tournaments.update', $tournament), [
            'name' => 'Legends Cup',
        ]);
        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Tournament updated successfully.')
            ->assertJsonPath('data.id', $tournament->getKey())
            ->assertJsonPath('data.name', 'Legends Cup')
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

        $tournament->refresh();

        $this->assertModelExists($tournament);
        $this->assertSame('Legends Cup', $tournament->name);
    }

    public function test_it_requires_a_name_to_update_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->putJson(route('tournaments.update', $tournament), []);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_requires_the_name_to_be_a_string_to_update_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->putJson(route('tournaments.update', $tournament), [
            'name' => ['Legends Cup'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_requires_the_name_to_be_at_most_255_characters_to_update_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->putJson(route('tournaments.update', $tournament), [
            'name' => str_repeat('A', 256),
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_returns_not_found_when_updating_a_tournament_that_does_not_exist(): void
    {
        $response = $this->putJson(route('tournaments.update', [
            'tournament' => (string) Str::uuid(),
        ]), [
            'name' => 'Legends Cup',
        ]);

        $response->assertNotFound();
    }
}
