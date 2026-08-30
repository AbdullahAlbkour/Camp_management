<?php

namespace Tests\Feature;

use App\Models\AidDistribution;
use App\Models\AidType;
use App\Models\Camp;
use App\Models\Household;
use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\Shelter;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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

    public function test_a_camp_that_does_not_exist_is_reported_instead_of_being_ignored(): void
    {
        // The reported defect: naming an unknown camp dropped the filter and the
        // totals of every other camp were returned as though understood.
        $this->actingAsRole('housing_officer');
        $salam = Camp::factory()->create(['name' => 'مخيم السلام']);
        Shelter::factory()->capacity(4)->count(3)->create(['camp_id' => $salam->id]);

        $answer = $this->ask('ما الوحدات المتاحة في مخيم الزعتري؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertStringContainsString('لا يوجد مخيم', $answer['text']);
        $this->assertSame([], $answer['figures']);
        // No count from مخيم السلام may appear in an answer about a camp that
        // does not exist.
        $this->assertStringNotContainsString('وحدة فارغة', $answer['text']);
    }

    public function test_the_unknown_camp_answer_quotes_the_name_as_typed(): void
    {
        $this->actingAsRole('housing_officer');
        Camp::factory()->create(['name' => 'مخيم السلام']);

        $answer = $this->ask('كم وحدة فارغة في مخيم الأمل؟');

        // Matching folds "الأمل" to "الامل"; the reply spells it back as written.
        $this->assertStringContainsString('«الأمل»', $answer['text']);
    }

    public function test_the_unknown_camp_answer_lists_the_camps_that_do_exist(): void
    {
        $this->actingAsRole('registration_officer');
        Camp::factory()->create(['name' => 'مخيم السلام']);
        Camp::factory()->create(['name' => 'مخيم النور']);

        $answer = $this->ask('كم عدد السكان في مخيم الزعتري؟');

        $titles = array_column($answer['items'], 'title');
        $this->assertContains('مخيم السلام', $titles);
        $this->assertContains('مخيم النور', $titles);
    }

    public function test_the_unhoused_question_refuses_an_unknown_camp(): void
    {
        $this->actingAsRole('housing_officer');
        $salam = Camp::factory()->create(['name' => 'مخيم السلام']);
        Refugee::factory()->count(4)->create([
            'current_camp_id' => $salam->id,
            'housing_status' => 'unassigned',
        ]);

        $answer = $this->ask('كم لاجئًا بلا سكن في مخيم الزعتري؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertStringContainsString('لا يوجد مخيم', $answer['text']);
        $this->assertSame([], $answer['figures']);
    }

    public function test_the_aid_summary_refuses_an_unknown_camp(): void
    {
        $this->actingAsRole('aid_officer');
        Camp::factory()->create(['name' => 'مخيم السلام']);
        AidDistribution::factory()->count(3)->create(['distribution_date' => now()->toDateString()]);

        $answer = $this->ask('كم مساعدة وُزّعت في مخيم الزعتري هذا الشهر؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertStringContainsString('لا يوجد مخيم', $answer['text']);
    }

    public function test_a_question_with_no_camp_still_answers_for_the_whole_system(): void
    {
        // The strict check must not turn every system-wide question into a
        // complaint about a missing camp.
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create(['name' => 'مخيم السلام']);
        Refugee::factory()->count(5)->create(['current_camp_id' => $camp->id, 'status' => 'active']);

        $answer = $this->ask('كم عدد السكان المسجلين؟');

        $this->assertSame('population', $answer['intent']);
        $this->assertContains(['label' => 'إجمالي السكان', 'value' => '5'], $answer['figures']);
    }

    public function test_the_bare_word_camp_with_no_name_is_not_treated_as_a_missing_camp(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create(['name' => 'مخيم السلام']);
        Refugee::factory()->count(3)->create(['current_camp_id' => $camp->id, 'status' => 'active']);

        $answer = $this->ask('كم عدد السكان في المخيم؟');

        $this->assertSame('population', $answer['intent']);
        $this->assertContains(['label' => 'إجمالي السكان', 'value' => '3'], $answer['figures']);
    }

    public function test_the_plural_asks_about_every_camp_rather_than_naming_one(): void
    {
        $this->actingAsRole('registration_officer');
        $a = Camp::factory()->create(['name' => 'مخيم السلام']);
        $b = Camp::factory()->create(['name' => 'مخيم النور']);
        Refugee::factory()->count(2)->create(['current_camp_id' => $a->id, 'status' => 'active']);
        Refugee::factory()->count(4)->create(['current_camp_id' => $b->id, 'status' => 'active']);

        $answer = $this->ask('كم عدد السكان في المخيمات؟');

        $this->assertSame('population', $answer['intent']);
        $this->assertContains(['label' => 'إجمالي السكان', 'value' => '6'], $answer['figures']);
    }

    public function test_a_time_phrase_after_the_camp_word_is_not_read_as_a_camp_name(): void
    {
        // "مخيم" followed by a period word must not report a camp called "اليوم".
        $this->actingAsRole('aid_officer');
        $camp = Camp::factory()->create(['name' => 'النور']);
        AidDistribution::factory()->count(2)->create([
            'camp_id' => $camp->id,
            'distribution_date' => now()->toDateString(),
        ]);

        $answer = $this->ask('كم مساعدة وُزّعت في مخيم النور اليوم؟');

        $this->assertSame('aid_summary', $answer['intent']);
        $this->assertContains(['label' => 'عمليات التوزيع', 'value' => '2'], $answer['figures']);
    }

    public function test_an_existing_camp_named_without_the_word_camp_still_resolves(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create(['name' => 'الزعتري']);
        Refugee::factory()->count(7)->create(['current_camp_id' => $camp->id, 'status' => 'active']);
        Refugee::factory()->count(2)->create(['status' => 'active']);

        $answer = $this->ask('كم عدد السكان في الزعتري؟');

        $this->assertContains(['label' => 'إجمالي السكان', 'value' => '7'], $answer['figures']);
    }

    public function test_an_existing_camp_with_zero_results_reads_differently_from_a_missing_one(): void
    {
        // "no shelters registered there" and "that camp does not exist" are
        // different facts and must not share a sentence.
        $this->actingAsRole('housing_officer');
        Camp::factory()->create(['name' => 'مخيم الزعتري']);

        $answer = $this->ask('ما الوحدات المتاحة في مخيم الزعتري؟');

        $this->assertStringContainsString('لا توجد وحدات سكنية فعّالة', $answer['text']);
        $this->assertStringNotContainsString('لا يوجد مخيم', $answer['text']);
    }

    public function test_households_are_counted_only_for_the_camp_named(): void
    {
        $this->actingAsRole('registration_officer');
        $salam = Camp::factory()->create(['name' => 'مخيم السلام']);
        $nour = Camp::factory()->create(['name' => 'مخيم النور']);

        $here = Household::factory()->create();
        Refugee::factory()->count(2)->create(['household_id' => $here->id, 'current_camp_id' => $salam->id]);

        $elsewhere = Household::factory()->create();
        Refugee::factory()->count(3)->create(['household_id' => $elsewhere->id, 'current_camp_id' => $nour->id]);

        $answer = $this->ask('كم عدد الأسر في مخيم السلام؟');

        $this->assertSame('household', $answer['intent']);
        $this->assertContains(['label' => 'عدد الأسر', 'value' => '1'], $answer['figures']);
    }

    public function test_the_household_count_refuses_an_unknown_camp(): void
    {
        $this->actingAsRole('registration_officer');
        Camp::factory()->create(['name' => 'مخيم السلام']);
        Household::factory()->count(4)->create();

        $answer = $this->ask('كم عدد الأسر في مخيم الزعتري؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertStringContainsString('لا يوجد مخيم', $answer['text']);
        $this->assertSame([], $answer['figures']);
    }

    public function test_a_refugee_who_does_not_exist_is_reported_not_substituted(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'أحمد', 'father_name' => null, 'last_name' => 'الحسن']);

        $answer = $this->ask('ابحث عن زياد المصري');

        $this->assertSame('empty', $answer['tone']);
        $this->assertSame([], $answer['items']);
        $this->assertStringContainsString('لم أجد', $answer['text']);
    }

    public function test_housing_status_for_an_unknown_person_returns_nobody_elses_address(): void
    {
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        $shelter = Shelter::factory()->create(['camp_id' => $camp->id]);
        Refugee::factory()->inShelter($shelter->id, $camp->id)->create([
            'first_name' => 'أحمد', 'father_name' => null, 'last_name' => 'الحسن',
        ]);

        $answer = $this->ask('أين يسكن زياد المصري؟');

        $this->assertSame('empty', $answer['tone']);
        $this->assertSame([], $answer['items']);
        $this->assertSame([], $answer['figures']);
    }

    public function test_a_household_code_that_does_not_exist_is_reported(): void
    {
        $this->actingAsRole('registration_officer');
        Household::factory()->create(['household_code' => 'HH-0001']);

        $answer = $this->ask('أفراد أسرة HH-9999');

        $this->assertSame('empty', $answer['tone']);
        $this->assertSame([], $answer['items']);
        $this->assertStringContainsString('لم أجد أسرة', $answer['text']);
    }

    public function test_example_questions_name_a_camp_that_actually_exists(): void
    {
        $this->actingAsRole('housing_officer');
        Camp::factory()->create(['name' => 'مخيم النور', 'status' => 'active']);

        $suggestions = $this->getJson(route('assistant.suggestions'))->json('suggestions');
        $joined = implode(' | ', $suggestions);

        $this->assertStringContainsString('مخيم النور', $joined);
        $this->assertStringNotContainsString('{camp}', $joined);
    }

    public function test_example_questions_survive_a_system_with_no_camps_yet(): void
    {
        $this->actingAsRole('housing_officer');

        $suggestions = $this->getJson(route('assistant.suggestions'))->json('suggestions');

        $this->assertNotEmpty($suggestions);
        $this->assertStringNotContainsString('{camp}', implode(' | ', $suggestions));
    }

    public function test_housing_status_reports_the_date_of_the_last_transfer(): void
    {
        // Regression: this figure was ordered and read by a column that does not
        // exist (transfer_date; the column is transferred_at). SQLite accepts an
        // unknown identifier in ORDER BY as a string literal, so the whole suite
        // passed while MySQL answered the request with a 500.
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        $shelter = Shelter::factory()->create(['camp_id' => $camp->id]);
        $refugee = Refugee::factory()->inShelter($shelter->id, $camp->id)->create([
            'first_name' => 'وليد', 'father_name' => null, 'last_name' => 'الشامي',
        ]);

        ResidencyTransfer::factory()->create([
            'refugee_id' => $refugee->id,
            'transferred_at' => now()->subDays(9),
        ]);
        ResidencyTransfer::factory()->create([
            'refugee_id' => $refugee->id,
            'transferred_at' => now()->subDay(),
        ]);

        $answer = $this->ask('أين يسكن وليد الشامي؟');

        $this->assertSame('housing_status', $answer['intent']);
        $this->assertContains(
            ['label' => 'آخر انتقال', 'value' => now()->subDay()->format('Y-m-d')],
            $answer['figures'],
            'The most recent transfer must be the one reported.'
        );
    }

    public function test_a_common_first_name_returns_the_matches_rather_than_failing(): void
    {
        // The reported case: "اين يسكن محمد" matches many people.
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        $shelter = Shelter::factory()->capacity(9)->create(['camp_id' => $camp->id]);

        foreach (['العلي', 'الحسن', 'الخطيب'] as $family) {
            Refugee::factory()->inShelter($shelter->id, $camp->id)->create([
                'first_name' => 'محمد', 'father_name' => null, 'last_name' => $family,
            ]);
        }

        $answer = $this->ask('اين يسكن محمد');

        $this->assertSame('housing_status', $answer['intent']);
        $this->assertCount(3, $answer['items']);
        // No figure may be reported: none of the three is "the" person.
        $this->assertSame([], $answer['figures']);
    }

    public function test_an_ambiguous_name_is_told_how_to_narrow_it(): void
    {
        // "اختر السجل" is no help to someone who typed the only name they had.
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();

        foreach (['العلي', 'الحسن'] as $family) {
            Refugee::factory()->create([
                'first_name' => 'محمد', 'father_name' => null, 'last_name' => $family,
                'current_camp_id' => $camp->id,
            ]);
        }

        $answer = $this->ask('اين يسكن محمد');

        $this->assertStringContainsString('الاسم الثلاثي', $answer['text']);
        $this->assertStringContainsString('رقم الوثيقة', $answer['text']);
        $this->assertStringContainsString('محمد', $answer['text']);
    }

    public function test_an_ambiguous_aid_question_is_told_how_to_narrow_it_too(): void
    {
        $this->actingAsRole('aid_officer');

        foreach (['العلي', 'الحسن'] as $family) {
            Refugee::factory()->create([
                'first_name' => 'محمد', 'father_name' => null, 'last_name' => $family,
            ]);
        }

        $answer = $this->ask('ماذا استلم محمد من مساعدات؟');

        $this->assertSame('aid_for_refugee', $answer['intent']);
        $this->assertStringContainsString('الاسم الثلاثي', $answer['text']);
        $this->assertCount(2, $answer['items']);
    }

    public function test_the_full_three_part_name_resolves_a_single_person(): void
    {
        // The guidance has to actually work: given the full name, one record.
        $this->actingAsRole('housing_officer');
        $camp = Camp::factory()->create();
        $shelter = Shelter::factory()->capacity(5)->create(['camp_id' => $camp->id]);

        Refugee::factory()->inShelter($shelter->id, $camp->id)->create([
            'first_name' => 'محمد', 'father_name' => 'سالم', 'last_name' => 'العلي',
        ]);
        Refugee::factory()->inShelter($shelter->id, $camp->id)->create([
            'first_name' => 'محمد', 'father_name' => 'خالد', 'last_name' => 'الحسن',
        ]);

        $answer = $this->ask('أين يسكن محمد سالم العلي؟');

        $this->assertCount(1, $answer['items']);
        $this->assertSame('محمد سالم العلي', $answer['items'][0]['title']);
        $this->assertNotEmpty($answer['figures']);
    }

    public function test_an_unexpected_failure_answers_with_an_apology_not_a_500(): void
    {
        $this->actingAsRole('registration_officer');

        // Stand in for any future breakage inside the assistant.
        $this->mock(AssistantService::class, function ($mock): void {
            $mock->shouldReceive('ask')->andThrow(new RuntimeException('boom'));
            $mock->shouldReceive('suggestions')->andReturn([]);
        });

        $response = $this->postJson(route('assistant.ask'), ['question' => 'كم عدد السكان؟'])
            ->assertOk();

        $this->assertSame('error', $response->json('answer.tone'));
        $this->assertStringContainsString('تعذّر', $response->json('answer.text'));
        // The cause must not be handed to the browser.
        $this->assertStringNotContainsString('boom', $response->getContent());
    }

    public function test_a_failure_is_logged_with_the_question_that_caused_it(): void
    {
        $this->actingAsRole('registration_officer');

        $this->mock(AssistantService::class, function ($mock): void {
            $mock->shouldReceive('ask')->andThrow(new RuntimeException('boom'));
            $mock->shouldReceive('suggestions')->andReturn([]);
        });

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context) => $context['question'] === 'كم عدد السكان؟');

        $this->postJson(route('assistant.ask'), ['question' => 'كم عدد السكان؟'])->assertOk();
    }

    public function test_a_housing_officer_gets_the_link_to_act_on_the_answer(): void
    {
        $this->actingAsRole('housing_officer');
        Refugee::factory()->create(['housing_status' => 'unassigned']);

        $answer = $this->ask('كم لاجئًا بلا سكن؟');

        $this->assertSame(route('housing.unassigned'), $answer['links'][0]['url']);
    }
}
