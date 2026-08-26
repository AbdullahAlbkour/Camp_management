<?php

namespace App\Services;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\IntentRegistry;
use App\Models\User;
use App\Support\ArabicText;
use App\Support\RoleScope;

/**
 * The assistant: turn one Arabic sentence into one answer from the database.
 *
 * There is no model call and no generated SQL anywhere in this path. Each
 * intent owns a query a developer wrote and a test covers; the sentence only
 * decides which of those runs and what values are bound into it. That is what
 * makes the answers exact rather than plausible, and it is why refugee names,
 * document numbers and diagnoses never leave the deployment.
 */
class AssistantService
{
    public function __construct(
        private readonly IntentRegistry $registry,
        private readonly GlobalSearchService $search,
    ) {}

    public function ask(string $question, ?User $user): Answer
    {
        if ($user === null) {
            return Answer::denied('unauthenticated', 'يجب تسجيل الدخول لاستخدام المساعد.');
        }

        $query = new AssistantQuery($question);

        if (ArabicText::isTooShort($query->text, 2)) {
            return Answer::unknown(
                'اكتب سؤالك بشكل أوضح قليلًا — حرف واحد لا يكفي.',
                $this->registry->examplesFor($user, 4),
            );
        }

        $intent = $this->resolve($query);

        if ($intent === null) {
            return $this->fallback($query, $user);
        }

        // Scoring runs over every intent, then the winner is checked. Picking a
        // lower-scoring intent the role happens to be allowed would answer a
        // different question than the one asked, which is worse than refusing.
        if (! RoleScope::allows($user, $intent->group())) {
            return Answer::denied(
                $intent->name(),
                'هذا السؤال يخص قسمًا خارج صلاحيات دورك، فلا يمكنني عرض بياناته.',
            );
        }

        return $intent->handle($query, $user);
    }

    /**
     * The intent claiming the question hardest. Registry order breaks a tie,
     * so a specific intent beats a broad one at equal confidence.
     */
    private function resolve(AssistantQuery $query): ?Intent
    {
        $best = null;
        $bestScore = 0;

        foreach ($this->registry->all() as $intent) {
            $score = $intent->score($query);

            if ($score !== null && $score > $bestScore) {
                $best = $intent;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * No intent claimed the sentence — which includes the common case of a bare
     * name typed with no question around it. The global search already resolves
     * loose text across refugees, households, shelters and camps, so it answers
     * that here instead of an intent guessing at a reading.
     */
    private function fallback(AssistantQuery $query, User $user): Answer
    {
        $groups = $this->search->search($query->raw, $user);

        if ($groups->isNotEmpty()) {
            $items = $groups
                ->flatMap(fn (array $group) => $group['items']->map(fn (array $item) => $item + ['group' => $group['label']]))
                ->take(6)
                ->values()
                ->all();

            return Answer::make(
                'search_fallback',
                'هذه السجلات تطابق «'.$query->raw.'»:',
                $items,
                followUps: $this->registry->examplesFor($user, 3),
            );
        }

        return Answer::unknown(
            'لم أفهم السؤال، ولم أجد سجلًا يطابقه. جرّب صياغة أقرب إلى هذه الأمثلة:',
            $this->registry->examplesFor($user, 4),
        );
    }

    /**
     * @return list<string>
     */
    public function suggestions(?User $user): array
    {
        return $this->registry->examplesFor($user, 6);
    }
}
