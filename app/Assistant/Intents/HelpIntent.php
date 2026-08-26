<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Models\User;
use App\Support\RoleScope;

/**
 * "ماذا تستطيع أن تفعل؟" — the assistant describing its own scope.
 *
 * The examples it lists are the ones the asking user's role can actually run,
 * so nobody is invited to type a question that will only be refused.
 */
class HelpIntent extends Intent
{
    /** @var list<string> */
    private const TRIGGERS = ['ساعدني', 'ماذا تستطيع', 'ماذا يمكنك', 'كيف أستخدم', 'كيف استخدمك', 'ماذا تفعل', 'كيف تعمل', 'ما الأسئلة', 'قائمة الأسئلة', 'أمثلة'];

    /**
     * @param  callable(User): list<string>  $examplesFor
     */
    public function __construct(private $examplesFor) {}

    public function name(): string
    {
        return 'help';
    }

    public function group(): string
    {
        return RoleScope::LOOKUP;
    }

    public function score(AssistantQuery $query): ?int
    {
        // The bare word "مساعدة" is deliberately not a trigger. In a camp system
        // it is the domain noun for aid, and "كم مساعدة وُزّعت اليوم" is a
        // question about distributions, not a request for instructions.
        return $query->hasAny(self::TRIGGERS) ? 5 : null;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $examples = ($this->examplesFor)($user);

        return Answer::make(
            $this->name(),
            'أستطيع الإجابة عن أسئلة مرتبطة ببيانات النظام مباشرة. جرّب أحد هذه الأسئلة:',
            [],
            [],
            [],
            $examples,
        );
    }

    public function examples(): array
    {
        return [];
    }
}
