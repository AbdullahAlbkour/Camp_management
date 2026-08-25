<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Refugee;
use App\Models\Shelter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListFilteringTest extends TestCase
{
    use RefreshDatabase;

    // ---- Arabic-insensitive search ----

    public function test_a_name_written_without_hamza_finds_the_name_written_with_it(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'أحمد', 'father_name' => null, 'last_name' => 'الحسن']);

        $this->get(route('refugees.index', ['q' => 'احمد']))
            ->assertOk()
            ->assertSee('أحمد الحسن');
    }

    public function test_a_name_written_with_hamza_finds_the_name_written_without_it(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'ابراهيم', 'father_name' => null, 'last_name' => 'خليل']);

        $this->get(route('refugees.index', ['q' => 'إبراهيم']))
            ->assertOk()
            ->assertSee('ابراهيم خليل');
    }

    public function test_ta_marbuta_and_alef_maksura_are_interchangeable(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'فاطمة', 'father_name' => null, 'last_name' => 'يحيى']);

        $this->get(route('refugees.index', ['q' => 'فاطمه']))->assertOk()->assertSee('فاطمة يحيى');
        $this->get(route('refugees.index', ['q' => 'يحيي']))->assertOk()->assertSee('فاطمة يحيى');
    }

    public function test_a_search_spanning_first_and_last_name_matches(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'سميرة', 'father_name' => 'آدم', 'last_name' => 'الأحمد']);

        $this->get(route('refugees.index', ['q' => 'سميره ادم']))
            ->assertOk()
            ->assertSee('سميرة آدم الأحمد');
    }

    public function test_search_by_document_number(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'خالد', 'father_name' => null, 'last_name' => 'سالم', 'document_number' => 'ID-55501']);
        Refugee::factory()->create(['first_name' => 'مروان', 'father_name' => null, 'last_name' => 'عمر', 'document_number' => 'ID-99900']);

        $this->get(route('refugees.index', ['q' => 'ID-55501']))
            ->assertOk()
            ->assertSee('خالد سالم')
            ->assertDontSee('مروان عمر');
    }

    public function test_arabic_indic_digits_match_ascii_digits(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'ليلى', 'father_name' => null, 'last_name' => 'نور', 'document_number' => '1234567']);

        $this->get(route('refugees.index', ['q' => '١٢٣٤٥٦٧']))
            ->assertOk()
            ->assertSee('ليلى نور');
    }

    public function test_search_by_badge_code(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create(['first_name' => 'رامي', 'father_name' => null, 'last_name' => 'زيد']);

        $this->get(route('refugees.index', ['q' => $refugee->badge_code]))
            ->assertOk()
            ->assertSee('رامي زيد');
    }

    // ---- Refugee filters ----

    public function test_filtering_by_camp(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();
        Refugee::factory()->create(['first_name' => 'زياد', 'father_name' => null, 'last_name' => 'قنديل', 'current_camp_id' => $camp->id]);
        Refugee::factory()->create(['first_name' => 'وسام', 'father_name' => null, 'last_name' => 'برهوم']);

        $this->get(route('refugees.index', ['camp_id' => $camp->id]))
            ->assertOk()
            ->assertSee('زياد قنديل')
            ->assertDontSee('وسام برهوم');
    }

    public function test_filtering_by_housing_status(): void
    {
        $this->actingAsRole('registration_officer');
        $shelter = Shelter::factory()->capacity(4)->create();
        Refugee::factory()->inShelter($shelter->id, $shelter->camp_id)->create(['first_name' => 'نادر', 'father_name' => null, 'last_name' => 'شاهين']);
        Refugee::factory()->create(['first_name' => 'غيث', 'father_name' => null, 'last_name' => 'مرعي']);

        $this->get(route('refugees.index', ['housing_status' => 'unassigned']))
            ->assertOk()
            ->assertSee('غيث مرعي')
            ->assertDontSee('نادر شاهين');
    }

    public function test_filtering_by_gender_and_status(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'رجل', 'father_name' => null, 'last_name' => 'فعال', 'gender' => 'male', 'status' => 'active']);
        Refugee::factory()->create(['first_name' => 'امرأة', 'father_name' => null, 'last_name' => 'فعالة', 'gender' => 'female', 'status' => 'active']);

        $this->get(route('refugees.index', ['gender' => 'female']))
            ->assertOk()
            ->assertSee('امرأة فعالة')
            ->assertDontSee('رجل فعال');
    }

    public function test_filtering_by_age_range(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'طفل', 'father_name' => null, 'last_name' => 'صغير', 'date_of_birth' => now()->subYears(6)->toDateString()]);
        Refugee::factory()->create(['first_name' => 'شاب', 'father_name' => null, 'last_name' => 'بالغ', 'date_of_birth' => now()->subYears(30)->toDateString()]);
        Refugee::factory()->create(['first_name' => 'مسن', 'father_name' => null, 'last_name' => 'كبير', 'date_of_birth' => now()->subYears(70)->toDateString()]);

        $this->get(route('refugees.index', ['age_min' => 18, 'age_max' => 60]))
            ->assertOk()
            ->assertSee('شاب بالغ')
            ->assertDontSee('طفل صغير')
            ->assertDontSee('مسن كبير');
    }

    public function test_age_boundaries_are_inclusive(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'حد', 'father_name' => null, 'last_name' => 'أدنى', 'date_of_birth' => now()->subYears(18)->toDateString()]);
        Refugee::factory()->create(['first_name' => 'حد', 'father_name' => null, 'last_name' => 'أعلى', 'date_of_birth' => now()->subYears(60)->toDateString()]);

        $this->get(route('refugees.index', ['age_min' => 18, 'age_max' => 60]))
            ->assertOk()
            ->assertSee('حد أدنى')
            ->assertSee('حد أعلى');
    }

    public function test_filtering_by_days_without_housing(): void
    {
        $this->actingAsRole('registration_officer');
        Refugee::factory()->create(['first_name' => 'قديم', 'father_name' => null, 'last_name' => 'الانتظار', 'created_at' => now()->subDays(20)]);
        Refugee::factory()->create(['first_name' => 'جديد', 'father_name' => null, 'last_name' => 'اليوم', 'created_at' => now()]);

        $this->get(route('refugees.index', ['unhoused_days' => 7]))
            ->assertOk()
            ->assertSee('قديم الانتظار')
            ->assertDontSee('جديد اليوم');
    }

    public function test_filters_combine_rather_than_replace_each_other(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();
        Refugee::factory()->create(['first_name' => 'مطابق', 'father_name' => null, 'last_name' => 'تمامًا', 'current_camp_id' => $camp->id, 'gender' => 'female']);
        Refugee::factory()->create(['first_name' => 'مخيم', 'father_name' => null, 'last_name' => 'صحيح', 'current_camp_id' => $camp->id, 'gender' => 'male']);
        Refugee::factory()->create(['first_name' => 'جنس', 'father_name' => null, 'last_name' => 'صحيح', 'gender' => 'female']);

        $this->get(route('refugees.index', ['camp_id' => $camp->id, 'gender' => 'female']))
            ->assertOk()
            ->assertSee('مطابق تمامًا')
            ->assertDontSee('مخيم صحيح')
            ->assertDontSee('جنس صحيح');
    }
}
