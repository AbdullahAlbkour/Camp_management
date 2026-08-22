<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Refugee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_registration_officer_can_attach_a_document(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create();

        $this->post(route('refugees.attachments.store', $refugee), [
            'file' => UploadedFile::fake()->create('identity.pdf', 120, 'application/pdf'),
            'category' => 'identity',
            'description' => 'صورة الهوية',
        ])->assertRedirect(route('refugees.show', $refugee));

        $attachment = Attachment::first();

        $this->assertNotNull($attachment);
        $this->assertSame('identity.pdf', $attachment->original_name);
        $this->assertSame('identity', $attachment->category);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_the_stored_path_does_not_reuse_the_uploaded_filename(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create();

        $this->post(route('refugees.attachments.store', $refugee), [
            'file' => UploadedFile::fake()->create('../../evil.pdf', 10, 'application/pdf'),
            'category' => 'document',
        ]);

        $attachment = Attachment::first();

        $this->assertStringNotContainsString('..', $attachment->path);
        $this->assertStringNotContainsString('evil', $attachment->path);
    }

    public function test_an_executable_upload_is_rejected(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create();

        $this->from(route('refugees.show', $refugee))
            ->post(route('refugees.attachments.store', $refugee), [
                'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
                'category' => 'document',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::count());
    }

    public function test_an_oversized_upload_is_rejected(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create();

        $this->from(route('refugees.show', $refugee))
            ->post(route('refugees.attachments.store', $refugee), [
                'file' => UploadedFile::fake()->create('huge.pdf', 9000, 'application/pdf'),
                'category' => 'document',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_a_medical_attachment_is_not_downloadable_by_other_roles(): void
    {
        $attachment = Attachment::factory()->create(['category' => 'medical']);

        $this->actingAsRole('registration_officer');
        $this->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_a_medical_officer_can_download_a_medical_attachment(): void
    {
        $this->actingAsRole('medical_officer');
        $refugee = Refugee::factory()->create();

        $this->post(route('refugees.attachments.store', $refugee), [
            'file' => UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf'),
            'category' => 'medical',
        ]);

        $this->get(route('attachments.download', Attachment::first()))->assertOk();
    }

    public function test_deleting_an_attachment_removes_the_file_and_is_audited(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create();

        $this->post(route('refugees.attachments.store', $refugee), [
            'file' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf'),
            'category' => 'document',
        ]);

        $attachment = Attachment::first();
        $path = $attachment->path;

        $this->delete(route('attachments.destroy', $attachment))->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
        $this->assertTrue(AuditLog::where('action', 'detach')->exists());
    }

    public function test_uploading_is_audited(): void
    {
        $this->actingAsRole('registration_officer');
        $refugee = Refugee::factory()->create();

        $this->post(route('refugees.attachments.store', $refugee), [
            'file' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf'),
            'category' => 'document',
        ]);

        $this->assertTrue(AuditLog::where('action', 'attach')->where('sensitivity', 'high')->exists());
    }

    public function test_an_aid_officer_cannot_attach_files_to_a_refugee(): void
    {
        $this->actingAsRole('aid_officer');
        $refugee = Refugee::factory()->create();

        $this->post(route('refugees.attachments.store', $refugee), [
            'file' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf'),
            'category' => 'document',
        ])->assertForbidden();
    }
}
