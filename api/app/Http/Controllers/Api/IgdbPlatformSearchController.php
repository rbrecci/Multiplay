<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IgdbNotConfigured;
use App\Exceptions\IgdbRequestFailed;
use App\Http\Controllers\Controller;
use App\Services\IgdbClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IgdbPlatformSearchController extends Controller
{
    public function __invoke(Request $request, IgdbClient $igdb): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        try {
            return response()->json($igdb->platforms($validated['q']));
        } catch (IgdbNotConfigured $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (IgdbRequestFailed $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
