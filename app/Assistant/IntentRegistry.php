<?php

namespace App\Assistant;

use App\Assistant\Intents\AidByOrganizationIntent;
use App\Assistant\Intents\AidForHouseholdIntent;
use App\Assistant\Intents\AidForRefugeeIntent;
use App\Assistant\Intents\AidSummaryIntent;
use App\Assistant\Intents\CheckpointTrafficIntent;
use App\Assistant\Intents\HelpIntent;
use App\Assistant\Intents\HouseholdIntent;
use App\Assistant\Intents\HousingStatusIntent;
use App\Assistant\Intents\LastMovementIntent;
use App\Assistant\Intents\OrganizationsIntent;
use App\Assistant\Intents\OverviewIntent;
use App\Assistant\Intents\PopulationIntent;
use App\Assistant\Intents\PresenceIntent;
use App\Assistant\Intents\RefugeeLookupIntent;
use App\Assistant\Intents\ShelterAvailabilityIntent;
use App\Assistant\Intents\ShelterLookupIntent;
use App\Assistant\Intents\UnhousedIntent;
use App\Models\Camp;
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
     * The order questions are *offered* in, which is deliberately not the order
     * they are resolved in.
     *
     * Resolution puts the narrow intents first so one wins a tie against the
     * broad intent it shares trigger words with. The widget wants the opposite:
     * six chips that span the areas a role can reach, led by the questions that
     * read as invitations rather than as templates to fill in.
     *
     * @var list<string>
     */
    private const SUGGESTED_FIRST = [
        'housing_status', 'population', 'shelter_availability', 'aid_summary',
        'unhoused', 'household', 'presence', 'shelter_lookup', 'last_movement',
        'checkpoint_traffic', 'aid_for_household', 'aid_by_organization',
        'organizations', 'aid_for_refugee',
    ];

    /**
     * @return list<Intent>
     */
    public function all(): array
    {
        return $this->intents ??= [
            new HelpIntent(fn (User $user) => $this->examplesFor($user)),

            // Questions naming one record come first: each of these shares its
            // trigger words with a broader intent below, and would be swallowed
            // by it at equal confidence.
            new ShelterLookupIntent,
            new PresenceIntent,
            new LastMovementIntent,
            new AidForHouseholdIntent,
            new AidByOrganizationIntent,

            new HousingStatusIntent,
            new UnhousedIntent,
            new ShelterAvailabilityIntent,
            new CheckpointTrafficIntent,
            new AidForRefugeeIntent,
            new AidSummaryIntent,
            new OrganizationsIntent,
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
        $camp = $this->sampleCampName();
        $examples = [];

        foreach ($this->suggestionOrder($user) as $intent) {
            foreach ($intent->examples() as $example) {
                $examples[] = str_replace('{camp}', $camp, $example);
            }
        }

        return array_slice(array_values(array_unique($examples)), 0, $limit);
    }

    /**
     * The intents this user may reach, in the order their examples should be
     * offered. Anything not named in SUGGESTED_FIRST keeps registry order,
     * behind everything that is.
     *
     * @return list<Intent>
     */
    private function suggestionOrder(?User $user): array
    {
        $intents = $this->forUser($user);
        $rank = array_flip(self::SUGGESTED_FIRST);
        $last = count(self::SUGGESTED_FIRST);

        usort($intents, fn (Intent $a, Intent $b) => ($rank[$a->name()] ?? $last) <=> ($rank[$b->name()] ?? $last));

        return $intents;
    }

    /**
     * A camp that actually exists, for the examples that name one.
     *
     * Suggesting "كم عدد السكان في مخيم الزعتري" to a deployment with no such
     * camp invites a question the assistant can only refuse.
     */
    private function sampleCampName(): string
    {
        $name = Camp::query()->where('status', 'active')->orderBy('name')->value('name')
            ?? Camp::query()->orderBy('name')->value('name');

        if ($name === null) {
            return 'مخيم المثال';
        }

        return str_starts_with(trim($name), 'مخيم') ? $name : 'مخيم '.$name;
    }
}
