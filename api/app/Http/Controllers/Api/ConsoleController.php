<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Console;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsoleController extends Controller
{
    private const COVERS_LIMIT = 4;

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $consoles = Console::visibleTo($userId)
            ->withCount([
                'games',
                'games as completed_count' => fn ($query) => $query->whereHas(
                    'users',
                    fn ($users) => $users->where('users.id', $userId)->where('game_user.status', 'zerado'),
                ),
            ])
            ->with(['games' => fn ($query) => $query
                ->select(['id', 'console_id', 'cover_url', 'created_at'])
                ->whereNotNull('cover_url')
                ->orderByDesc('created_at')
                ->orderByDesc('id')])
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('release_year')
            ->orderBy('name')
            ->get()
            ->map(fn (Console $console) => $this->item(
                $console,
                $console->games->take(self::COVERS_LIMIT)->pluck('cover_url')->all(),
            ));

        return response()->json($consoles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'manufacturer' => ['required', 'string', 'max:60'],
            'release_year' => ['required', 'integer', 'min:1970', 'max:2100'],
            'igdb_platform_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $console = $request->user()->consoles()->create($data);

        return response()->json($this->item($console, []), 201);
    }

    public function games(Request $request, Console $console): JsonResponse
    {
        $userId = $request->user()->id;

        $games = $console->games()
            ->with(['users' => fn ($query) => $query->where('users.id', $userId)])
            ->orderBy('name')
            ->get()
            ->map(fn (Game $game) => [
                'id' => $game->id,
                'igdb_id' => $game->igdb_id,
                'name' => $game->name,
                'cover_url' => $game->cover_url,
                'first_release_year' => $game->first_release_year,
                'status' => $game->users->first()?->pivot->status,
                'notes' => $game->users->first()?->pivot->notes,
                'rating' => $game->users->first()?->pivot->rating,
                'favorite' => (bool) $game->users->first()?->pivot->favorite,
                'created_at' => $game->created_at->toIso8601String(),
            ]);

        return response()->json([
            'console' => [
                'id' => $console->id,
                'name' => $console->name,
                'manufacturer' => $console->manufacturer,
                'release_year' => $console->release_year,
                'igdb_platform_id' => $console->igdb_platform_id,
                'user_id' => $console->user_id,
            ],
            'games' => $games,
        ]);
    }

    /**
     * @param  list<string>  $covers
     */
    private function item(Console $console, array $covers): array
    {
        return [
            'id' => $console->id,
            'name' => $console->name,
            'manufacturer' => $console->manufacturer,
            'release_year' => $console->release_year,
            'sort_order' => $console->sort_order,
            'igdb_platform_id' => $console->igdb_platform_id,
            'user_id' => $console->user_id,
            'games_count' => $console->games_count ?? 0,
            'completed_count' => $console->completed_count ?? 0,
            'covers' => $covers,
        ];
    }
}
