<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    private const FAVORITES_LIMIT = 12;

    private const RECENT_LIMIT = 10;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $countsByStatus = $user->games()
            ->selectRaw('game_user.status, count(*) as total')
            ->whereNotNull('game_user.status')
            ->groupBy('game_user.status')
            ->pluck('total', 'status');

        $counts = [];
        foreach (GameStatus::cases() as $status) {
            $counts[$status->value] = (int) ($countsByStatus[$status->value] ?? 0);
        }

        $favorites = $user->games()
            ->with('console:id,name')
            ->wherePivot('favorite', true)
            ->orderByDesc('game_user.updated_at')
            ->orderByDesc('game_user.id')
            ->limit(self::FAVORITES_LIMIT)
            ->get()
            ->map(fn (Game $game) => [
                'game_id' => $game->id,
                'name' => $game->name,
                'cover_url' => $game->cover_url,
                'console' => ['id' => $game->console->id, 'name' => $game->console->name],
            ]);

        $recent = $user->games()
            ->with('console:id,name')
            ->orderByDesc('game_user.updated_at')
            ->orderByDesc('game_user.id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Game $game) => [
                'game_id' => $game->id,
                'name' => $game->name,
                'cover_url' => $game->cover_url,
                'status' => $game->pivot->status,
                'console' => ['id' => $game->console->id, 'name' => $game->console->name],
                'updated_at' => $game->pivot->updated_at->toIso8601String(),
            ]);

        return response()->json([
            'counts' => $counts,
            'favorites_count' => $user->games()->wherePivot('favorite', true)->count(),
            'favorites' => $favorites,
            'recent' => $recent,
        ]);
    }
}
