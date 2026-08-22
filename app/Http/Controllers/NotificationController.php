<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $rows = $this->paginatedRows(
            Notification::visibleFor(auth()->user())
                ->where('status', '!=', 'resolved')
                ->latest()
                ->limit(300)
                ->get()
                ->unique('dedupe_key')
                ->values()
        );

        return view('notifications.index', compact('rows'));
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        $this->authorizeVisibility($notification);
        $this->matchingVisibleNotifications($notification)
            ->where('status', 'unread')
            ->update(['status' => 'read']);

        return back()->with('success', 'تم تعليم التنبيه كمقروء.');
    }

    public function resolve(Notification $notification): RedirectResponse
    {
        $this->authorizeVisibility($notification);
        $this->matchingVisibleNotifications($notification)
            ->where('status', '!=', 'resolved')
            ->update(['status' => 'resolved']);

        return back()->with('success', 'تمت معالجة التنبيه.');
    }

    private function paginatedRows(Collection $notifications): LengthAwarePaginator
    {
        $perPage = 30;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $notifications->forPage($page, $perPage)->values(),
            $notifications->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function matchingVisibleNotifications(Notification $notification)
    {
        return Notification::visibleFor(auth()->user())->equivalentTo($notification);
    }

    private function authorizeVisibility(Notification $notification): void
    {
        $user = auth()->user();

        if (! $user->hasRole('admin') && $notification->user_id !== $user->id && $notification->target_role !== $user->role?->name) {
            abort(403);
        }
    }
}
