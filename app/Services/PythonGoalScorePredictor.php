<?php

namespace App\Services;

use App\Enums\RoundPhase;
use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

class PythonGoalScorePredictor
{
    /**
     * @return array{
     *     home_goals: int,
     *     away_goals: int,
     *     source: string,
     *     version: string,
     *     confidence: float
     * }
     *
     * @throws RuntimeException
     */
    public function predict(
        string $tournamentId,
        RoundPhase $roundPhase,
        string $homeTeamId,
        string $awayTeamId,
    ): array {
        $payload = [
            'tournament_id' => $tournamentId,
            'round_type' => $roundPhase->value,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
        ];

        try {
            $result = Process::path(base_path())
                ->timeout((int) config('services.tournament_simulation.timeout', 10))
                ->input(json_encode($payload, JSON_THROW_ON_ERROR))
                ->run([
                    (string) config('services.tournament_simulation.python_binary', 'python3'),
                    (string) config('services.tournament_simulation.script_path', base_path('scripts/predict_match_score.py')),
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to execute the Python score predictor.', 0, $e);
        }

        if ($result->failed()) {
            $errorOutput = trim($result->errorOutput());

            throw new RuntimeException(
                $errorOutput !== ''
                    ? 'Failed to execute the Python score predictor: '.$errorOutput
                    : 'Failed to execute the Python score predictor.',
            );
        }

        try {
            /** @var mixed $decodedPayload */
            $decodedPayload = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The Python score predictor returned invalid JSON.', 0, $e);
        }

        if (! \is_array($decodedPayload)) {
            throw new RuntimeException('The Python score predictor returned an invalid payload.');
        }

        $homeGoals = $decodedPayload['home_goals'] ?? null;
        $awayGoals = $decodedPayload['away_goals'] ?? null;
        $source = $decodedPayload['source'] ?? null;
        $version = $decodedPayload['version'] ?? null;
        $confidence = $decodedPayload['confidence'] ?? null;

        if (! \is_int($homeGoals) || ! \is_int($awayGoals)) {
            throw new RuntimeException('The Python score predictor must return integer goal scores.');
        }

        if (! \is_string($source) || $source === '' || ! \is_string($version) || $version === '') {
            throw new RuntimeException('The Python score predictor must return non-empty source and version metadata.');
        }

        if (! \is_int($confidence) && ! \is_float($confidence)) {
            throw new RuntimeException('The Python score predictor must return a numeric confidence value.');
        }

        return [
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'source' => $source,
            'version' => $version,
            'confidence' => (float) $confidence,
        ];
    }
}
