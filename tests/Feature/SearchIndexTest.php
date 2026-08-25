<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Refugee;
use App\Support\RefugeeSearchIndex;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_is_built_when_a_refugee_is_created(): void
    {
        $refugee = Refugee::factory()->create([
            'first_name' => 'أحمد', 'father_name' => null, 'last_name' => 'الحسن',
            'document_number' => 'DOC-1', 'phone' => '0900',
        ]);

        $this->assertSame('احمد الحسن doc-1 0900', $refugee->refresh()->search_text);
    }

    public function test_the_index_follows_a_rename(): void
    {
        $refugee = Refugee::factory()->create(['first_name' => 'أحمد', 'father_name' => null, 'last_name' => 'الحسن']);

        $refugee->update(['last_name' => 'الخطيب']);

        $this->assertStringContainsString('الخطيب', $refugee->refresh()->search_text);
        $this->assertStringNotContainsString('الحسن', $refugee->search_text);
    }

    public function test_a_renamed_refugee_is_found_under_the_new_name_only(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create(['first_name' => 'سمير', 'father_name' => null, 'last_name' => 'الحسن']);

        $refugee->update(['first_name' => 'سمر']);

        $this->get(route('refugees.index', ['q' => 'سمر']))->assertOk()->assertSee('سمر الحسن');
        $this->get(route('refugees.index', ['q' => 'سمير']))->assertOk()->assertSee('لا توجد نتائج');
    }

    public function test_a_bulk_insert_leaves_the_record_unsearchable_until_the_index_is_rebuilt(): void
    {
        $this->actingAsRole('registration_officer');
        $camp = Camp::factory()->create();

        // Bulk inserts bypass Eloquent events, which is how a seeded or imported
        // batch ends up invisible to search while form-created records work.
        DB::table('refugees')->insert([
            'first_name' => 'مستورد', 'father_name' => null, 'last_name' => 'بالجملة',
            'gender' => 'male', 'status' => 'active', 'current_camp_id' => $camp->id,
            'housing_status' => 'unassigned', 'presence_status' => 'inside',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(1, RefugeeSearchIndex::missingCount());
        $this->get(route('refugees.index', ['q' => 'مستورد']))->assertOk()->assertSee('لا توجد نتائج');

        RefugeeSearchIndex::rebuild();

        $this->assertSame(0, RefugeeSearchIndex::missingCount());
        $this->get(route('refugees.index', ['q' => 'مستورد']))->assertOk()->assertSee('مستورد بالجملة');
    }

    public function test_the_rebuild_command_reports_and_repairs(): void
    {
        $camp = Camp::factory()->create();

        DB::table('refugees')->insert([
            'first_name' => 'سجل', 'father_name' => null, 'last_name' => 'ناقص',
            'gender' => 'female', 'status' => 'active', 'current_camp_id' => $camp->id,
            'housing_status' => 'unassigned', 'presence_status' => 'inside',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('camps:rebuild-search --check')->assertFailed();
        $this->artisan('camps:rebuild-search')->assertSuccessful();
        $this->artisan('camps:rebuild-search --check')->assertSuccessful();
    }

    public function test_the_demo_seeder_leaves_every_record_searchable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, RefugeeSearchIndex::missingCount());
    }
}
