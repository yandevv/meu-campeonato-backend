<?php

namespace Tests\Integration\Services\Tournament;

use App\Enums\RoundPhase;
use App\Services\PythonGoalScorePredictor;
use Tests\TestCase;

class PythonGoalScorePredictorTest extends TestCase
{
    public function test_it_executes_the_real_python_predictor_script(): void
    {
        $prediction = app(PythonGoalScorePredictor::class)->predict(
            'tournament-123',
            RoundPhase::Finals,
            'team-home',
            'team-away',
        );

        $this->assertIsInt($prediction['home_goals']);
        $this->assertIsInt($prediction['away_goals']);
        $this->assertGreaterThanOrEqual(0, $prediction['home_goals']);
        $this->assertLessThanOrEqual(7, $prediction['home_goals']);
        $this->assertGreaterThanOrEqual(0, $prediction['away_goals']);
        $this->assertLessThanOrEqual(7, $prediction['away_goals']);
        $this->assertSame('mock-ml', $prediction['source']);
        $this->assertSame('v1', $prediction['version']);
        $this->assertIsFloat($prediction['confidence']);
        $this->assertGreaterThanOrEqual(0.5, $prediction['confidence']);
        $this->assertLessThanOrEqual(0.99, $prediction['confidence']);
    }
}
