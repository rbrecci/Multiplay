<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsoleController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GameSearchController;
use App\Http\Controllers\Api\IgdbPlatformSearchController;
use App\Http\Controllers\Api\IgdbSearchController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/stats', StatsController::class);

    Route::get('/consoles', [ConsoleController::class, 'index']);
    Route::post('/consoles', [ConsoleController::class, 'store']);
    Route::get('/consoles/{console}/games', [ConsoleController::class, 'games']);
    Route::get('/consoles/{console}/igdb/search', IgdbSearchController::class);
    Route::get('/igdb/platforms/search', IgdbPlatformSearchController::class);

    Route::post('/consoles/{console}/games', [GameController::class, 'store']);
    Route::post('/consoles/{console}/games/bulk', [GameController::class, 'bulk']);
    Route::get('/games/search', GameSearchController::class);
    Route::post('/games/{game}/status', [GameController::class, 'status']);
    Route::patch('/games/{game}/detail', [GameController::class, 'detail']);
});
