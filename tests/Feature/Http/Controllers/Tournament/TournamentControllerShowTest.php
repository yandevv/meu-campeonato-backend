<?php

namespace Tests\Feature\Http\Controllers\Tournament;

use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TournamentControllerShowTest extends FeatureTestCase
{
    public function test_it_returns_the_requested_tournament_with_its_loaded_roster(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(2)->create();

        $tournament->teams()->attach($teams->modelKeys());

        $response = $this->getJson(route('tournaments.show', $tournament));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Tournament retrieved successfully.')
            ->assertJsonPath('data.id', $tournament->getKey())
            ->assertJsonPath('data.name', $tournament->name)
            ->assertJsonPath('data.teams.0.id', $teams[0]->getKey())
            ->assertJsonPath('data.teams.1.id', $teams[1]->getKey())
            ->assertJsonCount(2, 'data.teams')
            ->assertJsonStructure([
                'statusCode',
                'message',
                'data' => [
                    'id',
                    'name',
                    'created_at',
                    'updated_at',
                    'teams' => [
                        '*' => [
                            'id',
                            'name',
                            'joined_at',
                            'updated_at',
                        ],
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

    public function test_it_returns_not_found_when_the_tournament_does_not_exist(): void
    {
        $response = $this->getJson(route('tournaments.show', [
            'tournament' => (string) Str::uuid(),
        ]));

        $response->assertNotFound();
    }
}
