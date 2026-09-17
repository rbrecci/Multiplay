<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameSearchController extends Controller
{
    private const LIMIT = 50;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        $games = $request->user()->games()
            ->with('console:id,name,manufacturer')
            ->where('games.name', 'like', '%'.$validated['q'].'%')
            ->orderBy('games.name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'cover_url' => $game->cover_url,
                'first_release_year' => $game->first_release_year,
                'status' => $game->pivot->status,
                'favorite' => (bool) $game->pivot->favorite,
                'console' => [
                    'id' => $game->console->id,
                    'name' => $game->console->name,
                    'manufacturer' => $game->console->manufacturer,
                ],
            ]);

        return response()->json($games);
    }
}
