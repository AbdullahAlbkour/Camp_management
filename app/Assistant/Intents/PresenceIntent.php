<?php

namespace App\Assistant\Intents;

use App\Assistant\Answer;
use App\Assistant\AssistantQuery;
use App\Assistant\Intent;
use App\Assistant\ResolvesEntities;
use App\Models\EntryExitLog;
use App\Models\Refugee;
use App\Models\User;
use App\Support\Labels;
use App\Support\RoleScope;

/**
 * "هل أحمد داخل المخيم؟" / "وين أحمد الآن؟"
 */
class PresenceIntent extends Intent
{
    use ResolvesEntities;

    /** @var list<string> */
    private const TRIGGERS = ['داخل', 'خارج', 'موجود', 'موجودة', 'تواجد', 'حاضر', 'غادر', 'الآن', 'حاليا'];

    /** @var list<string> */
    private const COUNTING = ['كم', 'عدد', 'إحصائية', 'إحصائيات', 'مجموع', 'نسبة', 'قائمة'];

    public function name(): string
    {
        return 'presence';
    }

    /**
     * Presence is a column on the refugee record, shown on the profile page any
     * signed-in user can already open — so gating it by the security area would
     * refuse a question the screen beside it answers. The movement *log* is a
     * different matter and stays in the security group.
     */
    public function group(): string
    {
        return RoleScope::LOOKUP;
    }

    public function score(AssistantQuery $query): ?int
    {
        // "كم شخصًا داخل المخيم" counts the camp; that is PopulationIntent's
        // question, and answering it about one person would be a different one.
        if ($query->hasAny(self::COUNTING) || ! $query->hasAny(self::TRIGGERS)) {
            return null;
        }

        $identified = $query->codes() !== [] || $query->subject(self::TRIGGERS) !== '';

        return $identified ? 4 : null;
    }

    public function handle(AssistantQuery $query, User $user): Answer
    {
        $triggers = array_merge(self::TRIGGERS, $this->campWords($query));
        $matches = $this->refugeesIn($query, $triggers, 4);

        if ($matches->isEmpty()) {
            $subject = $query->subject($triggers);

            return $subject === ''
                ? $this->noSubject($this->name())
                : Answer::empty($this->name(), 'لم أجد أي لاجئ يطابق «'.$subject.'» لأعرض حالة تواجده.');
        }

        if ($matches->count() > 1) {
            return $this->tooManyPeople($this->name(), $matches, $query->subject($triggers));
        }

        /** @var Refugee $refugee */
        $refugee = $matches->first();
        $inside = $refugee->presence_status === 'inside';

        // The presence column says where the person is; the log says since when.
        // Reading it is what turns "خارج المخيم" into something actionable.
        $last = $this->lastMovement($refugee, $user);

        $figures = [
            ['label' => 'الحالة', 'value' => Labels::get('presence_status', $refugee->presence_status)],
            ['label' => 'المخيم', 'value' => $refugee->currentCamp?->name ?? '—'],
            ['label' => 'الوحدة', 'value' => $refugee->currentShelter?->display_name ?? '—'],
        ];

        if ($last !== null) {
            $figures[] = ['label' => 'آخر حركة', 'value' => Labels::get('movement_type', $last->movement_type)];
            $figures[] = ['label' => 'وقتها', 'value' => $last->movement_datetime?->format('Y-m-d H:i') ?? '—'];
        }

        $since = $last?->movement_datetime !== null
            ? ' منذ '.$last->movement_datetime->format('Y-m-d H:i').'.'
            : '.';

        return Answer::make(
            $this->name(),
            $refugee->full_name.($inside ? ' داخل المخيم' : ' خارج المخيم').$since,
            [$this->refugeeItem($refugee)],
            $figures,
            [['label' => 'ملف اللاجئ', 'url' => route('refugees.show', $refugee), 'icon' => 'user-round']],
        );
    }

    /**
     * The last recorded passage, read only for roles that may see the movement
     * log. A registration officer still gets the presence answer, just without
     * the timestamp behind it.
     */
    private function lastMovement(Refugee $refugee, User $user): ?EntryExitLog
    {
        if (! RoleScope::allows($user, 'security')) {
            return null;
        }

        return $refugee->entryExitLogs()->latest('movement_datetime')->first();
    }

    public function examples(): array
    {
        return ['هل أحمد الحسن داخل المخيم؟'];
    }
}
