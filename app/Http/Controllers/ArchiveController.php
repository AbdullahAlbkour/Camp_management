<?php

namespace App\Http\Controllers;

use App\Services\ArchiveService;
use App\Support\ArchivableResources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    public function __construct(private readonly ArchiveService $archive) {}

    /**
     * The archive browser: what has been archived, and the button to bring it back.
     */
    public function index(Request $request): View
    {
        $available = ArchivableResources::labelsFor($request->user());
        $resource = (string) $request->get('resource', array_key_first($available) ?? 'camps');

        abort_unless(isset($available[$resource]), 403, 'لا تملك صلاحية عرض هذا الأرشيف.');

        $class = ArchivableResources::model($resource);

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
            ->route(ArchivableResources::indexRoute($resource))
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
        abort_unless(
            ArchivableResources::allows($request->user(), $resource),
            403,
            'لا تملك صلاحية هذا الإجراء.'
        );

        $class = ArchivableResources::model($resource);
        $query = $trashed ? $class::onlyTrashed() : $class::query();

        return $query->findOrFail($id);
    }
}
