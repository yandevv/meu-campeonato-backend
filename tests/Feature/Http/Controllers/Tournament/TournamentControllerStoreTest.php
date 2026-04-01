<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Tournament;
use Tests\FeatureTestCase;

class TournamentControllerStoreTest extends FeatureTestCase
{
    public function test_it_creates_a_tournament_and_returns_the_created_resource(): void
    {
        $response = $this->postJson(route('tournaments.store'), [
            'name' => 'Champions Cup',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('statusCode', 201)
            ->assertJsonPath('message', 'Tournament created successfully.')
            ->assertJsonPath('data.name', 'Champions Cup')
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

        $createdTournament = Tournament::query()->findOrFail($response->json('data.id'));

        $response->assertJsonPath('data.id', $createdTournament->getKey());

        $this->assertModelExists($createdTournament);
        $this->assertSame('Champions Cup', $createdTournament->name);
    }

    public function test_it_requires_a_name_to_create_a_tournament(): void
    {
        $response = $this->postJson(route('tournaments.store'), []);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_requires_the_name_to_be_a_string_to_create_a_tournament(): void
    {
        $response = $this->postJson(route('tournaments.store'), [
            'name' => ['Champions Cup'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }

    public function test_it_requires_the_name_to_be_at_most_255_characters_to_create_a_tournament(): void
    {
        $response = $this->postJson(route('tournaments.store'), [
            'name' => str_repeat('A', 256),
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['name']);
    }
}
