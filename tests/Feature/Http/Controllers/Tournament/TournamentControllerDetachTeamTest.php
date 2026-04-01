<?php

namespace Tests\Feature\Http\Controllers\Tournament;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRound;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TournamentControllerDetachTeamTest extends FeatureTestCase
{
    public function test_it_detaches_a_team_from_a_tournament_and_returns_the_loaded_resource(): void
    {
        $tournament = Tournament::factory()->create([
            'name' => 'Champions Cup',
        ]);
        $detachedTeam = Team::factory()->create([
            'name' => 'Alpha FC',
        ]);
        $remainingTeam = Team::factory()->create([
            'name' => 'Beta FC',
        ]);

        $tournament->teams()->attach([
            $detachedTeam->getKey(),
            $remainingTeam->getKey(),
        ]);

        $response = $this->deleteJson(route('tournaments.teams.destroy', [
            'tournament' => $tournament,
            'team' => $detachedTeam,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Team removed from tournament successfully.')
            ->assertJsonPath('data.id', $tournament->getKey())
            ->assertJsonPath('data.name', 'Champions Cup')
            ->assertJsonCount(1, 'data.teams')
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
            ])
            ->assertJsonFragment([
                'id' => $remainingTeam->getKey(),
                'name' => 'Beta FC',
            ])
            ->assertJsonMissing([
                'id' => $detachedTeam->getKey(),
                'name' => 'Alpha FC',
            ]);

        $this->assertSame(
            [$remainingTeam->getKey()],
            $tournament->fresh()->teams()->pluck('teams.id')->all(),
        );
    }

    public function test_it_returns_not_found_when_detaching_a_team_from_a_tournament_that_does_not_exist(): void
    {
        $team = Team::factory()->create();

        $response = $this->deleteJson(route('tournaments.teams.destroy', [
            'tournament' => (string) Str::uuid(),
            'team' => $team,
        ]));

        $response->assertNotFound();
    }

    public function test_it_returns_not_found_when_detaching_a_team_that_does_not_exist(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->deleteJson(route('tournaments.teams.destroy', [
            'tournament' => $tournament,
            'team' => (string) Str::uuid(),
        ]));

        $response->assertNotFound();
    }

    public function test_it_returns_not_found_when_the_team_is_not_linked_to_the_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $linkedTeam = Team::factory()->create();
        $unlinkedTeam = Team::factory()->create();

        $tournament->teams()->attach($linkedTeam);

        $response = $this->deleteJson(route('tournaments.teams.destroy', [
            'tournament' => $tournament,
            'team' => $unlinkedTeam,
        ]));

        $response
            ->assertNotFound()
            ->assertJsonPath('statusCode', 404)
            ->assertJsonPath('message', 'The team is not linked to this tournament.');

        $this->assertSame(
            [$linkedTeam->getKey()],
            $tournament->fresh()->teams()->pluck('teams.id')->all(),
        );
    }

    public function test_it_returns_conflict_when_the_tournament_already_has_a_simulation(): void
    {
        $tournament = Tournament::factory()->create();
        $linkedTeam = Team::factory()->create();

        $tournament->teams()->attach($linkedTeam);
        TournamentRound::factory()->create([
            'tournament_id' => $tournament->getKey(),
        ]);

        $response = $this->deleteJson(route('tournaments.teams.destroy', [
            'tournament' => $tournament,
            'team' => $linkedTeam,
        ]));

        $response
            ->assertConflict()
            ->assertJsonPath('statusCode', 409)
            ->assertJsonPath('message', 'A team cannot be removed from a tournament that already has a simulation.');

        $this->assertSame(
            [$linkedTeam->getKey()],
            $tournament->fresh()->teams()->pluck('teams.id')->all(),
        );
    }
}
