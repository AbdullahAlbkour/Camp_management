<?php

namespace App\Http\Controllers;

use App\Assistant\Answer;
use App\Http\Requests\AssistantAskRequest;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant) {}

    public function ask(AssistantAskRequest $request): JsonResponse
    {
        $question = (string) $request->validated('question');

        try {
            $answer = $this->assistant->ask($question, $request->user())->toArray();
        } catch (Throwable $e) {
            // A chat box must not answer with a 500. Whatever broke, the person
            // asking gets a sentence they can act on, and the cause goes to the
            // log with the question that produced it so it can be fixed rather
            // than swallowed.
            Log::error('Assistant failed to answer a question.', [
                'question' => $question,
                'user_id' => $request->user()?->id,
                'exception' => $e,
            ]);

            $answer = Answer::failed(
                'تعذّر إعداد الإجابة على هذا السؤال. جرّب صياغة أبسط، أو استخدم البحث من الشريط العلوي.'
            )->toArray();
        }

        return response()->json([
            'question' => $question,
            'answer' => $answer,
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
