<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TeamController extends Controller
{
    public function __construct(
        private TeamService $teamService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $teams = $this->teamService->getAllTeams();

        return ApiResponse::success(
            TeamResource::collection($teams),
            'Teams retrieved successfully.',
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = $this->teamService->createTeam($request->validated());

        return ApiResponse::success(
            new TeamResource($team),
            'Team created successfully.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Team $team): JsonResponse
    {
        $team = $this->teamService->loadTeamMatchHistory($team);

        return ApiResponse::success(
            new TeamResource($team),
            'Team retrieved successfully.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $team = $this->teamService->updateTeam($team, $request->validated());

        return ApiResponse::success(
            new TeamResource($team),
            'Team updated successfully.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team): JsonResponse
    {
        try {
            $this->teamService->deleteTeam($team);
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
