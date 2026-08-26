<?php

namespace Tests\Feature;

use App\Models\AidDistribution;
use App\Models\AidType;
use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ask(string $question, array $overrides = []): array
    {
        $response = $this->postJson(route('assistant.ask'), ['question' => $question] + $overrides);
        $response->assertOk();

        return $response->json('answer');
    }

    public function test_the_assistant_is_closed_to_guests(): void
    {
        $this->postJson(route('assistant.ask'), ['question' => 'كم عدد السكان؟'])
            ->assertUnauthorized();
    }

    public function test_the_widget_renders_on_an_authenticated_page(): void
    {
        $this->actingAsRole('registration_officer');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-assistant', false)
            ->assertSee('المساعد الذكي');
    }

    public function test_an_empty_question_is_rejected_by_validation(): void
    {
        $this->actingAsRole('registration_officer');

        $this->postJson(route('assistant.ask'), ['question' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    public function test_a_question_longer_than_a_sentence_is_rejected(): void
    {
        $this->actingAsRole('registration_officer');

        $this->postJson(route('assistant.ask'), ['question' => str_repeat('ا', 301)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    public function test_a_refugee_is_found_by_name_despite_spelling_variants(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'أحمد', 'father_name' => null, 'last_name' => 'الحسن']);

        // Typed without the hamza, the way a clerk in a hurry writes it.
        $answer = $this->ask('ابحث عن احمد الحسن');

        $this->assertSame('refugee_lookup', $answer['intent']);
        $this->assertSame('أحمد الحسن', $answer['items'][0]['title']);
    }

    public function test_a_refugee_is_found_by_document_number(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'خالد', 'document_number' => 'DOC55443']);

        $answer = $this->ask('من هو صاحب الوثيقة DOC55443؟');

        $this->assertSame('refugee_lookup', $answer['intent']);
        $this->assertCount(1, $answer['items']);
        $this->assertSame('DOC55443', $answer['items'][0]['meta']);
    }

    public function test_housing_status_reports_the_unit_a_refugee_lives_in(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create(['name' => 'مخيم الشمال']);
        $shelter = Shelter::factory()->create(['camp_id' => $camp->id, 'code' => 'B-12', 'type' => 'caravan']);
        Refugee::factory()->inShelter($shelter->id, $camp->id)->create([
            'first_name' => 'سميرة', 'father_name' => null, 'last_name' => 'العلي',
        ]);

        $answer = $this->ask('أين تسكن سميرة العلي؟');

        $this->assertSame('housing_status', $answer['intent']);
        $this->assertStringContainsString('كرفان B-12', $answer['text']);
        $this->assertContains(['label' => 'المخيم', 'value' => 'مخيم الشمال'], $answer['figures']);
    }

    public function test_housing_status_says_plainly_when_someone_has_no_shelter(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create([
            'first_name' => 'ليلى', 'father_name' => null, 'last_name' => 'قاسم',
            'housing_status' => 'unassigned',
        ]);

        $answer = $this->ask('أين تسكن ليلى قاسم؟');

        $this->assertStringContainsString('غير مخصص له سكن', $answer['text']);
    }

    public function test_population_is_counted_for_the_camp_named_in_the_question(): void
    {
        $this->actingAsRole('registration_officer');
        $zaatari = Camp::factory()->create(['name' => 'الزعتري']);
        $other = Camp::factory()->create(['name' => 'الأزرق']);

        Refugee::factory()->count(4)->create(['current_camp_id' => $zaatari->id, 'status' => 'active']);
        Refugee::factory()->count(7)->create(['current_camp_id' => $other->id, 'status' => 'active']);

        $answer = $this->ask('كم عدد السكان في مخيم الزعتري؟');

        $this->assertSame('population', $answer['intent']);
        $this->assertStringContainsString('4', $answer['text']);
        $this->assertContains(['label' => 'إجمالي السكان', 'value' => '4'], $answer['figures']);
    }

    public function test_population_excludes_archived_records(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create(['name' => 'الزعتري']);

        Refugee::factory()->count(3)->create(['current_camp_id' => $camp->id, 'status' => 'active']);
        Refugee::factory()->count(2)->create(['current_camp_id' => $camp->id, 'status' => 'archived']);

        $answer = $this->ask('كم عدد السكان في مخيم الزعتري؟');

        $this->assertContains(['label' => 'إجمالي السكان', 'value' => '3'], $answer['figures']);
    }

    public function test_the_unhoused_question_counts_only_unassigned_active_refugees(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        $shelter = Shelter::factory()->create(['camp_id' => $camp->id]);

        Refugee::factory()->count(2)->create(['current_camp_id' => $camp->id, 'housing_status' => 'unassigned']);
        Refugee::factory()->inShelter($shelter->id, $camp->id)->count(3)->create();
        Refugee::factory()->create(['housing_status' => 'unassigned', 'status' => 'archived']);

        $answer = $this->ask('كم لاجئًا بلا سكن؟');

        $this->assertSame('unhoused', $answer['intent']);
        $this->assertContains(['label' => 'بلا سكن', 'value' => '2'], $answer['figures']);
    }

    public function test_shelter_availability_splits_units_into_exclusive_states(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();

        $full = Shelter::factory()->capacity(2)->create(['camp_id' => $camp->id]);
        Refugee::factory()->inShelter($full->id, $camp->id)->count(2)->create();

        $partial = Shelter::factory()->capacity(4)->create(['camp_id' => $camp->id]);
        Refugee::factory()->inShelter($partial->id, $camp->id)->create();

        Shelter::factory()->capacity(3)->create(['camp_id' => $camp->id]);

        $answer = $this->ask('كم وحدة سكنية فارغة؟');

        $this->assertSame('shelter_availability', $answer['intent']);
        $figures = collect($answer['figures'])->pluck('value', 'label');

        $this->assertSame('1', $figures['وحدات فارغة']);
        $this->assertSame('1', $figures['مشغولة جزئيًا']);
        $this->assertSame('1', $figures['ممتلئة']);
        // 3 free in the empty unit plus 3 left in the partly filled one.
        $this->assertSame('6', $figures['أماكن شاغرة']);
    }

    public function test_aid_is_summarised_for_the_current_month_only(): void
    {
        $this->actingAsRole('aid_officer');
        $type = AidType::factory()->create(['name' => 'سلة غذائية', 'unit' => 'سلة']);

        AidDistribution::factory()->count(3)->create([
            'aid_type_id' => $type->id,
            'distribution_date' => now()->startOfMonth()->addDay()->toDateString(),
        ]);
        AidDistribution::factory()->create([
            'aid_type_id' => $type->id,
            'distribution_date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        ]);

        $answer = $this->ask('كم مساعدة وُزّعت هذا الشهر؟');

        $this->assertSame('aid_summary', $answer['intent']);
        $this->assertContains(['label' => 'عمليات التوزيع', 'value' => '3'], $answer['figures']);
    }

    public function test_last_month_is_read_as_last_month_and_not_as_this_one(): void
    {
        $this->actingAsRole('aid_officer');

        AidDistribution::factory()->count(2)->create([
            'distribution_date' => now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateString(),
        ]);
        AidDistribution::factory()->count(5)->create([
            'distribution_date' => now()->startOfMonth()->toDateString(),
        ]);

        $answer = $this->ask('كم مساعدة وُزّعت الشهر الماضي؟');

        $this->assertSame('aid_summary', $answer['intent']);
        $this->assertContains(['label' => 'عمليات التوزيع', 'value' => '2'], $answer['figures']);
    }

    public function test_aid_for_one_person_includes_what_reached_them_through_their_household(): void
    {
        $this->actingAsRole('aid_officer');
        $household = Household::factory()->create(['household_code' => 'HH-7001']);
        $refugee = Refugee::factory()->create([
            'first_name' => 'مروان', 'father_name' => null, 'last_name' => 'سعيد',
            'household_id' => $household->id,
        ]);

        AidDistribution::factory()->create(['refugee_id' => $refugee->id, 'household_id' => null]);
        AidDistribution::factory()->create(['refugee_id' => null, 'household_id' => $household->id]);
        AidDistribution::factory()->create();

        $answer = $this->ask('ماذا استلم مروان سعيد من مساعدات؟');

        $this->assertSame('aid_for_refugee', $answer['intent']);
        $this->assertCount(2, $answer['items']);
    }

    public function test_a_household_answer_lists_its_members(): void
    {
        $this->actingAsRole('registration_officer');
        $household = Household::factory()->create(['household_code' => 'HH-4402']);
        Refugee::factory()->count(3)->create(['household_id' => $household->id]);

        $answer = $this->ask('أفراد أسرة HH-4402');

        $this->assertSame('household', $answer['intent']);
        $this->assertStringContainsString('HH-4402', $answer['text']);
        $this->assertCount(3, $answer['items']);
    }

    public function test_a_question_nobody_matched_falls_back_to_the_global_search(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'نادر', 'father_name' => null, 'last_name' => 'حمود']);

        // A bare name with no question around it: no intent claims it.
        $answer = $this->ask('نادر حمود');

        $this->assertSame('search_fallback', $answer['intent']);
        $this->assertSame('نادر حمود', $answer['items'][0]['title']);
    }

    public function test_an_unanswerable_question_offers_examples_instead_of_guessing(): void
    {
        $this->actingAsRole('registration_officer');

        $answer = $this->ask('ما هو الطقس غدًا؟');

        $this->assertSame('unknown', $answer['tone']);
        $this->assertNotEmpty($answer['follow_ups']);
    }

    public function test_an_empty_result_is_reported_as_empty_rather_than_as_a_number(): void
    {
        $this->actingAsRole('housing_officer');
        Camp::factory()->create(['name' => 'الزعتري']);

        $answer = $this->ask('كم لاجئًا بلا سكن؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertSame([], $answer['figures']);
    }

    public function test_a_role_outside_the_area_is_refused_rather_than_answered_differently(): void
    {
        // A medical officer has no aid scope on the dashboard, so the assistant
        // must not answer aid questions for them either.
        $this->actingAsRole('medical_officer');
        AidDistribution::factory()->create(['distribution_date' => now()->toDateString()]);

        $answer = $this->ask('كم مساعدة وُزّعت هذا الشهر؟');

        $this->assertSame('denied', $answer['tone']);
        $this->assertSame([], $answer['figures']);
    }

    public function test_every_role_can_still_look_a_person_up(): void
    {
        // The top-bar search already exposes this to every signed-in user, so the
        // assistant refusing it would be a step backwards, not a safeguard.
        $this->actingAsRole('security_officer');
        Refugee::factory()->create(['first_name' => 'رامي', 'father_name' => null, 'last_name' => 'خضر']);

        $answer = $this->ask('ابحث عن رامي خضر');

        $this->assertSame('refugee_lookup', $answer['intent']);
        $this->assertNotEmpty($answer['items']);
    }

    public function test_suggestions_are_scoped_to_what_the_role_can_be_answered(): void
    {
        $this->actingAsRole('registration_officer');

        $suggestions = $this->getJson(route('assistant.suggestions'))
            ->assertOk()
            ->json('suggestions');

        $joined = implode(' | ', $suggestions);

        $this->assertStringNotContainsString('مساعدة', $joined);
        $this->assertStringNotContainsString('وحدة سكنية فارغة', $joined);
    }

    public function test_an_aid_officer_is_offered_the_aid_questions(): void
    {
        $this->actingAsRole('aid_officer');

        $suggestions = $this->getJson(route('assistant.suggestions'))->json('suggestions');

        $this->assertStringContainsString('مساعدة', implode(' | ', $suggestions));
    }

    public function test_a_manager_gets_aid_figures_without_a_link_they_cannot_open(): void
    {
        // The aid screens are limited to admin and aid officers, so a manager who
        // may read the numbers must not be handed a link that 403s.
        $this->actingAsRole('manager');
        AidDistribution::factory()->create(['distribution_date' => now()->toDateString()]);

        $answer = $this->ask('كم مساعدة وُزّعت هذا الشهر؟');

        $this->assertSame('aid_summary', $answer['intent']);
        $this->assertNotEmpty($answer['figures']);
        $this->assertSame([], $answer['links']);
    }

    public function test_a_housing_officer_gets_the_link_to_act_on_the_answer(): void
    {
        $this->actingAsRole('housing_officer');
        Refugee::factory()->create(['housing_status' => 'unassigned']);

        $answer = $this->ask('كم لاجئًا بلا سكن؟');

        $this->assertSame(route('housing.unassigned'), $answer['links'][0]['url']);
    }
}
