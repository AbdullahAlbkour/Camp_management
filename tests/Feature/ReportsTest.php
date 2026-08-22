<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MedicalRecord;
use App\Models\Refugee;
use App\Models\SecurityReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_refugee_report_shows_readable_headings_not_column_names(): void
    {
        $this->actingAsRole('manager');
        Refugee::factory()->create(['first_name' => 'سميرة']);

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('الاسم الكامل')
            ->assertSee('حالة السكن')
            ->assertSee('سميرة')
            ->assertDontSee('current_camp_id');
    }

    public function test_csv_export_carries_headings_and_data(): void
    {
        $this->actingAsRole('manager');
        Refugee::factory()->create(['first_name' => 'سميرة', 'last_name' => 'الأحمد']);

        $response = $this->get(route('reports.export', ['report' => 'refugees', 'format' => 'csv']));
        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Excel needs the BOM to read Arabic.');
        $this->assertStringContainsString('الاسم الكامل', $csv);
        $this->assertStringContainsString('سميرة', $csv);
        $this->assertStringNotContainsString('حقل 1', $csv);
    }

    public function test_xlsx_export_produces_a_readable_workbook(): void
    {
        $this->actingAsRole('manager');
        Refugee::factory()->create(['first_name' => 'سميرة']);

        $response = $this->get(route('reports.export', ['report' => 'refugees', 'format' => 'xlsx']));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'assert_').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'The export should be a valid zip container.');

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($sheet);
        $this->assertStringContainsString('الاسم الكامل', $sheet);
        $this->assertStringContainsString('سميرة', $sheet);
    }

    public function test_exports_are_recorded_in_the_audit_trail(): void
    {
        $this->actingAsRole('manager');

        $this->get(route('reports.export', ['report' => 'refugees', 'format' => 'csv']))->streamedContent();

        $this->assertTrue(
            AuditLog::where('action', 'export')->where('module', 'reports')->exists()
        );
    }

    public function test_a_registration_officer_cannot_open_the_medical_report(): void
    {
        $this->actingAsRole('registration_officer');

        $this->get(route('reports.index', ['report' => 'medical']))->assertForbidden();
    }

    public function test_a_manager_sees_medical_rows_without_the_diagnosis(): void
    {
        $this->actingAsRole('manager');

        MedicalRecord::factory()->create(['diagnosis' => 'تشخيص سري جدا']);

        $this->get(route('reports.index', ['report' => 'medical']))
            ->assertOk()
            ->assertDontSee('تشخيص سري جدا')
            ->assertDontSee('التشخيص');
    }

    public function test_a_medical_officer_sees_the_diagnosis(): void
    {
        $this->actingAsRole('medical_officer');

        MedicalRecord::factory()->create(['diagnosis' => 'التهاب رئوي']);

        $this->get(route('reports.index', ['report' => 'medical']))
            ->assertOk()
            ->assertSee('التهاب رئوي');
    }

    public function test_a_manager_sees_security_rows_without_the_narrative(): void
    {
        $this->actingAsRole('manager');

        SecurityReport::factory()->create(['description' => 'وصف الحادثة الحساس']);

        $this->get(route('reports.index', ['report' => 'security']))
            ->assertOk()
            ->assertDontSee('وصف الحادثة الحساس');
    }

    public function test_an_invalid_date_range_is_rejected(): void
    {
        $this->actingAsRole('manager');

        $this->get(route('reports.index', ['report' => 'refugees', 'from' => '2026-05-01', 'to' => '2026-01-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_an_unknown_report_key_is_refused(): void
    {
        $this->actingAsRole('manager');

        $this->get(route('reports.index', ['report' => 'salaries']))->assertForbidden();
    }
}
