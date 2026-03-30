<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tournament\StoreTournamentRequest;
use App\Http\Requests\Tournament\UpdateTournamentRequest;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use App\Services\TournamentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TournamentController extends Controller
{
    public function __construct(
        private TournamentService $tournamentService,
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
}
