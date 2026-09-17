<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IgdbNotConfigured;
use App\Exceptions\IgdbRequestFailed;
use App\Http\Controllers\Controller;
use App\Models\Console;
use App\Services\IgdbClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IgdbSearchController extends Controller
{
    public function __invoke(Request $request, Console $console, IgdbClient $igdb): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        if ($console->igdb_platform_id === null) {
            return response()->json(['message' => "O console {$console->name} nao tem igdb_platform_id definido."], 503);
        }

        try {
            $results = $igdb->searchGames($console->igdb_platform_id, $validated['q']);
        } catch (IgdbNotConfigured $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (IgdbRequestFailed $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $importedIds = $console->games()
            ->whereIn('igdb_id', array_column($results, 'igdb_id'))
            ->pluck('id', 'igdb_id');

        $payload = array_map(fn (array $item) => $item + [
            'game_id' => $importedIds[$item['igdb_id']] ?? null,
        ], $results);

        return response()->json($payload);
    }
}
