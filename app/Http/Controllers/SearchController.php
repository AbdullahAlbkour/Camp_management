<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(private readonly GlobalSearchService $search) {}

    public function index(Request $request): View
    {
        $term = (string) $request->get('q', '');

        return view('search.index', [
            'term' => $term,
            'groups' => $this->search->search($term, $request->user()),
        ]);
    }

    /**
     * Backs the type-ahead panel in the top bar.
     */
    public function suggest(Request $request): JsonResponse
    {
        $term = (string) $request->get('q', '');

        $groups = $this->search->search($term, $request->user())
            ->map(fn (array $group) => [
                'label' => $group['label'],
                'icon' => $group['icon'],
                'items' => $group['items']->values(),
            ]);

        return response()->json([
            'term' => $term,
            'groups' => $groups,
        ]);
    }
}
