<?php

use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Team Endpoints
|--------------------------------------------------------------------------
*/

Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');           // List all teams
Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');          // Create a new team
Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');      // Show a specific team
Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');  // Update a specific team
Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy'); // Delete a specific team

/*
|--------------------------------------------------------------------------
| Tournament Endpoints
|--------------------------------------------------------------------------
*/

Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
Route::post('/tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');
Route::put('/tournaments/{tournament}', [TournamentController::class, 'update'])->name('tournaments.update');
Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy'])->name('tournaments.destroy');
