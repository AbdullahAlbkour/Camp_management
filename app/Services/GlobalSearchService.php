<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Models\User;
use App\Support\ArabicText;
use Illuminate\Support\Collection;

/**
 * One search box over the modules the signed-in user is allowed to see.
 *
 * Each group is capped, because the point is to jump to a record quickly rather
 * than to browse — the module screens already do filtered listing well.
 */
class GlobalSearchService
{
    private const PER_GROUP = 6;

    /**
     * @return Collection<int, array{label: string, icon: string, items: Collection<int, array<string, string>>}>
     */
    public function search(string $term, ?User $user): Collection
    {
        $term = trim($term);

        if ($term === '' || $user === null) {
            return collect();
        }

        $groups = collect([
            $this->refugees($term),
            $this->households($term),
        ]);

        if ($user->hasAnyRole(['admin', 'manager', 'housing_officer'])) {
            $groups->push($this->shelters($term));
            $groups->push($this->camps($term));
        }

        return $groups->filter(fn (array $group) => $group['items']->isNotEmpty())->values();
    }

    public function totalFor(string $term, ?User $user): int
    {
        return $this->search($term, $user)->sum(fn (array $group) => $group['items']->count());
    }

    /**
     * @return array{label: string, icon: string, items: Collection<int, array<string, string>>}
     */
    private function refugees(string $term): array
    {
        // Matched against the folded blob rather than column by column. The old
        // per-column LIKE could not find "نادر حمود" at all: the term spans the
        // first and last name, so no single column contains it. The blob also
        // brings the Arabic folding with it, so "احمد" finds "أحمد".
        $folded = ArabicText::normalize($term);

        $items = Refugee::query()
            ->with(['currentCamp', 'currentShelter'])
            ->where(function ($query) use ($folded, $term): void {
                $query->where('search_text', 'like', '%'.$folded.'%')
                    ->orWhere('id', '=', ctype_digit($term) ? (int) $term : 0);
            })
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Refugee $refugee) => [
                'title' => $refugee->full_name,
                'subtitle' => trim(($refugee->currentCamp?->name ?? '—').' • '.($refugee->currentShelter?->display_name ?? 'بدون سكن')),
                'meta' => $refugee->document_number ?? $refugee->badge_code,
                'url' => route('refugees.show', $refugee),
            ]);

        return ['label' => 'اللاجئون', 'icon' => 'user-round', 'items' => $items];
    }

    /**
     * @return array{label: string, icon: string, items: Collection<int, array<string, string>>}
     */
    private function households(string $term): array
    {
        $like = '%'.$term.'%';

        $items = Household::query()
            ->with('head')
            ->withCount('members')
            ->where('household_code', 'like', $like)
            ->orWhereHas('head', function ($query) use ($like): void {
                $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like);
            })
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Household $household) => [
                'title' => $household->household_code,
                'subtitle' => 'رب الأسرة: '.($household->head?->full_name ?? '—'),
                'meta' => $household->members_count.' فرد',
                'url' => route('households.show', $household),
            ]);

        return ['label' => 'الأسر', 'icon' => 'house', 'items' => $items];
    }

    /**
     * @return array{label: string, icon: string, items: Collection<int, array<string, string>>}
     */
    private function shelters(string $term): array
    {
        $like = '%'.$term.'%';

        $items = Shelter::query()
            ->with('camp')
            ->withCount(['refugees' => fn ($query) => $query->where('status', 'active')])
            ->where('code', 'like', $like)
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Shelter $shelter) => [
                'title' => $shelter->display_name,
                'subtitle' => $shelter->camp?->name ?? '—',
                'meta' => $shelter->refugees_count.'/'.$shelter->capacity,
                'url' => route('shelters.edit', $shelter),
            ]);

        return ['label' => 'الوحدات السكنية', 'icon' => 'bed', 'items' => $items];
    }

    /**
     * @return array{label: string, icon: string, items: Collection<int, array<string, string>>}
     */
    private function camps(string $term): array
    {
        $like = '%'.$term.'%';

        $items = Camp::query()
            ->where('name', 'like', $like)
            ->orWhere('location', 'like', $like)
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Camp $camp) => [
                'title' => $camp->name,
                'subtitle' => $camp->location ?? '—',
                'meta' => 'السعة '.$camp->capacity,
                'url' => route('camps.edit', $camp),
            ]);

        return ['label' => 'المخيمات', 'icon' => 'map', 'items' => $items];
    }
}
