<?php

namespace Tests\Feature\Tournament;

use App\Enums\RoundPhase;
use App\Services\PythonGoalScorePredictor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class PythonGoalScorePredictorTest extends TestCase
{
    public function test_it_returns_the_predicted_score_and_uses_the_expected_process_contract(): void
    {
        $scriptPath = base_path('tests/Fixtures/predictors/test_predict_match_score.py');
        $payload = [
            'tournament_id' => 'tournament-123',
            'round_type' => RoundPhase::SemiFinals->value,
            'home_team_id' => 'team-home',
            'away_team_id' => 'team-away',
        ];

        Config::set('services.tournament_simulation.python_binary', '/opt/python-test');
        Config::set('services.tournament_simulation.script_path', $scriptPath);
        Config::set('services.tournament_simulation.timeout', 12);

        Process::preventStrayProcesses();
        Process::fake([
            '*' => Process::result(output: json_encode([
                'home_goals' => 3,
                'away_goals' => 1,
                'source' => 'python-ml',
                'version' => '2026.04',
                'confidence' => 1,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $prediction = $this->predictor()->predict(
            $payload['tournament_id'],
            RoundPhase::SemiFinals,
            $payload['home_team_id'],
            $payload['away_team_id'],
        );

        $this->assertSame([
            'home_goals' => 3,
            'away_goals' => 1,
            'source' => 'python-ml',
            'version' => '2026.04',
            'confidence' => 1.0,
        ], $prediction);

        Process::assertRan(function (PendingProcess $process) use ($payload, $scriptPath): bool {
            return $process->command === ['/opt/python-test', $scriptPath]
                && $process->path === base_path()
                && $process->timeout === 12
                && $process->input === json_encode($payload, JSON_THROW_ON_ERROR);
        });
    }

    public function test_it_wraps_process_execution_failures(): void
    {
        Config::set('services.tournament_simulation.python_binary', 'python3');
        Config::set('services.tournament_simulation.script_path', base_path('scripts/predict_match_score.py'));
        Config::set('services.tournament_simulation.timeout', 10);

        $previousException = new RuntimeException('Python binary is unavailable.');

        Process::preventStrayProcesses();
        Process::fake([
            '*' => fn (): RuntimeException => $previousException,
        ]);

        try {
            $this->predictor()->predict('tournament-123', RoundPhase::Finals, 'team-home', 'team-away');
            $this->fail('Expected the predictor to wrap process execution failures.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failed to execute the Python score predictor.', $exception->getMessage());
            $this->assertSame($previousException, $exception->getPrevious());
        }
    }

    public function test_it_includes_stderr_when_the_process_fails(): void
    {
        Process::preventStrayProcesses();
        Process::fake([
            '*' => Process::result(errorOutput: " script crashed \n", exitCode: 1),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute the Python score predictor: script crashed');

        $this->predictor()->predict('tournament-123', RoundPhase::QuarterFinals, 'team-home', 'team-away');
    }

    public function test_it_uses_a_generic_message_when_the_failed_process_has_no_stderr(): void
    {
        Process::preventStrayProcesses();
        Process::fake([
            '*' => Process::result(errorOutput: " \n\t ", exitCode: 1),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute the Python score predictor.');

        $this->predictor()->predict('tournament-123', RoundPhase::QuarterFinals, 'team-home', 'team-away');
    }

    public function test_it_throws_when_the_python_script_returns_invalid_json(): void
    {
        Process::preventStrayProcesses();
        Process::fake([
            '*' => Process::result(output: '{"home_goals":'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Python score predictor returned invalid JSON.');

        $this->predictor()->predict('tournament-123', RoundPhase::QuarterFinals, 'team-home', 'team-away');
    }

    public function test_it_throws_when_the_python_script_returns_a_non_array_payload(): void
    {
        Process::preventStrayProcesses();
        Process::fake([
            '*' => Process::result(output: json_encode('invalid', JSON_THROW_ON_ERROR)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Python score predictor returned an invalid payload.');

        $this->predictor()->predict('tournament-123', RoundPhase::QuarterFinals, 'team-home', 'team-away');
    }

    public function test_it_requires_integer_goal_scores(): void
    {
        Process::preventStrayProcesses();
        Process::fake([
            '*' => Process::result(output: json_encode([
                'home_goals' => '3',
                'away_goals' => 1,
                'source' => 'python-ml',
                'version' => '2026.04',
                'confidence' => 0.91,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Python score predictor must return integer goal scores.');

        $this->predictor()->predict('tournament-123', RoundPhase::QuarterFinals, 'team-home', 'team-away');
    }

    public function test_it_requires_non_empty_source_and_version_metadata(): void
    {
        Process::preventStrayProcesses();
        Process::fake([
            '*' => Process::result(output: json_encode([
                'home_goals' => 3,
                'away_goals' => 1,
                'source' => '',
                'version' => '2026.04',
                'confidence' => 0.91,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Python score predictor must return non-empty source and version metadata.');

        $this->predictor()->predict('tournament-123', RoundPhase::QuarterFinals, 'team-home', 'team-away');
    }

    public function test_it_requires_a_numeric_confidence_value(): void
    {
        Process::preventStrayProcesses();
        Process::fake([
            '*' => Process::result(output: json_encode([
                'home_goals' => 3,
                'away_goals' => 1,
                'source' => 'python-ml',
                'version' => '2026.04',
                'confidence' => 'high',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Python score predictor must return a numeric confidence value.');

        $this->predictor()->predict('tournament-123', RoundPhase::QuarterFinals, 'team-home', 'team-away');
    }

    private function predictor(): PythonGoalScorePredictor
    {
        return app(PythonGoalScorePredictor::class);
    }
}
