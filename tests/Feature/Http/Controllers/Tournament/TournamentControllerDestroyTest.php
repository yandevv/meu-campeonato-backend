<?php

namespace Tests\Feature\Http\Controllers\Tournament;

use App\Models\Tournament;
use Illuminate\Support\Str;
use Tests\FeatureTestCase;

class TournamentControllerDestroyTest extends FeatureTestCase
{
    public function test_it_deletes_a_tournament_and_returns_no_content(): void
    {
        $tournament = Tournament::factory()->create();

        $response = $this->deleteJson(route('tournaments.destroy', $tournament));

        $response->assertNoContent();

        $this->assertSame('', $response->getContent());
        $this->assertModelMissing($tournament);
    }

    public function test_it_returns_not_found_when_deleting_a_tournament_that_does_not_exist(): void
    {
        $response = $this->deleteJson(route('tournaments.destroy', [
            'tournament' => (string) Str::uuid(),
        ]));

        $response->assertNotFound();
    }
}
