<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Refugee;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments) {}

    public function store(Request $request, Refugee $refugee): RedirectResponse
    {
        $request->validate(AttachmentService::validationRules());

        $this->attachments->store(
            $refugee,
            $request->file('file'),
            $request->string('category')->value(),
            $request->input('description')
        );

        return redirect()
            ->route('refugees.show', $refugee)
            ->with('success', 'تم إرفاق الملف بالسجل.');
    }

    /**
     * Files live on the private disk, so every download is re-authorized here rather
     * than being reachable by guessing a public URL.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorizeAccess($attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404, 'الملف غير موجود على القرص.');

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $this->authorizeAccess($attachment);

        $owner = $attachment->attachable;
        $this->attachments->delete($attachment);

        return $owner instanceof Refugee
            ? redirect()->route('refugees.show', $owner)->with('success', 'تم حذف المرفق.')
            : back()->with('success', 'تم حذف المرفق.');
    }

    /**
     * Medical attachments follow the same confidentiality rule as medical records.
     */
    private function authorizeAccess(Attachment $attachment): void
    {
        if ($attachment->category === 'medical') {
            abort_unless(
                auth()->user()?->hasAnyRole(['admin', 'medical_officer']),
                403,
                'المرفقات الطبية متاحة للكادر الطبي فقط.'
            );
        }
    }
}
