<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;

class NotificationService
{
    public function forRoles(
        array $roles,
        string $type,
        string $title,
        ?string $body = null,
        ?Model $related = null
    ): void {
        $relatedType = $related ? $related::class : null;
        $relatedId = $related?->getKey();

        foreach (array_unique($roles) as $role) {
            $existing = Notification::query()
                ->where('target_role', $role)
                ->whereNull('user_id')
                ->where('type', $type)
                ->where('title', $title)
                ->when($body === null, fn ($query) => $query->whereNull('body'), fn ($query) => $query->where('body', $body))
                ->when($relatedType === null, fn ($query) => $query->whereNull('related_type'), fn ($query) => $query->where('related_type', $relatedType))
                ->when($relatedId === null, fn ($query) => $query->whereNull('related_id'), fn ($query) => $query->where('related_id', $relatedId))
                ->where('status', '!=', 'resolved')
                ->latest()
                ->first();

            if ($existing) {
                $existing->update([
                    'status' => 'unread',
                    'created_by' => auth()->id(),
                ]);

                continue;
            }

            Notification::query()->create([
                'target_role' => $role,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'status' => 'unread',
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'created_by' => auth()->id(),
            ]);
        }
    }
}
