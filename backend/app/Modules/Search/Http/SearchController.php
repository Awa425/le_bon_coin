<?php

namespace App\Modules\Search\Http;

use App\Http\Controllers\Controller;
use App\Modules\Search\ListingSearch;
use App\Modules\Search\OpenSearch\SearchException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    /** GET /api/v1/search — résultats, pagination et facettes. */
    public function index(SearchRequest $request, ListingSearch $search): JsonResponse
    {
        try {
            $result = $search->search($request->criteria(), $request->schema());
        } catch (SearchException $e) {
            Log::error('Recherche indisponible', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'La recherche est momentanément indisponible. Réessayez dans un instant.'], 503);
        }

        // Cache court côté CDN pour les recherches anonymes (§5.2 : 60 s, stale-while-revalidate).
        return response()->json($result)->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=120');
    }

    /** GET /api/v1/search/suggest?q= — autocomplétion dès 2 caractères (RECH-01). */
    public function suggest(Request $request, ListingSearch $search): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']]);

        try {
            $suggestions = $search->suggest($data['q']);
        } catch (SearchException) {
            $suggestions = [];
        }

        return response()->json(['data' => $suggestions])->header('Cache-Control', 'public, max-age=300');
    }
}
