<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssistantAskRequest;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant) {}

    public function ask(AssistantAskRequest $request): JsonResponse
    {
        $question = (string) $request->validated('question');

        return response()->json([
            'question' => $question,
            'answer' => $this->assistant->ask($question, $request->user())->toArray(),
        ]);
    }

    /**
     * Sample questions for the widget's empty state, scoped to the role so the
     * chips only offer questions this user can actually get answered.
     */
    public function suggestions(Request $request): JsonResponse
    {
        return response()->json([
            'suggestions' => $this->assistant->suggestions($request->user()),
        ]);
    }
}
