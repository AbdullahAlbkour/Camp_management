<?php

namespace App\Assistant;

use App\Assistant\Intents\AidForRefugeeIntent;
use App\Assistant\Intents\AidSummaryIntent;
use App\Assistant\Intents\HelpIntent;
use App\Assistant\Intents\HouseholdIntent;
use App\Assistant\Intents\HousingStatusIntent;
use App\Assistant\Intents\OverviewIntent;
use App\Assistant\Intents\PopulationIntent;
use App\Assistant\Intents\RefugeeLookupIntent;
use App\Assistant\Intents\ShelterAvailabilityIntent;
use App\Assistant\Intents\UnhousedIntent;
use App\Models\User;
use App\Support\RoleScope;

/**
 * Everything the assistant knows how to answer, in resolution order.
 *
 * Order is the tie-breaker only: an intent wins on its own score first, and the
 * list order decides between two intents that claim a question equally hard.
 * Specific intents therefore come before the broad lookup that would otherwise
 * absorb any sentence containing a name.
 */
class IntentRegistry
{
    /** @var list<Intent>|null */
    private ?array $intents = null;

    /**
     * @return list<Intent>
     */
    public function all(): array
    {
        return $this->intents ??= [
            new HelpIntent(fn (User $user) => $this->examplesFor($user)),
            new HousingStatusIntent,
            new UnhousedIntent,
            new ShelterAvailabilityIntent,
            new AidForRefugeeIntent,
            new AidSummaryIntent,
            new PopulationIntent,
            new HouseholdIntent,
            new OverviewIntent,
            new RefugeeLookupIntent,
        ];
    }

    /**
     * The intents a role is allowed to reach.
     *
     * @return list<Intent>
     */
    public function forUser(?User $user): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (Intent $intent) => RoleScope::allows($user, $intent->group())
        ));
    }

    public function find(string $name): ?Intent
    {
        foreach ($this->all() as $intent) {
            if ($intent->name() === $name) {
                return $intent;
            }
        }

        return null;
    }

    /**
     * Sample questions for the widget's suggestion chips, limited to what this
     * user's role can actually be answered.
     *
     * @return list<string>
     */
    public function examplesFor(?User $user, int $limit = 6): array
    {
        $examples = [];

        foreach ($this->forUser($user) as $intent) {
            foreach ($intent->examples() as $example) {
                $examples[] = $example;
            }
        }

        return array_slice(array_values(array_unique($examples)), 0, $limit);
    }
}
