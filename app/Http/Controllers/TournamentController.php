<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tournament\AttachTournamentTeamsRequest;
use App\Http\Requests\Tournament\StoreTournamentRequest;
use App\Http\Requests\Tournament\UpdateTournamentRequest;
use App\Http\Resources\TournamentResource;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\TournamentService;
use App\Services\TournamentSimulationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TournamentController extends Controller
{
    public function __construct(
        private TournamentService $tournamentService,
        private TournamentSimulationService $tournamentSimulationService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $tournaments = $this->tournamentService->getAllTournaments();

        return ApiResponse::success(
            TournamentResource::collection($tournaments),
            'Tournaments retrieved successfully.',
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTournamentRequest $request): JsonResponse
    {
        $tournament = $this->tournamentService->createTournament($request->validated());

        return ApiResponse::success(
            new TournamentResource($tournament),
            'Tournament created successfully.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Tournament $tournament): JsonResponse
    {
        $tournament = $this->tournamentService->loadTournamentRoster($tournament);

        return ApiResponse::success(
            new TournamentResource($tournament),
            'Tournament retrieved successfully.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTournamentRequest $request, Tournament $tournament): JsonResponse
    {
        $tournament = $this->tournamentService->updateTournament($tournament, $request->validated());

        return ApiResponse::success(
            new TournamentResource($tournament),
            'Tournament updated successfully.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tournament $tournament): JsonResponse
    {
        $this->tournamentService->deleteTournament($tournament);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Add teams to a tournament roster.
     */
    public function attachTeams(AttachTournamentTeamsRequest $request, Tournament $tournament): JsonResponse
    {
        try {
            $tournament = $this->tournamentService->attachTeamsToTournament(
                $tournament,
                $request->validated('team_ids'),
            );

            return ApiResponse::success(
                new TournamentResource($tournament),
                'Teams added to tournament successfully.',
            );
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    /**
     * Remove a team from a tournament roster.
     */
    public function detachTeam(Tournament $tournament, Team $team): JsonResponse
    {
        try {
            $tournament = $this->tournamentService->detachTeamFromTournament($tournament, $team->getKey());

            return ApiResponse::success(
                new TournamentResource($tournament),
                'Team removed from tournament successfully.',
            );
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_CONFLICT);
        } catch (NotFoundHttpException $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Simulate a tournament and replace any previous simulation.
     */
    public function simulate(Tournament $tournament): JsonResponse
    {
        try {
            $tournament = $this->tournamentSimulationService->simulateTournament($tournament);

            return ApiResponse::success(
                new TournamentResource($tournament),
                'Tournament simulated successfully.',
            );
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    /**
     * Display the tournament simulation.
     */
    public function showSimulation(Tournament $tournament): JsonResponse
    {
        try {
            $tournament = $this->tournamentService->loadTournamentSimulation($tournament);

            return ApiResponse::success(
                new TournamentResource($tournament),
                'Tournament simulation retrieved successfully.',
            );
        } catch (NotFoundHttpException $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }
}
