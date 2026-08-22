<?php

namespace App\Http\Controllers;

use App\Models\Camp;
use App\Models\Household;
use App\Models\Organization;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Services\ArchiveService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    /**
     * Archivable resource key => [model class, index route, Arabic label, roles].
     */
    private const RESOURCES = [
        'camps' => [Camp::class, 'camps.index', 'المخيمات', ['admin', 'housing_officer']],
        'shelters' => [Shelter::class, 'shelters.index', 'الوحدات السكنية', ['admin', 'housing_officer']],
        'refugees' => [Refugee::class, 'refugees.index', 'اللاجئون', ['admin', 'registration_officer']],
        'households' => [Household::class, 'households.index', 'الأسر', ['admin', 'registration_officer']],
        'organizations' => [Organization::class, 'aid.organizations', 'الجهات الداعمة', ['admin', 'aid_officer']],
    ];

    public function __construct(private readonly ArchiveService $archive) {}

    /**
     * The archive browser: what has been archived, and the button to bring it back.
     */
    public function index(Request $request): View
    {
        $available = $this->availableFor($request);
        $resource = (string) $request->get('resource', array_key_first($available) ?? 'camps');

        abort_unless(isset($available[$resource]), 403, 'لا تملك صلاحية عرض هذا الأرشيف.');

        [$class] = self::RESOURCES[$resource];

        return view('archive.index', [
            'resource' => $resource,
            'available' => $available,
            'rows' => $class::onlyTrashed()->latest('deleted_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function archive(Request $request, string $resource, int $id): RedirectResponse
    {
        $model = $this->find($request, $resource, $id);

        $this->archive->archive($model, $request->input('reason'));

        return redirect()
            ->route(self::RESOURCES[$resource][1])
            ->with('success', 'تمت أرشفة السجل. يمكن استرجاعه من صفحة الأرشيف.');
    }

    public function restore(Request $request, string $resource, int $id): RedirectResponse
    {
        $model = $this->find($request, $resource, $id, trashed: true);

        $this->archive->restore($model);

        return redirect()
            ->route('archive.index', ['resource' => $resource])
            ->with('success', 'تم استرجاع السجل.');
    }

    private function find(Request $request, string $resource, int $id, bool $trashed = false): Model
    {
        abort_unless(isset($this->availableFor($request)[$resource]), 403, 'لا تملك صلاحية هذا الإجراء.');

        [$class] = self::RESOURCES[$resource];
        $query = $trashed ? $class::onlyTrashed() : $class::query();

        return $query->findOrFail($id);
    }

    /**
     * @return array<string, string>
     */
    private function availableFor(Request $request): array
    {
        $user = $request->user();
        $available = [];

        foreach (self::RESOURCES as $key => [, , $label, $roles]) {
            if ($user !== null && $user->hasAnyRole($roles)) {
                $available[$key] = $label;
            }
        }

        return $available;
    }
}
