<?php

namespace Tests\Feature\Http\Controllers\Tournament;

use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TournamentControllerAttachTeamsTest extends FeatureTestCase
{
    public function test_it_attaches_teams_to_a_tournament_and_returns_the_loaded_resource(): void
    {
        $tournament = Tournament::factory()->create([
            'name' => 'Champions Cup',
        ]);
        $teams = Team::factory()->count(2)->create();

        $response = $this->postJson(route('tournaments.teams.store', $tournament), [
            'team_ids' => $teams->modelKeys(),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('message', 'Teams added to tournament successfully.')
            ->assertJsonPath('data.id', $tournament->getKey())
            ->assertJsonPath('data.name', 'Champions Cup')
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

        $this->assertEqualsCanonicalizing(
            $teams->modelKeys(),
            $tournament->fresh()->teams()->pluck('teams.id')->all(),
        );
    }

    public function test_it_requires_team_ids_to_attach_teams_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->postJson(route('tournaments.teams.store', $tournament), []);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['team_ids']);
    }

    public function test_it_requires_team_ids_to_be_an_array_to_attach_teams_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->postJson(route('tournaments.teams.store', $tournament), [
            'team_ids' => 'not-an-array',
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['team_ids']);
    }

    public function test_it_requires_at_least_one_team_id_to_attach_teams_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->postJson(route('tournaments.teams.store', $tournament), [
            'team_ids' => [],
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['team_ids']);
    }

    public function test_it_requires_no_more_than_eight_team_ids_to_attach_teams_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $teams = Team::factory()->count(9)->create();

        $response = $this->postJson(route('tournaments.teams.store', $tournament), [
            'team_ids' => $teams->modelKeys(),
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['team_ids']);
    }

    public function test_it_requires_each_team_id_to_be_a_valid_uuid_to_attach_teams_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->postJson(route('tournaments.teams.store', $tournament), [
            'team_ids' => ['not-a-uuid'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['team_ids.0']);
    }

    public function test_it_requires_team_ids_to_be_distinct_to_attach_teams_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $team = Team::factory()->create();

        $response = $this->postJson(route('tournaments.teams.store', $tournament), [
            'team_ids' => [$team->getKey(), $team->getKey()],
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['team_ids.0']);
    }

    public function test_it_requires_each_team_id_to_exist_to_attach_teams_to_a_tournament(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->postJson(route('tournaments.teams.store', $tournament), [
            'team_ids' => [(string) Str::uuid()],
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['team_ids.0']);
    }

    public function test_it_returns_not_found_when_attaching_teams_to_a_tournament_that_does_not_exist(): void
    {
        $teams = Team::factory()->count(2)->create();

        $response = $this->postJson(route('tournaments.teams.store', [
            'tournament' => (string) Str::uuid(),
        ]), [
            'team_ids' => $teams->modelKeys(),
        ]);

        $response->assertNotFound();
    }

    public function test_it_returns_conflict_when_one_or_more_teams_are_already_linked_to_the_tournament(): void
    {
        $tournament = Tournament::factory()->create();
        $linkedTeam = Team::factory()->create();
        $newTeam = Team::factory()->create();

        $tournament->teams()->attach($linkedTeam);

        $response = $this->postJson(route('tournaments.teams.store', $tournament), [
            'team_ids' => [$linkedTeam->getKey(), $newTeam->getKey()],
        ]);

        $response
            ->assertConflict()
            ->assertJsonPath('statusCode', 409)
            ->assertJsonPath('message', 'One or more teams are already linked to this tournament.');

        $this->assertEqualsCanonicalizing(
            [$linkedTeam->getKey()],
            $tournament->fresh()->teams()->pluck('teams.id')->all(),
        );
    }

    public function test_it_returns_conflict_when_attaching_teams_would_exceed_the_maximum_roster_size(): void
    {
        $tournament = Tournament::factory()->create();
        $existingTeams = Team::factory()->count(7)->create();
        $newTeams = Team::factory()->count(2)->create();

        $tournament->teams()->attach($existingTeams->modelKeys());

        $response = $this->postJson(route('tournaments.teams.store', $tournament), [
            'team_ids' => $newTeams->modelKeys(),
        ]);

        $response
            ->assertConflict()
            ->assertJsonPath('statusCode', 409)
            ->assertJsonPath('message', 'A tournament can have at most 8 teams.');

        $this->assertEqualsCanonicalizing(
            $existingTeams->modelKeys(),
            $tournament->fresh()->teams()->pluck('teams.id')->all(),
        );
    }
}
