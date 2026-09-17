<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Models\Console;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GameController extends Controller
{
    private const ITEM_RULES = [
        'igdb_id' => ['required', 'integer', 'min:1'],
        'name' => ['required', 'string', 'max:255'],
        'cover_url' => ['nullable', 'url', 'max:2048'],
        'first_release_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
    ];

    public function store(Request $request, Console $console): JsonResponse
    {
        $data = $request->validate(self::ITEM_RULES);

        $game = $this->import($console, $request->user(), $data);

        return response()->json($this->item($game), $game->wasRecentlyCreated ? 201 : 200);
    }

    public function bulk(Request $request, Console $console): JsonResponse
    {
        $rules = ['games' => ['required', 'array', 'min:1', 'max:50']];
        foreach (self::ITEM_RULES as $field => $fieldRules) {
            $rules["games.*.{$field}"] = $fieldRules;
        }
        $data = $request->validate($rules);

        $user = $request->user();
        $games = array_map(
            fn (array $item) => $this->item($this->import($console, $user, $item)),
            $data['games'],
        );

        return response()->json(['games' => $games]);
    }

    /**
     * Idempotente por (console_id, igdb_id). Devolve o jogo com a relacao
     * `users` reduzida ao usuario logado, pra `item()` ler o status do pivot.
     */
    private function import(Console $console, User $user, array $data): Game
    {
        $game = $console->games()->firstOrCreate(
            ['igdb_id' => $data['igdb_id']],
            [
                'name' => $data['name'],
                'cover_url' => $data['cover_url'] ?? null,
                'first_release_year' => $data['first_release_year'] ?? null,
            ],
        );

        $game->setRelation('users', $game->wasRecentlyCreated
            ? new Collection
            : $game->users()->where('users.id', $user->id)->get());

        return $game;
    }

    private function item(Game $game): array
    {
        return [
            'id' => $game->id,
            'igdb_id' => $game->igdb_id,
            'name' => $game->name,
            'cover_url' => $game->cover_url,
            'first_release_year' => $game->first_release_year,
            'status' => $game->users->first()?->pivot->status,
        ];
    }

    public function status(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate([
            'status' => ['present', 'nullable', Rule::enum(GameStatus::class)],
        ]);

        $user = $request->user();
        $user->games()->syncWithoutDetaching([$game->id => ['status' => $data['status']]]);

        if ($data['status'] === null) {
            $user->games()
                ->wherePivotNull('notes')
                ->wherePivotNull('rating')
                ->wherePivot('favorite', false)
                ->detach($game->id);
        }

        return response()->json([
            'game_id' => $game->id,
            'status' => $data['status'],
        ]);
    }

    public function detail(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'favorite' => ['sometimes', 'boolean'],
        ]);

        if ($data === []) {
            throw ValidationException::withMessages([
                'detail' => 'Envie ao menos um de notes, rating ou favorite.',
            ]);
        }

        $user = $request->user();
        $user->games()->syncWithoutDetaching([$game->id => $data]);

        $pivot = $user->games()->where('games.id', $game->id)->first()->pivot;

        return response()->json([
            'game_id' => $game->id,
            'status' => $pivot->status,
            'notes' => $pivot->notes,
            'rating' => $pivot->rating,
            'favorite' => (bool) $pivot->favorite,
        ]);
    }
}
