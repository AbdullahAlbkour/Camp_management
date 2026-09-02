<?php

namespace Tests\Feature;

use App\Models\AidType;
use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\Household;
use App\Models\MedicalService;
use App\Models\Organization;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The delete button on every index screen, and the archive route behind it.
 */
class ArchiveButtonsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function sections(): array
    {
        // resource key => [index route, role that may delete, role that may not]
        return [
            'المخيمات' => ['camps.index', 'housing_officer', 'medical_officer'],
            'الوحدات السكنية' => ['shelters.index', 'housing_officer', 'medical_officer'],
            'اللاجئون' => ['refugees.index', 'registration_officer', 'medical_officer'],
            'الأسر' => ['households.index', 'registration_officer', 'medical_officer'],
            'الجهات الداعمة' => ['aid.organizations', 'aid_officer', 'medical_officer'],
            'أنواع المساعدات' => ['aid.types', 'aid_officer', 'medical_officer'],
            'الخدمات الطبية' => ['medical.services', 'medical_officer', 'aid_officer'],
            'نقاط التفتيش' => ['security.checkpoints', 'security_officer', 'aid_officer'],
        ];
    }

    private function seedOneOfEach(): void
    {
        $camp = Camp::factory()->create();
        Shelter::factory()->create(['camp_id' => $camp->id]);
        Refugee::factory()->create(['current_camp_id' => $camp->id]);
        Household::factory()->create();
        $organization = Organization::factory()->create();
        AidType::factory()->create(['organization_id' => $organization->id]);
        MedicalService::factory()->create();
        Checkpoint::factory()->create(['camp_id' => $camp->id]);
    }

    #[DataProvider('sections')]
    public function test_the_section_offers_a_delete_button_to_the_role_that_owns_it(string $indexRoute, string $allowed, string $denied): void
    {
        $this->actingAsRole($allowed);
        $this->seedOneOfEach();

        $this->get(route($indexRoute))
            ->assertOk()
            ->assertSee('سيُنقل «', false)
            ->assertSee('value="DELETE"', false);
    }

    #[DataProvider('sections')]
    public function test_a_role_outside_the_section_is_not_offered_the_button(string $indexRoute, string $allowed, string $denied): void
    {
        $this->actingAsRole($denied);
        $this->seedOneOfEach();

        $response = $this->get(route($indexRoute));

        // Some sections are closed to the other role outright; where the screen
        // does open, the button must not be on it.
        if ($response->status() === 200) {
            $response->assertDontSee('سيُنقل «', false);
        } else {
            $response->assertForbidden();
        }
    }

    public function test_each_section_can_actually_be_archived(): void
    {
        $camp = Camp::factory()->create();
        $organization = Organization::factory()->create();
        // Its own camp, with nothing in it: ArchiveService refuses to archive a
        // camp that still holds units, which is a rule of its own and tested
        // separately below.
        $emptyCamp = Camp::factory()->create();
        // Likewise a donor with no aid types: an organization still offering
        // one is refused, which is again its own rule.
        $emptyOrganization = Organization::factory()->create();

        $cases = [
            ['camps', $emptyCamp->id, 'housing_officer', 'camps'],
            ['shelters', Shelter::factory()->create(['camp_id' => $camp->id])->id, 'housing_officer', 'shelters'],
            ['households', Household::factory()->create()->id, 'registration_officer', 'households'],
            ['organizations', $emptyOrganization->id, 'aid_officer', 'organizations'],
            ['aid_types', AidType::factory()->create(['organization_id' => $organization->id])->id, 'aid_officer', 'aid_types'],
            ['medical_services', MedicalService::factory()->create()->id, 'medical_officer', 'medical_services'],
            ['checkpoints', Checkpoint::factory()->create(['camp_id' => $camp->id])->id, 'security_officer', 'checkpoints'],
        ];

        foreach ($cases as [$resource, $id, $role, $table]) {
            $this->actingAsRole($role);

            $this->delete(route('archive.store', [$resource, $id]))
                ->assertSessionHas('success');

            $this->assertSoftDeleted($table, ['id' => $id]);
        }
    }

    public function test_the_route_refuses_a_role_the_button_is_hidden_from(): void
    {
        $this->actingAsRole('medical_officer');
        $camp = Camp::factory()->create();

        $this->delete(route('archive.store', ['camps', $camp->id]))->assertForbidden();

        $this->assertNotSoftDeleted('camps', ['id' => $camp->id]);
    }

    public function test_a_camp_still_holding_people_is_refused(): void
    {
        // The guard is ArchiveService's; the button routes through it, so the
        // refusal reaches the screen instead of the camp vanishing under its
        // own residents.
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        Refugee::factory()->create(['current_camp_id' => $camp->id, 'status' => 'active']);

        $this->from(route('camps.index'))
            ->delete(route('archive.store', ['camps', $camp->id]))
            ->assertRedirect(route('camps.index'))
            ->assertSessionHasErrors('archive');

        $this->assertNotSoftDeleted('camps', ['id' => $camp->id]);
    }
}
