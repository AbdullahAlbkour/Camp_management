<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores supporting documents for a record.
 *
 * Files go to the private disk and are only ever served back through a controller
 * that re-checks authorization, so a leaked path is not a leaked document.
 */
class AttachmentService
{
    public const MAX_KILOBYTES = 8192;

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public const CATEGORIES = [
        'document' => 'مستند',
        'identity' => 'وثيقة هوية',
        'photo' => 'صورة شخصية',
        'medical' => 'مستند طبي',
    ];

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @return array<string, string>
     */
    public static function validationRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_KILOBYTES,
                'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
            ],
            'category' => 'required|in:'.implode(',', array_keys(self::CATEGORIES)),
            'description' => 'nullable|string|max:255',
        ];
    }

    public function store(Model $owner, UploadedFile $file, string $category, ?string $description = null): Attachment
    {
        // A generated name: the uploaded filename is untrusted and is kept only as a label.
        $path = $file->store($this->directoryFor($owner), 'local');

        $attachment = $owner->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'category' => $category,
            'description' => $description,
            'uploaded_by' => auth()->id(),
        ]);

        $this->auditLog->log('attach', $this->moduleFor($owner), $owner, 'إرفاق ملف بالسجل', 'high', [
            'attachment_id' => $attachment->id,
            'category' => $category,
        ]);

        return $attachment;
    }

    /**
     * Soft-delete the record and remove the file from disk: an archived attachment
     * row is only kept so the audit trail can still name what was removed.
     */
    public function delete(Attachment $attachment): void
    {
        $owner = $attachment->attachable;

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        $this->auditLog->log(
            'detach',
            $owner ? $this->moduleFor($owner) : 'attachments',
            $owner,
            'حذف ملف مرفق: '.$attachment->original_name,
            'high',
            ['attachment_id' => $attachment->id]
        );
    }

    private function directoryFor(Model $owner): string
    {
        return 'attachments/'.str($owner::class)->classBasename()->snake()->plural().'/'.$owner->getKey();
    }

    private function moduleFor(Model $owner): string
    {
        return str($owner::class)->classBasename()->snake()->plural()->value();
    }
}
