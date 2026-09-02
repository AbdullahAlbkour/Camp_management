<?php

namespace Tests\Feature;

use App\Models\AidDistribution;
use App\Models\AidType;
use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\EntryExitLog;
use App\Models\Household;
use App\Models\Organization;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four areas the assistant was widened to cover: statistics, housing
 * lookup, movement, and aid by household or donor.
 */
class AssistantExpandedIntentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function ask(string $question): array
    {
        $response = $this->postJson(route('assistant.ask'), ['question' => $question]);
        $response->assertOk();

        return $response->json('answer');
    }

    // ---------------------------------------------------------------- housing

    public function test_a_unit_is_looked_up_by_its_hyphenated_code(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create(['name' => 'مخيم السلام']);
        $shelter = Shelter::factory()->capacity(4)->create(['camp_id' => $camp->id, 'code' => 'A-01', 'type' => 'tent']);
        Refugee::factory()->count(2)->inShelter($shelter->id, $camp->id)->create();

        $answer = $this->ask('من يسكن في الخيمة A-01؟');

        $this->assertSame('shelter_lookup', $answer['intent']);
        $this->assertCount(2, $answer['items']);
        $this->assertStringContainsString('2', $answer['text']);
    }

    public function test_a_full_unit_reads_differently_from_an_empty_one(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        $full = Shelter::factory()->capacity(2)->create(['camp_id' => $camp->id, 'code' => 'B-01']);
        Shelter::factory()->capacity(3)->create(['camp_id' => $camp->id, 'code' => 'B-02']);
        Refugee::factory()->count(2)->inShelter($full->id, $camp->id)->create();

        $this->assertStringContainsString('ممتلئة', $this->ask('ما حالة الوحدة B-01؟')['text']);
        $this->assertStringContainsString('فارغة', $this->ask('ما حالة الوحدة B-02؟')['text']);
    }

    public function test_a_unit_that_does_not_exist_is_reported_not_substituted(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        Shelter::factory()->capacity(3)->create(['camp_id' => $camp->id, 'code' => 'C-01']);

        $answer = $this->ask('من يسكن في الوحدة Z-99؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertStringContainsString('لا توجد وحدة سكنية بالرمز', $answer['text']);
        // The units that do exist are listed, so the real code settles it.
        $this->assertStringContainsString('Z-99', $answer['text']);
        $this->assertNotEmpty($answer['items']);
    }

    public function test_asking_who_lives_in_a_unit_is_not_read_as_a_person_search(): void
    {
        // "يسكن" also triggers the housing-status intent, which would look for a
        // refugee named "الوحدة A-01" and report them missing.
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        Shelter::factory()->capacity(3)->create(['camp_id' => $camp->id, 'code' => 'A-01']);

        $this->assertSame('shelter_lookup', $this->ask('من يسكن في الوحدة A-01؟')['intent']);
    }

    public function test_counting_empty_units_still_reaches_the_availability_intent(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        Shelter::factory()->capacity(3)->create(['camp_id' => $camp->id, 'code' => 'D-01']);

        $this->assertSame('shelter_availability', $this->ask('كم وحدة سكنية فارغة؟')['intent']);
    }

    // --------------------------------------------------------------- presence

    public function test_presence_answers_inside_or_outside_for_one_person(): void
    {
        $this->actingAsRole('security_officer');
        $camp = Camp::factory()->create();
        Refugee::factory()->create([
            'first_name' => 'سالم', 'father_name' => 'يوسف', 'last_name' => 'الديري',
            'current_camp_id' => $camp->id, 'presence_status' => 'outside',
        ]);

        $answer = $this->ask('هل سالم يوسف الديري داخل المخيم؟');

        $this->assertSame('presence', $answer['intent']);
        $this->assertStringContainsString('خارج المخيم', $answer['text']);
    }

    public function test_presence_keeps_the_ambiguous_name_guidance(): void
    {
        $this->actingAsRole('security_officer');
        $camp = Camp::factory()->create();

        foreach (['العلي', 'الحسن'] as $family) {
            Refugee::factory()->create([
                'first_name' => 'محمد', 'father_name' => null, 'last_name' => $family,
                'current_camp_id' => $camp->id,
            ]);
        }

        $answer = $this->ask('هل محمد موجود داخل المخيم؟');

        $this->assertStringContainsString('الاسم الثلاثي', $answer['text']);
        $this->assertCount(2, $answer['items']);
    }

    public function test_counting_people_inside_is_not_answered_about_one_person(): void
    {
        $this->actingAsRole('admin');
        $camp = Camp::factory()->create();
        Refugee::factory()->count(3)->create(['current_camp_id' => $camp->id]);

        $this->assertSame('population', $this->ask('كم عدد السكان داخل المخيم؟')['intent']);
    }

    // --------------------------------------------------------------- movement

    public function test_the_last_movement_is_reported_with_its_gate_and_time(): void
    {
        $this->actingAsRole('security_officer');
        $camp = Camp::factory()->create();
        $checkpoint = Checkpoint::factory()->create(['camp_id' => $camp->id, 'name' => 'البوابة الرئيسية']);
        $refugee = Refugee::factory()->create([
            'first_name' => 'كرم', 'father_name' => 'نبيل', 'last_name' => 'الشامي',
            'current_camp_id' => $camp->id,
        ]);

        EntryExitLog::factory()->create([
            'refugee_id' => $refugee->id, 'camp_id' => $camp->id, 'checkpoint_id' => $checkpoint->id,
            'movement_type' => 'exit', 'movement_datetime' => now()->subHours(3),
        ]);

        $answer = $this->ask('متى كانت آخر حركة لكرم نبيل الشامي؟');

        $this->assertSame('last_movement', $answer['intent']);
        $this->assertStringContainsString('خروج', $answer['text']);
        $this->assertStringContainsString('البوابة الرئيسية', $answer['text']);
    }

    public function test_a_person_with_no_movements_is_told_so_plainly(): void
    {
        $this->actingAsRole('security_officer');
        $camp = Camp::factory()->create();
        Refugee::factory()->create([
            'first_name' => 'ريم', 'father_name' => 'سامي', 'last_name' => 'الخوري',
            'current_camp_id' => $camp->id,
        ]);

        $answer = $this->ask('متى كانت آخر حركة لريم سامي الخوري؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertStringContainsString('لا توجد حركات', $answer['text']);
    }

    public function test_movements_are_counted_for_the_gate_that_was_named(): void
    {
        $this->actingAsRole('security_officer');
        $camp = Camp::factory()->create();
        $main = Checkpoint::factory()->create(['camp_id' => $camp->id, 'name' => 'البوابة الرئيسية']);
        $side = Checkpoint::factory()->create(['camp_id' => $camp->id, 'name' => 'البوابة الشرقية']);
        $refugee = Refugee::factory()->create(['current_camp_id' => $camp->id]);

        EntryExitLog::factory()->count(3)->create([
            'refugee_id' => $refugee->id, 'camp_id' => $camp->id,
            'checkpoint_id' => $main->id, 'movement_datetime' => now(),
        ]);
        EntryExitLog::factory()->create([
            'refugee_id' => $refugee->id, 'camp_id' => $camp->id,
            'checkpoint_id' => $side->id, 'movement_datetime' => now(),
        ]);

        $answer = $this->ask('كم حركة عبر البوابة الرئيسية اليوم؟');

        $this->assertSame('checkpoint_traffic', $answer['intent']);
        $this->assertStringContainsString('3', $answer['text']);
        $this->assertStringNotContainsString('4', $answer['text']);
    }

    public function test_a_gate_that_does_not_exist_is_refused_not_widened(): void
    {
        $this->actingAsRole('security_officer');
        $camp = Camp::factory()->create();
        $checkpoint = Checkpoint::factory()->create(['camp_id' => $camp->id, 'name' => 'البوابة الرئيسية']);
        $refugee = Refugee::factory()->create(['current_camp_id' => $camp->id]);
        EntryExitLog::factory()->count(2)->create([
            'refugee_id' => $refugee->id, 'camp_id' => $camp->id,
            'checkpoint_id' => $checkpoint->id, 'movement_datetime' => now(),
        ]);

        $answer = $this->ask('كم حركة عبر البوابة الجنوبية اليوم؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertStringContainsString('لا توجد نقطة تفتيش', $answer['text']);
        // Never the count of the gates that do exist.
        $this->assertStringNotContainsString('2 حركة', $answer['text']);
    }

    // -------------------------------------------------------------------- aid

    public function test_household_aid_counts_both_the_family_and_its_members(): void
    {
        $this->actingAsRole('aid_officer');
        $camp = Camp::factory()->create();
        $household = Household::factory()->create(['household_code' => 'HH-0012']);
        $member = Refugee::factory()->create(['current_camp_id' => $camp->id, 'household_id' => $household->id]);
        $type = AidType::factory()->create(['name' => 'سلة غذائية']);

        AidDistribution::factory()->create([
            'aid_type_id' => $type->id, 'household_id' => $household->id, 'refugee_id' => null,
            'camp_id' => $camp->id, 'distribution_date' => now(),
        ]);
        AidDistribution::factory()->create([
            'aid_type_id' => $type->id, 'household_id' => null, 'refugee_id' => $member->id,
            'camp_id' => $camp->id, 'distribution_date' => now(),
        ]);

        $answer = $this->ask('ما المساعدات التي استلمتها الأسرة HH-0012؟');

        $this->assertSame('aid_for_household', $answer['intent']);
        $this->assertCount(2, $answer['items']);
    }

    public function test_a_household_aid_question_is_not_read_as_a_person(): void
    {
        // AidForRefugeeIntent claims "مساعدات" plus a name and would search for
        // a refugee called "الأسرة HH-0012".
        $this->actingAsRole('aid_officer');
        Household::factory()->create(['household_code' => 'HH-0012']);

        $this->assertSame('aid_for_household', $this->ask('ما المساعدات التي استلمتها الأسرة HH-0012؟')['intent']);
    }

    public function test_aid_is_summarised_for_the_donor_that_was_named(): void
    {
        $this->actingAsRole('aid_officer');
        $camp = Camp::factory()->create();
        $unicef = Organization::factory()->create(['name' => 'اليونيسف']);
        $other = Organization::factory()->create(['name' => 'الهلال المحلي']);
        $theirs = AidType::factory()->create(['organization_id' => $unicef->id, 'name' => 'حقيبة مدرسية']);
        $others = AidType::factory()->create(['organization_id' => $other->id, 'name' => 'بطانية']);

        AidDistribution::factory()->count(2)->create([
            'aid_type_id' => $theirs->id, 'camp_id' => $camp->id, 'distribution_date' => now(),
        ]);
        AidDistribution::factory()->count(5)->create([
            'aid_type_id' => $others->id, 'camp_id' => $camp->id, 'distribution_date' => now(),
        ]);

        $answer = $this->ask('ما المساعدات المقدمة من اليونيسف؟');

        $this->assertSame('aid_by_organization', $answer['intent']);
        $this->assertStringContainsString('اليونيسف', $answer['text']);
        $this->assertStringContainsString('2', $answer['text']);
    }

    public function test_an_unknown_donor_is_reported_with_the_donors_that_exist(): void
    {
        $this->actingAsRole('aid_officer');
        Organization::factory()->create(['name' => 'الهلال المحلي']);

        $answer = $this->ask('ما المساعدات المقدمة من جهة النور؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertStringContainsString('لا توجد جهة داعمة', $answer['text']);
        $this->assertNotEmpty($answer['items']);
    }

    public function test_the_donor_register_is_counted(): void
    {
        $this->actingAsRole('aid_officer');
        Organization::factory()->count(3)->create();
        Organization::factory()->create(['status' => 'inactive']);

        $answer = $this->ask('كم عدد الجهات الداعمة المسجلة؟');

        $this->assertSame('organizations', $answer['intent']);
        $this->assertStringContainsString('4', $answer['text']);
    }

    // ------------------------------------------------------------ boundaries

    public function test_every_new_area_refuses_a_role_outside_it(): void
    {
        $this->actingAsRole('registration_officer');
        Organization::factory()->create(['name' => 'الهلال المحلي']);

        foreach (['كم عدد الجهات الداعمة؟', 'ما المساعدات المقدمة من الهلال المحلي؟'] as $question) {
            $this->assertSame('denied', $this->ask($question)['tone'], $question);
        }
    }

    public function test_a_count_of_the_register_is_not_read_as_a_name(): void
    {
        // "المسجلة" describes the register; read as a name it turns the count
        // into a search for a household called that, and reports it missing.
        $this->actingAsRole('admin');
        Household::factory()->count(3)->create();

        $answer = $this->ask('كم عدد الأسر المسجلة؟');

        $this->assertSame('normal', $answer['tone']);
        $this->assertStringContainsString('3', $answer['text']);
    }

    public function test_an_unmatched_question_still_falls_back_rather_than_failing(): void
    {
        $this->actingAsRole('admin');

        $answer = $this->ask('بلابلابلا');

        $this->assertSame('unknown', $answer['tone']);
        $this->assertNotEmpty($answer['follow_ups']);
    }
}
