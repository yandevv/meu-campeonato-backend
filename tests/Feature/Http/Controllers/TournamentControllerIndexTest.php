<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Tournament;
use Tests\FeatureTestCase;

class TournamentControllerIndexTest extends FeatureTestCase
{
    public function test_it_returns_the_tournament_collection_response(): void
    {
        $tournaments = Tournament::factory()->count(2)->create();

        $response = $this->getJson(route('tournaments.index'));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Tournaments retrieved successfully.')
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

        foreach ($tournaments as $tournament) {
            $response->assertJsonFragment([
                'id' => $tournament->getKey(),
                'name' => $tournament->name,
            ]);
        }
    }

    public function test_it_returns_an_empty_collection_when_there_are_no_tournaments(): void
    {
        $response = $this->getJson(route('tournaments.index'));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Tournaments retrieved successfully.')
            ->assertJsonCount(0, 'data')
            ->assertExactJson([
                'statusCode' => 200,
                'message' => 'Tournaments retrieved successfully.',
                'data' => [],
            ]);
    }
}
